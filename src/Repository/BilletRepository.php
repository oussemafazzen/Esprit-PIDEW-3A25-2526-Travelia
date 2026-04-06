<?php

namespace App\Repository;

use App\Entity\Billet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BilletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Billet::class);
    }

    /**
     * Recherche souple :
     * - essaie destination + date + transport
     * - si aucun résultat, retourne les billets disponibles
     *   mais en respectant le transport choisi
     */
    public function findAvailableFlights(string $destination, string $date, string $typeTransport = ''): array
    {
        $destination = mb_strtolower(trim($destination));
        $typeTransport = mb_strtolower(trim($typeTransport));
        $targetDate = $date ? new \DateTime($date) : null;

        $billets = $this->findAll();
        $results = [];

        foreach ($billets as $billet) {
            $arrival = $this->extractArrivalValue($billet);
            $departDate = $this->extractDepartureDate($billet);
            $transport = mb_strtolower(trim((string) $this->extractTransportType($billet)));

            if (!$departDate) {
                continue;
            }

            $matchesDestination = true;
            $matchesDate = true;
            $matchesTransport = true;

            if ($destination !== '') {
                $arrivalNormalized = mb_strtolower(trim((string) $arrival));
                $matchesDestination = str_contains($arrivalNormalized, $destination);
            }

            if ($targetDate) {
                $matchesDate = $departDate->format('Y-m-d') === $targetDate->format('Y-m-d');
            }

            if ($typeTransport !== '') {
                $matchesTransport = ($transport === $typeTransport);
            }

            if ($matchesDestination && $matchesDate && $matchesTransport) {
                $results[] = $billet;
            }
        }

        if (count($results) === 0) {
            return $this->findFallbackFlights($typeTransport);
        }

        usort($results, function ($a, $b) {
            $dateA = $this->extractDepartureDate($a);
            $dateB = $this->extractDepartureDate($b);

            if (!$dateA && !$dateB) {
                return 0;
            }

            if (!$dateA) {
                return 1;
            }

            if (!$dateB) {
                return -1;
            }

            return $dateA <=> $dateB;
        });

        return $results;
    }

    /**
     * Fallback intelligent :
     * - si transport choisi => retourne seulement ce transport
     * - sinon => retourne tous les billets
     */
    private function findFallbackFlights(string $typeTransport = ''): array
    {
        $billets = $this->findAll();

        if ($typeTransport !== '') {
            $billets = array_filter($billets, function ($billet) use ($typeTransport) {
                $transport = mb_strtolower(trim((string) $this->extractTransportType($billet)));
                return $transport === $typeTransport;
            });
        }

        $billets = array_values($billets);

        usort($billets, function ($a, $b) {
            $dateA = $this->extractDepartureDate($a);
            $dateB = $this->extractDepartureDate($b);

            if (!$dateA && !$dateB) {
                return 0;
            }

            if (!$dateA) {
                return 1;
            }

            if (!$dateB) {
                return -1;
            }

            return $dateA <=> $dateB;
        });

        return $billets;
    }

    /**
     * Retourne tous les billets disponibles triés par date
     */
    public function findAllAvailableFlights(): array
    {
        return $this->findFallbackFlights();
    }

    /**
     * Normalisation pour Twig
     */
    public function normalizeBilletsForDisplay(array $billets): array
    {
        $normalized = [];

        foreach ($billets as $billet) {
            $normalized[] = [
                'id' => $this->extractId($billet),
                'reference' => $this->extractReference($billet),
                'depart' => $this->extractDepartureValue($billet),
                'arrivee' => $this->extractArrivalValue($billet),
                'dateDepart' => $this->extractDepartureDate($billet),
                'dateArrivee' => $this->extractArrivalDate($billet),
                'prix' => $this->extractPrice($billet),
                'typeTransport' => $this->extractTransportType($billet),
            ];
        }

        return $normalized;
    }

    private function extractId(Billet $billet): ?int
    {
        return method_exists($billet, 'getId') ? $billet->getId() : null;
    }

    private function extractReference(Billet $billet): string
    {
        $possibleGetters = [
            'getReference',
            'getCodeBillet',
            'getNumeroBillet',
            'getNumero',
        ];

        foreach ($possibleGetters as $getter) {
            if (method_exists($billet, $getter)) {
                $value = $billet->$getter();

                if (!empty($value)) {
                    return (string) $value;
                }
            }
        }

        $id = $this->extractId($billet);

        return $id ? 'Billet #' . $id : 'Billet';
    }

    private function extractDepartureValue(Billet $billet): ?string
    {
        $possibleGetters = [
            'getVilleDepart',
            'getDepart',
            'getDepartureCity',
            'getLieuDepart',
            'getOrigine',
        ];

        foreach ($possibleGetters as $getter) {
            if (method_exists($billet, $getter)) {
                $value = $billet->$getter();

                if (!empty($value)) {
                    return (string) $value;
                }
            }
        }

        return 'TUN';
    }

    private function extractArrivalValue(Billet $billet): ?string
    {
        $possibleGetters = [
            'getDestination',
            'getVilleArrivee',
            'getArrivalCity',
            'getArrivee',
            'getPays',
            'getPaysDestination',
            'getVille',
            'getLieuArrivee',
        ];

        foreach ($possibleGetters as $getter) {
            if (method_exists($billet, $getter)) {
                $value = $billet->$getter();

                if (!empty($value)) {
                    return (string) $value;
                }
            }
        }

        return 'Destination';
    }

    private function extractDepartureDate(Billet $billet): ?\DateTimeInterface
    {
        $possibleGetters = [
            'getDateDepart',
            'getDepartureDate',
            'getDateVol',
            'getDate',
        ];

        foreach ($possibleGetters as $getter) {
            if (method_exists($billet, $getter)) {
                $value = $billet->$getter();

                if ($value instanceof \DateTimeInterface) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractArrivalDate(Billet $billet): ?\DateTimeInterface
    {
        $possibleGetters = [
            'getDateArrivee',
            'getArrivalDate',
        ];

        foreach ($possibleGetters as $getter) {
            if (method_exists($billet, $getter)) {
                $value = $billet->$getter();

                if ($value instanceof \DateTimeInterface) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractPrice(Billet $billet): float|int|string|null
    {
        $possibleGetters = [
            'getPrix',
            'getPrice',
            'getMontant',
            'getTarif',
        ];

        foreach ($possibleGetters as $getter) {
            if (method_exists($billet, $getter)) {
                $value = $billet->$getter();

                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractTransportType(Billet $billet): ?string
    {
        $possibleGetters = [
            'getTypeTransport',
            'getTransportType',
            'getType',
        ];

        foreach ($possibleGetters as $getter) {
            if (method_exists($billet, $getter)) {
                $value = $billet->$getter();

                if (!empty($value)) {
                    return (string) $value;
                }
            }
        }

        return 'avion';
    }
}