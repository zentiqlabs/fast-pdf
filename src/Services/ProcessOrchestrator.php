<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf\Services;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use ZentiqLabs\FastPdf\Contracts\PdfEngineInterface;
use ZentiqLabs\FastPdf\Exceptions\BinaryNotFoundException;
use ZentiqLabs\FastPdf\Exceptions\PdfGenerationFailedException;

class ProcessOrchestrator implements PdfEngineInterface
{
    private readonly string $binary;

    /**
     * @param  array<int, string> $discoveryPaths
     * @param  array<int, string> $extraFlags       Caller-supplied flags appended verbatim
     *                                               (e.g. --no-sandbox for container environments).
     * @param  bool               $allowLocalFileAccess  When false (default) the hardened flag set
     *                                               prevents the rendered page from accessing
     *                                               file:// URIs other than the document itself.
     *                                               Set to true only when you intentionally
     *                                               render templates that load local disk assets.
     */
    public function __construct(
        private readonly array $discoveryPaths,
        private readonly array $extraFlags,
        private readonly int $timeout,
        private readonly string $tempDir,
        ?string $binaryOverride = null,
        private readonly bool $allowLocalFileAccess = false,
    ) {
        $this->binary = $binaryOverride !== null && $binaryOverride !== ''
            ? $binaryOverride
            : $this->discoverBinary();
    }

    /**
     * {@inheritDoc}
     */
    public function render(string $html, array $options = []): string
    {
        $inputPath  = $this->writeTemp($html, 'html');
        $outputPath = $this->tempPath('pdf');

        try {
            $command = $this->buildCommand($inputPath, $outputPath, $options);
            $process = new Process($command, timeout: $this->timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                throw PdfGenerationFailedException::fromProcessError(
                    $process->getErrorOutput(),
                    $process->getExitCode() ?? 1,
                );
            }

            if (! is_file($outputPath) || filesize($outputPath) === 0) {
                throw PdfGenerationFailedException::fromProcessError(
                    'Output PDF was not written or is empty.',
                    1,
                );
            }

            return (string) file_get_contents($outputPath);
        } catch (ProcessTimedOutException) {
            throw PdfGenerationFailedException::timeout($this->timeout);
        } finally {
            $this->cleanUp($inputPath, $outputPath);
        }
    }

    public function getBinary(): string
    {
        return $this->binary;
    }

    public function isLocalFileAccessAllowed(): bool
    {
        return $this->allowLocalFileAccess;
    }

    /**
     * @param  array<string, mixed> $options
     * @return array<int, string>
     */
    private function buildCommand(string $inputPath, string $outputPath, array $options): array
    {
        /** @var array{float, float} $dims */
        $dims   = isset($options['dimensions']) && is_array($options['dimensions'])
            ? $options['dimensions']
            : [8.27, 11.69];
        $width  = (float) $dims[0];
        $height = (float) $dims[1];

        if (! empty($options['landscape'])) {
            [$width, $height] = [$height, $width];
        }

        $command = [
            $this->binary,
            '--headless=new',
            '--disable-gpu',
            '--run-all-compositor-stages-before-draw',
            '--print-to-pdf-no-header',
            '--no-pdf-header-footer',
            "--paper-width={$width}",
            "--paper-height={$height}",
        ];

        // Security hardening: prevent the rendered page from reading arbitrary
        // local filesystem paths (e.g. <iframe src="file:///etc/passwd">).
        // These flags are emitted unconditionally unless the caller has
        // explicitly opted in via allowLocalFileAccess = true.
        if (! $this->allowLocalFileAccess) {
            $command[] = '--disable-local-file-access';
            $command[] = '--allow-file-access-from-files=false';
            $command[] = '--disable-web-security=false';
        }

        if (isset($options['margins']) && is_array($options['margins'])) {
            /** @var array{top: float, right: float, bottom: float, left: float} $m */
            $m = $options['margins'];
            $command[] = '--margin-top=' . (float) $m['top'];
            $command[] = '--margin-right=' . (float) $m['right'];
            $command[] = '--margin-bottom=' . (float) $m['bottom'];
            $command[] = '--margin-left=' . (float) $m['left'];
        }

        $rawMedia = $options['emulate_media'] ?? '';
        $media    = is_string($rawMedia) ? $rawMedia : '';
        if ($media !== '') {
            $command[] = "--emulate-media-type={$media}";
        }

        foreach ($this->extraFlags as $flag) {
            $command[] = $flag;
        }

        $command[] = "--print-to-pdf={$outputPath}";
        $command[] = 'file://' . $inputPath;

        return $command;
    }

    private function discoverBinary(): string
    {
        foreach ($this->discoveryPaths as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        throw BinaryNotFoundException::forPaths($this->discoveryPaths);
    }

    private function writeTemp(string $content, string $ext): string
    {
        $path = $this->tempPath($ext);
        file_put_contents($path, $content);

        return $path;
    }

    private function tempPath(string $ext): string
    {
        return rtrim($this->tempDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'fast_pdf_' . bin2hex(random_bytes(8)) . '.' . $ext;
    }

    private function cleanUp(string ...$paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
