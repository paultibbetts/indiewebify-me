<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\ValidateHEntry;
use App\Tests\WebTestCase;

final class HEntryTest extends WebTestCase
{
    public function testHEntryPageLoadsWithoutQuery(): void
    {
        $response = $this->get('/validate-h-entry');
        $body = (string) $response->getBody();
        $xpath = self::xpathFor($body);
        $query = '//form[contains(@action, "/validate-h-entry")]//input[@name="url"]';

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $xpath->query($query)->length);
    }

    public function testHEntryPageRequiresAUrl(): void
    {
        $response = $this->get('/validate-h-entry?' . http_build_query([
            'url' => '',
        ]));

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Empty URLs lead nowhere!', $payload);
    }

    public function testHEntryPageAcceptsBareDomain(): void
    {
        $url = 'example.com/post';
        $expected = "http://{$url}";

        $response = $this->get('/validate-h-entry?' . http_build_query([
            'url' => $url,
        ]));

        self::assertSame(302, $response->getStatusCode());

        $location = $response->getHeaderLine('Location');
        $parts = parse_url($location);
        parse_str($parts['query'] ?? '', $query);

        self::assertSame('/validate-h-entry', $parts['path']);
        self::assertSame($expected, $query['url'] ?? null);
    }

    public function testHEntryPageRendersFoundHEntryProperties(): void
    {
        $url = 'https://example.com/';
        $post = "{$url}post";
        $content = 'This is the post content.';
        $author = 'Example Person';
        $photo = "{$url}photo.jpg";

        $result = [
            'entries' => [
                [
                    'type' => ['h-entry'],
                    'properties' => [
                        'these are not rendered' => true,
                    ],
                ],
            ],
            'postType' => 'post',
            'properties' => [
                'name' => 'Example post',
                'name_state' => 'valid',
                'author' => [
                    'is_h_card' => true,
                    'name' => $author,
                    'photo' => $photo,
                    'url' => $url,
                ],
                'content' => $content,
                'is_content_html' => true,
                'published' => '2026-04-30T12:00:00+00:00',
                'is_published_valid' => true,
                'url' => $post,
                'categories' => ['indieweb'],
                'syndications' => [],
            ],
        ];

        $validator = $this->createMock(ValidateHEntry::class);

        $validator->expects(self::once())
            ->method('validate')
            ->with($url)
            ->willReturn($result);

        $this->container()->set(ValidateHEntry::class, $validator);

        $response = $this->get('/validate-h-entry?' . http_build_query([
            'url' => $url,
        ]));

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString($content, $payload);
        self::assertStringContainsString($author, $payload);
        self::assertStringContainsString($photo, $payload);
        self::assertStringNotContainsString('these are not rendered', $payload);
    }

    public function testHEntryPageShowsNoHEntryError(): void
    {
        $url = 'https://example.com/';

        $result = [
            'entries' => [],
        ];

        $validator = $this->createMock(ValidateHEntry::class);

        $validator->expects(self::once())
            ->method('validate')
            ->with($url)
            ->willReturn($result);

        $this->container()->set(ValidateHEntry::class, $validator);

        $response = $this->get('/validate-h-entry?' . http_build_query([
            'url' => $url,
        ]));

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No h-entry was found on that page', $payload);
    }
}
