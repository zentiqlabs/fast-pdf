# Fast PDF

> High-performance, sub-second, pixel-perfect HTML-to-PDF rendering for PHP applications powered by direct headless Chromium IPC pipes — **zero Node.js, NPM, or Puppeteer required. Zero framework lock-in.**

[![Tests](https://github.com/zentiqlabs/fast-pdf/actions/workflows/tests.yml/badge.svg)](https://github.com/zentiqlabs/fast-pdf/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/zentiq-labs/fast-pdf.svg?style=flat-square)](https://packagist.org/packages/zentiq-labs/fast-pdf)
[![Total Downloads](https://img.shields.io/packagist/dt/zentiq-labs/fast-pdf.svg?style=flat-square)](https://packagist.org/packages/zentiq-labs/fast-pdf)
[![License](https://img.shields.io/github/license/zentiqlabs/fast-pdf?style=flat-square&color=blue)](https://github.com/zentiqlabs/fast-pdf/blob/main/LICENSE)

---

## Table of Contents

- [Why Fast PDF?](#why-fast-pdf)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Fluent Builder API](#fluent-builder-api)
- [PHP Template Files](#php-template-files)
- [Tailwind CSS Support](#tailwind-css-support)
- [Output Methods](#output-methods)
- [Paper Sizes & Orientation](#paper-sizes--orientation)
- [Framework Integration](#framework-integration)
  - [Core PHP](#core-php)
  - [CodeIgniter 4](#codeigniter-4)
  - [Symfony](#symfony)
  - [Laravel](#laravel)
  - [WordPress](#wordpress)
- [Docker & Alpine Linux](#docker--alpine-linux)
- [Manual Test Script](#manual-test-script)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## Why Fast PDF?

Most PHP PDF solutions fall into one of two traps: they ship a bundled renderer with incomplete CSS support, or they delegate to a Node.js + Puppeteer stack — adding hundreds of megabytes and an IPC overhead tax to every request.

**Fast PDF** takes a third path. It speaks directly to the system's headless Chromium binary through native OS process pipes. Your HTML is rendered by the full Blink engine and the binary PDF stream is returned — no Node.js runtime, no browser automation framework, no intermediate file conversion.

| Approach | CSS Fidelity | Node.js | Framework Required |
|---|---|---|---|
| DOMPDF / mPDF | Partial (CSS 2.1) | No | No |
| Puppeteer / Playwright | Full | **Yes** | No |
| wkhtmltopdf | Partial (WebKit 2014) | No | No |
| **Fast PDF** | **Full (Blink engine)** | **No** | **No** |

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.3` |
| Chromium / Google Chrome | Any headless-capable build |

> Chromium must be installed on the host. The package probes common system paths automatically and raises `BinaryNotFoundException` with actionable instructions if it cannot locate a binary.
>
> - **Alpine Linux**: `apk add chromium`
> - **Debian / Ubuntu**: `apt-get install chromium-browser`
> - **macOS (Homebrew)**: `brew install --cask chromium`

---

## Installation

```bash
composer require zentiq-labs/fast-pdf
```

---

## Quick Start

```php
use ZentiqLabs\FastPdf\FastPdf;

$pdf = new FastPdf();

// From a raw HTML string
$pdf->fromHtml('<html><body><h1>Hello World</h1></body></html>')
    ->save('/var/www/output.pdf');

// From a PHP template file
$pdf->fromFile(__DIR__ . '/templates/invoice.php', ['invoice' => $invoice])
    ->landscape()
    ->download('invoice.pdf');   // streams + exits
```

---

## Configuration

Pass any subset of the configuration array to the constructor. Unset keys fall back to environment variables, then to built-in defaults.

```php
$pdf = new FastPdf([
    'binary'       => '/usr/bin/chromium-browser',
    'timeout'      => 15,
    'paper_size'   => 'a4',
    'orientation'  => 'portrait',
    'emulate_media' => 'print',
    'margins'      => ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15, 'unit' => 'mm'],
    'chromium_flags' => [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
    ],
    'tailwind_cdn_url' => 'https://cdn.tailwindcss.com',
    'temp_dir'     => '/tmp',
]);
```

Alternatively, publish and require the bundled config file:

```php
$pdf = new FastPdf(require __DIR__ . '/config/fast-pdf.php');
```

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `FAST_PDF_BINARY` | *(auto-discovered)* | Absolute path to the Chromium binary |
| `FAST_PDF_TIMEOUT` | `30` | Max seconds before `PdfGenerationFailedException` |
| `FAST_PDF_PAPER_SIZE` | `a4` | Default paper size |
| `FAST_PDF_ORIENTATION` | `portrait` | Default orientation |
| `FAST_PDF_MEDIA` | `print` | CSS media type to emulate |
| `FAST_PDF_TAILWIND_CDN` | `https://cdn.tailwindcss.com` | Tailwind standalone CDN URL |
| `FAST_PDF_TEMP_DIR` | `sys_get_temp_dir()` | Scratch directory |

---

## Fluent Builder API

All methods return `$this` and can be chained in any order.

| Method | Description |
|---|---|
| `fromHtml(string $html)` | Set a raw HTML string as the source |
| `fromFile(string $path, array $data = [])` | Render a PHP template file as the source |
| `paperSize(PaperSize\|string $size)` | Set paper size (see enum values) |
| `landscape()` | Landscape orientation |
| `portrait()` | Portrait orientation |
| `margins(float $t, float $r, float $b, float $l, string $unit = 'mm')` | Override margins. Unit: `mm`, `cm`, `in`, `px` |
| `withTailwind()` | Inject standalone Tailwind CDN before rendering |
| `emulateMedia(string $media = 'print')` | CSS media type: `'print'` or `'screen'` |
| `output(): string` | Render and return raw binary PDF bytes |
| `save(string $path)` | Render and write to a file path |
| `download(string $filename = 'document.pdf')` | Emit download headers, stream, and `exit` |
| `stream(string $filename = 'document.pdf')` | Emit inline headers, stream, and `exit` |

---

## PHP Template Files

`fromFile()` works exactly like a simple view engine: variables in `$data` are extracted into the template scope, and the output buffer contents become the HTML source.

```php
// templates/invoice.php
?>
<!DOCTYPE html>
<html>
<body>
    <h1><?= htmlspecialchars($invoice['number']) ?></h1>
    <p>Total: $<?= number_format($invoice['total'], 2) ?></p>
</body>
</html>
```

```php
$pdf->fromFile(__DIR__ . '/templates/invoice.php', [
    'invoice' => ['number' => 'INV-001', 'total' => 499.00],
])->save('/var/invoices/INV-001.pdf');
```

---

## Tailwind CSS Support

Chain `->withTailwind()` to inject the standalone Tailwind CDN script into the `<head>` before Chromium renders the page. No build pipeline required.

```php
$pdf->fromHtml($html)
    ->withTailwind()
    ->paperSize('a4')
    ->save('/tmp/report.pdf');
```

> For offline environments or strict CSP policies, override `tailwind_cdn_url` to a self-hosted build of the Tailwind standalone CLI output.

---

## Output Methods

### Raw binary string

```php
$bytes = $pdf->fromHtml($html)->output();
file_put_contents('/var/cache/report.pdf', $bytes);
```

### Save to disk

```php
$pdf->fromFile('/templates/receipt.php', $data)->save('/receipts/001.pdf');
```

### Browser download

```php
// Sends Content-Disposition: attachment and calls exit()
$pdf->fromFile('/templates/receipt.php', $data)->download('receipt-001.pdf');
```

### Inline browser render

```php
// Sends Content-Disposition: inline and calls exit()
$pdf->fromFile('/templates/receipt.php', $data)->stream('receipt-001.pdf');
```

---

## Paper Sizes & Orientation

Available `PaperSize` enum cases (accepts the lowercase string or the enum value):

`A0` `A1` `A2` `A3` `A4` `A5` `A6` `Letter` `Legal` `Tabloid` `Ledger`

```php
use ZentiqLabs\FastPdf\Enums\PaperSize;

$pdf->fromHtml($html)->paperSize(PaperSize::Letter)->output();
$pdf->fromHtml($html)->paperSize('legal')->landscape()->output();
```

---

## Framework Integration

### Core PHP

```php
require 'vendor/autoload.php';

use ZentiqLabs\FastPdf\FastPdf;

$pdf = new FastPdf(['binary' => '/usr/bin/chromium']);
$pdf->fromHtml('<h1>Core PHP</h1>')->save('/tmp/output.pdf');
```

### CodeIgniter 4

Register a singleton in `app/Config/Services.php`:

```php
use ZentiqLabs\FastPdf\FastPdf;

public static function fastPdf(bool $getShared = true): FastPdf
{
    if ($getShared) {
        return static::getSharedInstance('fastPdf');
    }
    return new FastPdf(['binary' => env('FAST_PDF_BINARY')]);
}
```

Use it in a controller:

```php
service('fastPdf')
    ->fromFile(APPPATH . 'Views/pdf/invoice.php', compact('invoice'))
    ->download('invoice.pdf');
```

### Symfony

Register as a service in `config/services.yaml`:

```yaml
ZentiqLabs\FastPdf\FastPdf:
    arguments:
        $config:
            binary: '%env(FAST_PDF_BINARY)%'
            timeout: 30
```

Inject into a controller or service via constructor injection:

```php
public function __construct(private readonly FastPdf $pdf) {}

public function export(): Response
{
    $bytes = $this->pdf->fromHtml($html)->output();
    return new Response($bytes, 200, ['Content-Type' => 'application/pdf']);
}
```

### Laravel

The package is framework-agnostic but integrates cleanly with Laravel via manual binding in a service provider:

```php
// app/Providers/AppServiceProvider.php
use ZentiqLabs\FastPdf\FastPdf;

$this->app->singleton(FastPdf::class, fn () => new FastPdf([
    'binary' => config('services.chromium.binary'),
]));
```

Then inject `FastPdf` anywhere the service container resolves it.

### WordPress

```php
// In a plugin bootstrap file
require_once WP_CONTENT_DIR . '/vendor/autoload.php';

use ZentiqLabs\FastPdf\FastPdf;

function zentiq_pdf(): FastPdf
{
    static $instance;
    return $instance ??= new FastPdf(['binary' => defined('CHROMIUM_BIN') ? CHROMIUM_BIN : null]);
}
```

---

## Docker & Alpine Linux

A ready-to-use test image is included in `docker/Dockerfile.test`. It runs the full Pest suite against a real Chromium binary inside an Alpine container.

```bash
docker build -f docker/Dockerfile.test -t fast-pdf-test .
docker run --rm fast-pdf-test
```

Minimal additions for your own application Dockerfile:

```dockerfile
FROM php:8.3-fpm-alpine
RUN apk add --no-cache chromium
ENV FAST_PDF_BINARY=/usr/bin/chromium-browser
```

Always include `--no-sandbox` and `--disable-setuid-sandbox` in `chromium_flags` (the package defaults already include them) when running inside unprivileged Docker containers.

---

## Manual Test Script

A standalone smoke-test script is included at `bin/test-render.php`. It generates a Tailwind-styled invoice PDF to `output.pdf` in the current working directory.

```bash
# Auto-discover Chromium
php bin/test-render.php

# Explicit binary path
php bin/test-render.php /usr/bin/chromium-browser
```

---

## Testing

```bash
composer test
```

With coverage:

```bash
composer test:coverage
```

Static analysis (PHPStan level 9):

```bash
composer check
```

Code style (PSR-12):

```bash
composer cs
```

---

## Contributing

Contributions, issues, and feature requests are welcome. Please ensure any pull request:

1. Targets the `develop` branch.
2. Ships with corresponding test coverage for all new behaviour.
3. Passes the full CI pipeline (`test`, `check`, `cs`) locally before opening a PR.
4. Follows [Conventional Commits](https://www.conventionalcommits.org/).

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

---

*Developed and maintained by [Usman Khan](https://github.com/usman-khan) at [Zentiq Labs](https://zentiq.com).*
