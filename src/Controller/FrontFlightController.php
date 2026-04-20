<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Entity\FlightSearchData;
use App\Entity\Reservation;
use App\Form\FlightSearchType;
use App\Repository\BilletRepository;
use App\Repository\ReservationRepository;
use App\Service\AmadeusFlightService;
use App\Service\DestinationCodeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FrontFlightController extends AbstractController
{
    #[Route('/vols', name: 'app_flights_search')]
    public function search(Request $request): Response
    {
        $searchData = new FlightSearchData();

        $reservationId = (int) $request->query->get('reservation_id', 0);
        $destination = trim((string) $request->query->get('destination', ''));
        $dateDepart = trim((string) $request->query->get('date_depart', ''));
        $passagers = max(1, (int) $request->query->get('passagers', 1));

        if ($destination !== '' && method_exists($searchData, 'setDestination')) {
            $searchData->setDestination($destination);
        }

        if ($dateDepart !== '' && method_exists($searchData, 'setDateDepart')) {
            try {
                $searchData->setDateDepart(new \DateTimeImmutable($dateDepart));
            } catch (\Exception $e) {
            }
        }

        if (method_exists($searchData, 'setPassagers')) {
            $searchData->setPassagers($passagers);
        }

        $form = $this->createForm(FlightSearchType::class, $searchData, [
            'method' => 'GET',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('app_flights_results', [
                'reservation_id' => $reservationId,
                'destination' => $searchData->getDestination(),
                'date_depart' => $searchData->getDateDepart()?->format('Y-m-d'),
                'date_arrivee' => '',
                'type_transport' => 'avion',
                'passagers' => $searchData->getPassagers(),
            ]);
        }

        return $this->render('front/flights/search.html.twig', [
            'form' => $form->createView(),
            'reservation_id' => $reservationId,
        ]);
    }

    #[Route('/vols/resultats', name: 'app_flights_results')]
    public function results(
        Request $request,
        BilletRepository $billetRepository,
        AmadeusFlightService $amadeusFlightService,
        DestinationCodeResolver $destinationCodeResolver
    ): Response {
        $destination = trim((string) $request->query->get('destination', ''));
        $dateDepart = trim((string) $request->query->get('date_depart', ''));
        $dateArrivee = trim((string) $request->query->get('date_arrivee', ''));
        $typeTransport = trim((string) $request->query->get('type_transport', 'avion'));
        $passagers = max(1, (int) $request->query->get('passagers', 1));
        $reservationId = (int) $request->query->get('reservation_id', 0);

        $flightsWithOptions = [];
        $apiError = null;
        $destinationCode = null;
        $usedMockFallback = false;

        if ($destination !== '' && $dateDepart !== '') {
            $destinationCodes = $destinationCodeResolver->resolveCandidates($destination);
            if (count($destinationCodes) > 0) {
                foreach ($destinationCodes as $candidateCode) {
                    $destinationCode = $candidateCode;
                    error_log('Flight search destination sent to SerpAPI: ' . $destinationCode);

                    try {
                        $apiFlights = $amadeusFlightService->searchFlights(
                            destinationCode: $destinationCode,
                            departureDate: $dateDepart,
                            adults: $passagers
                        );

                        if (!empty($apiFlights)) {
                            $normalizedApiFlights = $this->normalizeApiFlightsForDisplay(
                                $apiFlights,
                                $destination,
                                $dateDepart
                            );

                            $normalizedApiFlights = $this->filterFlightsByDepartureDate($normalizedApiFlights, $dateDepart);
                            $flightsWithOptions = $this->buildFlightVariants($normalizedApiFlights);

                            if (count($flightsWithOptions) > 0) {
                                break;
                            }
                        }
                    } catch (\Throwable $e) {
                        $apiError = $e->getMessage();
                    }
                }
            } else {
                $apiError = 'Impossible de résoudre cette destination pour la recherche de vols.';
            }
        }

        if (count($flightsWithOptions) === 0) {
            $billets = [];

            if (method_exists($billetRepository, 'findAvailableFlights')) {
                $billets = $billetRepository->findAvailableFlights($destination, $dateDepart, $typeTransport);
            }

            if (count($billets) === 0) {
                $allBillets = $billetRepository->findAll();

                $billets = array_values(array_filter($allBillets, function (Billet $billet) use ($destination, $dateDepart, $typeTransport) {
                    if (!$this->matchesTransport($billet, $typeTransport)) {
                        return false;
                    }

                    if (!$this->matchesDateDepart($billet, $dateDepart)) {
                        return false;
                    }

                    if (!$this->matchesDestination($billet, $destination)) {
                        return false;
                    }

                    return true;
                }));
            }

            if (count($billets) === 0) {
                $allBillets = $billetRepository->findAll();

                $billets = array_values(array_filter($allBillets, function (Billet $billet) use ($typeTransport) {
                    return $this->matchesTransport($billet, $typeTransport);
                }));
            }

            $baseFlights = [];

            if (method_exists($billetRepository, 'normalizeBilletsForDisplay')) {
                $baseFlights = $billetRepository->normalizeBilletsForDisplay($billets);
            }

            if (count($baseFlights) === 0 && count($billets) > 0) {
                $baseFlights = $this->normalizeBilletsFallback($billets);
            }

            $usedMockFallback = count($baseFlights) > 0;

            $filteredFlights = $baseFlights;

            if ($dateArrivee !== '') {
                $filteredFlights = array_filter($filteredFlights, function ($flight) use ($dateArrivee) {
                    if (empty($flight['dateArrivee'])) {
                        return true;
                    }

                    if ($flight['dateArrivee'] instanceof \DateTimeInterface) {
                        return $flight['dateArrivee']->format('Y-m-d') === $dateArrivee;
                    }

                    try {
                        return (new \DateTime((string) $flight['dateArrivee']))->format('Y-m-d') === $dateArrivee;
                    } catch (\Throwable $e) {
                        return true;
                    }
                });
            }

            if (count($filteredFlights) === 0 && count($baseFlights) > 0) {
                $filteredFlights = $baseFlights;
            }

            $filteredFlights = $this->filterFlightsByDepartureDate(array_values($filteredFlights), $dateDepart);
            $flightsWithOptions = $this->buildFlightVariants(array_values($filteredFlights));
        }

        return $this->render('front/flights/results.html.twig', [
            'flights' => $flightsWithOptions,
            'destination' => $destination,
            'date_depart' => $dateDepart,
            'date_arrivee' => $dateArrivee,
            'type_transport' => $typeTransport,
            'passagers' => $passagers,
            'reservation_id' => $reservationId,
            'api_error' => $apiError,
            'destination_code_debug' => $destinationCode,
            'used_mock_fallback' => $usedMockFallback,
        ]);
    }

    #[Route('/vols/paiement', name: 'app_flights_payment_submit', methods: ['POST'])]
    public function paymentSubmit(
        Request $request,
        BilletRepository $billetRepository,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        $billetId = (int) $request->request->get('billet_id', 0);
        $reservationId = (int) $request->request->get('reservation_id', 0);
        $paymentMethod = trim((string) $request->request->get('payment_method', ''));
        $selectedPrice = (float) $request->request->get('selected_price', 0);
        $selectedDateDepart = trim((string) $request->request->get('selected_date_depart', ''));
        $selectedDateArrivee = trim((string) $request->request->get('selected_date_arrivee', ''));
        $selectedDestination = trim((string) $request->request->get('destination', ''));
        $cardholderName = trim((string) $request->request->get('cardholder_name', ''));
        $cardNumber = trim((string) $request->request->get('card_number', ''));
        $expiryDate = trim((string) $request->request->get('expiry_date', ''));
        $cvv = trim((string) $request->request->get('cvv', ''));
        $bankName = trim((string) $request->request->get('bank_name', ''));
        $ibanOrRib = trim((string) $request->request->get('iban_or_rib', ''));
        $accountHolderName = trim((string) $request->request->get('account_holder_name', ''));

        // Normalisation backend avant validation (sans dépendre du navigateur)
        $cardholderName = preg_replace('/\s+/', ' ', $cardholderName) ?? $cardholderName;
        $bankName = preg_replace('/\s+/', ' ', $bankName) ?? $bankName;
        $accountHolderName = preg_replace('/\s+/', ' ', $accountHolderName) ?? $accountHolderName;
        $cardNumber = preg_replace('/[\s-]+/', '', $cardNumber) ?? $cardNumber;
        $ibanOrRib = preg_replace('/\s+/', '', $ibanOrRib) ?? $ibanOrRib;
        $paymentPayload = [
            'billet_id' => $billetId,
            'reservation_id' => $reservationId,
            'selected_price' => $selectedPrice > 0 ? number_format($selectedPrice, 2, '.', '') : '',
            'selected_date_depart' => $selectedDateDepart,
            'selected_date_arrivee' => $selectedDateArrivee,
            'destination' => $selectedDestination,
            'payment_method' => $paymentMethod,
            'cardholder_name' => $cardholderName,
            'card_number' => $cardNumber,
            'expiry_date' => $expiryDate,
            'cvv' => $cvv,
            'bank_name' => $bankName,
            'iban_or_rib' => $ibanOrRib,
            'account_holder_name' => $accountHolderName,
            'reference' => (string) $request->request->get('reference', ''),
            'origin_code' => (string) $request->request->get('origin_code', ''),
            'destination_code' => (string) $request->request->get('destination_code', ''),
            'offer_label' => (string) $request->request->get('offer_label', ''),
        ];

        $validationBillet = new Billet();
        $validationBillet->setModePaiement($paymentMethod);
        $validationBillet->setCardHolderName($cardholderName);
        $validationBillet->setCardNumber($cardNumber);
        $validationBillet->setExpiryDate($expiryDate);
        $validationBillet->setCvv($cvv);
        $validationBillet->setBankName($bankName);
        $validationBillet->setIbanOrRib($ibanOrRib);
        $validationBillet->setAccountHolderName($accountHolderName);

        $validationGroups = ['payment_default'];

        if ($paymentMethod === 'carte') {
            $validationGroups[] = 'payment_carte';
        } elseif ($paymentMethod === 'virement') {
            $validationGroups[] = 'payment_virement';
        } elseif ($paymentMethod === 'especes') {
            $validationGroups[] = 'payment_especes';
        }

        $violations = $validator->validate($validationBillet, null, $validationGroups);
        $validatorErrors = [];

        foreach ($violations as $violation) {
            $validatorErrors[] = $violation->getMessage();
        }

        if (count($validatorErrors) > 0) {
            return $this->render('front/flights/payment.html.twig', [
                'payment' => $paymentPayload,
                'payment_errors' => $validatorErrors,
            ]);
        }

        $paymentErrors = [];

        if ($paymentMethod === '') {
            $this->addFlash('error', 'Paiement invalide.');
            return $this->redirectToRoute('app_user_reservations');
        }

        if ($paymentMethod === 'carte') {
            if ($cardholderName === '') {
                $paymentErrors[] = 'Le nom du titulaire est obligatoire.';
            }
            if ($cardNumber === '') {
                $paymentErrors[] = 'Le numéro de carte est obligatoire.';
            }
            if ($expiryDate === '') {
                $paymentErrors[] = 'La date d\'expiration est obligatoire.';
            }
            if ($cvv === '') {
                $paymentErrors[] = 'Le CVV est obligatoire.';
            }
        } elseif ($paymentMethod === 'virement') {
            if ($bankName === '') {
                $paymentErrors[] = 'Le nom de la banque est obligatoire.';
            }
            if ($ibanOrRib === '') {
                $paymentErrors[] = 'L\'IBAN ou le RIB est obligatoire.';
            }
            if ($accountHolderName === '') {
                $paymentErrors[] = 'Le nom du titulaire du compte est obligatoire.';
            }
        } elseif ($paymentMethod !== 'especes') {
            $paymentErrors[] = 'Le mode de paiement sélectionné est invalide.';
        }

        if (count($paymentErrors) > 0) {
            return $this->render('front/flights/payment.html.twig', [
                'payment' => [
                    'billet_id' => $billetId,
                    'reservation_id' => $reservationId,
                    'selected_price' => $selectedPrice > 0 ? number_format($selectedPrice, 2, '.', '') : '',
                    'selected_date_depart' => $selectedDateDepart,
                    'selected_date_arrivee' => $selectedDateArrivee,
                    'destination' => $selectedDestination,
                    'payment_method' => $paymentMethod,
                    'cardholder_name' => $cardholderName,
                    'card_number' => $cardNumber,
                    'expiry_date' => $expiryDate,
                    'cvv' => $cvv,
                    'bank_name' => $bankName,
                    'iban_or_rib' => $ibanOrRib,
                    'account_holder_name' => $accountHolderName,
                    'reference' => (string) $request->request->get('reference', ''),
                    'origin_code' => (string) $request->request->get('origin_code', ''),
                    'destination_code' => (string) $request->request->get('destination_code', ''),
                    'offer_label' => (string) $request->request->get('offer_label', ''),
                ],
                'payment_errors' => $paymentErrors,
            ]);
        }

        $reservation = null;

        if (!$reservation) {
            $reservation = new Reservation();

            if (method_exists($reservation, 'setDateReservation')) {
                $reservation->setDateReservation(new \DateTime('today'));
            }

            if (method_exists($reservation, 'setStatut')) {
                $reservation->setStatut('confirmé');
            }

            if (method_exists($reservation, 'setModalitesPaiement')) {
                $reservation->setModalitesPaiement($paymentMethod);
            }

            if (method_exists($reservation, 'setClientId')) {
                $reservation->setClientId(51);
            }

            if (method_exists($reservation, 'setPaysDestination')) {
                $reservation->setPaysDestination($selectedDestination !== '' ? $selectedDestination : 'Non défini');
            }

            $em->persist($reservation);
            $em->flush();
        }

        $billet = null;

        if ($billetId > 0) {
            $billet = $billetRepository->find($billetId);
        }

        if (!$billet) {
            $billet = new Billet();
            $billet->setNumeroBillet('API-' . strtoupper(substr(uniqid(), -8)));
            $billet->setTypeTransport('avion');
            $billet->setStatut('confirme');
            $billet->setReservation($reservation);
            $em->persist($billet);
        }

        if (method_exists($reservation, 'setModalitesPaiement')) {
            $reservation->setModalitesPaiement($paymentMethod);
        }

        if (method_exists($reservation, 'setStatut')) {
            $reservation->setStatut('confirmé');
        }

        if ($selectedDestination !== '' && method_exists($reservation, 'setPaysDestination')) {
            $reservation->setPaysDestination($selectedDestination);
        }

        if ($selectedDateDepart !== '' && method_exists($billet, 'setDateDepart')) {
            try {
                $billet->setDateDepart(new \DateTime($selectedDateDepart));
            } catch (\Exception $e) {
                $this->addFlash('error', 'La date de départ sélectionnée est invalide.');
                return $this->redirectToRoute('app_user_reservations');
            }
        }

        if ($selectedDateArrivee !== '' && method_exists($billet, 'setDateArrivee')) {
            try {
                $billet->setDateArrivee(new \DateTime($selectedDateArrivee));
            } catch (\Exception $e) {
            }
        } elseif (method_exists($billet, 'getDateDepart') && method_exists($billet, 'setDateArrivee')) {
            $dateDepartObj = $billet->getDateDepart();
            if ($dateDepartObj instanceof \DateTimeInterface) {
                $dateArriveeAuto = \DateTime::createFromInterface($dateDepartObj);
                $dateArriveeAuto->modify('+2 hours');
                $billet->setDateArrivee($dateArriveeAuto);
            }
        }

        if ($selectedPrice > 0 && method_exists($billet, 'setPrix')) {
            $billet->setPrix($selectedPrice);
        }

        if (method_exists($billet, 'setReservation')) {
            $billet->setReservation($reservation);
        }

        if (method_exists($billet, 'setStatut')) {
            $billet->setStatut('confirme');
        }

        $em->flush();

        $this->addFlash('success', 'Le billet a bien été ajouté à la réservation avec la date choisie.');

        return $this->redirectToRoute('app_user_reservations');
    }

    #[Route('/vols/paiement/page', name: 'app_flights_payment_page', methods: ['POST'])]
    public function paymentPage(Request $request): Response
    {
        $paymentData = [
            'billet_id' => (int) $request->request->get('billet_id', 0),
            'reservation_id' => (int) $request->request->get('reservation_id', 0),
            'selected_price' => (string) $request->request->get('selected_price', ''),
            'selected_date_depart' => (string) $request->request->get('selected_date_depart', ''),
            'selected_date_arrivee' => (string) $request->request->get('selected_date_arrivee', ''),
            'reference' => (string) $request->request->get('reference', ''),
            'origin_code' => (string) $request->request->get('origin_code', ''),
            'destination_code' => (string) $request->request->get('destination_code', ''),
            'destination' => (string) $request->request->get('destination', ''),
            'offer_label' => (string) $request->request->get('offer_label', ''),
        ];

        return $this->render('front/flights/payment.html.twig', [
            'payment' => $paymentData,
        ]);
    }

    private function matchesTransport(Billet $billet, string $typeTransport): bool
    {
        if ($typeTransport === '' || mb_strtolower($typeTransport) === 'tous') {
            return true;
        }

        $billetTransport = '';

        if (method_exists($billet, 'getTypeTransport')) {
            $billetTransport = (string) $billet->getTypeTransport();
        }

        return mb_strtolower(trim($billetTransport)) === mb_strtolower(trim($typeTransport));
    }

    private function matchesDateDepart(Billet $billet, string $dateDepart): bool
    {
        if ($dateDepart === '') {
            return true;
        }

        if (!method_exists($billet, 'getDateDepart')) {
            return true;
        }

        $billetDate = $billet->getDateDepart();

        if (!$billetDate instanceof \DateTimeInterface) {
            return true;
        }

        return $billetDate->format('Y-m-d') === $dateDepart;
    }

    private function matchesDestination(Billet $billet, string $destination): bool
    {
        if ($destination === '') {
            return true;
        }

        $needle = mb_strtolower(trim($destination));
        $haystacks = [];

        if (method_exists($billet, 'getNumeroBillet')) {
            $haystacks[] = (string) $billet->getNumeroBillet();
        }

        if (method_exists($billet, 'getStatut')) {
            $haystacks[] = (string) $billet->getStatut();
        }

        if (method_exists($billet, 'getTypeTransport')) {
            $haystacks[] = (string) $billet->getTypeTransport();
        }

        if (method_exists($billet, 'getReservation')) {
            $reservation = $billet->getReservation();

            if ($reservation) {
                if (method_exists($reservation, 'getPaysDestination')) {
                    $haystacks[] = (string) $reservation->getPaysDestination();
                }
            }
        }

        foreach ($haystacks as $value) {
            if (str_contains(mb_strtolower($value), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeBilletsFallback(array $billets): array
    {
        $flights = [];

        foreach ($billets as $billet) {
            if (!$billet instanceof Billet) {
                continue;
            }

            $reservation = method_exists($billet, 'getReservation') ? $billet->getReservation() : null;

            $destination = '';
            if ($reservation && method_exists($reservation, 'getPaysDestination') && $reservation->getPaysDestination()) {
                $destination = (string) $reservation->getPaysDestination();
            }

            $dateDepartObj = method_exists($billet, 'getDateDepart') ? $billet->getDateDepart() : null;
            $dateArriveeObj = method_exists($billet, 'getDateArrivee') ? $billet->getDateArrivee() : null;
            $prix = method_exists($billet, 'getPrix') ? $billet->getPrix() : 500;
            $reference = method_exists($billet, 'getNumeroBillet')
                ? $billet->getNumeroBillet()
                : 'Billet-' . (method_exists($billet, 'getId') ? $billet->getId() : uniqid());

            $transport = method_exists($billet, 'getTypeTransport') ? $billet->getTypeTransport() : 'avion';
            $statut = method_exists($billet, 'getStatut') ? $billet->getStatut() : 'disponible';
            $id = method_exists($billet, 'getId') ? $billet->getId() : null;

            $flights[] = [
                'id' => $id,
                'reference' => $reference ?: 'Billet',
                'destination' => $destination,
                'dateDepart' => $dateDepartObj instanceof \DateTimeInterface ? $dateDepartObj : null,
                'dateArrivee' => $dateArriveeObj instanceof \DateTimeInterface ? $dateArriveeObj : null,
                'prix' => is_numeric($prix) ? (float) $prix : 500,
                'transport' => $transport ?: 'avion',
                'statut' => $statut ?: 'disponible',
                'source' => 'local',
            ];
        }

        return $flights;
    }

    private function normalizeApiFlightsForDisplay(array $apiFlights, string $destination, string $fallbackDateDepart): array
    {
        $flights = [];

        foreach ($apiFlights as $index => $flight) {
            $departureAt = null;
            $arrivalAt = null;
            $originCode = 'TUN';
            $destinationCode = $destination;
            $originLabel = 'Tunis';
            $destinationLabel = $destination;
            $durationMinutes = isset($flight['durationMinutes']) && is_numeric($flight['durationMinutes'])
                ? (int) $flight['durationMinutes']
                : null;

            if (isset($flight['departureAt'])) {
                try {
                    $departureAt = new \DateTime((string) $flight['departureAt']);
                } catch (\Throwable $e) {
                    $departureAt = null;
                }
            } elseif (isset($flight['itineraries'][0]['segments'][0]['departure']['at'])) {
                try {
                    $departureAt = new \DateTime((string) $flight['itineraries'][0]['segments'][0]['departure']['at']);
                } catch (\Throwable $e) {
                    $departureAt = null;
                }
            }

            if (isset($flight['arrivalAt'])) {
                try {
                    $arrivalAt = new \DateTime((string) $flight['arrivalAt']);
                } catch (\Throwable $e) {
                    $arrivalAt = null;
                }
            } elseif (isset($flight['itineraries'][0]['segments'][0]['arrival']['at'])) {
                try {
                    $arrivalAt = new \DateTime((string) $flight['itineraries'][0]['segments'][0]['arrival']['at']);
                } catch (\Throwable $e) {
                    $arrivalAt = null;
                }
            }

            if (!$departureAt && $fallbackDateDepart !== '') {
                try {
                    $departureAt = new \DateTime($fallbackDateDepart . ' 08:00:00');
                } catch (\Throwable $e) {
                    $departureAt = null;
                }
            }

            if (!$arrivalAt && $departureAt instanceof \DateTimeInterface) {
                $arrivalAt = \DateTime::createFromInterface($departureAt);
                $arrivalAt->modify('+2 hours');
            }

            if (isset($flight['origin'])) {
                $originCode = (string) $flight['origin'];
            } elseif (isset($flight['itineraries'][0]['segments'][0]['departure']['iataCode'])) {
                $originCode = (string) $flight['itineraries'][0]['segments'][0]['departure']['iataCode'];
            }

            if (!empty($flight['originLabel'])) {
                $originLabel = (string) $flight['originLabel'];
            }

            if (isset($flight['destination'])) {
                $destinationCode = (string) $flight['destination'];
            } elseif (isset($flight['itineraries'][0]['segments'][0]['arrival']['iataCode'])) {
                $destinationCode = (string) $flight['itineraries'][0]['segments'][0]['arrival']['iataCode'];
            }

            if (!empty($flight['destinationLabel'])) {
                $destinationLabel = (string) $flight['destinationLabel'];
            }

            if ($durationMinutes === null && $departureAt instanceof \DateTimeInterface && $arrivalAt instanceof \DateTimeInterface) {
                $durationMinutes = max(0, (int) round(($arrivalAt->getTimestamp() - $departureAt->getTimestamp()) / 60));
            }

            $reference = $flight['flightNumber']
                ?? $flight['reference']
                ?? ('API-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));

            $price = $flight['price']
                ?? $flight['prix']
                ?? ($flight['price']['grandTotal'] ?? 0);

            $currency = $flight['currency']
                ?? ($flight['price']['currency'] ?? 'EUR');

            $transport = $flight['transportType'] ?? $flight['transport'] ?? 'avion';

            $flights[] = [
                'id' => null,
                'reference' => (string) $reference,
                'destination' => $destinationLabel ?: (string) $destinationCode,
                'destinationCountry' => $destination,
                'originCode' => (string) $originCode,
                'destinationCode' => (string) $destinationCode,
                'originLabel' => (string) $originLabel,
                'destinationLabel' => (string) $destinationLabel,
                'dateDepart' => $departureAt,
                'dateArrivee' => $arrivalAt,
                'durationMinutes' => $durationMinutes,
                'prix' => is_numeric($price) ? (float) $price : 0,
                'currency' => (string) $currency,
                'airline' => (string) ($flight['airline'] ?? ''),
                'stopsCount' => isset($flight['stopsCount']) && is_numeric($flight['stopsCount']) ? (int) $flight['stopsCount'] : 0,
                'isDirect' => (bool) ($flight['isDirect'] ?? false),
                'cabinClass' => (string) ($flight['cabinClass'] ?? ''),
                'transport' => (string) $transport,
                'statut' => 'disponible',
                'source' => 'api',
            ];
        }

        return $flights;
    }

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
                ['label' => 'Eco', 'multiplier' => 0.88, 'suffix' => 'ECO'],
                ['label' => 'Standard', 'multiplier' => 1.00, 'suffix' => 'STD'],
                ['label' => 'Flex', 'multiplier' => 1.18, 'suffix' => 'FLX'],
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
            return ((float) ($a['prix'] ?? 0)) <=> ((float) ($b['prix'] ?? 0));
        });

        return $variants;
    }

    private function filterFlightsByDepartureDate(array $flights, string $selectedDate): array
    {
        if ($selectedDate === '') {
            return $flights;
        }

        $filtered = array_filter($flights, function ($flight) use ($selectedDate) {
            if (!isset($flight['dateDepart']) || empty($flight['dateDepart'])) {
                return false;
            }

            $dateDepart = $flight['dateDepart'];

            if ($dateDepart instanceof \DateTimeInterface) {
                return $dateDepart->format('Y-m-d') === $selectedDate;
            }

            try {
                return (new \DateTime((string) $dateDepart))->format('Y-m-d') === $selectedDate;
            } catch (\Throwable $e) {
                return false;
            }
        });

        return array_values($filtered);
    }
}
