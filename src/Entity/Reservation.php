<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reservation', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_reservation', type: 'date', nullable: true)]
    #[Assert\NotBlank(message: 'La date de réservation est obligatoire.')]
    #[Assert\Type(type: \DateTimeInterface::class, message: 'La date de réservation est invalide.')]
    #[Assert\GreaterThanOrEqual(
        'today',
        message: 'La date de réservation ne peut pas être dans le passé.'
    )]
    private ?\DateTimeInterface $dateReservation = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['confirmé', 'confirme', 'en_attente', 'annulé', 'annule'],
        message: 'Le statut sélectionné est invalide.'
    )]
    private ?string $statut = null;

    #[ORM\Column(name: 'modalites_paiement', type: 'string', length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'Les modalités de paiement sont obligatoires.')]
    #[Assert\Choice(
        choices: ['carte', 'virement', 'especes'],
        message: 'Le mode de paiement sélectionné est invalide.'
    )]
    private ?string $modalitesPaiement = null;

    #[ORM\Column(name: 'id_client', type: 'integer', nullable: true)]
    #[Assert\NotNull(message: 'Le client est obligatoire.')]
    #[Assert\Positive(message: 'Le client sélectionné est invalide.')]
    private ?int $clientId = null;

    #[ORM\Column(name: 'paysdestination', type: 'string', length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'La destination est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'La destination doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La destination ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $paysDestination = null;

    #[ORM\OneToMany(mappedBy: 'reservation', targetEntity: Billet::class, cascade: ['remove'], orphanRemoval: true)]
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

    public function setDateReservation(?\DateTimeInterface $dateReservation): static
    {
        $this->dateReservation = $dateReservation;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getModalitesPaiement(): ?string
    {
        return $this->modalitesPaiement;
    }

    public function setModalitesPaiement(?string $modalitesPaiement): static
    {
        $this->modalitesPaiement = $modalitesPaiement;
        return $this;
    }

    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    public function setClientId(?int $clientId): static
    {
        $this->clientId = $clientId;
        return $this;
    }

    public function getPaysDestination(): ?string
    {
        return $this->paysDestination;
    }

    public function setPaysDestination(?string $paysDestination): static
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
}
