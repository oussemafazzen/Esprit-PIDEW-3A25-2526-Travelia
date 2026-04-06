<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'paiement')]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_paiement')]
    private ?int $idPaiement = null;

    #[ORM\Column(name: 'date_paiement', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column]
    private ?float $montant = null;

    #[ORM\Column(name: 'methode_paiement', length: 255)]
    private ?string $methodePaiement = null;

    #[ORM\Column(name: 'id_reservation')]
    private ?int $idReservation = null;

    public function getIdPaiement(): ?int
    {
        return $this->idPaiement;
    }

    public function getDatePaiement(): ?\DateTimeInterface
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(\DateTimeInterface $datePaiement): self
    {
        $this->datePaiement = $datePaiement;
        return $this;
    }

    public function getMontant(): ?float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): self
    {
        $this->montant = $montant;
        return $this;
    }

    public function getMethodePaiement(): ?string
    {
        return $this->methodePaiement;
    }

    public function setMethodePaiement(string $methodePaiement): self
    {
        $this->methodePaiement = $methodePaiement;
        return $this;
    }

    public function getIdReservation(): ?int
    {
        return $this->idReservation;
    }

    public function setIdReservation(int $idReservation): self
    {
        $this->idReservation = $idReservation;
        return $this;
    }
}
