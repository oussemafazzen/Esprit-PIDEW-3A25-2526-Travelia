<?php

namespace App\Service;

use Symfony\Component\Intl\Countries;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HolidayService
 *
 * Fetches public holidays from the Abstract API (abstractapi.com/holidays-api)
 * using PHP/Symfony HttpClient (server-side only — no JavaScript involved).
 *
 * Country name → ISO code resolution works in THREE layers:
 *   1. Shorthand aliases map (UAE, USA, UK, etc.)
 *   2. symfony/intl full database (all country names in English + French + Arabic + more)
 *   3. Fuzzy partial match as last resort
 *
 * This means ANY country name added to the Hebergement table will be resolved
 * automatically — no manual updates to any map required.
 *
 * Implements simple file-based caching (TTL: 1 hour) to preserve the free-tier quota.
 */
class HolidayService
{
    private const API_URL  = 'https://holidays.abstractapi.com/v1/';
    private const CACHE_TTL = 3600; // 1 hour in seconds

    /**
     * Layer 1 — Shorthand / alias overrides.
     * Only abbreviations and common shortcuts that symfony/intl won't recognise.
     * You should NEVER need to add full country names here anymore.
     */
    private const ALIASES = [
        // Common abbreviations
        'uae'           => 'AE',
        'usa'           => 'US',
        'uk'            => 'GB',
        'us'            => 'US',

        // French abbreviations / informal names
        'etats-unis'    => 'US',
        'états-unis'    => 'US',
        'royaume-uni'   => 'GB',
        'royaume uni'   => 'GB',
        'grande-bretagne' => 'GB',
        'pays-bas'      => 'NL',
        'coree du sud'  => 'KR',
        'corée du sud'  => 'KR',
        'emirats arabes unis' => 'AE',
        'émirats arabes unis' => 'AE',
        'arabie saoudite' => 'SA',
        'afrique du sud' => 'ZA',
        'cote d\'ivoire' => 'CI',
        'côte d\'ivoire' => 'CI',

        // English informal
        'england'       => 'GB',
        'britain'       => 'GB',
        'holland'       => 'NL',
        'south korea'   => 'KR',
        'north korea'   => 'KP',
        'south africa'  => 'ZA',
        'saudi arabia'  => 'SA',
        'united states' => 'US',
        'united kingdom'=> 'GB',

        // Island / territory shortnames sometimes stored in DBs
        'st. lucia'     => 'LC',
        'saint lucia'   => 'LC',
        'st lucia'      => 'LC',
        'french polynesia' => 'PF',
        'polynésie française' => 'PF',
        'polynésie'     => 'PF',
        'maldives'      => 'MV',
        'seychelles'    => 'SC',
    ];

    /**
     * Locales to merge into the combined index.
     * Order matters: earlier locales take priority on conflicts.
     */
    private const INTL_LOCALES = ['en', 'fr', 'ar', 'es', 'de', 'it', 'pt'];

    /**
     * Single merged index: lowercase_country_name => ISO code.
     * Built once on first use from all locales — then every lookup is O(1).
     * @var array<string, string>|null
     */
    private ?array $mergedIndex = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $cacheDir
    ) {
    }

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Returns today's holiday for the given country name (as stored in the DB),
     * or null if there is no holiday today or the country cannot be resolved.
     *
     * @param string $paysName  e.g. "Tunisie", "Indonesia", "UAE", "St. Lucia"
     * @return array<string, mixed>|null
     */
    public function getHoliday(string $paysName, ?\DateTimeInterface $date = null): ?array
    {
        $date    ??= new \DateTimeImmutable();
        $isoCode   = $this->resolveIsoCode($paysName);

        if ($isoCode === null || !$this->isApiConfigured()) {
            return null;
        }

        $cached = $this->readCache($this->cacheKey($isoCode, $date));
        if ($cached !== null) {
            return $cached[0] ?? null;
        }

        $holidays = $this->fetchFromApi($isoCode, $date);
        $this->writeCache($this->cacheKey($isoCode, $date), $holidays);

        return $holidays[0] ?? null;
    }

    /**
     * Returns ALL holidays for the given country on the given date.
     *
     * @return list<array<string, mixed>>
     */
    public function getHolidays(string $paysName, ?\DateTimeInterface $date = null): array
    {
        $date    ??= new \DateTimeImmutable();
        $isoCode   = $this->resolveIsoCode($paysName);

        if ($isoCode === null || !$this->isApiConfigured()) {
            return [];
        }

        $cacheKey = $this->cacheKey($isoCode, $date);
        $cached   = $this->readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $holidays = $this->fetchFromApi($isoCode, $date);
        $this->writeCache($cacheKey, $holidays);

        return $holidays;
    }

    /**
     * Resolves a plain-text country name to an ISO 3166-1 alpha-2 code.
     *
     * Resolution order:
     *   1. ALIASES map  (abbreviations, informal names, shortcuts)
     *   2. Merged symfony/intl index — exact O(1) hash lookup (all languages)
     *   3. Prefix fuzzy match inside the merged index (pre-built, not looped)
     *
     * Returns null only when nothing matches at all.
     */
    public function resolveIsoCode(string $paysName): ?string
    {
        $normalized = $this->normalize($paysName);

        // ── Layer 1: aliases (instant) ────────────────────────────────────────
        if (isset(self::ALIASES[$normalized])) {
            return self::ALIASES[$normalized];
        }

        $index = $this->getMergedIndex();

        // ── Layer 2: exact match in merged index (O(1)) ───────────────────────
        if (isset($index[$normalized])) {
            return $index[$normalized];
        }

        // ── Layer 3: prefix match (one pass over pre-built index) ─────────────
        // Only runs when exact match fails (rare). Iterates the index once.
        foreach ($index as $name => $iso) {
            if (str_starts_with($name, $normalized) || str_starts_with($normalized, $name)) {
                return $iso;
            }
        }

        return null;
    }

    /**
     * Builds a human-friendly holiday message for the UI.
     */
    /**
     * @param array<string, mixed> $holiday
     */
    public function buildMessage(array $holiday): string
    {
        $name = $holiday['name'] ?? 'Jour Férié';
        $type = strtolower($holiday['type'] ?? '');

        return match ($type) {
            'national'   => "🎉 Jour Férié National : {$name} — Profitez de votre long weekend !",
            'observance' => "🌟 Événement spécial : {$name} — Une belle occasion de voyager !",
            'season'     => "🌸 {$name} — La saison idéale pour séjourner ici !",
            default      => "🎊 Jour Spécial : {$name} — Profitez de cette belle journée !",
        };
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * Builds (and memoises) the merged country-name → ISO index.
     * All locales are combined into ONE flat array, built only ONCE per request.
     * Earlier locales in INTL_LOCALES take priority on name conflicts.
     *
     * @return array<string, string>
     */
    private function getMergedIndex(): array
    {
        if ($this->mergedIndex !== null) {
            return $this->mergedIndex;
        }

        $merged = [];
        // Reverse so that earlier locales overwrite later ones (higher priority)
        foreach (array_reverse(self::INTL_LOCALES) as $locale) {
            try {
                $countryNames = Countries::getNames($locale);
            } catch (\Throwable) {
                // Some local WAMP/PHP setups miss ext-intl data for locales such as "pt".
                // Keep the admin page usable; enabling php_intl remains the correct fix.
                continue;
            }

            foreach ($countryNames as $iso => $name) {
                $merged[$this->normalize($name)] = $iso;
            }
        }

        $this->mergedIndex = $merged;
        return $this->mergedIndex;
    }

    private function isApiConfigured(): bool
    {
        return !empty($this->apiKey) && $this->apiKey !== 'your_api_key_here';
    }

    private function cacheKey(string $isoCode, \DateTimeInterface $date): string
    {
        return sprintf('holiday_%s_%s', $isoCode, $date->format('Y-m-d'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFromApi(string $isoCode, \DateTimeInterface $date): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => [
                    'api_key' => $this->apiKey,
                    'country' => $isoCode,
                    'year'    => (int) $date->format('Y'),
                    'month'   => (int) $date->format('m'),
                    'day'     => (int) $date->format('d'),
                ],
                'timeout' => 5,
            ]);

            return $response->toArray(false);
        } catch (\Throwable) {
            // Never break the page if the API is unreachable
            return [];
        }
    }

    // ─── File-based cache ────────────────────────────────────────────────────

    private function cacheFilePath(string $key): string
    {
        $dir = rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . 'holidays';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . DIRECTORY_SEPARATOR . md5($key) . '.json';
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function readCache(string $key): ?array
    {
        $file = $this->cacheFilePath($key);
        if (!file_exists($file)) {
            return null;
        }
        if ((time() - filemtime($file)) > self::CACHE_TTL) {
            @unlink($file);
            return null;
        }
        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }
        $data = json_decode($contents, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    private function writeCache(string $key, array $data): void
    {
        @file_put_contents($this->cacheFilePath($key), json_encode($data));
    }
}
