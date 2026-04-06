<?php

namespace App\Repository;

use App\Entity\Reservationhebergement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservationhebergement>
 */
class ReservationhebergementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservationhebergement::class);
    }

    /**
     * @return Reservationhebergement[]
     */
    public function searchAndSort(?string $search, ?string $sortBy): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.idHebergement', 'h');

        if ($search) {
            $qb->andWhere('h.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($sortBy === 'nom_hebergement') {
            $qb->orderBy('h.nom', 'ASC');
        } elseif ($sortBy === 'statut') {
            $qb->orderBy('r.statut', 'ASC');
        } else {
            $qb->orderBy('r.idReservationHebergement', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }
}
