<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reservation')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_reservation', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date de réservation est obligatoire.')]
    #[Assert\Type(type: \DateTimeInterface::class, message: 'La date de réservation est invalide.')]
    private ?\DateTimeInterface $dateReservation = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['confirmé', 'en_attente', 'annulé'],
        message: 'Le statut sélectionné est invalide.'
    )]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le statut doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le statut ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $statut = null;

    #[ORM\Column(name: 'modalites_paiement', length: 255)]
    #[Assert\NotBlank(message: 'Les modalités de paiement sont obligatoires.')]
    #[Assert\Choice(
        choices: ['carte', 'especes', 'virement'],
        message: 'Le mode de paiement sélectionné est invalide.'
    )]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Les modalités de paiement doivent contenir au moins {{ limit }} caractères.',
        maxMessage: 'Les modalités de paiement ne doivent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $modalitesPaiement = null;

    #[ORM\Column(name: 'id_client')]
    #[Assert\NotNull(message: 'Le client ID est obligatoire.')]
    #[Assert\Positive(message: 'Le client ID doit être un nombre positif.')]
    private ?int $clientId = null;

    #[ORM\Column(name: 'paysdestination', length: 255)]
    #[Assert\NotBlank(message: 'Le pays de destination est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le pays de destination doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le pays de destination ne doit pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[\p{L}\s\-\'()]+$/u',
        message: 'Le pays de destination ne doit contenir que des lettres, espaces, tirets ou apostrophes.'
    )]
    private ?string $paysDestination = null;

    /**
     * @var Collection<int, Billet>
     */
    #[ORM\OneToMany(mappedBy: 'reservation', targetEntity: Billet::class)]
    private Collection $billets;

    public function __construct()
    {
        $this->billets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateReservation(): ?\DateTimeInterface
    {
        return $this->dateReservation;
    }

    public function setDateReservation(\DateTimeInterface $dateReservation): static
    {
        $this->dateReservation = $dateReservation;
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

    public function getModalitesPaiement(): ?string
    {
        return $this->modalitesPaiement;
    }

    public function setModalitesPaiement(string $modalitesPaiement): static
    {
        $this->modalitesPaiement = $modalitesPaiement;
        return $this;
    }

    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    public function setClientId(int $clientId): static
    {
        $this->clientId = $clientId;
        return $this;
    }

    public function getPaysDestination(): ?string
    {
        return $this->paysDestination;
    }

    public function setPaysDestination(string $paysDestination): static
    {
        $this->paysDestination = $paysDestination;
        return $this;
    }

    /**
     * @return Collection<int, Billet>
     */
    public function getBillets(): Collection
    {
        return $this->billets;
    }

    public function addBillet(Billet $billet): static
    {
        if (!$this->billets->contains($billet)) {
            $this->billets->add($billet);
            $billet->setReservation($this);
        }

        return $this;
    }

    public function removeBillet(Billet $billet): static
    {
        if ($this->billets->removeElement($billet)) {
            if ($billet->getReservation() === $this) {
                $billet->setReservation(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return 'Reservation #' . ($this->id ?? '');
    }
}