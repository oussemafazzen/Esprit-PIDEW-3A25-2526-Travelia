<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UnsplashService
{
    private const BASE_URL = 'https://api.unsplash.com/search/photos';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $accessKey
    ) {
    }

    /**
     * Fetch the best matching photo URL for an accommodation.
     * Priority: search by accommodation name first, then city, then type.
     * Results are cached for 24 hours to avoid hitting API rate limits.
     */
    public function getPhotoForHebergement(
        string $nom,
        string $ville,
        string $pays,
        string $type = ''
    ): ?string {
        // Build search queries in priority order
        $queries = array_filter([
            trim("$nom $ville"), // Most specific: hotel name + city
            trim("$type hotel $ville"), // Type + city
            trim("$ville hotel"), // City hotel
            trim("$pays accommodation"), // Country accommodation
        ]);

        foreach ($queries as $query) {
            $cacheKey = 'unsplash_' . md5($query);

            $url = $this->cache->get($cacheKey, function (ItemInterface $item) use ($query): string|false {
                $item->expiresAfter(86400); // Cache for 24 hours

                try {
                    $response = $this->httpClient->request('GET', self::BASE_URL, [
                        'headers' => [
                            'Authorization' => 'Client-ID ' . $this->accessKey,
                            'Accept-Version' => 'v1',
                        ],
                        'query' => [
                            'query'       => $query,
                            'per_page'    => 5,
                            'orientation' => 'landscape',
                            'content_filter' => 'high',
                        ],
                        'verify_peer' => false,
                        'verify_host' => false,
                    ]);

                    $data = $response->toArray();

                    if (!empty($data['results'])) {
                        // Pick the photo with highest resolution from results
                        $photo = $data['results'][0];
                        return $photo['urls']['regular'] ?? false;
                    }

                    return false;
                } catch (\Throwable) {
                    return false;
                }
            });

            if ($url) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Fetch multiple photos for a gallery view (up to $count photos).
     */
    public function getGalleryPhotos(
        string $nom,
        string $ville,
        string $pays,
        int $count = 6
    ): array {
        $query    = trim("$nom $ville hotel");
        $cacheKey = 'unsplash_gallery_' . md5($query . $count);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $count): array {
            $item->expiresAfter(86400);

            try {
                $response = $this->httpClient->request('GET', self::BASE_URL, [
                    'headers' => [
                        'Authorization' => 'Client-ID ' . $this->accessKey,
                        'Accept-Version' => 'v1',
                    ],
                    'query' => [
                        'query'          => $query,
                        'per_page'       => $count,
                        'orientation'    => 'landscape',
                        'content_filter' => 'high',
                    ],
                    'verify_peer' => false,
                    'verify_host' => false,
                ]);

                $data = $response->toArray();

                return array_map(
                    fn($photo) => [
                        'url'         => $photo['urls']['regular'],
                        'thumb'       => $photo['urls']['thumb'],
                        'photographer' => $photo['user']['name'] ?? '',
                        'photographer_url' => $photo['user']['links']['html'] ?? '#',
                        'unsplash_url' => $photo['links']['html'] ?? '#',
                    ],
                    $data['results'] ?? []
                );
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
