<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Client) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    
    /**
     * Search and sort clients
     */
    public function findBySearchAndSort(?string $search = null, ?string $sortBy = 'nom', ?string $direction = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('c')
           ->where('c.role != :adminRole')
           ->setParameter('adminRole', 'ADMINISTRATEUR');

        if ($search) {
            $qb->andWhere('c.nom LIKE :search OR c.prenom LIKE :search OR c.email LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $validSortFields = ['nom', 'prenom', 'email', 'date_creation', 'role'];
        if (in_array($sortBy, $validSortFields)) {
            $qb->orderBy('c.' . $sortBy, $direction);
        } else {
            $qb->orderBy('c.nom', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get nationality statistics
     */
    public function getNationalityStats(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.nationalite, COUNT(c.id) as count')
            ->groupBy('c.nationalite')
            ->orderBy('count', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get age group statistics
     */
    public function getAgeGroupStats(): array
    {
        $allClients = $this->createQueryBuilder('c')
            ->select('c.date_naissance')
            ->getQuery()
            ->getResult();

        $stats = [
            '18-25' => 0,
            '26-40' => 0,
            '41-60' => 0,
            '60+' => 0,
        ];

        $now = new \DateTime();

        foreach ($allClients as $client) {
            $birthDate = $client['date_naissance'];
            if (!$birthDate) continue;

            $age = $now->diff($birthDate)->y;

            if ($age >= 18 && $age <= 25) {
                $stats['18-25']++;
            } elseif ($age >= 26 && $age <= 40) {
                $stats['26-40']++;
            } elseif ($age >= 41 && $age <= 60) {
                $stats['41-60']++;
            } elseif ($age > 60) {
                $stats['60+']++;
            }
        }

        return $stats;
    }
}
