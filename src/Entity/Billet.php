<?php

namespace App\Entity;

use App\Repository\BilletRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BilletRepository::class)]
#[ORM\Table(name: 'billet')]
class Billet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_billet', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'type_transport', type: 'string', length: 50, nullable: true)]
    private ?string $typeTransport = null;

    #[ORM\Column(name: 'numero_billet', type: 'string', length: 100, nullable: true)]
    private ?string $numeroBillet = null;

    #[ORM\Column(name: 'date_depart', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateDepart = null;

    #[ORM\Column(name: 'date_arrivee', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateArrivee = null;

    #[ORM\Column(name: 'prix', type: 'float', nullable: true)]
    private ?float $prix = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 50, nullable: true)]
    private ?string $statut = null;

    #[ORM\ManyToOne(inversedBy: 'billets')]
    #[ORM\JoinColumn(name: 'id_reservation', referencedColumnName: 'id_reservation', nullable: true)]
    private ?Reservation $reservation = null;

    #[Assert\NotBlank(message: 'Le mode de paiement est obligatoire.', groups: ['payment_default'])]
    #[Assert\Choice(choices: ['carte', 'virement', 'especes'], message: 'Le mode de paiement sélectionné est invalide.', groups: ['payment_default'])]
    private ?string $modePaiement = null;

    #[Assert\NotBlank(message: 'Le nom du titulaire est obligatoire.', groups: ['payment_carte'])]
    #[Assert\Length(
        min: 2,
        max: 80,
        minMessage: 'Le nom du titulaire doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom du titulaire ne doit pas dépasser {{ limit }} caractères.',
        groups: ['payment_carte']
    )]
    #[Assert\Regex(
        pattern: "/^[\p{L}][\p{L}\s'\-]{1,79}$/u",
        message: 'Le nom du titulaire ne peut contenir que des lettres, espaces, apostrophes et tirets.',
        groups: ['payment_carte']
    )]
    private ?string $cardHolderName = null;

    #[Assert\NotBlank(message: 'Le numéro de carte est obligatoire.', groups: ['payment_carte'])]
    #[Assert\Regex(
        pattern: '/^\d+$/',
        message: 'Le numéro de carte doit contenir uniquement des chiffres.',
        groups: ['payment_carte']
    )]
    #[Assert\Length(
        min: 13,
        max: 19,
        minMessage: 'Le numéro de carte doit contenir au moins {{ limit }} chiffres.',
        maxMessage: 'Le numéro de carte ne doit pas dépasser {{ limit }} chiffres.',
        groups: ['payment_carte']
    )]
    private ?string $cardNumber = null;

    #[Assert\NotBlank(message: 'La date d\'expiration est obligatoire.', groups: ['payment_carte'])]
    #[Assert\Regex(
        pattern: '/^(0[1-9]|1[0-2])\/\d{2}$/',
        message: 'La date d\'expiration doit respecter le format MM/AA.',
        groups: ['payment_carte']
    )]
    private ?string $expiryDate = null;

    #[Assert\NotBlank(message: 'Le CVV est obligatoire.', groups: ['payment_carte'])]
    #[Assert\Regex(
        pattern: '/^\d{3,4}$/',
        message: 'Le CVV doit contenir 3 ou 4 chiffres.',
        groups: ['payment_carte']
    )]
    private ?string $cvv = null;

    #[Assert\NotBlank(message: 'Le nom de la banque est obligatoire.', groups: ['payment_virement'])]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom de la banque doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom de la banque ne doit pas dépasser {{ limit }} caractères.',
        groups: ['payment_virement']
    )]
    #[Assert\Regex(
        pattern: "/^[\p{L}][\p{L}\s'\-\.&]{1,99}$/u",
        message: 'Le nom de la banque contient des caractères invalides.',
        groups: ['payment_virement']
    )]
    private ?string $bankName = null;

    #[Assert\NotBlank(message: 'L\'IBAN ou le RIB est obligatoire.', groups: ['payment_virement'])]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9]+$/',
        message: 'L\'IBAN ou le RIB doit contenir uniquement des lettres et des chiffres.',
        groups: ['payment_virement']
    )]
    #[Assert\Length(
        min: 10,
        max: 34,
        minMessage: 'L\'IBAN ou le RIB doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'IBAN ou le RIB ne doit pas dépasser {{ limit }} caractères.',
        groups: ['payment_virement']
    )]
    private ?string $ibanOrRib = null;

    #[Assert\NotBlank(message: 'Le nom du titulaire du compte est obligatoire.', groups: ['payment_virement'])]
    #[Assert\Length(
        min: 2,
        max: 80,
        minMessage: 'Le nom du titulaire du compte doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom du titulaire du compte ne doit pas dépasser {{ limit }} caractères.',
        groups: ['payment_virement']
    )]
    #[Assert\Regex(
        pattern: "/^[\p{L}][\p{L}\s'\-]{1,79}$/u",
        message: 'Le nom du titulaire du compte ne peut contenir que des lettres, espaces, apostrophes et tirets.',
        groups: ['payment_virement']
    )]
    private ?string $accountHolderName = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeTransport(): ?string
    {
        return $this->typeTransport;
    }

    public function setTypeTransport(?string $typeTransport): static
    {
        $this->typeTransport = $typeTransport;
        return $this;
    }

    public function getNumeroBillet(): ?string
    {
        return $this->numeroBillet;
    }

    public function setNumeroBillet(?string $numeroBillet): static
    {
        $this->numeroBillet = $numeroBillet;
        return $this;
    }

    public function getDateDepart(): ?\DateTimeInterface
    {
        return $this->dateDepart;
    }

    public function setDateDepart(?\DateTimeInterface $dateDepart): static
    {
        $this->dateDepart = $dateDepart;
        return $this;
    }

    public function getDateArrivee(): ?\DateTimeInterface
    {
        return $this->dateArrivee;
    }

    public function setDateArrivee(?\DateTimeInterface $dateArrivee): static
    {
        $this->dateArrivee = $dateArrivee;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(?float $prix): static
    {
        $this->prix = $prix;
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

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;
        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    public function getCardHolderName(): ?string
    {
        return $this->cardHolderName;
    }

    public function setCardHolderName(?string $cardHolderName): static
    {
        $this->cardHolderName = $cardHolderName;
        return $this;
    }

    public function getCardNumber(): ?string
    {
        return $this->cardNumber;
    }

    public function setCardNumber(?string $cardNumber): static
    {
        $this->cardNumber = $cardNumber;
        return $this;
    }

    public function getExpiryDate(): ?string
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?string $expiryDate): static
    {
        $this->expiryDate = $expiryDate;
        return $this;
    }

    public function getCvv(): ?string
    {
        return $this->cvv;
    }

    public function setCvv(?string $cvv): static
    {
        $this->cvv = $cvv;
        return $this;
    }

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function setBankName(?string $bankName): static
    {
        $this->bankName = $bankName;
        return $this;
    }

    public function getIbanOrRib(): ?string
    {
        return $this->ibanOrRib;
    }

    public function setIbanOrRib(?string $ibanOrRib): static
    {
        $this->ibanOrRib = $ibanOrRib;
        return $this;
    }

    public function getAccountHolderName(): ?string
    {
        return $this->accountHolderName;
    }

    public function setAccountHolderName(?string $accountHolderName): static
    {
        $this->accountHolderName = $accountHolderName;
        return $this;
    }
}
