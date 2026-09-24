<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf;

/**
 * Immutable value object returned by toInlineResponse() and toDownloadResponse().
 *
 * Carries the raw PDF bytes and the HTTP headers required to serve them, without
 * calling exit() or emitting anything. Framework adapters can inspect the headers
 * array and body string to build a framework-native response; plain PHP scripts
 * can call send() to emit directly via the SAPI.
 */
final class PdfResponse
{
    /**
     * @param string               $body    Raw binary PDF bytes.
     * @param array<string,string> $headers HTTP headers keyed by header name.
     */
    public function __construct(
        public readonly string $body,
        public readonly array $headers,
    ) {}

    /**
     * Emit all headers and the PDF body via the PHP SAPI.
     */
    public function send(): void
    {
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}
