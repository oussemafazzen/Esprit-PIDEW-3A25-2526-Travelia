<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DestinationCodeResolver
{
    /**
     * Deterministic major airport candidates by country ISO2 code.
     * Ordered by practical hub priority for better hit rate.
     */
    private array $countryAirportCandidatesByIso2 = [
        'AT' => ['VIE', 'SZG', 'INN'],
        'AU' => ['SYD', 'MEL', 'BNE', 'PER'],
        'BE' => ['BRU', 'CRL'],
        'BH' => ['BAH'],
        'BJ' => ['COO'],
        'BR' => ['GRU', 'GIG', 'BSB'],
        'CA' => ['YYZ', 'YUL', 'YVR', 'YYC'],
        'CH' => ['ZRH', 'GVA', 'BSL'],
        'CL' => ['SCL'],
        'CN' => ['PEK', 'PVG', 'CAN'],
        'CO' => ['BOG', 'MDE', 'CLO'],
        'CZ' => ['PRG'],
        'DE' => ['FRA', 'MUC', 'BER', 'DUS'],
        'DK' => ['CPH'],
        'DZ' => ['ALG', 'ORN'],
        'EC' => ['UIO', 'GYE'],
        'EG' => ['CAI', 'HRG'],
        'ES' => ['MAD', 'BCN', 'AGP', 'PMI'],
        'FI' => ['HEL'],
        'FR' => ['CDG', 'ORY', 'LYS', 'MRS', 'NCE'],
        'GB' => ['LHR', 'LGW', 'MAN', 'EDI'],
        'GE' => ['TBS'],
        'GH' => ['ACC'],
        'GR' => ['ATH', 'SKG'],
        'HU' => ['BUD'],
        'ID' => ['CGK', 'DPS'],
        'IE' => ['DUB'],
        'IN' => ['DEL', 'BOM', 'BLR'],
        'IQ' => ['BGW', 'EBL'],
        'IR' => ['IKA'],
        'IS' => ['KEF'],
        'IT' => ['FCO', 'MXP', 'VCE', 'NAP'],
        'JP' => ['HND', 'NRT', 'KIX'],
        'JO' => ['AMM'],
        'KE' => ['NBO'],
        'KR' => ['ICN', 'GMP'],
        'KW' => ['KWI'],
        'KZ' => ['ALA', 'NQZ'],
        'LB' => ['BEY'],
        'LK' => ['CMB'],
        'LU' => ['LUX'],
        'LY' => ['TIP'],
        'MA' => ['CMN', 'RAK', 'AGA', 'RBA'],
        'MG' => ['TNR'],
        'ML' => ['BKO'],
        'MT' => ['MLA'],
        'MX' => ['MEX', 'CUN', 'GDL'],
        'MY' => ['KUL'],
        'NG' => ['LOS', 'ABV'],
        'NL' => ['AMS'],
        'NO' => ['OSL'],
        'NP' => ['KTM'],
        'NZ' => ['AKL'],
        'OM' => ['MCT'],
        'PA' => ['PTY'],
        'PE' => ['LIM'],
        'PH' => ['MNL', 'CEB'],
        'PK' => ['KHI', 'LHE', 'ISB'],
        'PL' => ['WAW', 'KRK'],
        'PT' => ['LIS', 'OPO', 'FAO'],
        'QA' => ['DOH'],
        'RO' => ['OTP'],
        'RS' => ['BEG'],
        'RU' => ['SVO', 'DME', 'LED'],
        'RW' => ['KGL'],
        'SA' => ['RUH', 'JED', 'DMM'],
        'SE' => ['ARN', 'GOT'],
        'SG' => ['SIN'],
        'SK' => ['BTS'],
        'SN' => ['DSS'],
        'TH' => ['BKK', 'HKT'],
        'TN' => ['TUN', 'MIR', 'DJE'],
        'TR' => ['IST', 'SAW', 'AYT'],
        'TZ' => ['DAR', 'ZNZ'],
        'UA' => ['KBP'],
        'UG' => ['EBB'],
        'US' => ['JFK', 'EWR', 'LAX', 'ORD', 'ATL', 'MIA'],
        'UY' => ['MVD'],
        'VE' => ['CCS'],
        'VN' => ['SGN', 'HAN'],
        'ZA' => ['JNB', 'CPT'],
        'ZM' => ['LUN'],
        'ZW' => ['HRE'],
        'BO' => ['VVI', 'LPB', 'CBB'],
        'AR' => ['EZE', 'AEP'],
        'AE' => ['DXB', 'AUH'],
        'BH' => ['BAH'],
        'CI' => ['ABJ'],
        'ET' => ['ADD'],
        'CM' => ['NSI', 'DLA'],
    ];

    private array $majorAirportFallbacks = [
        'france' => 'CDG',
        'belgique' => 'BRU',
        'belgium' => 'BRU',
        'benin' => 'COO',
        'bahrein' => 'BAH',
        'bahrain' => 'BAH',
        'tunisie' => 'TUN',
        'tunisia' => 'TUN',
        'usa' => 'JFK',
        'etats unis' => 'JFK',
        'etats-unis' => 'JFK',
        'united states' => 'JFK',
        'allemagne' => 'FRA',
        'germany' => 'FRA',
        'espagne' => 'MAD',
        'spain' => 'MAD',
        'italie' => 'FCO',
        'italy' => 'FCO',
        'royaume uni' => 'LHR',
        'royaume-uni' => 'LHR',
        'united kingdom' => 'LHR',
        'uk' => 'LHR',
        'maroc' => 'CMN',
        'morocco' => 'CMN',
        'algerie' => 'ALG',
        'algeria' => 'ALG',
        'egypte' => 'CAI',
        'egypt' => 'CAI',
        'turquie' => 'IST',
        'turkey' => 'IST',
        'canada' => 'YYZ',
        'bresil' => 'GRU',
        'brazil' => 'GRU',
        'argentine' => 'EZE',
        'argentina' => 'EZE',
        'japon' => 'HND',
        'japan' => 'HND',
        'chine' => 'PEK',
        'china' => 'PEK',
        'inde' => 'DEL',
        'india' => 'DEL',
        'australie' => 'SYD',
        'australia' => 'SYD',
        'emirats arabes unis' => 'DXB',
        'united arab emirates' => 'DXB',
        'arabie saoudite' => 'RUH',
        'saudi arabia' => 'RUH',
        'qatar' => 'DOH',
        'russie' => 'SVO',
        'russia' => 'SVO',
    ];

    public function __construct(
        private HttpClientInterface $client
    ) {
    }

    public function resolve(string $destination): ?string
    {
        $candidates = $this->resolveCandidates($destination);
        return $candidates[0] ?? null;
    }

    public function resolveCandidates(string $destination): array
    {
        $original = trim($destination);
        $normalized = $this->normalizeInput($original);

        if ($normalized === '') {
            return [];
        }

        if (preg_match('/^[A-Z]{3}$/', strtoupper($normalized)) === 1) {
            return [strtoupper($normalized)];
        }

        $candidates = [];
        $countryData = $this->resolveCountryData($original, $normalized);

        $mappedCandidates = $this->resolveMappedCountryAirportCandidates($normalized, $countryData);
        if (count($mappedCandidates) > 0) {
            $candidates = array_merge($candidates, $mappedCandidates);
        }

        if ($countryData !== null) {
            if ($countryData['capitalLat'] !== null && $countryData['capitalLng'] !== null) {
                $candidates = array_merge(
                    $candidates,
                    $this->resolveNearestAirportCodesFromCoordinates(
                        $countryData['capitalLat'],
                        $countryData['capitalLng']
                    )
                );
            }

            if ($countryData['capital'] !== null) {
                $candidates = array_merge(
                    $candidates,
                    $this->resolveNearestAirportCodes($countryData['capital'])
                );

                $candidates = array_merge(
                    $candidates,
                    $this->resolveNearestAirportCodes($countryData['capital'] . ', ' . $original)
                );
            }
        }

        $candidates = array_merge($candidates, $this->resolveNearestAirportCodes($original));

        $fallbackCode = $this->resolveFallbackAirportCode($normalized);
        if ($fallbackCode !== null) {
            $candidates[] = $fallbackCode;
        }

        $candidates = array_values(array_unique(array_filter(array_map(
            static fn($code) => is_string($code) ? strtoupper(trim($code)) : '',
            $candidates
        ), static fn(string $code) => preg_match('/^[A-Z]{3}$/', $code) === 1)));

        return $candidates;
    }

    private function resolveCountryData(string $original, string $normalized): ?array
    {
        $queries = array_values(array_unique(array_filter([
            $original,
            $normalized,
        ])));

        foreach ($queries as $query) {
            foreach (['true', 'false'] as $fullText) {
                try {
                    $response = $this->client->request('GET', 'https://restcountries.com/v3.1/name/' . rawurlencode($query), [
                        'headers' => [
                            'Accept' => 'application/json',
                        ],
                        'query' => [
                            'fullText' => $fullText,
                            'fields' => 'capital,capitalInfo,translations,name,cca2,cca3',
                        ],
                    ]);

                    $countries = $response->toArray(false);
                    $country = $countries[0] ?? null;

                    if (!is_array($country)) {
                        continue;
                    }

                    $capital = $country['capital'][0] ?? null;
                    $capitalLat = $country['capitalInfo']['latlng'][0] ?? null;
                    $capitalLng = $country['capitalInfo']['latlng'][1] ?? null;

                    if (is_string($capital) && trim($capital) !== '') {
                        $countryNameCandidates = [];
                        if (isset($country['name']) && is_array($country['name'])) {
                            if (!empty($country['name']['common']) && is_string($country['name']['common'])) {
                                $countryNameCandidates[] = $country['name']['common'];
                            }
                            if (!empty($country['name']['official']) && is_string($country['name']['official'])) {
                                $countryNameCandidates[] = $country['name']['official'];
                            }
                        }

                        if (isset($country['translations']) && is_array($country['translations'])) {
                            foreach ($country['translations'] as $translation) {
                                if (!is_array($translation)) {
                                    continue;
                                }
                                if (!empty($translation['common']) && is_string($translation['common'])) {
                                    $countryNameCandidates[] = $translation['common'];
                                }
                                if (!empty($translation['official']) && is_string($translation['official'])) {
                                    $countryNameCandidates[] = $translation['official'];
                                }
                            }
                        }

                        return [
                            'capital' => trim($capital),
                            'capitalLat' => is_numeric($capitalLat) ? (float) $capitalLat : null,
                            'capitalLng' => is_numeric($capitalLng) ? (float) $capitalLng : null,
                            'iso2' => isset($country['cca2']) && is_string($country['cca2']) ? strtoupper($country['cca2']) : null,
                            'iso3' => isset($country['cca3']) && is_string($country['cca3']) ? strtoupper($country['cca3']) : null,
                            'countryNames' => array_values(array_unique(array_filter(array_map(
                                fn(string $name) => $this->normalizeInput($name),
                                array_filter($countryNameCandidates, static fn($n) => is_string($n) && trim($n) !== '')
                            )))),
                        ];
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return null;
    }

    private function resolveNearestAirportCode(string $placeQuery): ?string
    {
        $codes = $this->resolveNearestAirportCodes($placeQuery);
        return $codes[0] ?? null;
    }

    private function resolveNearestAirportCodes(string $placeQuery): array
    {
        $coordinatesCandidates = $this->resolveCoordinatesCandidates($placeQuery, 3);

        if (count($coordinatesCandidates) === 0) {
            return [];
        }

        $codes = [];

        foreach ($coordinatesCandidates as $coordinates) {
            $codes = array_merge(
                $codes,
                $this->resolveNearestAirportCodesFromCoordinates($coordinates['lat'], $coordinates['lng'])
            );
        }

        return array_values(array_unique($codes));
    }

    private function resolveNearestAirportCodeFromCoordinates(float $lat, float $lng): ?string
    {
        $codes = $this->resolveNearestAirportCodesFromCoordinates($lat, $lng);
        return $codes[0] ?? null;
    }

    private function resolveNearestAirportCodesFromCoordinates(float $lat, float $lng): array
    {
        try {
            $response = $this->client->request('GET', 'https://www.iatageo.com/v2/airports/nearest', [
                'query' => [
                    'lat' => $lat,
                    'lng' => $lng,
                    'types' => 'large_airport,medium_airport',
                    'range' => 500000,
                ],
            ]);

            $payload = $response->toArray(false);
            $iataCodes = [];

            if (isset($payload['data']) && is_array($payload['data'])) {
                if (isset($payload['data']['iataCode']) && is_string($payload['data']['iataCode'])) {
                    $iataCodes[] = strtoupper($payload['data']['iataCode']);
                }

                if (array_is_list($payload['data'])) {
                    foreach ($payload['data'] as $airport) {
                        if (is_array($airport) && isset($airport['iataCode']) && is_string($airport['iataCode'])) {
                            $iataCodes[] = strtoupper($airport['iataCode']);
                        }
                    }
                }
            }

            $iataCodes = array_values(array_unique(array_filter($iataCodes, static fn(string $code) => preg_match('/^[A-Z]{3}$/', $code) === 1)));
            if (count($iataCodes) > 0) {
                return $iataCodes;
            }
        } catch (\Throwable $e) {
        }

        return [];
    }

    private function resolveCoordinates(string $placeQuery): ?array
    {
        $coordinates = $this->resolveCoordinatesCandidates($placeQuery, 1);
        return $coordinates[0] ?? null;
    }

    private function resolveCoordinatesCandidates(string $placeQuery, int $limit = 1): array
    {
        $query = trim($placeQuery);

        if ($query === '') {
            return [];
        }

        try {
            $response = $this->client->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Travelia/1.0',
                ],
                'query' => [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => max(1, $limit),
                ],
            ]);

            $results = $response->toArray(false);
            $coordinates = [];

            foreach ($results as $result) {
                if (!is_array($result)) {
                    continue;
                }

                $lat = isset($result['lat']) ? (float) $result['lat'] : null;
                $lng = isset($result['lon']) ? (float) $result['lon'] : null;

                if ($lat === null || $lng === null) {
                    continue;
                }

                $coordinates[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            }

            return $coordinates;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function normalizeInput(string $destination): string
    {
        $value = trim($destination);
        $asciiValue = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($asciiValue !== false) {
            $value = $asciiValue;
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9\s-]/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function resolveFallbackAirportCode(string $normalized): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            $normalized,
            str_replace('-', ' ', $normalized),
            str_replace('-', '', $normalized),
            preg_replace('/\s+/', ' ', $normalized),
            preg_replace('/[\s-]+/', '', $normalized),
        ])));

        foreach ($candidates as $candidate) {
            if (isset($this->majorAirportFallbacks[$candidate])) {
                return $this->majorAirportFallbacks[$candidate];
            }
        }

        return null;
    }

    private function resolveMappedCountryAirportCandidates(string $normalized, ?array $countryData): array
    {
        $codes = [];

        if ($countryData !== null) {
            $iso2 = $countryData['iso2'] ?? null;
            if (is_string($iso2) && isset($this->countryAirportCandidatesByIso2[$iso2])) {
                $codes = array_merge($codes, $this->countryAirportCandidatesByIso2[$iso2]);
            }

            $countryNames = $countryData['countryNames'] ?? [];
            if (is_array($countryNames)) {
                foreach ($countryNames as $name) {
                    $fallbackCode = $this->resolveFallbackAirportCode((string) $name);
                    if ($fallbackCode !== null) {
                        $codes[] = $fallbackCode;
                    }
                }
            }
        }

        $fallbackFromInput = $this->resolveFallbackAirportCode($normalized);
        if ($fallbackFromInput !== null) {
            $codes[] = $fallbackFromInput;
        }

        return array_values(array_unique(array_filter(array_map(
            static fn($code) => is_string($code) ? strtoupper(trim($code)) : '',
            $codes
        ), static fn(string $code) => preg_match('/^[A-Z]{3}$/', $code) === 1)));
    }
}
