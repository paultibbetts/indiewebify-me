<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Domain\SiteHintsDetector;
use PHPUnit\Framework\TestCase;

final class SiteHintsDetectorTest extends TestCase
{
    public function testSiteHintsForSilo(): void
    {
        $sh = new SiteHintsDetector();

        $hints = $sh->hintsFor('https://example.github.io', '');

        self::assertSame('github', $hints['silo']);
    }

    public function testSiteHintsForGenerator(): void
    {
        $sh = new SiteHintsDetector();

        $hints = $sh->hintsFor('https://example.com', '<html><head><meta name=generator content="Hugo 0.161.1"></head></html>');

        self::assertSame('hugo', $hints['software']);
    }
}
