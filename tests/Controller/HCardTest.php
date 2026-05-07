<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\Microformats;
use App\Tests\WebTestCase;
use RuntimeException;

final class HCardTest extends WebTestCase
{
    public function testHCardPageLoadsWithoutQuery(): void
    {
        $response = $this->get('/validate-h-card');
        $body = (string) $response->getBody();
        $xpath = self::xpathFor($body);
        $query = '//form[contains(@action, "/validate-h-card")]//input[@name="url"]';

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $xpath->query($query)->length);
    }

    public function testHCardPageRequiresAUrl(): void
    {
        $response = $this->get('/validate-h-card?' . http_build_query([
            'url' => '',
        ]));

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Empty URLs lead nowhere!', $payload);
    }

    public function testHCardPageAcceptsBareDomain(): void
    {
        $url = 'example.com';
        $expected = "http://{$url}/";

        $response = $this->get('/validate-h-card?' . http_build_query([
            'url' => $url,
        ]));

        self::assertSame(302, $response->getStatusCode());

        $location = $response->getHeaderLine('Location');
        $parts = parse_url($location);
        parse_str($parts['query'] ?? '', $query);

        self::assertSame('/validate-h-card', $parts['path']);
        self::assertSame($expected, $query['url'] ?? null);
    }

    public function testHCardPageRendersFoundHCardProperties(): void
    {
        $name = 'Example Person';
        $url = 'https://example.com/';
        $photo = "{$url}photo.jpg";
        $note = 'A test h-card';

        $hCard = [
            'type' => ['h-card'],
            'properties' => [
                'name' => [$name],
                'url' => [$url],
                'photo' => [$photo],
                'note' => [$note],
            ],
        ];

        $microformats = $this->getMockBuilder(Microformats::class)
            ->onlyMethods(['findHCards'])
            ->getMock();

        $microformats->expects(self::once())
            ->method('findHCards')
            ->with($url)
            ->willReturn(
                [
                    'cards' => [$hCard],
                    'representative' => $hCard,
                ],
            );

        $this->container()->set(Microformats::class, $microformats);

        $response = $this->getFollowingRedirects('/validate-h-card?' . http_build_query([
            'url' => $url,
        ]));

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString($name, $payload);
        self::assertStringContainsString($url, $payload);
        self::assertStringContainsString($photo, $payload);
        self::assertStringContainsString($note, $payload);
    }

    public function testHCardPageShowsNoHCardError(): void
    {
        $url = 'https://example.com/';

        $microformats = $this->getMockBuilder(Microformats::class)
            ->onlyMethods(['findHCards'])
            ->getMock();

        $microformats->expects(self::once())
            ->method('findHCards')
            ->with($url)
            ->willReturn(
                [
                    'cards' => [],
                    'representative' => null,
                ],
            );

        $this->container()->set(Microformats::class, $microformats);

        $response = $this->get('/validate-h-card?' . http_build_query([
            'url' => $url,
        ]));

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No h-cards were found', $payload);
    }

    public function testHCardPageShowsFetchOrParseError(): void
    {
        $url = 'https://example.com/';
        $error = 'computer says no';

        $microformats = $this->getMockBuilder(Microformats::class)
          ->onlyMethods(['findHCards'])
          ->getMock();

        $microformats->expects(self::once())
            ->method('findHCards')
            ->with($url)
            ->willThrowException(new RuntimeException($error));

        $this->container()->set(Microformats::class, $microformats);

        $response = $this->get('/validate-h-card?' . http_build_query([
            'url' => $url,
        ]));

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString($error, $payload);
    }
}
