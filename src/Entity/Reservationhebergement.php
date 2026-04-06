<?php

namespace App\Entity;

use App\Repository\ReservationhebergementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ReservationhebergementRepository::class)]
#[ORM\Table(name: 'reservationhebergement')]
class Reservationhebergement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reservation_hebergement')]
    private ?int $idReservationHebergement = null;

    #[ORM\Column(name: 'date_debut', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: "La date de début est obligatoire")]
    #[Assert\GreaterThanOrEqual("today", message: "La date de début ne peut pas être dans le passé")]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: "La date de fin est obligatoire")]
    #[Assert\GreaterThan(propertyPath: "dateDebut", message: "La date de fin doit être après la date de début")]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(name: 'nombre_personnes')]
    #[Assert\NotNull(message: "Le nombre de personnes est obligatoire")]
    #[Assert\Positive(message: "Le nombre de personnes doit être supérieur à 0")]
    private ?int $nombrePersonnes = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column(name: 'id_client')]
    private ?int $idClient = null;

    #[ORM\ManyToOne(targetEntity: Hebergement::class)]
    #[ORM\JoinColumn(name: 'id_hebergement', referencedColumnName: 'id_hebergement', nullable: false)]
    private ?Hebergement $idHebergement = null;

    public function getIdReservationHebergement(): ?int
    {
        return $this->idReservationHebergement;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getNombrePersonnes(): ?int
    {
        return $this->nombrePersonnes;
    }

    public function setNombrePersonnes(int $nombrePersonnes): static
    {
        $this->nombrePersonnes = $nombrePersonnes;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getIdClient(): ?int
    {
        return $this->idClient;
    }

    public function setIdClient(int $idClient): static
    {
        $this->idClient = $idClient;
        return $this;
    }

    public function getIdHebergement(): ?Hebergement
    {
        return $this->idHebergement;
    }

    public function setIdHebergement(?Hebergement $idHebergement): static
    {
        $this->idHebergement = $idHebergement;
        return $this;
    }
 
    #[Assert\Callback]
    public function validateCapacity(ExecutionContextInterface $context, $payload): void
    {
        if ($this->idHebergement !== null && $this->nombrePersonnes !== null) {
            if ($this->nombrePersonnes > $this->idHebergement->getCapacite()) {
                $context->buildViolation("Le nombre de personnes dépasse la capacité de l'hébergement ({$this->idHebergement->getCapacite()})")
                    ->atPath('nombrePersonnes')
                    ->addViolation();
            }
        }
    }
}
