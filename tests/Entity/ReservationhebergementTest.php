<?php

namespace App\Tests\Entity;

use App\Entity\Hebergement;
use App\Entity\Reservationhebergement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Tests unitaires pour l'entité Reservationhebergement.
 *
 * Ces tests vérifient les setters/getters, la logique de validation
 * de capacité et les contraintes de dates.
 */
class ReservationhebergementTest extends TestCase
{
    private Reservationhebergement $reservation;

    protected function setUp(): void
    {
        $this->reservation = new Reservationhebergement();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 1 : Setter et getter de la date de début
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetDateDebut(): void
    {
        $date = new \DateTime('2026-06-01');
        $this->reservation->setDateDebut($date);

        $this->assertSame($date, $this->reservation->getDateDebut());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 2 : Setter et getter de la date de fin
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetDateFin(): void
    {
        $date = new \DateTime('2026-06-10');
        $this->reservation->setDateFin($date);

        $this->assertSame($date, $this->reservation->getDateFin());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 3 : Setter et getter du nombre de personnes
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetNombrePersonnes(): void
    {
        $this->reservation->setNombrePersonnes(3);

        $this->assertSame(3, $this->reservation->getNombrePersonnes());
        $this->assertGreaterThan(0, $this->reservation->getNombrePersonnes());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 4 : Setter et getter du statut
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetStatut(): void
    {
        $this->reservation->setStatut('en attente');

        $this->assertSame('en attente', $this->reservation->getStatut());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 5 : Setter et getter de l'id client
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetIdClient(): void
    {
        $this->reservation->setIdClient(42);

        $this->assertSame(42, $this->reservation->getIdClient());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 6 : Setter et getter de l'hébergement associé
    // ──────────────────────────────────────────────────────────────────────────
    public function testSetAndGetIdHebergement(): void
    {
        $hebergement = new Hebergement();
        $hebergement->setNom('Villa Jasmine');

        $this->reservation->setIdHebergement($hebergement);

        $this->assertSame($hebergement, $this->reservation->getIdHebergement());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 7 : validateCapacity — DOIT déclencher une violation
    //          si le nombre de personnes dépasse la capacité
    // ──────────────────────────────────────────────────────────────────────────
    public function testValidateCapacityDepasseeDeclencheViolation(): void
    {
        // Préparer un hébergement avec capacité = 4
        $hebergement = new Hebergement();
        $hebergement->setCapacite(4);

        // Réservation avec 5 personnes (dépasse la capacité)
        $this->reservation->setIdHebergement($hebergement);
        $this->reservation->setNombrePersonnes(5);

        // Mock du builder de violation
        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())
            ->method('atPath')
            ->with('nombrePersonnes')
            ->willReturnSelf();
        $violationBuilder->expects($this->once())
            ->method('addViolation');

        // Mock du contexte de validation
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->willReturn($violationBuilder);

        $this->reservation->validateCapacity($context, null);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 8 : validateCapacity — NE DOIT PAS déclencher de violation
    //          si le nombre de personnes est dans la limite
    // ──────────────────────────────────────────────────────────────────────────
    public function testValidateCapacityDansLimiteAucuneViolation(): void
    {
        // Préparer un hébergement avec capacité = 4
        $hebergement = new Hebergement();
        $hebergement->setCapacite(4);

        // Réservation avec 3 personnes (dans la limite)
        $this->reservation->setIdHebergement($hebergement);
        $this->reservation->setNombrePersonnes(3);

        // Le contexte NE DOIT PAS appeler buildViolation
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())
            ->method('buildViolation');

        $this->reservation->validateCapacity($context, null);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 9 : validateCapacity — exactement à la capacité max → pas de violation
    // ──────────────────────────────────────────────────────────────────────────
    public function testValidateCapacityExactementEgalAucuneViolation(): void
    {
        $hebergement = new Hebergement();
        $hebergement->setCapacite(4);

        $this->reservation->setIdHebergement($hebergement);
        $this->reservation->setNombrePersonnes(4); // Exactement la capacité max

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())
            ->method('buildViolation');

        $this->reservation->validateCapacity($context, null);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 10 : validateCapacity — sans hébergement, aucune violation
    // ──────────────────────────────────────────────────────────────────────────
    public function testValidateCapacitySansHebergementAucuneViolation(): void
    {
        $this->reservation->setNombrePersonnes(10);
        // Pas d'hébergement associé → pas de validation possible

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())
            ->method('buildViolation');

        $this->reservation->validateCapacity($context, null);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 11 : La date de fin doit être après la date de début (test logique)
    // ──────────────────────────────────────────────────────────────────────────
    public function testDateFinEstApresDateDebut(): void
    {
        $debut = new \DateTime('2026-06-01');
        $fin   = new \DateTime('2026-06-05');

        $this->reservation->setDateDebut($debut);
        $this->reservation->setDateFin($fin);

        $this->assertTrue(
            $this->reservation->getDateFin() > $this->reservation->getDateDebut(),
            'La date de fin doit être après la date de début'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Test 12 : Etat initial — tous les champs sont null par défaut
    // ──────────────────────────────────────────────────────────────────────────
    public function testEtatInitialNullParDefaut(): void
    {
        $r = new Reservationhebergement();

        $this->assertNull($r->getIdReservationHebergement());
        $this->assertNull($r->getDateDebut());
        $this->assertNull($r->getDateFin());
        $this->assertNull($r->getNombrePersonnes());
        $this->assertNull($r->getStatut());
        $this->assertNull($r->getIdClient());
        $this->assertNull($r->getIdHebergement());
    }
}
