<?php

declare(strict_types=1);

use Mockery\MockInterface;
use ZentiqLabs\FastPdf\Enums\PaperSize;
use ZentiqLabs\FastPdf\FastPdf;
use ZentiqLabs\FastPdf\PdfBuilder;
use ZentiqLabs\FastPdf\Services\ProcessOrchestrator;
use ZentiqLabs\FastPdf\Services\TailwindCompiler;

const FAKE_PDF = '%PDF-1.4 fake binary content';

function mockOrchestratorFeature(string $returns = FAKE_PDF): ProcessOrchestrator&MockInterface
{
    return Mockery::mock(ProcessOrchestrator::class, static function (MockInterface $mock) use ($returns): void {
        $mock->shouldReceive('render')->andReturn($returns);
    });
}

/**
 * Build a FastPdf instance whose internal ProcessOrchestrator is replaced
 * with the given mock, bypassing real Chromium entirely.
 */
function makeFastPdf(ProcessOrchestrator&MockInterface $orchestrator): FastPdf
{
    $pdf = new class ($orchestrator) extends FastPdf {
        public function __construct(private readonly ProcessOrchestrator $fakeOrchestrator)
        {
            // Skip the parent constructor's orchestrator construction.
        }

        public function builder(): PdfBuilder
        {
            return new PdfBuilder(
                orchestrator:    $this->fakeOrchestrator,
                tailwindCompiler: new TailwindCompiler('https://cdn.tailwindcss.com'),
                defaultConfig:   [
                    'paper_size' => 'a4', 'orientation' => 'portrait',
                    'emulate_media' => 'print',
                    'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10, 'unit' => 'mm'],
                ],
            );
        }
    };

    return $pdf;
}

// ------------------------------------------------------------------
// FastPdf instantiation
// ------------------------------------------------------------------

it('FastPdf can be constructed with an empty config array', function (): void {
    // Will throw BinaryNotFoundException on a machine without Chromium — that's expected.
    // We only test that the constructor does not blow up for unrelated reasons.
    expect(fn () => new FastPdf([]))->not->toThrow(\TypeError::class);
});

it('FastPdf::fromHtml returns a PdfBuilder', function (): void {
    $pdf = makeFastPdf(mockOrchestratorFeature());

    expect($pdf->fromHtml('<p>Test</p>'))->toBeInstanceOf(PdfBuilder::class);
});

it('FastPdf::fromFile returns a PdfBuilder', function (): void {
    $template = tempnam(sys_get_temp_dir(), 'fpdf_') . '.php';
    file_put_contents($template, '<p>Hello</p>');

    $pdf = makeFastPdf(mockOrchestratorFeature());

    expect($pdf->fromFile($template))->toBeInstanceOf(PdfBuilder::class);

    unlink($template);
});

// ------------------------------------------------------------------
// Full fluent chain
// ------------------------------------------------------------------

it('full chain produces a non-empty string output', function (): void {
    $result = makeFastPdf(mockOrchestratorFeature())
        ->fromHtml('<html><body><h1>Invoice</h1></body></html>')
        ->paperSize(PaperSize::A4)
        ->portrait()
        ->margins(15, 15, 15, 15)
        ->emulateMedia('print')
        ->output();

    expect($result)->toBeString()->not->toBeEmpty();
});

it('landscape() flag is forwarded to the orchestrator', function (): void {
    $captured = [];

    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(function (string $_, array $opts) use (&$captured): bool {
            $captured = $opts;
            return true;
        })
        ->andReturn(FAKE_PDF);

    makeFastPdf($o)
        ->fromHtml('<p>Landscape</p>')
        ->paperSize(PaperSize::A4)
        ->landscape()
        ->output();

    expect($captured['landscape'])->toBeTrue();
});

// ------------------------------------------------------------------
// Tailwind injection (integration-level)
// ------------------------------------------------------------------

it('withTailwind injects cdn script into the html before rendering', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $html) => str_contains($html, 'cdn.tailwindcss.com'))
        ->andReturn(FAKE_PDF);

    makeFastPdf($o)
        ->fromHtml('<html><head></head><body>Hello</body></html>')
        ->withTailwind()
        ->output();
});

// ------------------------------------------------------------------
// save() to disk
// ------------------------------------------------------------------

it('save() writes the PDF bytes to the specified path', function (): void {
    $path = sys_get_temp_dir() . '/fast_pdf_feature_' . uniqid() . '.pdf';

    makeFastPdf(mockOrchestratorFeature())
        ->fromHtml('<p>Save integration</p>')
        ->save($path);

    expect(is_file($path))->toBeTrue()
        ->and(filesize($path))->toBeGreaterThan(0);

    unlink($path);
});

// ------------------------------------------------------------------
// PHP template rendering
// ------------------------------------------------------------------

it('fromFile renders php template variables into the html', function (): void {
    $template = tempnam(sys_get_temp_dir(), 'fpdf_') . '.php';
    file_put_contents($template, '<?php echo "<h1>{$title}</h1>"; ?>');

    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $html) => str_contains($html, 'My Report'))
        ->andReturn(FAKE_PDF);

    makeFastPdf($o)
        ->fromFile($template, ['title' => 'My Report'])
        ->output();

    unlink($template);
});

// ------------------------------------------------------------------
// TailwindCompiler singleton behaviour
// ------------------------------------------------------------------

it('TailwindCompiler can be instantiated standalone', function (): void {
    $compiler = new TailwindCompiler('https://cdn.tailwindcss.com');
    $html     = $compiler->inject('<html><head></head><body></body></html>');

    expect($html)->toContain('cdn.tailwindcss.com');
});

// ------------------------------------------------------------------
// FastPdf::fake()
// ------------------------------------------------------------------

it('FastPdf::fake() returns a FastPdf instance', function (): void {
    expect(FastPdf::fake())->toBeInstanceOf(FastPdf::class);
});

it('FastPdf::fake() bypasses Chromium and returns fake PDF bytes', function (): void {
    $pdf    = FastPdf::fake();
    $result = $pdf->fromHtml('<p>Hello</p>')->output();

    expect($result)->toBeString()->not->toBeEmpty();
});

it('FastPdf::fake() records render calls', function (): void {
    $pdf = FastPdf::fake();
    $pdf->fromHtml('<p>First</p>')->output();
    $pdf->fromHtml('<p>Second</p>')->output();

    expect($pdf->renderedCalls())->toHaveCount(2);
});

it('assertRendered() passes after a render call', function (): void {
    $pdf = FastPdf::fake();
    $pdf->fromHtml('<h1>Test</h1>')->output();

    expect(fn () => $pdf->assertRendered())->not->toThrow(\RuntimeException::class);
});

it('assertRendered() throws when no render occurred', function (): void {
    $pdf = FastPdf::fake();

    expect(fn () => $pdf->assertRendered())->toThrow(\RuntimeException::class);
});

it('assertRenderedCount() matches the number of renders', function (): void {
    $pdf = FastPdf::fake();
    $pdf->fromHtml('<p>A</p>')->output();
    $pdf->fromHtml('<p>B</p>')->output();

    expect(fn () => $pdf->assertRenderedCount(2))->not->toThrow(\RuntimeException::class);
    expect(fn () => $pdf->assertRenderedCount(1))->toThrow(\RuntimeException::class);
});

it('assertRenderedHtmlContains() matches an HTML fragment', function (): void {
    $pdf = FastPdf::fake();
    $pdf->fromHtml('<h1>Invoice #42</h1>')->output();

    expect(fn () => $pdf->assertRenderedHtmlContains('Invoice #42'))->not->toThrow(\RuntimeException::class);
    expect(fn () => $pdf->assertRenderedHtmlContains('Nonexistent'))->toThrow(\RuntimeException::class);
});

it('assertRendered() throws LogicException when not in fake mode', function (): void {
    // A real instance (without fake()) should throw LogicException on assertions.
    $pdf = new class extends FastPdf {
        public function __construct()
        {
            // skip real constructor
        }

        public function builder(): PdfBuilder
        {
            return new PdfBuilder(
                orchestrator:     \Mockery::mock(\ZentiqLabs\FastPdf\Services\ProcessOrchestrator::class),
                tailwindCompiler: new TailwindCompiler('https://cdn.tailwindcss.com'),
                defaultConfig:    [],
            );
        }
    };

    expect(fn () => $pdf->assertRendered())->toThrow(\LogicException::class);
});

it('FastPdf::fake() fromFile() also records the render call', function (): void {
    $template = tempnam(sys_get_temp_dir(), 'fpdf_') . '.php';
    file_put_contents($template, '<p><?= $name ?></p>');

    $pdf = FastPdf::fake();
    $pdf->fromFile($template, ['name' => 'Zentiq'])->output();

    $pdf->assertRendered();
    $pdf->assertRenderedHtmlContains('Zentiq');

    unlink($template);
});
