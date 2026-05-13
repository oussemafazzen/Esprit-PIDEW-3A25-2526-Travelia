<?php

namespace App\Repository;

use App\Entity\InscriptionActivite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InscriptionActivite>
 */
class InscriptionActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InscriptionActivite::class);
    }

    /**
     * Search + sort for admin list.
     *
     * @return InscriptionActivite[]
     */
    public function searchAndSort(?string $search, ?string $sortBy, string $direction = 'DESC'): array
    {
        $allowedSorts = ['date', 'statut', 'participants', 'activite', 'client'];
        $allowedDirs  = ['ASC', 'DESC'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'date';
        }
        if (!in_array($direction, $allowedDirs, true)) {
            $direction = 'DESC';
        }

        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.activite', 'a')
            ->addSelect('a');

        $fieldMap = [
            'date'         => 'i.dateActivite',
            'statut'       => 'i.statut',
            'participants' => 'i.nombreParticipants',
            'activite'     => 'a.nom',
            'client'       => 'i.idClient',
        ];
        $qb->orderBy($fieldMap[$sortBy], $direction);

        if ($search !== null && $search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(i.statut)', ':s'),
                    $qb->expr()->like('LOWER(a.nom)', ':s'),
                    $qb->expr()->like('CAST(i.idClient AS TEXT)', ':s'),
                )
            )->setParameter('s', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** Stats helpers */
    public function countByStatut(): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.statut AS statut, COUNT(i.idInscription) AS total')
            ->groupBy('i.statut')
            ->getQuery()
            ->getResult();
    }

    public function totalParticipants(): int
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.nombreParticipants)')
            ->getQuery()
            ->getSingleScalarResult();
        return (int) $result;
    }
}
