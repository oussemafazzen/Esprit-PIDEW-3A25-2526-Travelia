<?php

namespace App\Repository;

use App\Entity\Activite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activite>
 */
class ActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    /**
     * Search + sort for admin list.
     *
     * @return Activite[]
     */
    public function searchAndSort(?string $search, ?string $sortBy, string $direction = 'ASC'): array
    {
        $allowedSorts = ['nom', 'lieu', 'prix', 'duree', 'capacite_max', 'categorie'];
        $allowedDirs  = ['ASC', 'DESC'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'nom';
        }
        if (!in_array($direction, $allowedDirs, true)) {
            $direction = 'ASC';
        }

        // Map PHP property names to DQL field names
        $fieldMap = [
            'capacite_max' => 'a.capaciteMax',
        ];
        $dqlField = $fieldMap[$sortBy] ?? "a.{$sortBy}";

        $qb = $this->createQueryBuilder('a')
            ->orderBy($dqlField, $direction);

        if ($search !== null && $search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(a.nom)', ':s'),
                    $qb->expr()->like('LOWER(a.lieu)', ':s'),
                    $qb->expr()->like('LOWER(a.categorie)', ':s'),
                    $qb->expr()->like('LOWER(a.description)', ':s'),
                )
            )->setParameter('s', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** Stats helpers */
    public function countByCategorie(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.categorie AS categorie, COUNT(a.idActivite) AS total')
            ->groupBy('a.categorie')
            ->getQuery()
            ->getResult();
    }

    public function avgPrix(): float
    {
        $result = $this->createQueryBuilder('a')
            ->select('AVG(a.prix)')
            ->getQuery()
            ->getSingleScalarResult();
        return round((float) $result, 2);
    }
}
