<?php

declare(strict_types=1);

namespace ZentiqLabs\FastPdf\Services;

final class TailwindCompiler
{
    public function __construct(private readonly string $cdnUrl) {}

    /**
     * Inject the Tailwind standalone CDN script into the document HEAD.
     *
     * Handles three shapes of input:
     *   1. Full HTML document with </head> — injects before the closing tag.
     *   2. HTML with <body> but no <head> — wraps a <head> block before <body>.
     *   3. Bare fragment — prepends the script tag directly.
     */
    public function inject(string $html): string
    {
        $tag = $this->buildScriptTag();

        if (stripos($html, '</head>') !== false) {
            return (string) preg_replace('/<\/head>/i', $tag . '</head>', $html, 1);
        }

        if (stripos($html, '<body') !== false) {
            return (string) preg_replace(
                '/(<body[^>]*>)/i',
                "<head>{$tag}</head>\n$1",
                $html,
                1,
            );
        }

        return $tag . $html;
    }

    private function buildScriptTag(): string
    {
        $url = htmlspecialchars($this->cdnUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return "<script src=\"{$url}\"></script>\n";
    }
}
