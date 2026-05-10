<?php

namespace App\Tests\Entity;

use App\Entity\Client;
use App\Entity\PasswordResetToken;
use PHPUnit\Framework\TestCase;

class PasswordResetTokenTest extends TestCase
{
    public function testIsExpired(): void
    {
        $token = new PasswordResetToken();
        
        // Token expired 1 hour ago
        $expiryDate = new \DateTime('-1 hour');
        $token->setExpiryDate($expiryDate);
        $this->assertTrue($token->isExpired());

        // Token expires in 1 hour
        $expiryDate = new \DateTime('+1 hour');
        $token->setExpiryDate($expiryDate);
        $this->assertFalse($token->isExpired());
    }

    public function testUserRelationship(): void
    {
        $token = new PasswordResetToken();
        $client = new Client();
        
        $token->setUser($client);
        $this->assertSame($client, $token->getUser());
    }

    public function testDefaultValues(): void
    {
        $token = new PasswordResetToken();
        
        $this->assertFalse($token->isUsed());
        $this->assertInstanceOf(\DateTimeImmutable::class, $token->getCreatedAt());
    }
}
