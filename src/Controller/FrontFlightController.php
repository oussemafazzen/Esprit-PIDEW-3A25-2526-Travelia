<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Entity\Client;
use App\Entity\FlightSearchData;
use App\Entity\Reservation;
use App\Form\FlightSearchType;
use App\Repository\BilletRepository;
use App\Repository\ClientRepository;
use App\Repository\ReservationRepository;
use App\Service\AmadeusFlightService;
use App\Service\DestinationCodeResolver;
use App\Service\PromoCodeEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
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
        $dateArrivee = trim((string) $request->query->get('date_arrivee', ''));
        $tripType = $this->normalizeTripType((string) $request->query->get('trip_type', 'return'));
        $travelClass = $this->normalizeSearchTravelClass((string) $request->query->get('travel_class', 'business'));
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
                'date_arrivee' => $tripType === 'return' ? $dateArrivee : '',
                'type_transport' => 'avion',
                'trip_type' => $tripType,
                'travel_class' => $travelClass,
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
        $tripType = $this->normalizeTripType((string) $request->query->get('trip_type', 'return'));
        $travelClass = $this->normalizeSearchTravelClass((string) $request->query->get('travel_class', 'business'));
        $typeTransport = trim((string) $request->query->get('type_transport', 'avion'));
        $passagers = max(1, (int) $request->query->get('passagers', 1));
        $reservationId = (int) $request->query->get('reservation_id', 0);

        $flightsWithOptions = [];
        $apiError = null;
        $destinationCode = null;
        $usedMockFallback = false;
        $apiReturnedFlights = false;
        $apiRawFlightsCount = 0;
        $apiNormalizedFlightsCount = 0;
        $apiDateFilteredFlightsCount = 0;
        $criteriaNotice = null;

        if ($tripType === 'return' && $dateArrivee === '') {
            $criteriaNotice = 'No exact match found for your selected criteria — showing closest available options.';
        }

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
                            adults: $passagers,
                            returnDate: $tripType === 'return' ? $dateArrivee : '',
                            tripType: $tripType,
                            travelClass: $travelClass
                        );

                        $apiRawFlightsCount = count($apiFlights);
                        error_log('Front flight API raw normalized count: ' . $apiRawFlightsCount);

                        if (!empty($apiFlights)) {
                            $apiReturnedFlights = true;
                            $normalizedApiFlights = $this->normalizeApiFlightsForDisplay(
                                $apiFlights,
                                $destination,
                                $dateDepart,
                                $tripType === 'return' ? $dateArrivee : ''
                            );

                            $apiNormalizedFlightsCount = count($normalizedApiFlights);
                            $normalizedApiFlights = $this->filterFlightsByDepartureDate($normalizedApiFlights, $dateDepart);
                            $apiDateFilteredFlightsCount = count($normalizedApiFlights);
                            error_log('Front flight API display count before date filter: ' . $apiNormalizedFlightsCount);
                            error_log('Front flight API display count after date filter: ' . $apiDateFilteredFlightsCount);
                            if ($apiNormalizedFlightsCount > 0 && $apiDateFilteredFlightsCount === 0) {
                                $criteriaNotice = 'No exact match found for your selected departure date — showing closest available options.';
                                $normalizedApiFlights = $this->decorateFlightsWithSearchCriteria(
                                    $this->normalizeApiFlightsForDisplay(
                                        $apiFlights,
                                        $destination,
                                        $dateDepart,
                                        $tripType === 'return' ? $dateArrivee : ''
                                    ),
                                    $tripType,
                                    $travelClass,
                                    $dateArrivee
                                );
                            } else {
                                $classMatchedFlights = $this->filterFlightsByRequestedClass($normalizedApiFlights, $travelClass);
                                if (count($classMatchedFlights) > 0) {
                                    $normalizedApiFlights = $classMatchedFlights;
                                }
                                $normalizedApiFlights = $this->decorateFlightsWithSearchCriteria($normalizedApiFlights, $tripType, $travelClass, $dateArrivee);
                            }
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

        if (count($flightsWithOptions) === 0 && (!$apiReturnedFlights || $apiError !== null)) {
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
            if ($usedMockFallback && $criteriaNotice === null) {
                $criteriaNotice = 'No exact match found for your selected criteria — showing closest available options.';
            }

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
                $criteriaNotice = 'No exact match found for your selected criteria — showing closest available options.';
                $filteredFlights = $baseFlights;
            }

            $filteredFlights = $this->filterFlightsByDepartureDate(array_values($filteredFlights), $dateDepart);
            if (count($filteredFlights) === 0 && count($baseFlights) > 0) {
                $criteriaNotice = 'No exact match found for your selected departure date — showing closest available options.';
                $filteredFlights = $baseFlights;
            }
            $filteredFlights = $this->decorateFlightsWithSearchCriteria($filteredFlights, $tripType, $travelClass, $dateArrivee);
            $flightsWithOptions = $this->buildFlightVariants(array_values($filteredFlights));
        }

        $flights = $flightsWithOptions;

        return $this->render('front/flights/results.html.twig', [
            'flights' => $flights,
            'destination' => $destination,
            'date_depart' => $dateDepart,
            'date_arrivee' => $dateArrivee,
            'trip_type' => $tripType,
            'travel_class' => $travelClass,
            'travel_class_label' => $this->getTravelClassLabel($travelClass),
            'type_transport' => $typeTransport,
            'passagers' => $passagers,
            'reservation_id' => $reservationId,
            'api_error' => $apiError,
            'destination_code_debug' => $destinationCode,
            'used_mock_fallback' => $usedMockFallback,
            'api_raw_flights_count' => $apiRawFlightsCount,
            'api_normalized_flights_count' => $apiNormalizedFlightsCount,
            'api_date_filtered_flights_count' => $apiDateFilteredFlightsCount,
            'criteria_notice' => $criteriaNotice,
        ]);
    }

    #[Route('/vols/ai-recommendations', name: 'app_flights_ai_recommendations', methods: ['POST'])]
    public function aiRecommendations(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json([
                'recommendations' => [],
                'error' => 'invalid_json_payload',
            ], Response::HTTP_BAD_REQUEST);
        }

        $submittedFlights = $payload['flights'] ?? [];
        $profile = in_array(($payload['profile'] ?? ''), ['budget', 'comfort', 'balanced'], true)
            ? (string) $payload['profile']
            : 'balanced';

        if (!is_array($submittedFlights)) {
            return $this->json([
                'recommendations' => [],
                'error' => 'invalid_flights_payload',
            ], Response::HTTP_BAD_REQUEST);
        }

        error_log('AI recommendation flights received: ' . json_encode($submittedFlights));

        $pythonBinary = $this->resolvePythonBinary();

        if ($pythonBinary === null) {
            return $this->json([
                'recommendations' => [],
                'error' => 'python_binary_not_found',
            ]);
        }

        $featureRows = [];

        foreach ($submittedFlights as $flightPayload) {
            if (!is_array($flightPayload)) {
                continue;
            }

            $index = isset($flightPayload['index']) ? (int) $flightPayload['index'] : null;

            if ($index === null) {
                continue;
            }

            $row = $this->buildAiRecommendationRow($flightPayload);

            if ($row === null) {
                error_log('AI recommendation skipped for flight ' . $index . ': invalid_feature_payload');
                continue;
            }

            $featureRows[] = $row;
        }

        error_log('AI recommendation rows sent to Python: ' . json_encode($featureRows));

        if (count($featureRows) === 0) {
            return $this->json([
                'recommendations' => [],
                'error' => 'no_valid_feature_rows',
            ]);
        }

        $debugReason = null;
        $recommendations = $this->recommendFlightsBatch(
            $pythonBinary,
            $featureRows,
            $profile,
            $debugReason
        );

        if ($recommendations === null) {
            error_log('AI recommendation batch skipped: ' . ($debugReason ?? 'recommendation_returned_null'));

            return $this->json([
                'recommendations' => [],
                'error' => $debugReason ?? 'recommendation_returned_null',
            ]);
        }

        return $this->json([
            'profile' => $profile,
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * Converts browser-submitted flight card data into the same feature shape
     * used by the existing Python/scikit-learn prediction script.
     */
    private function normalizeAiPredictionPayload(array $flightPayload): array
    {
        return [
            'destinationCountry' => (string) ($flightPayload['destination'] ?? ''),
            'destinationLabel' => (string) ($flightPayload['destination'] ?? ''),
            'prix' => isset($flightPayload['currentPrice']) && is_numeric($flightPayload['currentPrice'])
                ? (float) $flightPayload['currentPrice']
                : 0.0,
            'dateDepart' => (string) ($flightPayload['dateDepart'] ?? ''),
            'cabinClass' => (string) ($flightPayload['travelClass'] ?? ''),
            'offerLabel' => (string) ($flightPayload['travelClass'] ?? ''),
            'offerType' => (string) ($flightPayload['offerType'] ?? ''),
            'stopsCount' => isset($flightPayload['stopsCount']) && is_numeric($flightPayload['stopsCount'])
                ? (int) $flightPayload['stopsCount']
                : 0,
            'durationMinutes' => isset($flightPayload['durationMinutes']) && is_numeric($flightPayload['durationMinutes'])
                ? (int) $flightPayload['durationMinutes']
                : 120,
            'airline' => (string) ($flightPayload['airline'] ?? 'unknown'),
        ];
    }

    private function buildAiRecommendationRow(array $flightPayload): ?array
    {
        $flight = $this->normalizeAiPredictionPayload($flightPayload);
        $price = isset($flight['prix']) && is_numeric($flight['prix']) ? (float) $flight['prix'] : 0.0;

        if ($price <= 0) {
            return null;
        }

        return [
            'index' => isset($flightPayload['index']) ? (int) $flightPayload['index'] : 0,
            'destination' => (string) ($flight['destinationCountry'] ?? $flight['destinationLabel'] ?? 'unknown'),
            'current_price' => $price,
            'travel_class' => $this->normalizeTravelClass((string) ($flight['cabinClass'] ?? $flight['offerLabel'] ?? 'economy')),
            'offer_type' => (string) ($flight['offerType'] ?: ($flight['offerLabel'] ?? $flight['cabinClass'] ?? 'standard')),
            'airline' => (string) ($flight['airline'] ?? 'unknown'),
            'stops_count' => max(0, (int) ($flight['stopsCount'] ?? 0)),
            'duration_minutes' => max(1, (int) ($flight['durationMinutes'] ?? 120)),
            'departure_hour' => $this->resolveFlightDepartureHour((string) ($flight['dateDepart'] ?? $flightPayload['dateDepart'] ?? '')),
        ];
    }

    #[Route('/vols/paiement', name: 'app_flights_payment_submit', methods: ['POST'])]
    public function paymentSubmit(
        Request $request,
        BilletRepository $billetRepository,
        ReservationRepository $reservationRepository,
        ClientRepository $clientRepository,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        PromoCodeEvaluator $promoCodeEvaluator
    ): Response {
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

        $travelClass = $this->normalizeSearchTravelClass((string) $request->request->get('travel_class', 'economy'));
        $promoCode = trim((string) $request->request->get('promo_code', ''));
        $promoEvaluation = $promoCodeEvaluator->evaluate($promoCode, $selectedPrice, $travelClass);
        $promoErrors = [];
        if (in_array($promoEvaluation['status'], ['invalid', 'condition_failed'], true) && $promoEvaluation['message'] !== null && $promoEvaluation['message'] !== '') {
            $promoErrors[] = $promoEvaluation['message'];
        }

        $paymentPayload = $this->buildFlightPaymentPayloadForTemplate(
            $request,
            $cardholderName,
            $cardNumber,
            $expiryDate,
            $cvv,
            $bankName,
            $ibanOrRib,
            $accountHolderName,
            $paymentMethod
        );

        $promoRulesJson = json_encode(
            $promoCodeEvaluator->getClientRulesConfig(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
        );

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

        $validationErrors = array_merge($promoErrors, $validatorErrors);

        if (count($validationErrors) > 0) {
            return $this->render('front/flights/payment.html.twig', [
                'payment' => $paymentPayload,
                'payment_errors' => $validationErrors,
                'promo_rules_json' => $promoRulesJson,
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
                'payment' => $paymentPayload,
                'payment_errors' => $paymentErrors,
                'promo_rules_json' => $promoRulesJson,
            ]);
        }

        $billetId = (int) $request->request->get('billet_id', 0);
        $reservationId = (int) $request->request->get('reservation_id', 0);

        $reservation = null;
        $currentClient = $this->resolveAuthenticatedClient($clientRepository);

        if (!$currentClient instanceof Client || $currentClient->getId() === null) {
            return $this->render('front/flights/payment.html.twig', [
                'payment' => $paymentPayload,
                'payment_errors' => ['Vous devez être connecté avec un compte client valide pour confirmer cette réservation.'],
                'promo_rules_json' => $promoRulesJson,
            ]);
        }

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
                $reservation->setClientId($currentClient->getId());
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

        $this->applyBilletBookingSnapshot($billet, $request);

        $finalPrice = $promoEvaluation['final_price'];
        if ($finalPrice > 0 && method_exists($billet, 'setPrix')) {
            $billet->setPrix($finalPrice);
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
    public function paymentPage(Request $request, PromoCodeEvaluator $promoCodeEvaluator): Response
    {
        $travelClass = $this->normalizeSearchTravelClass((string) $request->request->get('travel_class', 'economy'));
        $tripType = $this->normalizeTripType((string) $request->request->get('trip_type', 'return'));
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
            'travel_class' => $travelClass,
            'travel_class_label' => $this->getTravelClassLabel($travelClass),
            'trip_type' => $tripType,
            'booked_stops_count' => (string) $request->request->get('booked_stops_count', ''),
            'booked_duration_minutes' => (string) $request->request->get('booked_duration_minutes', ''),
            'booked_return_date' => (string) $request->request->get('booked_return_date', ''),
            'promo_code' => '',
            'payment_method' => 'carte',
        ];

        $promoRulesJson = json_encode(
            $promoCodeEvaluator->getClientRulesConfig(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
        );

        return $this->render('front/flights/payment.html.twig', [
            'payment' => $paymentData,
            'promo_rules_json' => $promoRulesJson,
        ]);
    }

    private function resolveAuthenticatedClient(ClientRepository $clientRepository): ?Client
    {
        $user = $this->getUser();

        if ($user instanceof Client) {
            return $user;
        }

        $identifier = null;

        if ($user instanceof UserInterface) {
            $identifier = $user->getUserIdentifier();
        } elseif (is_object($user) && method_exists($user, 'getEmail')) {
            $identifier = (string) $user->getEmail();
        }

        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return null;
        }

        return $clientRepository->findOneBy(['email' => $identifier]);
    }

    private function buildFlightPaymentPayloadForTemplate(
        Request $request,
        string $cardholderName,
        string $cardNumber,
        string $expiryDate,
        string $cvv,
        string $bankName,
        string $ibanOrRib,
        string $accountHolderName,
        string $paymentMethod
    ): array {
        $selectedPrice = (float) $request->request->get('selected_price', 0);
        $travelClass = $this->normalizeSearchTravelClass((string) $request->request->get('travel_class', 'economy'));
        $tripType = $this->normalizeTripType((string) $request->request->get('trip_type', 'return'));

        return [
            'billet_id' => (int) $request->request->get('billet_id', 0),
            'reservation_id' => (int) $request->request->get('reservation_id', 0),
            'selected_price' => $selectedPrice > 0 ? number_format($selectedPrice, 2, '.', '') : trim((string) $request->request->get('selected_price', '')),
            'selected_date_depart' => trim((string) $request->request->get('selected_date_depart', '')),
            'selected_date_arrivee' => trim((string) $request->request->get('selected_date_arrivee', '')),
            'destination' => trim((string) $request->request->get('destination', '')),
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
            'travel_class' => $travelClass,
            'travel_class_label' => $this->getTravelClassLabel($travelClass),
            'trip_type' => $tripType,
            'booked_stops_count' => trim((string) $request->request->get('booked_stops_count', '')),
            'booked_duration_minutes' => trim((string) $request->request->get('booked_duration_minutes', '')),
            'booked_return_date' => trim((string) $request->request->get('booked_return_date', '')),
            'promo_code' => trim((string) $request->request->get('promo_code', '')),
        ];
    }

    private function applyBilletBookingSnapshot(Billet $billet, Request $request): void
    {
        $tripType = $this->normalizeTripType((string) $request->request->get('trip_type', 'return'));
        $travelClass = $this->normalizeSearchTravelClass((string) $request->request->get('travel_class', 'economy'));
        $fareLabel = trim((string) $request->request->get('offer_label', ''));

        $stopsRaw = $request->request->get('booked_stops_count');
        $stops = is_numeric($stopsRaw) ? (int) $stopsRaw : null;

        $durationRaw = $request->request->get('booked_duration_minutes');
        $durationMinutes = is_numeric($durationRaw) ? (int) $durationRaw : null;

        $origin = strtoupper(trim((string) $request->request->get('origin_code', '')));
        $destination = strtoupper(trim((string) $request->request->get('destination_code', '')));
        if ($origin === '---') {
            $origin = '';
        }
        if ($destination === '---') {
            $destination = '';
        }

        $billet->setBookedTripType($tripType);
        $billet->setBookedTravelClass($travelClass);
        $billet->setBookedFareLabel($fareLabel !== '' ? $fareLabel : null);
        $billet->setBookedStopsCount($stops);
        $billet->setBookedDurationMinutes($durationMinutes);
        $billet->setBookedOriginCode($origin !== '' ? $origin : null);
        $billet->setBookedDestinationCode($destination !== '' ? $destination : null);

        $returnDateStr = trim((string) $request->request->get('booked_return_date', ''));
        if ($tripType === 'return' && $returnDateStr !== '') {
            try {
                $billet->setBookedReturnDate(new \DateTimeImmutable($returnDateStr));
            } catch (\Throwable) {
                $billet->setBookedReturnDate(null);
            }
        } else {
            $billet->setBookedReturnDate(null);
        }
    }

    /**
     * Finds a local Python command. The page must keep working even when Python
     * is not installed, so this returns null instead of throwing.
     */
    private function resolvePythonBinary(): ?string
    {
        $projectDir = dirname(__DIR__, 2);
        $configuredBinary = $_ENV['PYTHON_BINARY']
            ?? $_SERVER['PYTHON_BINARY']
            ?? (getenv('PYTHON_BINARY') ?: null);
        $candidates = array_filter([
            $configuredBinary,
            $projectDir . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
            $projectDir . DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
            'python',
            'py',
            'python3',
        ]);

        foreach ($candidates as $binary) {
            try {
                $process = new Process([$binary, '--version']);
                $process->setTimeout(2);
                $process->run();

                if ($process->isSuccessful()) {
                    return $binary;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Runs the Python recommendation engine once for all submitted flight cards.
     */
    private function recommendFlightsBatch(string $pythonBinary, array $featureRows, string $profile, ?string &$debugReason = null): ?array
    {
        $recommendationScript = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'recommend_flights.py';

        if (!is_file($recommendationScript)) {
            $debugReason = 'recommendation_script_not_found: ' . $recommendationScript;
            error_log('AI recommendation skipped: ' . $debugReason);
            return null;
        }

        try {
            $process = new Process([
                $pythonBinary,
                $recommendationScript,
                '--batch-json',
                '-',
                '--profile',
                $profile,
            ]);
            $process->setInput(json_encode($featureRows, JSON_THROW_ON_ERROR));
            $process->setTimeout(8);
            $process->run();

            if (!$process->isSuccessful()) {
                $debugReason = 'python_process_failed: ' . trim($process->getErrorOutput() ?: $process->getOutput());
                error_log('AI recommendation skipped: ' . $debugReason);
                return null;
            }

            $output = trim($process->getOutput());

            if ($output === '') {
                $debugReason = 'python_process_empty_output';
                error_log('AI recommendation skipped: ' . $debugReason);
                return null;
            }

            $payload = json_decode($output, true);
            error_log('AI recommendation raw Python stdout: ' . $output);
            error_log('AI recommendation decoded Python JSON: ' . json_encode($payload));

            if (!is_array($payload)) {
                $debugReason = 'python_json_parse_failed: ' . json_last_error_msg() . ' output=' . $output;
                error_log('AI recommendation skipped: ' . $debugReason);
                return null;
            }

            if (!empty($payload['error'])) {
                $debugReason = 'python_recommendation_error: ' . (string) $payload['error'];
                error_log('AI recommendation skipped: ' . $debugReason);
                return null;
            }

            $recommendations = $payload['recommendations'] ?? null;

            if (!is_array($recommendations)) {
                $debugReason = 'missing_recommendations';
                error_log('AI recommendation skipped: ' . $debugReason);
                return null;
            }

            return array_values($recommendations);
        } catch (\Throwable $e) {
            $debugReason = 'prediction_exception: ' . $e->getMessage();
            error_log('AI recommendation skipped: ' . $debugReason);
            return null;
        }
    }

    private function resolveFlightDepartureDate(array $flight, string $searchDateDepart): ?\DateTimeInterface
    {
        if (($flight['dateDepart'] ?? null) instanceof \DateTimeInterface) {
            return $flight['dateDepart'];
        }

        $dateValue = (string) ($flight['dateDepart'] ?? $searchDateDepart);

        if ($dateValue === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($dateValue);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveFlightDepartureHour(string $dateValue): int
    {
        if (trim($dateValue) === '') {
            return 9;
        }

        try {
            return (int) (new \DateTimeImmutable($dateValue))->format('G');
        } catch (\Throwable $e) {
            return 9;
        }
    }

    private function normalizeTravelClass(string $travelClass): string
    {
        $value = mb_strtolower(trim($travelClass));

        if (str_contains($value, 'first')) {
            return 'first';
        }

        if (str_contains($value, 'business')) {
            return 'business';
        }

        if (str_contains($value, 'flex')) {
            return 'flex';
        }

        if (str_contains($value, 'standard')) {
            return 'standard';
        }

        return 'economy';
    }

    private function filterFlightsByRequestedClass(array $flights, string $requestedClass): array
    {
        $requestedClass = $this->normalizeSearchTravelClass($requestedClass);

        $filtered = array_values(array_filter($flights, function (array $flight) use ($requestedClass): bool {
            $rawClass = (string) ($flight['cabinClass'] ?? $flight['offerLabel'] ?? '');
            if ($rawClass === '') {
                return false;
            }

            return $this->normalizeTravelClass($rawClass) === $requestedClass;
        }));

        return $filtered;
    }

    private function normalizeSearchTravelClass(string $travelClass): string
    {
        return match (mb_strtolower(trim($travelClass))) {
            'business' => 'business',
            'first' => 'first',
            'premium_economy' => 'premium_economy',
            default => 'economy',
        };
    }

    private function normalizeTripType(string $tripType): string
    {
        return $tripType === 'one_way' ? 'one_way' : 'return';
    }

    private function getTravelClassLabel(string $travelClass): string
    {
        return match ($this->normalizeSearchTravelClass($travelClass)) {
            'business' => 'Business',
            'first' => 'First Class',
            'premium_economy' => 'Premium Economy',
            default => 'Economy',
        };
    }

    private function decorateFlightsWithSearchCriteria(array $flights, string $tripType, string $travelClass, string $dateArrivee): array
    {
        $travelClass = $this->normalizeSearchTravelClass($travelClass);
        $travelClassLabel = $this->getTravelClassLabel($travelClass);

        return array_map(function (array $flight) use ($tripType, $travelClass, $travelClassLabel, $dateArrivee): array {
            $actualClassRaw = trim((string) ($flight['cabinClass'] ?? $flight['offerLabel'] ?? ''));
            $actualTravelClass = $actualClassRaw !== '' ? $this->normalizeTravelClass($actualClassRaw) : '';
            $actualTravelClassLabel = $actualTravelClass !== ''
                ? $this->getTravelClassLabel($actualTravelClass)
                : '';

            $flight['tripType'] = $tripType;
            $flight['requestedTravelClass'] = $travelClass;
            $flight['requestedTravelClassLabel'] = $travelClassLabel;
            $flight['requestedReturnDate'] = $tripType === 'return' ? $dateArrivee : '';
            $flight['actualTravelClass'] = $actualTravelClass;
            $flight['actualTravelClassLabel'] = $actualTravelClassLabel;
            $flight['classMismatch'] = $actualTravelClass !== '' && $actualTravelClass !== $travelClass;

            return $flight;
        }, $flights);
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

    private function normalizeApiFlightsForDisplay(
        array $apiFlights,
        string $destination,
        string $fallbackDateDepart,
        string $fallbackReturnDate = ''
    ): array {
        $flights = [];

        foreach ($apiFlights as $index => $flight) {
            $departureAt = null;
            $arrivalAt = null;
            $departureAtRaw = isset($flight['departureAt']) ? trim((string) $flight['departureAt']) : '';
            $arrivalAtRaw = isset($flight['arrivalAt']) ? trim((string) $flight['arrivalAt']) : '';
            $originCode = 'TUN';
            $destinationCode = $destination;
            $originLabel = 'Tunis';
            $destinationLabel = $destination;
            $durationMinutes = isset($flight['durationMinutes']) && is_numeric($flight['durationMinutes'])
                ? (int) $flight['durationMinutes']
                : null;

            if ($departureAtRaw !== '') {
                try {
                    $departureAt = new \DateTime($departureAtRaw);
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

            if ($arrivalAtRaw !== '') {
                try {
                    $arrivalAt = new \DateTime($arrivalAtRaw);
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

            if (
                $departureAt instanceof \DateTimeInterface
                && $fallbackDateDepart !== ''
                && $departureAt->format('Y-m-d') !== $fallbackDateDepart
                && preg_match('/^\d{1,2}:\d{2}(?:\s*[AP]M)?$/i', $departureAtRaw) === 1
            ) {
                try {
                    $departureAt = new \DateTime($fallbackDateDepart . ' ' . $departureAtRaw);
                } catch (\Throwable $e) {
                }
            }

            if (
                $arrivalAt instanceof \DateTimeInterface
                && $fallbackDateDepart !== ''
                && $arrivalAt->format('Y-m-d') !== $fallbackDateDepart
                && preg_match('/^\d{1,2}:\d{2}(?:\s*[AP]M)?$/i', $arrivalAtRaw) === 1
            ) {
                try {
                    $arrivalAt = new \DateTime($fallbackDateDepart . ' ' . $arrivalAtRaw);
                } catch (\Throwable $e) {
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

            $returnDepartureAt = null;
            $returnArrivalAt = null;
            $returnDepartureAtRaw = isset($flight['returnDepartureAt']) ? trim((string) $flight['returnDepartureAt']) : '';
            $returnArrivalAtRaw = isset($flight['returnArrivalAt']) ? trim((string) $flight['returnArrivalAt']) : '';

            if ($returnDepartureAtRaw !== '') {
                try {
                    $returnDepartureAt = new \DateTime($returnDepartureAtRaw);
                } catch (\Throwable $e) {
                    $returnDepartureAt = null;
                }
            }

            if ($returnArrivalAtRaw !== '') {
                try {
                    $returnArrivalAt = new \DateTime($returnArrivalAtRaw);
                } catch (\Throwable $e) {
                    $returnArrivalAt = null;
                }
            }

            if (
                $returnDepartureAt instanceof \DateTimeInterface
                && $fallbackReturnDate !== ''
                && $returnDepartureAt->format('Y-m-d') !== $fallbackReturnDate
                && preg_match('/^\d{1,2}:\d{2}(?:\s*[AP]M)?$/i', $returnDepartureAtRaw) === 1
            ) {
                try {
                    $returnDepartureAt = new \DateTime($fallbackReturnDate . ' ' . $returnDepartureAtRaw);
                } catch (\Throwable $e) {
                }
            }

            if (
                $returnArrivalAt instanceof \DateTimeInterface
                && $fallbackReturnDate !== ''
                && $returnArrivalAt->format('Y-m-d') !== $fallbackReturnDate
                && preg_match('/^\d{1,2}:\d{2}(?:\s*[AP]M)?$/i', $returnArrivalAtRaw) === 1
            ) {
                try {
                    $returnArrivalAt = new \DateTime($fallbackReturnDate . ' ' . $returnArrivalAtRaw);
                } catch (\Throwable $e) {
                }
            }

            if (!$returnArrivalAt && $returnDepartureAt instanceof \DateTimeInterface) {
                $returnArrivalAt = \DateTime::createFromInterface($returnDepartureAt);
                $returnArrivalAt->modify('+2 hours');
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
                'dateReturnDepart' => $returnDepartureAt,
                'dateReturnArrivee' => $returnArrivalAt,
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
