<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testTracksRedirects(): void
    {
        $mock = new MockHandler([
            new Response(301, ['Location' => 'https://example.com/end']),
            new Response(200, [], 'end body'),
        ]);

        $guzzle = new GuzzleClient([
            'handler' => HandlerStack::create($mock),
        ]);

        $client = new Client($guzzle);

        $result = $client->get('https://example.com/start');

        self::assertSame(200, $result['status']);
        self::assertSame('end body', $result['body']);
        self::assertNull($result['error']);

        self::assertSame([
            [
                'location' => 'https://example.com/start',
                'status' => 301,
            ],
            [
                'location' => 'https://example.com/end',
                'status' => 200,
            ],
        ], $result['redirects']);
    }
}
