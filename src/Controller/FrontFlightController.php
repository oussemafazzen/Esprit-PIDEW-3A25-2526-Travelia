<?php

namespace App\Controller;

use App\Repository\BilletRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontFlightController extends AbstractController
{
    #[Route('/vols', name: 'app_flights_search')]
    public function search(): Response
    {
        return $this->render('front/flights/search.html.twig');
    }

    #[Route('/vols/resultats', name: 'app_flights_results')]
    public function results(Request $request, BilletRepository $billetRepository): Response
    {
        $destination = trim((string) $request->query->get('destination', ''));
        $dateDepart = trim((string) $request->query->get('date_depart', ''));
        $dateArrivee = trim((string) $request->query->get('date_arrivee', ''));
        $typeTransport = trim((string) $request->query->get('type_transport', ''));
        $passagers = (int) $request->query->get('passagers', 1);

        $billets = $billetRepository->findAvailableFlights($destination, $dateDepart, $typeTransport);
        $baseFlights = $billetRepository->normalizeBilletsForDisplay($billets);

        $filteredFlights = $baseFlights;

        if ($dateArrivee !== '') {
            $filteredFlights = array_filter($filteredFlights, function ($flight) use ($dateArrivee) {
                if (empty($flight['dateArrivee'])) {
                    return true;
                }

                return $flight['dateArrivee']->format('Y-m-d') === $dateArrivee;
            });
        }

        if (count($filteredFlights) === 0 && count($baseFlights) > 0) {
            $filteredFlights = $baseFlights;
        }

        $filteredFlights = array_values($filteredFlights);

        // ✅ Générer plusieurs options avec prix différents
        $flightsWithOptions = $this->buildFlightVariants($filteredFlights);

        return $this->render('front/flights/results.html.twig', [
            'flights' => $flightsWithOptions,
            'destination' => $destination,
            'date_depart' => $dateDepart,
            'date_arrivee' => $dateArrivee,
            'type_transport' => $typeTransport,
            'passagers' => $passagers,
        ]);
    }

    /**
     * Génère plusieurs variantes tarifaires à partir des billets trouvés
     * sans modifier les autres pages.
     */
    private function buildFlightVariants(array $flights): array
    {
        $variants = [];

        foreach ($flights as $flight) {
            $basePrice = is_numeric($flight['prix'] ?? null) ? (float) $flight['prix'] : 0.0;
            $baseReference = (string) ($flight['reference'] ?? 'Billet');

            if ($basePrice <= 0) {
                $basePrice = 500;
            }

            $pricingOptions = [
                [
                    'label' => 'Eco',
                    'multiplier' => 0.88,
                    'suffix' => 'ECO',
                ],
                [
                    'label' => 'Standard',
                    'multiplier' => 1.00,
                    'suffix' => 'STD',
                ],
                [
                    'label' => 'Flex',
                    'multiplier' => 1.18,
                    'suffix' => 'FLX',
                ],
            ];

            foreach ($pricingOptions as $option) {
                $newFlight = $flight;

                $newFlight['reference'] = $baseReference . ' - ' . $option['suffix'];
                $newFlight['prix'] = round($basePrice * $option['multiplier'], 1);
                $newFlight['offerLabel'] = $option['label'];

                $variants[] = $newFlight;
            }
        }

        usort($variants, function ($a, $b) {
            $priceA = (float) ($a['prix'] ?? 0);
            $priceB = (float) ($b['prix'] ?? 0);

            return $priceA <=> $priceB;
        });

        return $variants;
    }
}