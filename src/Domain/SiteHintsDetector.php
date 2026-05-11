<?php

declare(strict_types=1);

namespace App\Domain;

use DOMElement;
use Mf2\Parser as MfParser;

final class SiteHintsDetector
{
    /*
    * @return array<silo: string, software: string>
    */
    public function hintsFor(string $url, string $html): array
    {
        return [
            'silo' => $this->siloForUrl($url),
            'software' => $this->softwareForPage($url, $html),
        ];
    }

    private function siloForUrl(string $url): ?string
    {
        if ($url === '' || $url === '0') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host)) {
            return null;
        }

        $host = strtolower($host);

        return match (true) {
            str_contains($host, '.wordpress.com') => 'wordpress.com',
            str_contains($host, '.github.') => 'github',
            str_contains($host, '.tumblr.com') => 'tumblr',
            default => null
        };
    }

    private function softwareForPage(string $url, string $html): ?string
    {
        if ($html === '' || $html === '0') {
            return null;
        }

        $generators = [
            'astro',
            'eleventy',
            'ghost',
            'hugo',
            'idno',
            'known',
            'mediawiki',
            'wordpress',
        ];

        $d = new MfParser($html, $url);
        foreach ($d->query('//meta[@name="generator"]') as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }
            $meta = strtolower($element->getAttribute('content'));
            foreach ($generators as $generator) {
                if (str_starts_with($meta, $generator)) {
                    return $generator;
                }
            }
        }

        return null;
    }
}
