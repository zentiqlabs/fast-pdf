<?php

declare(strict_types=1);

use ZentiqLabs\FastPdf\Exceptions\BinaryNotFoundException;
use ZentiqLabs\FastPdf\Exceptions\PdfGenerationFailedException;
use ZentiqLabs\FastPdf\Services\ProcessOrchestrator;

/**
 * Build an orchestrator that accepts a binaryOverride so tests never need
 * a real Chromium installation.
 *
 * @param  array<int, string> $extraFlags
 */
function makeOrchestrator(
    array $discoveryPaths = [],
    ?string $binaryOverride = null,
    int $timeout = 10,
    array $extraFlags = ['--no-sandbox'],
    bool $allowLocalFileAccess = false,
): ProcessOrchestrator {
    return new ProcessOrchestrator(
        discoveryPaths: $discoveryPaths,
        extraFlags: $extraFlags,
        timeout: $timeout,
        tempDir: sys_get_temp_dir(),
        binaryOverride: $binaryOverride,
        allowLocalFileAccess: $allowLocalFileAccess,
    );
}

/**
 * Invoke the private buildCommand method via reflection and return the
 * resulting argument array so tests can assert on the exact CLI flags
 * without executing a real Chromium process.
 *
 * @param  array<string, mixed> $options
 * @return array<int, string>
 */
function inspectCommand(ProcessOrchestrator $orchestrator, array $options = []): array
{
    $method = new ReflectionMethod(ProcessOrchestrator::class, 'buildCommand');

    return $method->invoke(
        $orchestrator,
        '/tmp/input.html',
        '/tmp/output.pdf',
        $options,
    );
}

// ------------------------------------------------------------------
// Binary discovery
// ------------------------------------------------------------------

it('throws BinaryNotFoundException when no candidate path exists', function (): void {
    makeOrchestrator(discoveryPaths: ['/nonexistent/chromium', '/also/not/here']);
})->throws(BinaryNotFoundException::class);

it('BinaryNotFoundException message lists every probed path', function (): void {
    expect(fn () => makeOrchestrator(['/x/y', '/a/b']))
        ->toThrow(BinaryNotFoundException::class, '/x/y');
});

it('accepts a binaryOverride without probing discovery paths', function (): void {
    $o = makeOrchestrator(
        discoveryPaths: ['/nonexistent/path'],
        binaryOverride: '/usr/bin/true',
    );

    expect($o->getBinary())->toBe('/usr/bin/true');
})->skipOnWindows();

it('treats an empty-string binaryOverride as absent', function (): void {
    expect(fn () => makeOrchestrator(
        discoveryPaths: [],
        binaryOverride: '',
    ))->toThrow(BinaryNotFoundException::class);
});

// ------------------------------------------------------------------
// Security: local file access hardening (default-off)
// ------------------------------------------------------------------

it('includes --disable-local-file-access by default', function (): void {
    $o       = makeOrchestrator(binaryOverride: '/usr/bin/chromium');
    $command = inspectCommand($o);

    expect($command)->toContain('--disable-local-file-access');
});

it('includes --allow-file-access-from-files=false by default', function (): void {
    $o       = makeOrchestrator(binaryOverride: '/usr/bin/chromium');
    $command = inspectCommand($o);

    expect($command)->toContain('--allow-file-access-from-files=false');
});

it('includes --disable-web-security=false by default', function (): void {
    $o       = makeOrchestrator(binaryOverride: '/usr/bin/chromium');
    $command = inspectCommand($o);

    expect($command)->toContain('--disable-web-security=false');
});

it('omits all three hardening flags when allowLocalFileAccess is true', function (): void {
    $o       = makeOrchestrator(binaryOverride: '/usr/bin/chromium', allowLocalFileAccess: true);
    $command = inspectCommand($o);

    expect($command)
        ->not->toContain('--disable-local-file-access')
        ->not->toContain('--allow-file-access-from-files=false')
        ->not->toContain('--disable-web-security=false');
});

it('isLocalFileAccessAllowed() reflects the constructor argument', function (): void {
    $locked = makeOrchestrator(binaryOverride: '/usr/bin/chromium', allowLocalFileAccess: false);
    $open   = makeOrchestrator(binaryOverride: '/usr/bin/chromium', allowLocalFileAccess: true);

    expect($locked->isLocalFileAccessAllowed())->toBeFalse()
        ->and($open->isLocalFileAccessAllowed())->toBeTrue();
});

// ------------------------------------------------------------------
// Command structure
// ------------------------------------------------------------------

it('command begins with the binary path', function (): void {
    $o       = makeOrchestrator(binaryOverride: '/usr/bin/chromium');
    $command = inspectCommand($o);

    expect($command[0])->toBe('/usr/bin/chromium');
});

it('command ends with the file:// input path', function (): void {
    $o       = makeOrchestrator(binaryOverride: '/usr/bin/chromium');
    $command = inspectCommand($o);

    expect(end($command))->toBe('file:///tmp/input.html');
});

it('landscape flag swaps paper width and height', function (): void {
    $o = makeOrchestrator(binaryOverride: '/usr/bin/chromium');

    $portrait  = inspectCommand($o, ['dimensions' => [8.27, 11.69], 'landscape' => false]);
    $landscape = inspectCommand($o, ['dimensions' => [8.27, 11.69], 'landscape' => true]);

    $extractDimension = static function (array $cmd, string $prefix): float {
        foreach ($cmd as $flag) {
            if (str_starts_with($flag, $prefix)) {
                return (float) substr($flag, strlen($prefix));
            }
        }
        return 0.0;
    };

    expect($extractDimension($portrait, '--paper-width='))->toBe(8.27)
        ->and($extractDimension($landscape, '--paper-width='))->toBe(11.69);
});

it('extra flags are appended to the command', function (): void {
    $o       = makeOrchestrator(
        binaryOverride: '/usr/bin/chromium',
        extraFlags: ['--custom-flag=1', '--another-flag'],
    );
    $command = inspectCommand($o);

    expect($command)->toContain('--custom-flag=1')
        ->and($command)->toContain('--another-flag');
});

// ------------------------------------------------------------------
// Exception factories
// ------------------------------------------------------------------

it('PdfGenerationFailedException carries the exit code', function (): void {
    $e = PdfGenerationFailedException::fromProcessError('stderr text', 127);

    expect($e->getCode())->toBe(127)
        ->and($e->getMessage())->toContain('127');
});

it('PdfGenerationFailedException timeout message names the duration', function (): void {
    $e = PdfGenerationFailedException::timeout(45);

    expect($e->getMessage())->toContain('45');
});

it('PdfGenerationFailedException wraps a previous throwable', function (): void {
    $prev = new \RuntimeException('root cause');
    $e    = PdfGenerationFailedException::fromProcessError('err', 1, $prev);

    expect($e->getPrevious())->toBe($prev);
});
