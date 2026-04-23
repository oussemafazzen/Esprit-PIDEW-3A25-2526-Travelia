<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AmadeusFlightService
{
    private HttpClientInterface $client;
    private string $apiKey;
    private string $defaultOrigin;

    public function __construct(
        HttpClientInterface $client,
        string $apiKey,
        string $defaultOrigin
    ) {
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->defaultOrigin = $defaultOrigin;
    }

    public function searchFlights(
        string $destinationCode,
        string $departureDate,
        int $adults,
        string $returnDate = '',
        string $tripType = 'one_way',
        string $travelClass = 'economy'
    ): array
    {
        $data = $this->fetchFlightsResponse($destinationCode, $departureDate, $adults, $returnDate, $tripType, $travelClass);
        $offers = array_merge($data['best_flights'] ?? [], $data['other_flights'] ?? []);

        $normalizedFlights = $this->normalizeSerpApiFlights($offers, strtoupper($destinationCode));
        error_log('SerpAPI normalized flights count: ' . count($normalizedFlights));

        return $normalizedFlights;
    }

    private function fetchFlightsResponse(
        string $destinationCode,
        string $departureDate,
        int $adults,
        string $returnDate,
        string $tripType,
        string $travelClass
    ): array
    {
        $isReturnTrip = $tripType === 'return' && $returnDate !== '';

        $query = [
            'engine' => 'google_flights',
            'departure_id' => strtoupper($this->defaultOrigin),
            'arrival_id' => strtoupper($destinationCode),
            'outbound_date' => $departureDate,
            'type' => $isReturnTrip ? 1 : 2,
            'adults' => max(1, $adults),
            'travel_class' => $this->mapTravelClassToSerpApi($travelClass),
            'currency' => 'EUR',
            'hl' => 'fr',
            'api_key' => $this->apiKey,
        ];

        if ($isReturnTrip) {
            $query['return_date'] = $returnDate;
        }

        error_log('SerpAPI request: ' . json_encode([
            'engine' => $query['engine'],
            'departure_id' => $query['departure_id'],
            'arrival_id' => $query['arrival_id'],
            'outbound_date' => $query['outbound_date'],
            'return_date' => $query['return_date'] ?? null,
            'type' => $query['type'],
            'adults' => $query['adults'],
            'travel_class' => $query['travel_class'],
            'currency' => $query['currency'],
        ]));

        try {
            $response = $this->client->request(
                'GET',
                'https://serpapi.com/search.json',
                [
                    'query' => $query,
                    'timeout' => 10,
                    'max_duration' => 12,
                ]
            );

            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            error_log('SerpAPI request failed or timed out: ' . $e->getMessage());
            return [];
        }

        if (!empty($data['error'])) {
            throw new \RuntimeException('SerpAPI error: ' . (string) $data['error']);
        }

        error_log('SerpAPI raw response: ' . json_encode($data));
        error_log('SerpAPI best_flights count: ' . count($data['best_flights'] ?? []));
        error_log('SerpAPI other_flights count: ' . count($data['other_flights'] ?? []));

        return $data;
    }

    private function mapTravelClassToSerpApi(string $travelClass): int
    {
        return match (mb_strtolower(trim($travelClass))) {
            'premium_economy' => 2,
            'business' => 3,
            'first' => 4,
            default => 1,
        };
    }

    private function normalizeSerpApiFlights(array $offers, string $tripDestinationAirportCode = ''): array
    {
        $normalizedFlights = [];

        foreach ($offers as $index => $offer) {
            $segments = array_values(array_filter($offer['flights'] ?? [], 'is_array'));
            if ($segments === []) {
                continue;
            }

            $destUpper = strtoupper(trim($tripDestinationAirportCode));
            $outboundEndIndex = null;
            if ($destUpper !== '' && count($segments) > 1) {
                foreach ($segments as $idx => $segment) {
                    $arrId = strtoupper((string) (($segment['arrival_airport'] ?? [])['id'] ?? ''));
                    if ($arrId === $destUpper) {
                        $outboundEndIndex = (int) $idx;
                        break;
                    }
                }
            }

            $returnSegments = [];
            if ($outboundEndIndex !== null && $outboundEndIndex < count($segments) - 1) {
                $outboundSegments = array_slice($segments, 0, $outboundEndIndex + 1);
                $returnSegments = array_slice($segments, $outboundEndIndex + 1);
            } else {
                $outboundSegments = $segments;
            }

            $firstSegment = $outboundSegments[0] ?? null;
            $lastOutboundSegment = $outboundSegments[count($outboundSegments) - 1] ?? null;

            if (!is_array($firstSegment) || !is_array($lastOutboundSegment)) {
                continue;
            }

            $departureAirport = $firstSegment['departure_airport'] ?? [];
            $arrivalAirport = $lastOutboundSegment['arrival_airport'] ?? [];
            $carrierNames = array_values(array_unique(array_filter(array_map(
                static fn(array $segment): string => (string) ($segment['airline'] ?? ''),
                $segments
            ))));
            $stopsCount = max(0, count($outboundSegments) - 1);

            $outboundDurationMinutes = null;
            foreach ($outboundSegments as $segment) {
                if (is_numeric($segment['duration'] ?? null)) {
                    $outboundDurationMinutes = ($outboundDurationMinutes ?? 0) + (int) $segment['duration'];
                }
            }
            $durationMinutes = $outboundDurationMinutes;
            if ($durationMinutes === null) {
                $durationMinutes = is_numeric($offer['total_duration'] ?? null)
                    ? (int) $offer['total_duration']
                    : (is_numeric($firstSegment['duration'] ?? null) ? (int) $firstSegment['duration'] : null);
            }

            $returnDepartureAt = '';
            $returnArrivalAt = '';
            if ($returnSegments !== []) {
                $firstReturn = $returnSegments[0];
                $lastReturn = $returnSegments[count($returnSegments) - 1];
                if (is_array($firstReturn) && is_array($lastReturn)) {
                    $returnDepartureAt = (string) (($firstReturn['departure_airport'] ?? [])['time'] ?? '');
                    $returnArrivalAt = (string) (($lastReturn['arrival_airport'] ?? [])['time'] ?? '');
                }
            }

            $normalizedFlights[] = [
                'id' => null,
                'reference' => (string) (
                    $firstSegment['flight_number']
                    ?? $offer['departure_token']
                    ?? ('SERP-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT))
                ),
                'origin' => (string) ($departureAirport['id'] ?? $this->defaultOrigin),
                'destination' => (string) ($arrivalAirport['id'] ?? ''),
                'originLabel' => (string) ($departureAirport['name'] ?? $departureAirport['id'] ?? $this->defaultOrigin),
                'destinationLabel' => (string) ($arrivalAirport['name'] ?? $arrivalAirport['id'] ?? ''),
                'departureAt' => (string) ($departureAirport['time'] ?? ''),
                'arrivalAt' => (string) ($arrivalAirport['time'] ?? ''),
                'returnDepartureAt' => $returnDepartureAt,
                'returnArrivalAt' => $returnArrivalAt,
                'durationMinutes' => $durationMinutes,
                'price' => $offer['price'] ?? 0,
                'currency' => (string) ($offer['price_info']['currency'] ?? 'EUR'),
                'airline' => (string) ($carrierNames[0] ?? $firstSegment['airline'] ?? ''),
                'stopsCount' => $stopsCount,
                'isDirect' => $stopsCount === 0,
                'cabinClass' => (string) ($offer['travel_class'] ?? $firstSegment['travel_class'] ?? ''),
                'transportType' => 'avion',
            ];
        }

        return $normalizedFlights;
    }
}
