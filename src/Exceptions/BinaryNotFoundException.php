<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf\Exceptions;

use RuntimeException;

final class BinaryNotFoundException extends RuntimeException
{
    /** @param array<int, string> $paths */
    public static function forPaths(array $paths): self
    {
        $list = implode(', ', $paths);

        return new self(
            "Chromium binary not found. Searched: [{$list}]. " .
            "Set the FAST_PDF_BINARY environment variable or pass 'binary' in the config array."
        );
    }
}
