<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf\Exceptions;

use RuntimeException;
use Throwable;

final class PdfGenerationFailedException extends RuntimeException
{
    public static function fromProcessError(string $errorOutput, int $exitCode, ?Throwable $previous = null): self
    {
        return new self(
            "PDF generation failed (exit {$exitCode}): {$errorOutput}",
            $exitCode,
            $previous
        );
    }

    public static function timeout(int $seconds): self
    {
        return new self("PDF generation timed out after {$seconds} seconds.");
    }
}
