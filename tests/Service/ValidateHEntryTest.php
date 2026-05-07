<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Microformats;
use App\Service\ValidateHEntry;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Slim\App;

final class ValidateHEntryTest extends TestCase
{
    protected App $app;

    /**
    * Runs before every test.
    *
    * Sets $app to a fresh instance of the application.
    */
    protected function setUp(): void
    {
        $this->app = require dirname(__DIR__, 2) . '/config/bootstrap.php';
    }

    public function testValidates(): void
    {
        $microformats = $this->getMockBuilder(Microformats::class)
            ->onlyMethods(['findHEntries'])
            ->getMock();
        $validator = new ValidateHEntry($microformats);

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

        $microformats->expects(self::once())
            ->method('findHEntries')
            ->with($url)
            ->willReturn($mf2);

        $result = $validator->validate($url);

        self::assertCount(1, $result['entries']);
        self::assertSame($post, $result['properties']['url']);
        self::assertSame($author, $result['properties']['author']['name']);
        self::assertSame($photo, $result['properties']['author']['photo']);
        self::assertSame($content, $result['properties']['content']);
        self::assertSame('post', $result['postType']);
    }

    public function testCalculatesPostType(): void
    {
        $microformats = $this->getMockBuilder(Microformats::class)
            ->onlyMethods(['findHEntries'])
            ->getMock();
        $validator = new ValidateHEntry($microformats);

        $url = 'https://example.com/';
        $post = "{$url}post";
        $liked = 'https://indieweb.org/principles';
        $content = '<a href="' . $liked . '" class="u-like-of">I like this page</a>';

        $mf2 = [
            [
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
            ],
        ];

        $microformats->expects(self::once())
            ->method('findHEntries')
            ->with($url)
            ->willReturn($mf2);

        $result = $validator->validate($url);

        self::assertSame('like', $result['postType']);
    }

}
