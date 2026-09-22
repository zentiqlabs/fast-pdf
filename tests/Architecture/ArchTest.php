<?php

declare(strict_types=1);

use ZentiqLabs\FastPdf\Contracts\PdfEngineInterface;
use ZentiqLabs\FastPdf\FastPdf;
use ZentiqLabs\FastPdf\PdfBuilder;

arch('strict types declared on every source file')
    ->expect('ZentiqLabs\FastPdf')
    ->toUseStrictTypes();

arch('no debugging helpers exist in source files')
    ->expect('ZentiqLabs\FastPdf')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'var_export']);

arch('exceptions extend RuntimeException')
    ->expect('ZentiqLabs\FastPdf\Exceptions\BinaryNotFoundException')
    ->toExtend(\RuntimeException::class);

arch('PdfGenerationFailedException extends RuntimeException')
    ->expect('ZentiqLabs\FastPdf\Exceptions\PdfGenerationFailedException')
    ->toExtend(\RuntimeException::class);

arch('TemplateNotFoundException extends InvalidArgumentException')
    ->expect('ZentiqLabs\FastPdf\Exceptions\TemplateNotFoundException')
    ->toExtend(\InvalidArgumentException::class);

arch('enums are string-backed')
    ->expect('ZentiqLabs\FastPdf\Enums')
    ->toBeStringBackedEnums();

arch('ProcessOrchestrator implements PdfEngineInterface')
    ->expect('ZentiqLabs\FastPdf\Services\ProcessOrchestrator')
    ->toImplement(PdfEngineInterface::class);

arch('PdfBuilder is final')
    ->expect(PdfBuilder::class)
    ->toBeFinal();


arch('no illuminate dependencies in the core source')
    ->expect('ZentiqLabs\FastPdf')
    ->not->toUse('Illuminate\\');
