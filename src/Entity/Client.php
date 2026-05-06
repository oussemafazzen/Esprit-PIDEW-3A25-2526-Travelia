<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class Client implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: "Le nom ne doit pas être vide")]
    private ?string $nom = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: "Le prénom ne doit pas être vide")]
    private ?string $prenom = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank(message: "L'email ne doit pas être vide")]
    #[Assert\Email(message: "L'email '{{ value }}' n'est pas un email valide.")]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le mot de passe ne doit pas être vide")]
    #[Assert\Length(min: 8, minMessage: "Le mot de passe doit faire au moins {{ limit }} caractères")]
    #[Assert\Regex(
        pattern: "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/",
        message: "Le mot de passe doit comporter au moins une lettre majuscule, une lettre minuscule et un chiffre"
    )]
    private ?string $password = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le téléphone ne doit pas être vide")]
    private ?string $telephone = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: "Veuillez sélectionner votre nationalité")]
    private ?string $nationalite = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "Veuillez choisir votre date de naissance")]
    private ?\DateTimeInterface $date_naissance = null;

    #[ORM\Column(length: 50)]
    private string $role = 'USER';

    #[ORM\Column(length: 50)]
    private string $statut = 'ACTIF';

    #[ORM\Column]
    private int $points_fidelite = 0;

    #[ORM\Column(length: 50)]
    private string $niveau_fidelite = 'BRONZE';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date_creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $derniere_connexion = null;

    #[ORM\Column]
    private int $failed_attempts = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $google_id = null;

    #[ORM\Column]
    private bool $email_confirmed = false;

    public function __construct()
    {
        $this->date_creation = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = [];
        if ($this->role === 'ADMINISTRATEUR') {
            $roles[] = 'ROLE_ADMIN';
        } else {
            $roles[] = 'ROLE_USER';
        }
        return array_unique($roles);
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // $this->plainPassword = null;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getNationalite(): ?string
    {
        return $this->nationalite;
    }

    public function setNationalite(string $nationalite): static
    {
        $this->nationalite = $nationalite;
        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->date_naissance;
    }

    public function setDateNaissance(\DateTimeInterface $date_naissance): static
    {
        $this->date_naissance = $date_naissance;
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

    public function getPointsFidelite(): ?int
    {
        return $this->points_fidelite;
    }

    public function setPointsFidelite(int $points_fidelite): static
    {
        $this->points_fidelite = $points_fidelite;
        return $this;
    }

    public function getNiveauFidelite(): ?string
    {
        return $this->niveau_fidelite;
    }

    public function setNiveauFidelite(string $niveau_fidelite): static
    {
        $this->niveau_fidelite = $niveau_fidelite;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(\DateTimeInterface $date_creation): static
    {
        $this->date_creation = $date_creation;
        return $this;
    }

    public function getDerniereConnexion(): ?\DateTimeInterface
    {
        return $this->derniere_connexion;
    }

    public function setDerniereConnexion(?\DateTimeInterface $derniere_connexion): static
    {
        $this->derniere_connexion = $derniere_connexion;
        return $this;
    }

    public function getFailedAttempts(): ?int
    {
        return $this->failed_attempts;
    }

    public function setFailedAttempts(int $failed_attempts): static
    {
        $this->failed_attempts = $failed_attempts;
        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->google_id;
    }

    public function setGoogleId(?string $google_id): static
    {
        $this->google_id = $google_id;
        return $this;
    }

    public function isEmailConfirmed(): ?bool
    {
        return $this->email_confirmed;
    }

    public function setEmailConfirmed(bool $email_confirmed): static
    {
        $this->email_confirmed = $email_confirmed;
        return $this;
    }
}
