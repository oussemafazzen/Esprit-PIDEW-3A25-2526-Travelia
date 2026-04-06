<?php

namespace App\Repository;

use App\Entity\Hebergement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Hebergement>
 */
class HebergementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hebergement::class);
    }

    /**
     * @return Hebergement[]
     */
    public function searchAndSort(?string $search, ?string $sortBy): array
    {
        $qb = $this->createQueryBuilder('h');

        if ($search) {
            $qb->andWhere('h.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($sortBy === 'ville') {
            $qb->orderBy('h.ville', 'ASC');
        } elseif ($sortBy === 'pays') {
            $qb->orderBy('h.pays', 'ASC');
        } else {
            $qb->orderBy('h.idHebergement', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }
}
