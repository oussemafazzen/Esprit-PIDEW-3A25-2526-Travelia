<?php

namespace App\Tests\Security;

use App\Entity\Client;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    private UserChecker $userChecker;

    protected function setUp(): void
    {
        $this->userChecker = new UserChecker();
    }

    public function testCheckPreAuthWithVerifiedAndActiveUser(): void
    {
        $user = new Client();
        $user->setEmailConfirmed(true);
        $user->setStatut('ACTIF');

        $this->userChecker->checkPreAuth($user);
        
        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testCheckPreAuthWithUnverifiedUser(): void
    {
        $user = new Client();
        $user->setEmailConfirmed(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte n\'est pas encore vérifié. Veuillez vérifier votre boîte mail.');

        $this->userChecker->checkPreAuth($user);
    }

    public function testCheckPreAuthWithBlockedUser(): void
    {
        $user = new Client();
        $user->setEmailConfirmed(true);
        $user->setStatut('BLOQUÉ');

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte a été suspendu. Veuillez contacter le support.');

        $this->userChecker->checkPreAuth($user);
    }

    public function testCheckPreAuthWithNonClientUser(): void
    {
        $user = $this->createMock(UserInterface::class);

        $this->userChecker->checkPreAuth($user);
        
        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }
}
