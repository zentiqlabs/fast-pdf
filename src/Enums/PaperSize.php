<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf\Enums;

enum PaperSize: string
{
    case A0 = 'a0';
    case A1 = 'a1';
    case A2 = 'a2';
    case A3 = 'a3';
    case A4 = 'a4';
    case A5 = 'a5';
    case A6 = 'a6';
    case Letter = 'letter';
    case Legal = 'legal';
    case Tabloid = 'tabloid';
    case Ledger = 'ledger';

    /**
     * Returns [widthInches, heightInches] for the given paper size.
     *
     * @return array{float, float}
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::A0      => [33.11, 46.81],
            self::A1      => [23.39, 33.11],
            self::A2      => [16.54, 23.39],
            self::A3      => [11.69, 16.54],
            self::A4      => [8.27, 11.69],
            self::A5      => [5.83, 8.27],
            self::A6      => [4.13, 5.83],
            self::Letter  => [8.50, 11.00],
            self::Legal   => [8.50, 14.00],
            self::Tabloid => [11.00, 17.00],
            self::Ledger  => [17.00, 11.00],
        };
    }

    public static function fromString(string $value): self
    {
        return self::from(strtolower($value));
    }
}
