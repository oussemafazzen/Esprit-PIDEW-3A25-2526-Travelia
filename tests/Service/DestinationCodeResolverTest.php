<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DestinationCodeResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AllowMockObjectsWithoutExpectations]
final class DestinationCodeResolverTest extends TestCase
{
    public function testDirectIataCodeIsReturnedWithoutApiCall(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::never())->method('request');

        $resolver = new DestinationCodeResolver($client);

        self::assertSame('CDG', $resolver->resolve('cdg'));
    }

    public function testNormalizationWithAccentsFallsBackToExpectedAirport(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('offline'));

        $resolver = new DestinationCodeResolver($client);

        self::assertSame('COO', $resolver->resolve('  Bénin  '));
        self::assertSame(['BAH'], $resolver->resolveCandidates('Bahreïn'));
    }

    public function testFallbackWorksWhenApiIsUnavailable(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::atLeastOnce())
            ->method('request')
            ->willThrowException(new \RuntimeException('API indisponible'));

        $resolver = new DestinationCodeResolver($client);

        self::assertSame('CDG', $resolver->resolve('France'));
    }
}
