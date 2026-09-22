<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf\Enums;

enum PaperOrientation: string
{
    case Portrait  = 'portrait';
    case Landscape = 'landscape';

    public function isLandscape(): bool
    {
        return $this === self::Landscape;
    }
}
