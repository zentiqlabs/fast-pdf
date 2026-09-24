<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf\Testing;

use ZentiqLabs\FastPdf\Contracts\PdfEngineInterface;

/**
 * In-process fake for PdfEngineInterface.
 *
 * Inject via FastPdf::fake() to prevent Chromium from spawning during unit
 * tests while still recording every render call for assertion.
 *
 * Usage:
 *
 *   $pdf = FastPdf::fake();
 *
 *   $pdf->fromHtml('<h1>Invoice</h1>')->output();
 *
 *   $pdf->assertRendered();
 *   $pdf->assertRenderedCount(1);
 *   $pdf->assertRenderedHtmlContains('Invoice');
 */
final class FakePdfEngine implements PdfEngineInterface
{
    private string $fakeBytes = '%PDF-1.4 fake';

    /** @var array<int, array{html: string, options: array<string, mixed>}> */
    private array $calls = [];

    public function render(string $html, array $options = []): string
    {
        $this->calls[] = ['html' => $html, 'options' => $options];

        return $this->fakeBytes;
    }

    /**
     * Override the bytes returned for every subsequent render call.
     */
    public function returns(string $bytes): void
    {
        $this->fakeBytes = $bytes;
    }

    /** @return array<int, array{html: string, options: array<string, mixed>}> */
    public function calls(): array
    {
        return $this->calls;
    }

    public function callCount(): int
    {
        return count($this->calls);
    }

    public function wasRendered(): bool
    {
        return $this->calls !== [];
    }

    public function assertRendered(): void
    {
        if ($this->calls === []) {
            throw new \RuntimeException('Expected at least one PDF render, but none occurred.');
        }
    }

    public function assertRenderedCount(int $expected): void
    {
        $actual = count($this->calls);

        if ($actual !== $expected) {
            throw new \RuntimeException(
                "Expected {$expected} PDF render(s), but got {$actual}.",
            );
        }
    }

    /**
     * Assert that at least one render call's HTML contained the given fragment.
     */
    public function assertRenderedHtmlContains(string $fragment): void
    {
        foreach ($this->calls as $call) {
            if (str_contains($call['html'], $fragment)) {
                return;
            }
        }

        throw new \RuntimeException(
            "No render call contained the HTML fragment: {$fragment}",
        );
    }
}
