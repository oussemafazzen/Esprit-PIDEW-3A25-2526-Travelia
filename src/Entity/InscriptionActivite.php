<?php

namespace App\Entity;

use App\Repository\InscriptionActiviteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InscriptionActiviteRepository::class)]
#[ORM\Table(name: 'inscriptionactivite')]
class InscriptionActivite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_inscription')]
    private ?int $idInscription = null;

    #[ORM\Column(name: 'date_activite', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateActivite = null;

    #[ORM\Column(name: 'nombre_participants', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le nombre de participants doit être positif')]
    private ?int $nombreParticipants = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(name: 'id_client', nullable: true)]
    private ?int $idClient = null;

    #[ORM\ManyToOne(targetEntity: Activite::class)]
    #[ORM\JoinColumn(name: 'id_activite', referencedColumnName: 'id_activite', nullable: true)]
    private ?Activite $activite = null;

    public function getIdInscription(): ?int
    {
        return $this->idInscription;
    }

    public function getDateActivite(): ?\DateTimeInterface
    {
        return $this->dateActivite;
    }

    public function setDateActivite(?\DateTimeInterface $dateActivite): static
    {
        $this->dateActivite = $dateActivite;
        return $this;
    }

    public function getNombreParticipants(): ?int
    {
        return $this->nombreParticipants;
    }

    public function setNombreParticipants(?int $nombreParticipants): static
    {
        $this->nombreParticipants = $nombreParticipants;
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

    public function getIdClient(): ?int
    {
        return $this->idClient;
    }

    public function setIdClient(?int $idClient): static
    {
        $this->idClient = $idClient;
        return $this;
    }

    public function getActivite(): ?Activite
    {
        return $this->activite;
    }

    public function setActivite(?Activite $activite): static
    {
        $this->activite = $activite;
        return $this;
    }
}
