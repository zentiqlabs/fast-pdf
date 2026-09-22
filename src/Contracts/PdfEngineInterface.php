<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf\Contracts;

interface PdfEngineInterface
{
    /**
     * Render an HTML document to a raw binary PDF payload.
     *
     * @param  string               $html    Full HTML document to render.
     * @param  array<string, mixed> $options Engine-specific rendering options.
     * @return string               Raw binary PDF bytes.
     */
    public function render(string $html, array $options = []): string;
}
