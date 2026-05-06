<?php

namespace App\Tests\Entity;

use App\Entity\Hebergement;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Hebergement.
 *
 * Ces tests vérifient que chaque getter/setter fonctionne correctement
 * et que la logique métier de l'entité est valide.
 */
class HebergementTest extends TestCase
{
    private Hebergement $hebergement;

    protected function setUp(): void
    {
        $this->hebergement = new Hebergement();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 1 : Setter et getter du nom
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetNom(): void
    {
        $this->hebergement->setNom('Villa Jasmine');

        $this->assertSame('Villa Jasmine', $this->hebergement->getNom());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 2 : Setter et getter du type
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetType(): void
    {
        $this->hebergement->setType('Hotel');

        $this->assertSame('Hotel', $this->hebergement->getType());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 3 : Setter et getter de l'adresse
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetAdresse(): void
    {
        $this->hebergement->setAdresse('12 rue de la Paix, Tunis');

        $this->assertSame('12 rue de la Paix, Tunis', $this->hebergement->getAdresse());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 4 : Setter et getter de la ville et du pays
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetVilleEtPays(): void
    {
        $this->hebergement->setVille('Tunis');
        $this->hebergement->setPays('Tunisia');

        $this->assertSame('Tunis', $this->hebergement->getVille());
        $this->assertSame('Tunisia', $this->hebergement->getPays());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 5 : Setter et getter de la capacité — valeur positive
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetCapacite(): void
    {
        $this->hebergement->setCapacite(4);

        $this->assertSame(4, $this->hebergement->getCapacite());
        $this->assertGreaterThan(0, $this->hebergement->getCapacite());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 6 : Setter et getter du tarif par nuit — valeur positive
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetTarifParNuit(): void
    {
        $this->hebergement->setTarifParNuit(150.00);

        $this->assertSame(150.00, $this->hebergement->getTarifParNuit());
        $this->assertGreaterThan(0, $this->hebergement->getTarifParNuit());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 7 : Setter et getter des équipements (nullable)
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetEquipements(): void
    {
        $this->hebergement->setEquipements('WiFi, Piscine, Climatisation');

        $this->assertSame('WiFi, Piscine, Climatisation', $this->hebergement->getEquipements());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 8 : Setter et getter de l'imageUrl (nullable)
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetImageUrl(): void
    {
        $this->hebergement->setImageUrl('/uploads/hotels/villa.jpg');

        $this->assertSame('/uploads/hotels/villa.jpg', $this->hebergement->getImageUrl());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 9 : ImageUrl nullable — doit accepter null
    // ──────────────────────────────────────────────────────────────────────────
    public function testImageUrlAcceptsNull(): void
    {
        $this->hebergement->setImageUrl(null);

        $this->assertNull($this->hebergement->getImageUrl());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 10 : __toString retourne le nom de l'hébergement
    // ──────────────────────────────────────────────────────────────────────────
    public function testToStringRetourneLeNom(): void
    {
        $this->hebergement->setNom('Riad El Amine');

        $this->assertSame('Riad El Amine', (string) $this->hebergement);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 11 : Etat initial de l'entité — tous les champs sont null par défaut
    // ──────────────────────────────────────────────────────────────────────────
    public function testEtatInitialNullParDefaut(): void
    {
        $h = new Hebergement();

        $this->assertNull($h->getNom());
        $this->assertNull($h->getTarifParNuit());
        $this->assertNull($h->getCapacite());
        $this->assertNull($h->getIdHebergement());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 12 : Fluent interface — les setters retournent l'instance (static)
    // ──────────────────────────────────────────────────────────────────────────
    public function testSettersRetournentStatic(): void
    {
        $result = $this->hebergement
            ->setNom('Test')
            ->setType('Appartement')
            ->setVille('Sfax')
            ->setPays('Tunisie')
            ->setCapacite(2)
            ->setTarifParNuit(80.0);

        $this->assertInstanceOf(Hebergement::class, $result);
    }
}
