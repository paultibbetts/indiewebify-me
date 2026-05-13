<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Http\Client;
use App\Tests\WebTestCase;

final class HEntryTest extends WebTestCase
{
    public function testHEntryPageLoadsWithoutQuery(): void
    {
        $response = $this->get('/validate-h-entry/');
        $body = (string) $response->getBody();
        $xpath = self::xpathFor($body);
        $query = '//form[contains(@action, "/validate-h-entry/")]//input[@name="url"]';

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $xpath->query($query)->length);
    }

    public function testHEntryPageRequiresAUrl(): void
    {
        $response = $this->get('/validate-h-entry/?' . http_build_query([
            'url' => '',
        ]));

        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Empty URLs lead nowhere!', $body);
    }

    public function testHEntryPageAcceptsBareDomain(): void
    {
        $url = 'example.com/post';
        $expected = "http://{$url}";

        $response = $this->get('/validate-h-entry/?' . http_build_query([
            'url' => $url,
        ]));

        self::assertSame(302, $response->getStatusCode());

        $location = $response->getHeaderLine('Location');
        $parts = parse_url($location);
        parse_str($parts['query'] ?? '', $query);

        self::assertSame('/validate-h-entry/', $parts['path']);
        self::assertSame($expected, $query['url'] ?? null);
    }

    public function testHEntryPageRendersFoundHEntryProperties(): void
    {
        $url = 'https://example.com/';
        $post = "{$url}post";
        $content = 'This is the post content.';
        $author = 'Example Person';
        $photo = "{$url}photo.jpg";
        $html = <<<HTML
            <html>
                <body>
                    <article class="h-entry">
                        <h1 class="p-name">Example post</h1>
                        <a class="u-url" href="{$post}">permalink</a>
                        <div class="p-author h-card">
                            <a class="u-url" href="{$url}">
                                <img class="u-photo" src="{$photo}" alt="">
                                <span class="p-name">{$author}</span>
                            </a>
                        </div>
                        <div class="e-content"><p>{$content}</p></div>
                    </article>
                </body>
            </html>
            HTML;

        $client = $this->createMock(Client::class);

        $client->expects(self::once())
            ->method('get')
            ->with($url)
            ->willReturn([
                'status' => 200,
                'body' => $html,
                'error' => null,
                'redirects' => [],
            ]);

        $this->container()->set(Client::class, $client);

        $response = $this->get('/validate-h-entry/?' . http_build_query([
            'url' => $url,
        ]));

        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString($content, $body);
        self::assertStringContainsString($author, $body);
        self::assertStringContainsString($photo, $body);
        self::assertStringContainsString('article', $body);
        self::assertStringContainsString($post, $body);
    }

    public function testHEntryPageShowsNoHEntryError(): void
    {
        $url = 'https://example.com/';
        $html = '<html><head></head><body>No h-entry here.</body></html>';

        $client = $this->createMock(Client::class);

        $client->expects(self::once())
            ->method('get')
            ->with($url)
            ->willReturn([
                'status' => 200,
                'body' => $html,
                'error' => null,
                'redirects' => [],
            ]);

        $this->container()->set(Client::class, $client);

        $response = $this->get('/validate-h-entry/?' . http_build_query([
            'url' => $url,
        ]));

        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No h-entry found', $body);
    }
}
