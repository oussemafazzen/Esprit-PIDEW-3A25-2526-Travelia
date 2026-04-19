<?php

namespace App\Controller;

use App\Entity\Paiement;
use App\Form\PaiementType;
use App\Entity\Reservationhebergement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/paiement')]
class PaiementController extends AbstractController
{
    #[Route('/new/{reservation_id}', name: 'app_paiement_new', methods: ['GET', 'POST'])]
    public function new(int $reservation_id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $paiement = new Paiement();
        $paiement->setIdReservation($reservation_id);
        $paiement->setDatePaiement(new \DateTime());

        // Find the reservation to get details (like total price)
        $reservation = $entityManager->getRepository(Reservationhebergement::class)->find($reservation_id);

        if ($reservation) {
            $hebergement = $reservation->getIdHebergement();
            if ($hebergement) {
                $paiement->setMontant($hebergement->getTarifParNuit());
            }
        }

        $form = $this->createForm(PaiementType::class, $paiement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($reservation) {
                $reservation->setStatut('Confirmé');
            }
            $entityManager->persist($paiement);
            $entityManager->flush();

            return $this->redirectToRoute('app_reservationhebergement_index');
        }

        // ── Fetch exchange rates SERVER-SIDE (API key never reaches the browser) ──
        $exchangeRates = $this->fetchExchangeRates();

        return $this->render('paiement/new.html.twig', [
            'paiement'      => $paiement,
            'form'          => $form,
            'reservation'   => $reservation,
            'exchangeRates' => $exchangeRates,
        ]);
    }

    /**
     * Calls ExchangeRate-API server-side using PHP's file_get_contents.
     * Returns an associative array of currency code => rate (base: EUR).
     * The API key stays on the server and is never sent to the client.
     */
    private function fetchExchangeRates(): array
    {
        $apiKey = 'c9613f28855f96b336b0e13b';
        $url    = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/EUR";

        // Only the currencies we display in the converter
        $wanted = ['EUR', 'USD', 'GBP', 'TND', 'MAD', 'DZD', 'SAR', 'AED', 'JPY', 'CAD', 'CHF', 'CNY', 'BRL', 'TRY', 'INR'];

        try {
            $context  = stream_context_create(['http' => ['timeout' => 5]]);
            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                return [];
            }

            $data = json_decode($response, true);

            if (($data['result'] ?? '') !== 'success') {
                return [];
            }

            $rates = [];
            foreach ($wanted as $code) {
                if (isset($data['conversion_rates'][$code])) {
                    $rates[$code] = $data['conversion_rates'][$code];
                }
            }
            return $rates;

        } catch (\Throwable $e) {
            return [];
        }
    }
}
