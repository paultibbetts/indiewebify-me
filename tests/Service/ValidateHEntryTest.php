<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ValidateHEntry;
use PHPUnit\Framework\TestCase;

final class ValidateHEntryTest extends TestCase
{
    public function testValidates(): void
    {
        $validator = new ValidateHEntry();

        $url = 'https://example.com/';
        $post = "{$url}post";
        $author = 'Example Person';
        $photo = "{$url}photo.jpg";
        $content = 'This is the content of the post';

        $mf2 = [
            [
                'type' => ['h-entry'],
                'properties' => [
                    'name' => ['Example post'],
                    'author' => [
                        [
                            'type' => ['h-card'],
                            'properties' => [
                                'name' => [$author],
                                'photo' => [$photo],
                                'url' => [$url],
                            ],
                        ],
                    ],
                    'content' => [
                        [
                            'html' => "<p>{$content}</p>",
                            'value' => $content,
                        ],
                    ],
                    'published' => ['2026-04-30T12:00:00+00:00'],
                    'url' => [$post],
                    'category' => ['indieweb'],
                ],
            ],
        ];
        $html = '<article class="h-entry">not used in this test</article>';

        $report = $validator->validate($url, $mf2, $html);

        self::assertTrue($report['found']);
        self::assertSame($url, $report['url']);
        self::assertSame('name', $report['checks'][0]['id']);
        self::assertSame('author', $report['checks'][1]['id']);
        self::assertSame('content', $report['checks'][2]['id']);
    }
}
