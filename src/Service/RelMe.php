<?php

declare(strict_types=1);

namespace App\Service;

use IndieWeb;

class RelMe
{
    public function documentUrl(string $url): array
    {
        return IndieWeb\relMeDocumentUrl($url);
    }

    public function links(string $html, string $url): array
    {
        return IndieWeb\relMeLinks($html, $url);
    }

    public function backlinkMatches(string $url, string $meUrl): array
    {
        return IndieWeb\backlinkingRelMeUrlMatches($url, $meUrl);
    }
}
