<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf\Exceptions;

use InvalidArgumentException;

final class TemplateNotFoundException extends InvalidArgumentException
{
    public static function forPath(string $path): self
    {
        return new self("PHP template file not found or not readable: [{$path}].");
    }
}
