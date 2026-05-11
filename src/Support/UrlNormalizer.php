<?php

declare(strict_types=1);

namespace App\Support;

use IndieWeb;

final class UrlNormalizer
{
    public static function normalize(string $url): string
    {
        $url = trim($url);

        $hasScheme = preg_match('#^https?://#i', $url) === 1;

        if (!$hasScheme) {
            $url = 'http://' . $url; // TODO: https?
        }

        return IndieWeb\normaliseUrl($url);
    }
}
