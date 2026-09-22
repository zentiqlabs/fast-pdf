#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Manual smoke-test for zentiq-labs/fast-pdf.
 *
 * Usage:
 *   php bin/test-render.php
 *   php bin/test-render.php /usr/bin/chromium-browser
 *
 * Output:
 *   output.pdf written to the current working directory.
 */

$root = dirname(__DIR__);

if (! is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "Run `composer install` before executing this script.\n");
    exit(1);
}

require $root . '/vendor/autoload.php';

use ZentiqLabs\FastPdf\FastPdf;

$binaryOverride = $argv[1] ?? null;

$config = array_merge(
    require $root . '/config/fast-pdf.php',
    $binaryOverride !== null ? ['binary' => $binaryOverride] : [],
);

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fast PDF — Smoke Test</title>
</head>
<body>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-10">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full p-10">

            <div class="flex items-center justify-between border-b border-gray-200 pb-6 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">INVOICE</h1>
                    <p class="text-sm text-gray-500 mt-1">zentiq-labs/fast-pdf — smoke test render</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Invoice No.</p>
                    <p class="text-lg font-semibold text-indigo-600">#INV-2026-001</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-8 mb-8">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-2">Bill To</p>
                    <p class="font-semibold text-gray-800">Acme Corporation</p>
                    <p class="text-sm text-gray-600">123 Business Avenue</p>
                    <p class="text-sm text-gray-600">San Francisco, CA 94105</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-2">Issued By</p>
                    <p class="font-semibold text-gray-800">Zentiq Labs</p>
                    <p class="text-sm text-gray-600">usman@zentiq.com</p>
                    <p class="text-sm text-gray-600">zentiq.com</p>
                </div>
            </div>

            <table class="w-full text-sm mb-8">
                <thead>
                    <tr class="bg-gray-100 text-gray-500 uppercase text-xs tracking-wider">
                        <th class="text-left py-3 px-4 rounded-l">Description</th>
                        <th class="text-center py-3 px-4">Qty</th>
                        <th class="text-right py-3 px-4 rounded-r">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr>
                        <td class="py-3 px-4 text-gray-800">Fast PDF — Core License</td>
                        <td class="py-3 px-4 text-center text-gray-600">1</td>
                        <td class="py-3 px-4 text-right font-medium text-gray-800">$299.00</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 text-gray-800">Annual Support &amp; Updates</td>
                        <td class="py-3 px-4 text-center text-gray-600">1</td>
                        <td class="py-3 px-4 text-right font-medium text-gray-800">$99.00</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-900">
                        <td colspan="2" class="py-3 px-4 text-right font-bold text-gray-900">Total</td>
                        <td class="py-3 px-4 text-right font-bold text-gray-900 text-base">$398.00</td>
                    </tr>
                </tfoot>
            </table>

            <p class="text-xs text-gray-400 text-center">
                Rendered by <strong>zentiq-labs/fast-pdf</strong> — direct Chromium IPC pipe. No Node.js. No Puppeteer.
            </p>
        </div>
    </div>
</body>
</html>
HTML;

$outputPath = getcwd() . DIRECTORY_SEPARATOR . 'output.pdf';

echo "Building PDF...\n";

try {
    (new FastPdf($config))
        ->fromHtml($html)
        ->withTailwind()
        ->paperSize('a4')
        ->portrait()
        ->margins(0, 0, 0, 0)
        ->save($outputPath);

    echo "Success! PDF written to: {$outputPath}\n";
    echo 'Size: ' . number_format(filesize($outputPath) / 1024, 1) . " KB\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    fwrite(STDERR, "Hint: pass the Chromium binary path as the first argument:\n");
    fwrite(STDERR, "  php bin/test-render.php /usr/bin/chromium-browser\n");
    exit(1);
}
