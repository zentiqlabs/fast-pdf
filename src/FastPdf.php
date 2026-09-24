<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf;

use ZentiqLabs\FastPdf\Contracts\PdfEngineInterface;
use ZentiqLabs\FastPdf\Services\ProcessOrchestrator;
use ZentiqLabs\FastPdf\Services\TailwindCompiler;
use ZentiqLabs\FastPdf\Testing\FakePdfEngine;

/**
 * Main entry point for zentiq-labs/fast-pdf.
 *
 * Works in any PHP 8.3+ application — no framework required.
 *
 * Usage:
 *
 *   $pdf = new FastPdf();                         // auto-discover Chromium
 *   $pdf = new FastPdf(['binary' => '/usr/bin/chromium']);
 *   $pdf = new FastPdf(require 'config/fast-pdf.php');
 *
 *   // From a raw HTML string
 *   $pdf->fromHtml('<h1>Hello</h1>')->save('/tmp/out.pdf');
 *
 *   // From a PHP template file
 *   $pdf->fromFile(__DIR__ . '/templates/invoice.php', ['invoice' => $data])
 *       ->landscape()
 *       ->withTailwind()
 *       ->download('invoice.pdf');
 *
 * Security notes:
 *   - By default Chromium is launched with --disable-local-file-access so that
 *     user-supplied HTML cannot read arbitrary files from the server's filesystem.
 *   - Set 'allow_local_file_access' => true only when you own and trust every
 *     byte of the HTML being rendered.
 *   - Set 'containerized' => true when running inside Docker / Alpine to add
 *     the sandbox-bypass flags required by unprivileged Linux containers.
 *     Never set this on bare-metal or VM deployments.
 */
class FastPdf
{
    private readonly PdfEngineInterface $orchestrator;
    private readonly TailwindCompiler $tailwindCompiler;

    /** @var array<string, mixed> */
    private readonly array $config;

    private ?FakePdfEngine $fake = null;

    /**
     * @param array<string, mixed>  $config
     * @param PdfEngineInterface|null $engine Supply a custom engine (e.g. FakePdfEngine for tests).
     *                                        When null the real ProcessOrchestrator is constructed.
     */
    public function __construct(array $config = [], ?PdfEngineInterface $engine = null)
    {
        $defaults     = $this->defaults();
        $this->config = array_replace_recursive($defaults, $config);

        $this->orchestrator = $engine ?? new ProcessOrchestrator(
            discoveryPaths:     (array) $this->config['binary_discovery_paths'],
            extraFlags:         $this->resolveContainerFlags(),
            timeout:            (int)   $this->config['timeout'],
            tempDir:            (string) $this->config['temp_dir'],
            binaryOverride:     $this->config['binary'] !== '' ? (string) $this->config['binary'] : null,
            allowLocalFileAccess: (bool) $this->config['allow_local_file_access'],
        );

        $this->tailwindCompiler = new TailwindCompiler(
            cdnUrl: (string) $this->config['tailwind_cdn_url'],
        );
    }

    /**
     * Return a FastPdf instance backed by FakePdfEngine.
     *
     * Chromium is never spawned. Every render call is recorded and can be
     * verified with the assertion helpers on the returned instance.
     *
     *   $pdf = FastPdf::fake();
     *   $pdf->fromHtml('<h1>Test</h1>')->output();
     *   $pdf->assertRendered();
     *   $pdf->assertRenderedCount(1);
     *   $pdf->assertRenderedHtmlContains('<h1>');
     *
     * @param array<string, mixed> $config
     */
    public static function fake(array $config = []): static
    {
        $engine   = new FakePdfEngine();
        $instance = new self($config, $engine);
        $instance->fake = $engine;

        return $instance;
    }

    // ------------------------------------------------------------------
    // Fake assertion helpers (only usable after fake())
    // ------------------------------------------------------------------

    public function assertRendered(): void
    {
        $this->requireFakeMode()->assertRendered();
    }

    public function assertRenderedCount(int $expected): void
    {
        $this->requireFakeMode()->assertRenderedCount($expected);
    }

    public function assertRenderedHtmlContains(string $fragment): void
    {
        $this->requireFakeMode()->assertRenderedHtmlContains($fragment);
    }

    /** @return array<int, array{html: string, options: array<string, mixed>}> */
    public function renderedCalls(): array
    {
        return $this->requireFakeMode()->calls();
    }

    /**
     * Start a new builder from a raw HTML string.
     */
    public function fromHtml(string $html): PdfBuilder
    {
        return $this->builder()->fromHtml($html);
    }

    /**
     * Start a new builder from a PHP template file.
     *
     * @param array<string, mixed> $data
     */
    public function fromFile(string $filePath, array $data = []): PdfBuilder
    {
        return $this->builder()->fromFile($filePath, $data);
    }

    /**
     * Return a fresh PdfBuilder for full manual control.
     */
    public function builder(): PdfBuilder
    {
        return new PdfBuilder(
            orchestrator:     $this->orchestrator,
            tailwindCompiler: $this->tailwindCompiler,
            defaultConfig:    $this->config,
        );
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function requireFakeMode(): FakePdfEngine
    {
        if ($this->fake === null) {
            throw new \LogicException(
                'Assertion helpers are only available on a FastPdf instance created via FastPdf::fake().',
            );
        }

        return $this->fake;
    }

    /**
     * Return sandbox-bypass flags only when running in a containerized
     * environment. On bare-metal or VM hosts these flags are omitted so
     * the OS user-namespace sandbox remains active.
     *
     * @return array<int, string>
     */
    private function resolveContainerFlags(): array
    {
        $base = (array) ($this->config['chromium_flags'] ?? []);

        if ((bool) $this->config['containerized']) {
            $containerFlags = ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'];

            return array_values(array_unique(array_merge($base, $containerFlags)));
        }

        // Strip any sandbox-bypass flags that may have been passed naively
        // in the user's config when not running in a container.
        return array_values(array_filter($base, static function (string $flag): bool {
            return ! in_array($flag, ['--no-sandbox', '--disable-setuid-sandbox'], true);
        }));
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'binary'                 => (string) (getenv('FAST_PDF_BINARY') ?: ''),
            'binary_discovery_paths' => [
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
                '/usr/bin/google-chrome',
                '/usr/bin/google-chrome-stable',
                '/snap/bin/chromium',
                '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
                '/Applications/Chromium.app/Contents/MacOS/Chromium',
            ],
            'timeout'             => (int) (getenv('FAST_PDF_TIMEOUT') ?: 30),
            'paper_size'          => (string) (getenv('FAST_PDF_PAPER_SIZE') ?: 'a4'),
            'orientation'         => (string) (getenv('FAST_PDF_ORIENTATION') ?: 'portrait'),
            'emulate_media'       => (string) (getenv('FAST_PDF_MEDIA') ?: 'print'),
            'tailwind_cdn_url'    => (string) (getenv('FAST_PDF_TAILWIND_CDN') ?: 'https://cdn.tailwindcss.com'),
            'temp_dir'            => (string) (getenv('FAST_PDF_TEMP_DIR') ?: sys_get_temp_dir()),
            'containerized'       => (bool)   (getenv('FAST_PDF_CONTAINERIZED') ?: false),
            'allow_local_file_access' => (bool) (getenv('FAST_PDF_ALLOW_LOCAL_FILE_ACCESS') ?: false),
            'chromium_flags'      => ['--disable-dev-shm-usage'],
            'margins' => [
                'top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10, 'unit' => 'mm',
            ],
        ];
    }
}
