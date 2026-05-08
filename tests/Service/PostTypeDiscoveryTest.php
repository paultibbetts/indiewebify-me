<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Domain\PostType;
use App\Service\PostTypeDiscovery;
use PHPUnit\Framework\TestCase;

final class PostTypeDiscoveryTest extends TestCase
{
    public function testCalculatesPostType(): void
    {
        $ptd = new PostTypeDiscovery();

        $url = 'https://example.com/';
        $post = "{$url}post";
        $liked = 'https://indieweb.org/principles';
        $content = '<a href="' . $liked . '" class="u-like-of">I like this page</a>';

        $entry = [
            'type' => ['h-entry'],
            'properties' => [
                'name' => ['Example post'],
                'content' => [
                    [
                        'html' => "<p>{$content}</p>",
                        'value' => $content,
                    ],
                ],
                'like-of' => [$liked],
                'published' => ['2026-04-30T12:00:00+00:00'],
                'url' => [$post],
                'category' => ['indieweb'],
            ],
        ];

        $result = $ptd->discover($entry);

        self::assertSame(PostType::Like, $result);
    }

}
