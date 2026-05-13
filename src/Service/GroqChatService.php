<?php

namespace App\Service;

use App\Repository\BilletRepository;
use App\Repository\HebergementRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends conversation turns to the Groq API (OpenAI-compatible endpoint).
 * Mirrors the logic of GeminiChatService.java from the desktop module,
 * adapted for Symfony's HttpClient.
 *
 * v2: Full-platform assistant — knows all Travelia modules:
 *   • Hébergements  (live DB data)
 *   • Vols / Billets (live DB aggregate stats)
 *   • Réservations  (static guide)
 *   • Paiements     (static guide)
 *   • Clients / Fidélité (static guide)
 *
 * The conversation history is kept browser-side and sent with every request
 * so this service is completely stateless — no session needed.
 */
final class GroqChatService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL   = 'llama-3.3-70b-versatile';

    public function __construct(
        private readonly HttpClientInterface   $httpClient,
        private readonly HebergementRepository $hebergementRepository,
        private readonly BilletRepository      $billetRepository,
        private readonly string $apiKey
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PUBLIC API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sends a user message plus the conversation history to Groq and returns
     * the assistant's reply text.
     *
     * @param list<array{role: string, content: string}> $history     Previous turns (from browser)
     * @param string                                     $userMessage The new user message
     */
    public function chat(array $history, string $userMessage): string
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->buildSystemPrompt()]],
            $history,
            [['role' => 'user', 'content' => $userMessage]]
        );

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => self::MODEL,
                    'temperature' => 0.7,
                    'max_tokens'  => 450,
                    'messages'    => $messages,
                ],
                'timeout' => 20,
            ]);

            $data = $response->toArray(false);

            if (isset($data['error'])) {
                return 'Désolé, une erreur API est survenue : ' . ($data['error']['message'] ?? 'inconnue');
            }

            return trim($data['choices'][0]['message']['content'] ?? 'Pas de réponse reçue.');

        } catch (\Throwable) {
            return 'Désolé, je rencontre un problème technique. Veuillez réessayer dans un moment.';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  SYSTEM PROMPT BUILDER
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assembles the full system prompt from all module sections.
     * Each section is built by a dedicated private method so future modules
     * can be added without touching anything else.
     */
    private function buildSystemPrompt(): string
    {
        $hotelCatalog   = $this->buildHotelCatalog();
        $flightsSummary = $this->buildFlightsSummary();
        $platformGuide  = $this->buildPlatformGuide();

        return <<<PROMPT
Tu es l'assistant IA de Travelia, une plateforme de voyage de luxe.
Tu peux aider les clients sur TOUS les modules de la plateforme :
hébergements, vols, réservations, paiements et comptes clients.

━━━ RÈGLES ABSOLUES ━━━
1. Ne révèle JAMAIS de données personnelles de clients (emails, mots de passe, numéros de carte).
2. Ne recommande JAMAIS un hébergement qui n'existe pas dans la liste ci-dessous.
3. Ne donne JAMAIS de prix de vol inventés — utilise uniquement les données agrégées fournies.
4. Redirige toujours vers le site pour les actions (réserver, payer, s'inscrire).
5. Réponds en français, de façon concise (2-4 phrases max).
6. Si la question est hors voyage / tourisme, décline poliment.
7. Si tu ne sais pas, dis-le honnêtement plutôt qu'inventer.

━━━ MODULE HÉBERGEMENTS ━━━
Voici TOUS les hébergements disponibles sur Travelia :
{$hotelCatalog}

━━━ MODULE VOLS & BILLETS ━━━
{$flightsSummary}

━━━ GUIDE PLATEFORME ━━━
{$platformGuide}
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  MODULE SECTIONS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Live hotel catalog from the database.
     * Includes name, type, city, country, capacity, price, amenities.
     */
    private function buildHotelCatalog(): string
    {
        $hebergements = $this->hebergementRepository->findAll();

        if (empty($hebergements)) {
            return "Aucun hébergement disponible pour le moment.\n";
        }

        $lines = [];
        foreach ($hebergements as $h) {
            $lines[] = sprintf(
                '• %s (%s) | %s, %s | Capacité : %d pers. | Tarif : €%s/nuit | Équipements : %s',
                $h->getNom(),
                strtoupper($h->getType() ?? 'HOTEL'),
                $h->getVille(),
                $h->getPays(),
                $h->getCapacite() ?? 0,
                $h->getTarifParNuit(),
                $h->getEquipements() ?? 'Non précisé'
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Aggregate flight statistics from the billet table.
     * Only exposes aggregate data — never individual booking details or client info.
     */
    private function buildFlightsSummary(): string
    {
        try {
            $billets = $this->billetRepository->findAll();

            if (empty($billets)) {
                return "Aucun vol enregistré pour le moment. Pour rechercher des vols, visitez la section Vols du site.\n";
            }

            $destinations  = [];
            $transportTypes = [];
            $prices        = [];

            foreach ($billets as $billet) {
                // Destination (arrival city/country)
                foreach (['getBookedDestinationCode', 'getPaysDestination', 'getDestination', 'getVilleArrivee'] as $getter) {
                    if (method_exists($billet, $getter)) {
                        $val = $billet->$getter();
                        if (!empty($val)) {
                            $destinations[] = (string) $val;
                            break;
                        }
                    }
                }

                // Transport type
                if (method_exists($billet, 'getTypeTransport')) {
                    $t = $billet->getTypeTransport();
                    if (!empty($t)) {
                        $transportTypes[] = ucfirst(strtolower((string) $t));
                    }
                }

                // Price
                if (method_exists($billet, 'getPrix')) {
                    $p = $billet->getPrix();
                    if ($p !== null && $p > 0) {
                        $prices[] = (float) $p;
                    }
                }
            }

            $uniqueDestinations  = array_unique(array_filter($destinations));
            $uniqueTransportTypes = array_unique(array_filter($transportTypes));

            $lines = [];
            $lines[] = 'Nombre total de billets disponibles : ' . count($billets);

            if (!empty($uniqueDestinations)) {
                sort($uniqueDestinations);
                $lines[] = 'Destinations desservies : ' . implode(', ', array_slice($uniqueDestinations, 0, 20));
            }

            if (!empty($uniqueTransportTypes)) {
                $lines[] = 'Types de transport : ' . implode(', ', $uniqueTransportTypes);
            }

            if (!empty($prices)) {
                $lines[] = sprintf(
                    'Fourchette de prix billets : €%s – €%s (moyenne : €%s)',
                    number_format(min($prices), 0),
                    number_format(max($prices), 0),
                    number_format(array_sum($prices) / count($prices), 0)
                );
            }

            $lines[] = 'Pour réserver un vol → section "Vols" du site → rechercher par destination et date.';

            return implode("\n", $lines) . "\n";

        } catch (\Throwable) {
            return "Les données de vols ne sont pas disponibles actuellement. Visitez la section Vols du site pour rechercher des billets.\n";
        }
    }

    /**
     * Static platform knowledge: reservations, payments, accounts, loyalty.
     * This section never changes and requires no DB access.
     */
    private function buildPlatformGuide(): string
    {
        return <<<'GUIDE'
RÉSERVATIONS HÉBERGEMENT :
• Pour réserver → cliquer sur la carte de l'hôtel sur le site → choisir dates & personnes → confirmer.
• Statuts de réservation : En attente → Confirmée → Annulée.
• Annulation : contacter le support depuis votre espace client.

PAIEMENTS :
• Méthodes acceptées : Carte bancaire (Visa/Mastercard), PayPal, Virement bancaire.
• La confirmation de paiement est immédiate après validation.
• En cas de problème de paiement → contacter support@travelia.com.
• Les transactions sont sécurisées (SSL).

COMPTES CLIENTS :
• Inscription : Email + mot de passe, ou via Google (OAuth), ou via Face ID (reconnaissance faciale).
• Connexion : email/mot de passe, Google, ou Face ID.
• Mot de passe oublié : lien de réinitialisation envoyé par email.

PROGRAMME FIDÉLITÉ :
• Niveaux : BRONZE → SILVER → GOLD → PLATINUM.
• Points gagnés à chaque réservation hébergement confirmée.
• Les points sont visibles dans votre espace client (profil).
• Les avantages augmentent avec chaque niveau (réductions, priorité, etc.).

SUPPORT :
• Email : support@travelia.com
• Pour toute question technique ou litige → contacter le support.
GUIDE;
    }
}
