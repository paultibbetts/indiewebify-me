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

        $report = [
            'url' => $url,
            'found' => true,
            'postType' => 'article',
            'checks' => [
                [
                    'id' => 'author',
                    'label' => 'Author',
                    'status' => 'pass',
                    'state' => 'author.h-card.complete',
                    'value' => [
                        'type' => 'author-card',
                        'name' => $author,
                        'photo' => $photo,
                        'url' => $url,
                    ],
                    'help' => null,
                    'children' => [],
                ],
                [
                    'id' => 'content',
                    'label' => 'Content',
                    'status' => 'pass',
                    'state' => 'content.html',
                    'value' => [
                        'type' => 'html-content',
                        'text' => $content,
                    ],
                    'help' => null,
                    'children' => [],
                ],
                [
                    'id' => 'url',
                    'label' => 'URL',
                    'status' => 'pass',
                    'state' => 'url.valid',
                    'value' => [
                        'type' => 'url',
                        'url' => $post,
                    ],
                    'help' => null,
                    'children' => [],
                ],
            ],
        ];

        $validator = $this->createMock(ValidateHEntry::class);

        $validator->expects(self::once())
            ->method('validate')
            ->with($url)
            ->willReturn($report);

        $this->container()->set(ValidateHEntry::class, $validator);

        $response = $this->get('/validate-h-entry?' . http_build_query([
            'url' => $url,
        ]));

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString($content, $payload);
        self::assertStringContainsString($author, $payload);
        self::assertStringContainsString($photo, $payload);
        self::assertStringContainsString('article', $payload);
        self::assertStringContainsString($post, $payload);
    }

    public function testHEntryPageShowsNoHEntryError(): void
    {
        $url = 'https://example.com/';

        $report = [
            'url' => $url,
            'found' => false,
            'postType' => null,
            'checks' => [],
        ];

        $validator = $this->createMock(ValidateHEntry::class);

        $validator->expects(self::once())
            ->method('validate')
            ->with($url)
            ->willReturn($report);

        $this->container()->set(ValidateHEntry::class, $validator);

        $response = $this->get('/validate-h-entry?' . http_build_query([
            'url' => $url,
        ]));

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No h-entry was found on https://example.com/', $payload);
    }
}
