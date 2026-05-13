<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Http\Client;
use App\Tests\WebTestCase;

final class HCardTest extends WebTestCase
{
    public function testHCardPageLoadsWithoutQuery(): void
    {
        $response = $this->get('/validate-h-card/');
        $body = (string) $response->getBody();
        $xpath = self::xpathFor($body);
        $query = '//form[contains(@action, "/validate-h-card/")]//input[@name="url"]';

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $xpath->query($query)->length);
    }

    public function testHCardPageRequiresAUrl(): void
    {
        $response = $this->get('/validate-h-card/?' . http_build_query([
            'url' => '',
        ]));

        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Empty URLs lead nowhere!', $body);
    }

    public function testHCardPageAcceptsBareDomain(): void
    {
        $url = 'example.com';
        $expected = "http://{$url}/";

        $response = $this->get('/validate-h-card/?' . http_build_query([
            'url' => $url,
        ]));

        self::assertSame(302, $response->getStatusCode());

        $location = $response->getHeaderLine('Location');
        $parts = parse_url($location);
        parse_str($parts['query'] ?? '', $query);

        self::assertSame('/validate-h-card/', $parts['path']);
        self::assertSame($expected, $query['url'] ?? null);
    }

    public function testHCardPageRendersFoundHCardProperties(): void
    {
        $name = 'Example Person';
        $url = 'https://example.com/';
        $photo = "{$url}photo.jpg";
        $note = 'A test h-card';
        $html = <<<HTML
            <html>
                <body>
                    <div class="h-card">
                        <p class="p-name">{$name}</p>
                        <a class="u-url u-uid" href="{$url}">{$url}</a>
                        <img class="u-photo" src="{$photo}" alt="">
                        <p class="p-note">{$note}</p>
                    </div>
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

        $response = $this->getFollowingRedirects('/validate-h-card/?' . http_build_query([
            'url' => $url,
        ]));

        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString($name, $body);
        self::assertStringContainsString($url, $body);
        self::assertStringContainsString($photo, $body);
        self::assertStringContainsString($note, $body);
    }

    public function testHCardPageShowsNoHCardError(): void
    {
        $url = 'https://example.com/';
        $html = '<html><head></head><body>No h-card here.</body></html>';

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

        $response = $this->get('/validate-h-card/?' . http_build_query([
            'url' => $url,
        ]));

        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No h-cards were found', $body);
    }

    public function testHCardPageShowsFetchOrParseError(): void
    {
        $url = 'https://example.com/';
        $error = 'computer says no';

        $client = $this->createMock(Client::class);

        $client->expects(self::once())
            ->method('get')
            ->with($url)
            ->willReturn([
                'status' => null,
                'body' => null,
                'error' => $error,
                'redirects' => null,
            ]);

        $this->container()->set(Client::class, $client);

        $response = $this->get('/validate-h-card/?' . http_build_query([
            'url' => $url,
        ]));

        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString($error, $body);
    }
}
