<?php

declare(strict_types=1);

/**
 * Default configuration for zentiq-labs/fast-pdf.
 *
 * Copy or require this file in your own bootstrap and pass the array
 * to the FastPdf constructor, or override individual keys as needed.
 *
 *   $pdf = new \ZentiqLabs\FastPdf\FastPdf(require __DIR__ . '/fast-pdf.php');
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Chromium Binary Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the headless Chromium (or Google Chrome) binary.
    | When null the package probes the paths listed in binary_discovery_paths.
    |
    */
    'binary' => getenv('FAST_PDF_BINARY') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Binary Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Ordered list of candidate binary locations. The first readable,
    | executable path wins.
    |
    */
    'binary_discovery_paths' => [
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/snap/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
    ],

    /*
    |--------------------------------------------------------------------------
    | Process Timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) (getenv('FAST_PDF_TIMEOUT') ?: 30),

    /*
    |--------------------------------------------------------------------------
    | Default Paper Size
    |--------------------------------------------------------------------------
    |
    | Accepts any value from the PaperSize enum (e.g. 'a4', 'letter').
    |
    */
    'paper_size' => getenv('FAST_PDF_PAPER_SIZE') ?: 'a4',

    /*
    |--------------------------------------------------------------------------
    | Default Orientation
    |--------------------------------------------------------------------------
    |
    | 'portrait' or 'landscape'.
    |
    */
    'orientation' => getenv('FAST_PDF_ORIENTATION') ?: 'portrait',

    /*
    |--------------------------------------------------------------------------
    | Default Margins (mm)
    |--------------------------------------------------------------------------
    */
    'margins' => [
        'top'    => 10,
        'right'  => 10,
        'bottom' => 10,
        'left'   => 10,
        'unit'   => 'mm',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSS Media Emulation
    |--------------------------------------------------------------------------
    */
    'emulate_media' => getenv('FAST_PDF_MEDIA') ?: 'print',

    /*
    |--------------------------------------------------------------------------
    | Containerized Environment
    |--------------------------------------------------------------------------
    |
    | Set to true when Chromium runs inside Docker or another unprivileged
    | Linux container. This adds --no-sandbox and --disable-setuid-sandbox
    | to the Chromium invocation — flags that are REQUIRED in containers but
    | must NOT be used on bare-metal or VM hosts, where the OS sandbox
    | provides real isolation.
    |
    */
    'containerized' => (bool) (getenv('FAST_PDF_CONTAINERIZED') ?: false),

    /*
    |--------------------------------------------------------------------------
    | Allow Local File Access
    |--------------------------------------------------------------------------
    |
    | When false (default) Chromium is launched with --disable-local-file-access
    | to prevent a malicious HTML payload from reading arbitrary files off the
    | server's filesystem via constructs such as:
    |
    |   <iframe src="file:///etc/passwd">
    |
    | Set to true ONLY when you are rendering trusted, internally-generated HTML
    | that intentionally loads local disk assets (e.g. embedded fonts or images
    | referenced with file:// URIs). Never enable this when rendering any
    | user-supplied or externally sourced HTML.
    |
    */
    'allow_local_file_access' => (bool) (getenv('FAST_PDF_ALLOW_LOCAL_FILE_ACCESS') ?: false),

    /*
    |--------------------------------------------------------------------------
    | Additional Chromium Flags
    |--------------------------------------------------------------------------
    |
    | Extra CLI flags forwarded verbatim to every Chromium invocation.
    | --disable-dev-shm-usage is safe on all platforms. Sandbox flags are
    | managed by the 'containerized' key above — do not add them here manually.
    |
    */
    'chromium_flags' => [
        '--disable-dev-shm-usage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tailwind CDN URL
    |--------------------------------------------------------------------------
    */
    'tailwind_cdn_url' => getenv('FAST_PDF_TAILWIND_CDN') ?: 'https://cdn.tailwindcss.com',

    /*
    |--------------------------------------------------------------------------
    | Temporary Directory
    |--------------------------------------------------------------------------
    */
    'temp_dir' => getenv('FAST_PDF_TEMP_DIR') ?: sys_get_temp_dir(),

];
