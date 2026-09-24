<?php

declare(strict_types=1);

use Mockery\MockInterface;
use ZentiqLabs\FastPdf\Enums\PaperSize;
use ZentiqLabs\FastPdf\Exceptions\PdfGenerationFailedException;
use ZentiqLabs\FastPdf\Exceptions\TemplateNotFoundException;
use ZentiqLabs\FastPdf\PdfBuilder;
use ZentiqLabs\FastPdf\Services\ProcessOrchestrator;
use ZentiqLabs\FastPdf\Services\TailwindCompiler;

function mockOrchestrator(string $returns = '%PDF-1.4 fake'): ProcessOrchestrator&MockInterface
{
    return Mockery::mock(ProcessOrchestrator::class, static function (MockInterface $mock) use ($returns): void {
        $mock->shouldReceive('render')->andReturn($returns);
    });
}

function defaultConfig(): array
{
    return [
        'paper_size'    => 'a4',
        'orientation'   => 'portrait',
        'emulate_media' => 'print',
        'margins'       => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10, 'unit' => 'mm'],
    ];
}

function makeBuilder(?ProcessOrchestrator $o = null, array $config = []): PdfBuilder
{
    return new PdfBuilder(
        orchestrator:    $o ?? mockOrchestrator(),
        tailwindCompiler: new TailwindCompiler('https://cdn.tailwindcss.com'),
        defaultConfig:   array_merge(defaultConfig(), $config),
    );
}

// ------------------------------------------------------------------
// fromHtml
// ------------------------------------------------------------------

it('output() returns a non-empty string from fromHtml', function (): void {
    expect(makeBuilder()->fromHtml('<p>Hello</p>')->output())
        ->toBeString()
        ->not->toBeEmpty();
});

it('passes the exact html to the orchestrator', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $html) => str_contains($html, 'UniqueMarker123'))
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>UniqueMarker123</p>')->output();
});

// ------------------------------------------------------------------
// fromFile
// ------------------------------------------------------------------

it('fromFile renders a PHP template with injected variables', function (): void {
    $template = tempnam(sys_get_temp_dir(), 'fpdf_') . '.php';
    file_put_contents($template, '<?php echo $greeting; ?>');

    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $html) => str_contains($html, 'Hello World'))
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromFile($template, ['greeting' => 'Hello World'])->output();

    unlink($template);
});

it('fromFile throws TemplateNotFoundException for missing file', function (): void {
    makeBuilder()->fromFile('/no/such/file.php');
})->throws(TemplateNotFoundException::class);

// ------------------------------------------------------------------
// Orientation
// ------------------------------------------------------------------

it('landscape() sets landscape flag in options', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $_, array $opts) => $opts['landscape'] === true)
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>x</p>')->landscape()->output();
});

it('portrait() clears landscape flag in options', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $_, array $opts) => $opts['landscape'] === false)
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>x</p>')->portrait()->output();
});

// ------------------------------------------------------------------
// Paper size
// ------------------------------------------------------------------

it('paperSize() accepts a PaperSize enum', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $_, array $opts) => $opts['dimensions'] === PaperSize::Letter->dimensions())
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>x</p>')->paperSize(PaperSize::Letter)->output();
});

it('paperSize() accepts a lowercase string', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $_, array $opts) => $opts['dimensions'] === PaperSize::A3->dimensions())
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>x</p>')->paperSize('a3')->output();
});

// ------------------------------------------------------------------
// Tailwind injection
// ------------------------------------------------------------------

it('withTailwind() injects the CDN script tag before the orchestrator call', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $html) => str_contains($html, 'cdn.tailwindcss.com'))
        ->andReturn('%PDF-1.4');

    makeBuilder($o)
        ->fromHtml('<html><head></head><body>Hello</body></html>')
        ->withTailwind()
        ->output();
});

// ------------------------------------------------------------------
// Media emulation
// ------------------------------------------------------------------

it('emulateMedia() forwards the value in options', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(fn (string $_, array $opts) => $opts['emulate_media'] === 'screen')
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>x</p>')->emulateMedia('screen')->output();
});

// ------------------------------------------------------------------
// save()
// ------------------------------------------------------------------

it('save() writes PDF bytes to disk', function (): void {
    $path = sys_get_temp_dir() . '/fast_pdf_unit_' . uniqid() . '.pdf';
    makeBuilder()->fromHtml('<p>Save test</p>')->save($path);

    expect(is_file($path))->toBeTrue()
        ->and(filesize($path))->toBeGreaterThan(0);

    unlink($path);
});

// ------------------------------------------------------------------
// Margin normalisation
// ------------------------------------------------------------------

it('converts mm margins to inch fractions', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(function (string $_, array $opts): bool {
            return abs($opts['margins']['top'] - 1.0) < 0.001; // 25.4 mm → 1.0 in
        })
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>x</p>')->margins(25.4, 25.4, 25.4, 25.4, 'mm')->output();
});

it('converts cm margins to inch fractions', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(function (string $_, array $opts): bool {
            return abs($opts['margins']['top'] - 1.0) < 0.001; // 2.54 cm → 1.0 in
        })
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>x</p>')->margins(2.54, 2.54, 2.54, 2.54, 'cm')->output();
});

// ------------------------------------------------------------------
// paper() convenience method
// ------------------------------------------------------------------

it('paper() sets paper size and portrait orientation', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(function (string $_, array $opts): bool {
            return $opts['dimensions'] === PaperSize::Letter->dimensions()
                && $opts['landscape'] === false;
        })
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>x</p>')->paper('letter', 'portrait')->output();
});

it('paper() sets paper size and landscape orientation', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(function (string $_, array $opts): bool {
            return $opts['dimensions'] === PaperSize::A3->dimensions()
                && $opts['landscape'] === true;
        })
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>x</p>')->paper('a3', 'landscape')->output();
});

it('paper() defaults to a4 portrait when called with no arguments', function (): void {
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')
        ->once()
        ->withArgs(function (string $_, array $opts): bool {
            return $opts['dimensions'] === PaperSize::A4->dimensions()
                && $opts['landscape'] === false;
        })
        ->andReturn('%PDF-1.4');

    makeBuilder($o)->fromHtml('<p>x</p>')->paper()->output();
});

// ------------------------------------------------------------------
// toInlineResponse() / toDownloadResponse()
// ------------------------------------------------------------------

it('toInlineResponse() returns a PdfResponse with inline disposition', function (): void {
    $response = makeBuilder()->fromHtml('<p>x</p>')->toInlineResponse('report.pdf');

    expect($response)->toBeInstanceOf(\ZentiqLabs\FastPdf\PdfResponse::class)
        ->and($response->headers['Content-Type'])->toBe('application/pdf')
        ->and($response->headers['Content-Disposition'])->toContain('inline')
        ->and($response->headers['Content-Disposition'])->toContain('report.pdf')
        ->and($response->body)->not->toBeEmpty();
});

it('toDownloadResponse() returns a PdfResponse with attachment disposition', function (): void {
    $response = makeBuilder()->fromHtml('<p>x</p>')->toDownloadResponse('invoice.pdf');

    expect($response)->toBeInstanceOf(\ZentiqLabs\FastPdf\PdfResponse::class)
        ->and($response->headers['Content-Disposition'])->toContain('attachment')
        ->and($response->headers['Content-Disposition'])->toContain('invoice.pdf');
});

it('toInlineResponse() defaults filename to document.pdf', function (): void {
    $response = makeBuilder()->fromHtml('<p>x</p>')->toInlineResponse();

    expect($response->headers['Content-Disposition'])->toContain('document.pdf');
});

it('toDownloadResponse() Content-Length matches body byte length', function (): void {
    $fakeBytes = str_repeat('x', 512);
    $o = Mockery::mock(ProcessOrchestrator::class);
    $o->shouldReceive('render')->once()->andReturn($fakeBytes);

    $response = makeBuilder($o)->fromHtml('<p>x</p>')->toDownloadResponse();

    expect($response->headers['Content-Length'])->toBe('512')
        ->and(strlen($response->body))->toBe(512);
});
