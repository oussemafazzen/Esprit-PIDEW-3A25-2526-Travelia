<?php

namespace App\Security;

use App\Entity\Client;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Client) {
            return;
        }

        if (!$user->isEmailConfirmed()) {
            throw new CustomUserMessageAccountStatusException('Votre compte n\'est pas encore vérifié. Veuillez vérifier votre boîte mail.');
        }

        if ($user->getStatut() === 'BLOQUÉ') {
            throw new CustomUserMessageAccountStatusException('Votre compte a été suspendu. Veuillez contacter le support.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
