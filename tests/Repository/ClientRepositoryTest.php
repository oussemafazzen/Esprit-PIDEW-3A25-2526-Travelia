<?php

namespace App\Tests\Repository;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ClientRepositoryTest extends KernelTestCase
{
    private ?ClientRepository $repository = null;
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->repository = $this->entityManager->getRepository(Client::class);
    }

    public function testFindBySearchAndSort(): void
    {
        // This is an integration test. 
        // In a real scenario, we would use a test database and fixtures.
        // For this workshop, we verify the method exists and can be called.
        
        $results = $this->repository->findBySearchAndSort('test', 'nom', 'ASC');
        $this->assertIsArray($results);
    }

    public function testGetNationalityStats(): void
    {
        $stats = $this->repository->getNationalityStats();
        $this->assertIsArray($stats);
        
        if (count($stats) > 0) {
            $this->assertArrayHasKey('nationalite', $stats[0]);
            $this->assertArrayHasKey('count', $stats[0]);
        }
    }

    public function testGetAgeGroupStats(): void
    {
        $stats = $this->repository->getAgeGroupStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('18-25', $stats);
        $this->assertArrayHasKey('26-40', $stats);
        $this->assertArrayHasKey('41-60', $stats);
        $this->assertArrayHasKey('60+', $stats);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
