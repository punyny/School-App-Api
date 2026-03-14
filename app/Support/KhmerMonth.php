<?php

namespace App\Support;

class KhmerMonth
{
    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return [
            1 => 'មករា',
            2 => 'កុម្ភៈ',
            3 => 'មិនា',
            4 => 'មេសា',
            5 => 'ឧសភា',
            6 => 'មិថុនា',
            7 => 'កក្កដា',
            8 => 'សីហា',
            9 => 'កញ្ញា',
            10 => 'តុលា',
            11 => 'វិច្ឆិកា',
            12 => 'ធ្នូ',
        ];
    }

    public static function label(?int $month, string $fallback = '-'): string
    {
        if ($month === null || $month < 1 || $month > 12) {
            return $fallback;
        }

        return self::options()[$month] ?? $fallback;
    }
}
