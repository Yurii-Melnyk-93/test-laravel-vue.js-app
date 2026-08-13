<?php

namespace App\Support;

/**
 * Money is stored and moved around as an integer number of cents.
 * Formatting for humans happens here and nowhere else, so the API
 * never has to decide how to round.
 */
final class Money
{
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', ' ');
    }

    /**
     * Shape used by every API resource that exposes an amount.
     *
     * @return array{cents: int, formatted: string}
     */
    public static function toArray(int $cents): array
    {
        return [
            'cents' => $cents,
            'formatted' => self::format($cents),
        ];
    }
}
