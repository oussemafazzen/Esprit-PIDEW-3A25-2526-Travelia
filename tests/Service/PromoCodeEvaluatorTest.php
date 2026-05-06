<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PromoCodeEvaluator;
use PHPUnit\Framework\TestCase;

final class PromoCodeEvaluatorTest extends TestCase
{
    private PromoCodeEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new PromoCodeEvaluator();
    }

    public function testSpring10AppliesTenPercentDiscount(): void
    {
        $result = $this->evaluator->evaluate('SPRING10', 200.0, 'economy');

        self::assertSame('applied', $result['status']);
        self::assertSame(20.0, $result['discount']);
        self::assertSame(180.0, $result['final_price']);
    }

    public function testWelcome50AppliesFixedDiscount(): void
    {
        $result = $this->evaluator->evaluate('WELCOME50', 120.0, 'economy');

        self::assertSame('applied', $result['status']);
        self::assertSame(50.0, $result['discount']);
        self::assertSame(70.0, $result['final_price']);
    }

    public function testBusiness15WorksOnlyForBusiness(): void
    {
        $applied = $this->evaluator->evaluate('BUSINESS15', 300.0, 'business');
        $rejected = $this->evaluator->evaluate('BUSINESS15', 300.0, 'economy');

        self::assertSame('applied', $applied['status']);
        self::assertSame(45.0, $applied['discount']);
        self::assertSame(255.0, $applied['final_price']);

        self::assertSame('condition_failed', $rejected['status']);
        self::assertSame(0.0, $rejected['discount']);
        self::assertSame(300.0, $rejected['final_price']);
    }

    public function testInvalidCodeReturnsErrorAndKeepsPrice(): void
    {
        $result = $this->evaluator->evaluate('UNKNOWN', 150.0, 'economy');

        self::assertSame('invalid', $result['status']);
        self::assertSame('UNKNOWN', $result['applied_code']);
        self::assertSame(0.0, $result['discount']);
        self::assertSame(150.0, $result['final_price']);
        self::assertNotNull($result['message']);
    }
}
