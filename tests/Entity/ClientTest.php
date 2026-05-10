<?php

namespace App\Tests\Entity;

use App\Entity\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $client = new Client();
        $email = 'test@example.com';
        $nom = 'Doe';
        $prenom = 'John';
        $telephone = '123456789';
        $nationalite = 'Française';
        $password = 'Password123';

        $client->setEmail($email)
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setTelephone($telephone)
            ->setNationalite($nationalite)
            ->setPassword($password);

        $this->assertEquals($email, $client->getEmail());
        $this->assertEquals($email, $client->getUserIdentifier());
        $this->assertEquals($nom, $client->getNom());
        $this->assertEquals($prenom, $client->getPrenom());
        $this->assertEquals($telephone, $client->getTelephone());
        $this->assertEquals($nationalite, $client->getNationalite());
        $this->assertEquals($password, $client->getPassword());
    }

    public function testGetRoles(): void
    {
        $client = new Client();

        // Default role should be ROLE_USER
        $this->assertContains('ROLE_USER', $client->getRoles());

        // Test ADMINISTRATEUR role
        $client->setRole('ADMINISTRATEUR');
        $this->assertContains('ROLE_ADMIN', $client->getRoles());
        $this->assertNotContains('ROLE_USER', $client->getRoles());

        // Test other role
        $client->setRole('VOYAGEUR');
        $this->assertContains('ROLE_USER', $client->getRoles());
    }

    public function testDefaultValues(): void
    {
        $client = new Client();

        $this->assertEquals(0, $client->getPointsFidelite());
        $this->assertEquals('BRONZE', $client->getNiveauFidelite());
        $this->assertEquals('ACTIF', $client->getStatut());
        $this->assertEquals(0, $client->getFailedAttempts());
        $this->assertFalse($client->isEmailConfirmed());
        $this->assertInstanceOf(\DateTime::class, $client->getDateCreation());
    }
}
