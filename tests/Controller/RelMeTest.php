<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\Microformats;
use App\Service\RelMe;
use App\Tests\WebTestCase;
use IndieWeb;

final class RelMeTest extends WebTestCase
{
    public function testRelMePageLoadsWithoutQuery(): void
    {
        $response = $this->get('/validate-rel-me');
        $body = (string) $response->getBody();
        $xpath = self::xpathFor($body);
        $query = '//form[contains(@action, "/validate-rel-me")]//input[@name="url"]';

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $xpath->query($query)->length);
    }

    public function testRelMePageAcceptsBareDomain(): void
    {
        $website = 'example.com';
        $normalized = IndieWeb\normaliseUrl("http://{$website}");
        $expected = '/validate-rel-me?' . http_build_query(['url' => $normalized]);

        $response = $this->get("/validate-rel-me?url={$website}");

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($expected, $response->getHeaderLine('Location'));
    }

    public function testRelMeCheckRequiresBothUrls(): void
    {
        $response = $this->get('/rel-me-check?' . http_build_query([
            'url1' => IndieWeb\normaliseUrl('example'),
        ]));
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertNotNull($payload['response']);
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

        $response = $this->get('/rel-me-check?' . http_build_query([
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
        $microformats = $this->createMock(Microformats::class);

        $relMe->expects(self::once())
            ->method('documentUrl')
            ->with($profile)
            ->willReturn([$profile, true, []]);
        $microformats->expects(self::once())
            ->method('httpGet')
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
        $this->container()->set(Microformats::class, $microformats);

        $response = $this->get('/rel-me-check?' . http_build_query([
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
        // TODO: mock this scenario
        $this->markTestIncomplete('Cover HTTP fetch failures for the rel-me document URL.');
    }

    public function testRelMePageShowsNoRelMeLinksError(): void
    {
        // TODO: show no links could be found on website
        $this->markTestIncomplete('Cover successful fetches that contain no rel=me links.');
    }

    public function testRelMeCheckFailsWhenBacklinkIsMissing(): void
    {
        // TODO: mock page with rel-me links pointing to something that does not link back
        $this->markTestIncomplete('Cover a profile URL that does not link back to the submitted site.');
    }

    public function testRelMeCheckReportsInsecureBacklink(): void
    {
        // TODO: mock this scenario
        $this->markTestIncomplete('Cover a matching backlink that redirects insecurely.');
    }
}
