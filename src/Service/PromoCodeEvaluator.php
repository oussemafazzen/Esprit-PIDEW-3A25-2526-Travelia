<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Predefined promo codes for the flight payment demo (no persistence).
 *
 * @phpstan-type ClientRule array{code: string, type: 'percent'|'fixed_tnd', percent?: float, amountTnd?: float, requiresTravelClass: ?string}
 */
final class PromoCodeEvaluator
{
    /**
     * JSON-safe rules for client-side preview; must stay in sync with {@see evaluate()}.
     *
     * @return list<ClientRule>
     */
    public function getClientRulesConfig(): array
    {
        return [
            ['code' => 'SPRING10', 'type' => 'percent', 'percent' => 10.0, 'requiresTravelClass' => null],
            ['code' => 'WELCOME50', 'type' => 'fixed_tnd', 'amountTnd' => 50.0, 'requiresTravelClass' => null],
            ['code' => 'BUSINESS15', 'type' => 'percent', 'percent' => 15.0, 'requiresTravelClass' => 'business'],
        ];
    }

    public function normalizeTravelClassSlug(string $travelClass): string
    {
        return match (mb_strtolower(trim($travelClass))) {
            'business' => 'business',
            'first' => 'first',
            'premium_economy' => 'premium_economy',
            default => 'economy',
        };
    }

    /**
     * @return array{
     *     status: 'none'|'applied'|'invalid'|'condition_failed',
     *     message: ?string,
     *     discount: float,
     *     final_price: float,
     *     applied_code: string
     * }
     */
    public function evaluate(string $promoCode, float $basePriceTnd, string $travelClassRaw): array
    {
        $code = strtoupper(trim($promoCode));
        $base = max(0.0, round($basePriceTnd, 2));
        $travelSlug = $this->normalizeTravelClassSlug($travelClassRaw);

        if ($code === '') {
            return [
                'status' => 'none',
                'message' => null,
                'discount' => 0.0,
                'final_price' => $base,
                'applied_code' => '',
            ];
        }

        switch ($code) {
            case 'SPRING10':
                $discount = round($base * 0.10, 2);

                return [
                    'status' => 'applied',
                    'message' => null,
                    'discount' => $discount,
                    'final_price' => max(0.0, round($base - $discount, 2)),
                    'applied_code' => $code,
                ];

            case 'WELCOME50':
                $discount = round(min(50.0, $base), 2);

                return [
                    'status' => 'applied',
                    'message' => null,
                    'discount' => $discount,
                    'final_price' => max(0.0, round($base - $discount, 2)),
                    'applied_code' => $code,
                ];

            case 'BUSINESS15':
                if ($travelSlug !== 'business') {
                    return [
                        'status' => 'condition_failed',
                        'message' => 'Ce code promotionnel s\'applique uniquement aux réservations en classe Business.',
                        'discount' => 0.0,
                        'final_price' => $base,
                        'applied_code' => $code,
                    ];
                }

                $discount = round($base * 0.15, 2);

                return [
                    'status' => 'applied',
                    'message' => null,
                    'discount' => $discount,
                    'final_price' => max(0.0, round($base - $discount, 2)),
                    'applied_code' => $code,
                ];

            default:
                return [
                    'status' => 'invalid',
                    'message' => 'Code promotionnel invalide.',
                    'discount' => 0.0,
                    'final_price' => $base,
                    'applied_code' => $code,
                ];
        }
    }
}
