<?php

namespace App\Entity;

use App\Repository\BilletRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BilletRepository::class)]
#[ORM\Table(name: 'billet')]
class Billet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_billet')]
    private ?int $id = null;

    #[ORM\Column(name: 'type_transport', length: 255)]
    #[Assert\NotBlank(message: 'Le type de transport est obligatoire.')]
    #[Assert\Choice(
        choices: ['avion', 'train', 'bus', 'bateau'],
        message: 'Le type de transport sélectionné est invalide.'
    )]
    private ?string $typeTransport = null;

    #[ORM\Column(name: 'numero_billet', length: 255)]
    #[Assert\NotBlank(message: 'Le numéro de billet est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Le numéro de billet doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le numéro de billet ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $numeroBillet = null;

    #[ORM\Column(name: 'date_depart', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date de départ est obligatoire.')]
    #[Assert\Type(type: \DateTimeInterface::class, message: 'La date de départ est invalide.')]
    private ?\DateTimeInterface $dateDepart = null;

    #[ORM\Column(name: 'date_arrivee', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date d’arrivée est obligatoire.')]
    #[Assert\Type(type: \DateTimeInterface::class, message: 'La date d’arrivée est invalide.')]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'dateDepart',
        message: 'La date d’arrivée doit être supérieure ou égale à la date de départ.'
    )]
    private ?\DateTimeInterface $dateArrivee = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le prix est obligatoire.')]
    #[Assert\Positive(message: 'Le prix doit être positif.')]
    private ?float $prix = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['confirme', 'en_attente', 'annule'],
        message: 'Le statut du billet est invalide.'
    )]
    private ?string $statut = null;

    #[ORM\ManyToOne(inversedBy: 'billets')]
    #[ORM\JoinColumn(name: 'id_reservation', referencedColumnName: 'id_reservation', nullable: false)]
    #[Assert\NotNull(message: 'La réservation liée est obligatoire.')]
    private ?Reservation $reservation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeTransport(): ?string
    {
        return $this->typeTransport;
    }

    public function setTypeTransport(string $typeTransport): static
    {
        $this->typeTransport = $typeTransport;
        return $this;
    }

    public function getNumeroBillet(): ?string
    {
        return $this->numeroBillet;
    }

    public function setNumeroBillet(string $numeroBillet): static
    {
        $this->numeroBillet = $numeroBillet;
        return $this;
    }

    public function getDateDepart(): ?\DateTimeInterface
    {
        return $this->dateDepart;
    }

    public function setDateDepart(\DateTimeInterface $dateDepart): static
    {
        $this->dateDepart = $dateDepart;
        return $this;
    }

    public function getDateArrivee(): ?\DateTimeInterface
    {
        return $this->dateArrivee;
    }

    public function setDateArrivee(\DateTimeInterface $dateArrivee): static
    {
        $this->dateArrivee = $dateArrivee;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = $prix;
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

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;
        return $this;
    }

    public function __toString(): string
    {
        return $this->numeroBillet ?? 'Billet';
    }
}