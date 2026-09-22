<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf;

use ZentiqLabs\FastPdf\Enums\PaperOrientation;
use ZentiqLabs\FastPdf\Enums\PaperSize;
use ZentiqLabs\FastPdf\Exceptions\PdfGenerationFailedException;
use ZentiqLabs\FastPdf\Exceptions\TemplateNotFoundException;
use ZentiqLabs\FastPdf\Services\ProcessOrchestrator;
use ZentiqLabs\FastPdf\Services\TailwindCompiler;

final class PdfBuilder
{
    private ?string $html = null;

    private PaperSize $paperSize;

    private PaperOrientation $orientation;

    /** @var array{top: float, right: float, bottom: float, left: float, unit: string} */
    private array $margins;

    private bool $useTailwind = false;

    private string $emulateMedia;

    /** @param array<string, mixed> $defaultConfig */
    public function __construct(
        private readonly ProcessOrchestrator $orchestrator,
        private readonly TailwindCompiler $tailwindCompiler,
        array $defaultConfig = [],
    ) {
        $rawSize = $defaultConfig['paper_size'] ?? 'a4';
        $this->paperSize = PaperSize::fromString(is_string($rawSize) ? $rawSize : 'a4');

        $rawOrientation = $defaultConfig['orientation'] ?? 'portrait';
        $this->orientation = PaperOrientation::from(is_string($rawOrientation) ? $rawOrientation : 'portrait');

        $rawMedia = $defaultConfig['emulate_media'] ?? 'print';
        $this->emulateMedia = is_string($rawMedia) ? $rawMedia : 'print';

        /** @var array<string, mixed> $marginConfig */
        $marginConfig = isset($defaultConfig['margins']) && is_array($defaultConfig['margins'])
            ? $defaultConfig['margins']
            : [];

        $rawTop    = $marginConfig['top'] ?? 10;
        $rawRight  = $marginConfig['right'] ?? 10;
        $rawBottom = $marginConfig['bottom'] ?? 10;
        $rawLeft   = $marginConfig['left'] ?? 10;
        $rawUnit   = $marginConfig['unit'] ?? 'mm';

        $this->margins = [
            'top'    => is_numeric($rawTop) ? (float) $rawTop : 10.0,
            'right'  => is_numeric($rawRight) ? (float) $rawRight : 10.0,
            'bottom' => is_numeric($rawBottom) ? (float) $rawBottom : 10.0,
            'left'   => is_numeric($rawLeft) ? (float) $rawLeft : 10.0,
            'unit'   => is_string($rawUnit) ? $rawUnit : 'mm',
        ];
    }

    /**
     * Use a raw HTML string as the PDF source.
     */
    public function fromHtml(string $html): static
    {
        $this->html = $html;

        return $this;
    }

    /**
     * Render a PHP template file as the PDF source.
     *
     * Variables in $data are extracted into the template scope, identical to
     * how view engines work — but with zero framework dependency.
     *
     * @param  array<string, mixed> $data
     */
    public function fromFile(string $filePath, array $data = []): static
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw TemplateNotFoundException::forPath($filePath);
        }

        $this->html = $this->renderFile($filePath, $data);

        return $this;
    }

    /**
     * Set the paper size via enum or lowercase string (e.g. 'a4', 'letter').
     */
    public function paperSize(PaperSize|string $size): static
    {
        $this->paperSize = $size instanceof PaperSize
            ? $size
            : PaperSize::fromString($size);

        return $this;
    }

    public function landscape(): static
    {
        $this->orientation = PaperOrientation::Landscape;

        return $this;
    }

    public function portrait(): static
    {
        $this->orientation = PaperOrientation::Portrait;

        return $this;
    }

    /**
     * Override page margins. Unit: 'mm' (default), 'cm', 'in', 'px'.
     */
    public function margins(
        float $top,
        float $right,
        float $bottom,
        float $left,
        string $unit = 'mm',
    ): static {
        $this->margins = compact('top', 'right', 'bottom', 'left', 'unit');

        return $this;
    }

    /**
     * Inject the standalone Tailwind CDN script before passing HTML to Chromium.
     */
    public function withTailwind(): static
    {
        $this->useTailwind = true;

        return $this;
    }

    /**
     * Set the CSS media type Chromium should emulate ('print' or 'screen').
     */
    public function emulateMedia(string $media = 'print'): static
    {
        $this->emulateMedia = $media;

        return $this;
    }

    /**
     * Render and return the raw binary PDF string.
     */
    public function output(): string
    {
        return $this->orchestrator->render(
            $this->prepareHtml(),
            $this->buildOptions(),
        );
    }

    /**
     * Render and write the PDF to an absolute file path.
     */
    public function save(string $path): void
    {
        $bytes = $this->output();

        if (file_put_contents($path, $bytes) === false) {
            throw PdfGenerationFailedException::fromProcessError(
                "Could not write PDF to path: {$path}",
                1,
            );
        }
    }

    /**
     * Emit HTTP headers and stream the PDF as a browser download.
     * Terminates the current script after flushing the output buffer.
     */
    public function download(string $filename = 'document.pdf'): never
    {
        $this->sendHeaders('attachment', $filename);
        echo $this->output();
        exit;
    }

    /**
     * Emit HTTP headers and stream the PDF inline (renders in the browser tab).
     * Terminates the current script after flushing the output buffer.
     */
    public function stream(string $filename = 'document.pdf'): never
    {
        $this->sendHeaders('inline', $filename);
        echo $this->output();
        exit;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function prepareHtml(): string
    {
        $html = $this->html ?? '';

        if ($this->useTailwind) {
            $html = $this->tailwindCompiler->inject($html);
        }

        return $html;
    }

    /** @return array<string, mixed> */
    private function buildOptions(): array
    {
        return [
            'dimensions'    => $this->paperSize->dimensions(),
            'landscape'     => $this->orientation->isLandscape(),
            'margins'       => $this->normalizeMargins(),
            'emulate_media' => $this->emulateMedia,
        ];
    }

    /** @return array{top: float, right: float, bottom: float, left: float} */
    private function normalizeMargins(): array
    {
        $unit   = (string) $this->margins['unit'];
        $top    = (float) $this->margins['top'];
        $right  = (float) $this->margins['right'];
        $bottom = (float) $this->margins['bottom'];
        $left   = (float) $this->margins['left'];

        $factor = match ($unit) {
            'cm'    => 1 / 2.54,
            'px'    => 1 / 96,
            'in'    => 1.0,
            default => 1 / 25.4, // mm
        };

        return [
            'top'    => round($top * $factor, 4),
            'right'  => round($right * $factor, 4),
            'bottom' => round($bottom * $factor, 4),
            'left'   => round($left * $factor, 4),
        ];
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $filePath, array $data): string
    {
        // Isolate the template scope so extracted variables cannot bleed
        // into the surrounding call stack.
        $render = static function (string $_fp, array $_d): string {
            extract($_d, EXTR_SKIP);
            ob_start();

            try {
                include $_fp;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render($filePath, $data);
    }

    private function sendHeaders(string $disposition, string $filename): void
    {
        if (headers_sent($file, $line)) {
            throw PdfGenerationFailedException::fromProcessError(
                "Cannot send PDF headers — output already started in {$file} on line {$line}.",
                1,
            );
        }

        $safe = rawurlencode(basename($filename));

        header('Content-Type: application/pdf');
        header("Content-Disposition: {$disposition}; filename=\"{$safe}\"; filename*=UTF-8''{$safe}");
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
    }
}
