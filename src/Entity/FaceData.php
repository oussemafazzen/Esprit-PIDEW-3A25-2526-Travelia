<?php

namespace App\Entity;

use App\Repository\FaceDataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FaceDataRepository::class)]
#[ORM\Table(name: 'face_data')]
class FaceData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Client::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?Client $user = null;

    #[ORM\Column(length: 255)]
    private ?string $face_token = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $face_encoding = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?Client
    {
        return $this->user;
    }

    public function setUser(Client $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getFaceToken(): ?string
    {
        return $this->face_token;
    }

    public function setFaceToken(string $face_token): static
    {
        $this->face_token = $face_token;
        return $this;
    }

    public function getFaceEncoding(): ?string
    {
        return $this->face_encoding;
    }

    public function setFaceEncoding(?string $face_encoding): static
    {
        $this->face_encoding = $face_encoding;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }
}
