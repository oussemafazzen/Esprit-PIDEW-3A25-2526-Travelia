<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class FlightSearchData
{
    #[Assert\NotBlank(message: 'La destination est obligatoire.')]
    #[Assert\Length(
        min: 2,
        minMessage: 'La destination doit contenir au moins {{ limit }} caractères.',
        max: 100,
        maxMessage: 'La destination ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $destination = null;

    #[Assert\NotNull(message: 'La date de départ est obligatoire.')]
    #[Assert\Type(type: \DateTimeInterface::class, message: 'La date de départ est invalide.')]
    private ?\DateTimeImmutable $dateDepart = null;

    #[Assert\NotNull(message: 'Le nombre de passagers est obligatoire.')]
    #[Assert\Type(type: 'integer', message: 'Le nombre de passagers doit être un entier.')]
    #[Assert\GreaterThanOrEqual(
        value: 1,
        message: 'Le nombre de passagers doit être supérieur ou égal à 1.'
    )]
    #[Assert\LessThanOrEqual(
        value: 10,
        message: 'Le nombre de passagers ne doit pas dépasser 10.'
    )]
    private ?int $passagers = 1;

    #[Assert\Callback]
    public function validateDateDepart(ExecutionContextInterface $context): void
    {
        if (!$this->dateDepart) {
            return;
        }

        $today = new \DateTimeImmutable('today');

        if ($this->dateDepart < $today) {
            $context->buildViolation('La date de départ ne peut pas être dans le passé.')
                ->atPath('dateDepart')
                ->addViolation();
        }
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(?string $destination): self
    {
        $this->destination = $destination;
        return $this;
    }

    public function getDateDepart(): ?\DateTimeImmutable
    {
        return $this->dateDepart;
    }

    public function setDateDepart(?\DateTimeImmutable $dateDepart): self
    {
        $this->dateDepart = $dateDepart;
        return $this;
    }

    public function getPassagers(): ?int
    {
        return $this->passagers;
    }

    public function setPassagers(?int $passagers): self
    {
        $this->passagers = $passagers;
        return $this;
    }
}