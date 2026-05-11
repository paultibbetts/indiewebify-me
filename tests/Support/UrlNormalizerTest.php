<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Support\UrlNormalizer;
use PHPUnit\Framework\TestCase;

final class UrlNormalizerTest extends TestCase
{
    public function testNormalizesUrl(): void
    {
        $bare = 'example.com';
        $normalized = UrlNormalizer::normalize($bare);

        self::assertSame('http://example.com/', $normalized);
    }
}
