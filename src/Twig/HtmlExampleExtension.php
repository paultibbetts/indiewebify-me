<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class HtmlExampleExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('html_example', [$this, 'highlight'], ['is_safe' => ['html']]),
        ];
    }

    public function highlight(string $html): string
    {
        $escaped = htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $escaped = preg_replace(
            '/(&lt;\/?)([a-z][a-z0-9:-]*)/i',
            '$1<span class="token-tag">$2</span>',
            $escaped
        );

        $escaped = preg_replace(
            '/\s([a-z:-]+)=(&quot;.*?&quot;)/i',
            ' <span class="token-attr">$1</span>=<span class="token-string">$2</span>',
            $escaped
        );

        return $escaped ?? htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
