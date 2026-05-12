<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Http\Client;
use App\Service\RelMe;
use App\Tests\WebTestCase;
use IndieWeb;

final class RelMeTest extends WebTestCase
{
    public function testRelMePageLoadsWithoutQuery(): void
    {
        $response = $this->get('/validate-rel-me/');
        $body = (string) $response->getBody();
        $xpath = self::xpathFor($body);
        $query = '//form[contains(@action, "/validate-rel-me/")]//input[@name="url"]';

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $xpath->query($query)->length);
    }

    public function testRelMePageAcceptsBareDomain(): void
    {
        $website = 'example.com';
        $normalized = IndieWeb\normaliseUrl("http://{$website}");
        $expected = '/validate-rel-me/?' . http_build_query(['url' => $normalized]);

        $response = $this->get("/validate-rel-me/?url={$website}");

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($expected, $response->getHeaderLine('Location'));
    }

    public function testRelMeCheckRequiresBothUrls(): void
    {
        $response = $this->get('/rel-me-check/?' . http_build_query([
            'url1' => 'http://example.com/',
        ]));
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Please provide both', $body['response']);
    }

    public function testRelMeCheckRequiresValidUrls(): void
    {
        $response = $this->get('/rel-me-check/?' . http_build_query([
            'url1' => 'https://example.com/',
            'url2' => 'mailto:my@email',
        ]));
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Only http and https', $body['response']);
    }

    public function testRelMeCheckReturnsJsonWhenProfileFetchFails(): void
    {
        $website = 'https://example.com/';
        $profile = 'https://profile.example/';

        $relMe = $this->createMock(RelMe::class);
        $relMe->expects(self::once())
            ->method('documentUrl')
            ->with($profile)
            ->willReturn(['file:///failed-fetch.html', true, []]);

        $this->container()->set(RelMe::class, $relMe);

        $response = $this->get('/rel-me-check/?' . http_build_query([
            'url1' => $website,
            'url2' => $profile,
        ]));
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['pass', 'response', 'status', 'secure'],
            array_keys($payload)
        );
        self::assertFalse($payload['pass']);
    }

    public function testRelMeCheckPassesWhenBacklinkIsFound(): void
    {
        $website = 'https://example.com/';
        $profile = 'https://profile.example/';
        $validBacklink = '<a rel="me" href="' . $website . '">Website</a>';

        $relMe = $this->getMockBuilder(RelMe::class)
            ->onlyMethods(['documentUrl', 'backlinkMatches'])
            ->getMock();
        $client = $this->createMock(Client::class);

        $relMe->expects(self::once())
            ->method('documentUrl')
            ->with($profile)
            ->willReturn([$profile, true, []]);
        $client->expects(self::once())
            ->method('get')
            ->with($profile)
            ->willReturn([
                'status' => 200,
                'body' => $validBacklink,
                'error' => null,
                'redirects' => [],
            ]);
        $relMe->expects(self::once())
            ->method('backlinkMatches')
            ->with($website, $website)
            ->willReturn([true, true, []]);

        $this->container()->set(RelMe::class, $relMe);
        $this->container()->set(Client::class, $client);

        $response = $this->get('/rel-me-check/?' . http_build_query([
            'url1' => $website,
            'url2' => $profile,
        ]));
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(200, $payload['status']);
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertTrue($payload['pass']);
        self::assertTrue($payload['secure']);
    }

    public function testRelMePageShowsFetchError(): void
    {
        $url = 'https://example.com/';
        $error = "The site {$url} returned 500 Internal Server Error when we tried to fetch it.";

        $relMe = $this->getMockBuilder(RelMe::class)
            ->onlyMethods(['documentUrl'])
            ->getMock();
        $client = $this->createMock(Client::class);

        $relMe->expects(self::once())
            ->method('documentUrl')
            ->with($url)
            ->willReturn([$url, true, []]);
        $client->expects(self::once())
            ->method('get')
            ->with($url)
            ->willReturn([
                'status' => 500,
                'body' => '',
                'error' => $error,
                'redirects' => [],
            ]);

        $this->container()->set(RelMe::class, $relMe);
        $this->container()->set(Client::class, $client);

        $response = $this->get('/validate-rel-me/?' . http_build_query([
            'url' => $url,
        ]));
        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString($error, $payload);
    }

    public function testRelMePageShowsNoRelMeLinksError(): void
    {
        $url = 'https://example.com/';

        $relMe = $this->getMockBuilder(RelMe::class)
            ->onlyMethods(['documentUrl'])
            ->getMock();
        $client = $this->createMock(Client::class);

        $relMe->expects(self::once())
            ->method('documentUrl')
            ->with($url)
            ->willReturn([$url, true, []]);
        $client->expects(self::once())
            ->method('get')
            ->with($url)
            ->willReturn([
                'status' => 200,
                'body' => '<html>This is an example website with no rel-me links on it.</html>',
                'error' => null,
                'redirects' => [],
            ]);

        $this->container()->set(RelMe::class, $relMe);
        $this->container()->set(Client::class, $client);

        $response = $this->get('/validate-rel-me/?' . http_build_query([
            'url' => $url,
        ]));
        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString("No <code>rel=\"me\"</code> links could be found on {$url}", $payload);
    }

    public function testRelMeCheckFailsWhenBacklinkIsMissing(): void
    {
        $website = 'https://example.com/';
        $profile = 'https://bsky.app/example';
        $inValidBacklink = '<a href="' . $website . '">Website</a>';

        $relMe = $this->getMockBuilder(RelMe::class)
            ->onlyMethods(['documentUrl', 'backlinkMatches'])
            ->getMock();
        $client = $this->createMock(Client::class);

        $relMe->expects(self::once())
            ->method('documentUrl')
            ->with($profile)
            ->willReturn([$profile, true, []]);
        $client->expects(self::once())
            ->method('get')
            ->with($profile)
            ->willReturn([
                'status' => 200,
                'body' => $inValidBacklink,
                'error' => null,
                'redirects' => [],
            ]);

        $this->container()->set(RelMe::class, $relMe);
        $this->container()->set(Client::class, $client);

        $response = $this->get('/rel-me-check/?' . http_build_query([
            'url1' => $website,
            'url2' => $profile,
        ]));
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(200, $payload['status']);
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Does not link back with rel=me', $payload['response']);
        self::assertFalse($payload['pass']);
    }

    public function testRelMeCheckReportsInsecureBacklink(): void
    {
        $https = 'https://example.com/';
        $http = 'http://example.com/';
        $profile = 'https://profile.example/';
        $insecureBacklink = '<a rel="me" href="' . $http . '">Website</a>';

        $relMe = $this->getMockBuilder(RelMe::class)
            ->onlyMethods(['documentUrl', 'backlinkMatches'])
            ->getMock();
        $client = $this->createMock(Client::class);

        $relMe->expects(self::once())
            ->method('documentUrl')
            ->with($profile)
            ->willReturn([$profile, true, []]);
        $client->expects(self::once())
            ->method('get')
            ->with($profile)
            ->willReturn([
                'status' => 200,
                'body' => $insecureBacklink,
                'error' => null,
                'redirects' => [],
            ]);
        $relMe->expects(self::once())
            ->method('backlinkMatches')
            ->with($http, $https)
            ->willReturn([true, false, []]);

        $this->container()->set(RelMe::class, $relMe);
        $this->container()->set(Client::class, $client);

        $response = $this->get('/rel-me-check/?' . http_build_query([
            'url1' => $https,
            'url2' => $profile,
        ]));
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(200, $payload['status']);
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertTrue($payload['pass']);
        self::assertFalse($payload['secure']);
    }

    public function testRelMeCheckHasPublicCors(): void
    {
        $response = $this->get('/rel-me-check/?' . http_build_query([
            'url1' => 'example',
        ]));

        self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}
