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

    /**
     * Reservations grouped by season (based on date_debut month).
     * Returns ['Hiver'=>n, 'Printemps'=>n, 'Été'=>n, 'Automne'=>n].
     */
    public function countBySeason(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('MONTH(r.dateDebut) AS mois, COUNT(r.idReservationHebergement) AS total')
            ->groupBy('mois')
            ->getQuery()
            ->getResult();

        $seasons = ['Hiver' => 0, 'Printemps' => 0, 'Été' => 0, 'Automne' => 0];

        foreach ($rows as $row) {
            $month = (int) $row['mois'];
            $season = match (true) {
                in_array($month, [12, 1, 2])  => 'Hiver',
                in_array($month, [3, 4, 5])   => 'Printemps',
                in_array($month, [6, 7, 8])   => 'Été',
                in_array($month, [9, 10, 11]) => 'Automne',
                default                        => 'Autre',
            };
            $seasons[$season] = ($seasons[$season] ?? 0) + (int) $row['total'];
        }

        return $seasons;
    }

    /**
     * Reservations grouped by hotel country.
     * Returns array of ['pays'=>'France', 'total'=>12].
     *
     * @return array<int, array{pays: string, total: int}>
     */
    public function countByCountry(): array
    {
        return $this->createQueryBuilder('r')
            ->select('h.pays AS pays, COUNT(r.idReservationHebergement) AS total')
            ->leftJoin('r.idHebergement', 'h')
            ->groupBy('h.pays')
            ->orderBy('total', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }
}
