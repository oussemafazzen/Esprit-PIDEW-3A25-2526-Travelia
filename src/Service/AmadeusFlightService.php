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

    public function searchFlights(string $destinationCode, string $departureDate, int $adults): array
    {
        $data = $this->fetchFlightsResponse($destinationCode, $departureDate, $adults);
        $offers = array_merge($data['best_flights'] ?? [], $data['other_flights'] ?? []);

        $normalizedFlights = $this->normalizeSerpApiFlights($offers);
        error_log('SerpAPI normalized flights count: ' . count($normalizedFlights));

        return $normalizedFlights;
    }

    private function fetchFlightsResponse(string $destinationCode, string $departureDate, int $adults): array
    {
        $query = [
            'engine' => 'google_flights',
            'departure_id' => strtoupper($this->defaultOrigin),
            'arrival_id' => strtoupper($destinationCode),
            'outbound_date' => $departureDate,
            'type' => 2,
            'adults' => max(1, $adults),
            'currency' => 'EUR',
            'hl' => 'fr',
            'api_key' => $this->apiKey,
        ];

        error_log('SerpAPI request: ' . json_encode([
            'engine' => $query['engine'],
            'departure_id' => $query['departure_id'],
            'arrival_id' => $query['arrival_id'],
            'outbound_date' => $query['outbound_date'],
            'adults' => $query['adults'],
            'currency' => $query['currency'],
        ]));

        $response = $this->client->request(
            'GET',
            'https://serpapi.com/search.json',
            [
                'query' => $query,
            ]
        );

        $data = $response->toArray(false);
        error_log('SerpAPI raw response: ' . json_encode($data));
        error_log('SerpAPI best_flights count: ' . count($data['best_flights'] ?? []));
        error_log('SerpAPI other_flights count: ' . count($data['other_flights'] ?? []));

        return $data;
    }

    private function normalizeSerpApiFlights(array $offers): array
    {
        $normalizedFlights = [];

        foreach ($offers as $index => $offer) {
            $segments = $offer['flights'] ?? [];
            $firstSegment = $segments[0] ?? null;
            $lastSegment = $segments[count($segments) - 1] ?? null;

            if (!is_array($firstSegment) || !is_array($lastSegment)) {
                continue;
            }

            $departureAirport = $firstSegment['departure_airport'] ?? [];
            $arrivalAirport = $lastSegment['arrival_airport'] ?? [];
            $carrierNames = array_values(array_unique(array_filter(array_map(
                static fn(array $segment): string => (string) ($segment['airline'] ?? ''),
                array_filter($segments, 'is_array')
            ))));
            $stopsCount = max(0, count($segments) - 1);

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
                'durationMinutes' => is_numeric($offer['total_duration'] ?? null)
                    ? (int) $offer['total_duration']
                    : (is_numeric($firstSegment['duration'] ?? null) ? (int) $firstSegment['duration'] : null),
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
