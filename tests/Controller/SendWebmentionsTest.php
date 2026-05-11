<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Http\Client;
use App\Service\WebmentionSender;
use App\Tests\WebTestCase;

final class SendWebmentionsTest extends WebTestCase
{
    public function testSendWebmentionsPageShowsForm(): void
    {
        $response = $this->get('/send-webmentions/');
        $body = (string) $response->getBody();
        $xpath = self::xpathFor($body);
        $query = '//form[contains(@action, "/send-webmentions/")]//input[@name="url"]';

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $xpath->query($query)->length);
    }

    public function testSendWebmentionsRequiresAUrl(): void
    {
        $url = '';

        $response = $this->post('/send-webmentions/', [
            'url' => $url,
        ]);

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Empty URLs lead nowhere!', $payload);
    }

    public function testSendWebmentionsFindsHEntries(): void
    {
        $url = 'https://example.com/post-with-no-h-entry-markup';
        $html = '<html><body>No h-entry markup here.</body></html>';

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

        $response = $this->post('/send-webmentions/', [
            'url' => $url,
        ]);

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString("No h-entry was found on {$url}", $payload);
    }

    public function testSendWebmentionsSendsMentions(): void
    {
        $url = 'https://example.com/post';
        $html = <<<HTML
            <html>
                <body>
                    <article class="h-entry">
                        <p class="e-content">This is a post.</p>
                        <a class="u-url" href="{$url}">Permalink</a>
                    </article>
                </body>
            </html>
            HTML;

        $client = $this->createMock(Client::class);
        $webmentionSender = $this->createMock(WebmentionSender::class);

        $client->expects(self::once())
            ->method('get')
            ->with($url)
            ->willReturn([
                'status' => 200,
                'body' => $html,
                'error' => null,
                'redirects' => [],
            ]);

        $webmentionSender->expects(self::once())
            ->method('send')
            ->with($url)
            ->willReturn(2);

        $this->container()->set(Client::class, $client);
        $this->container()->set(WebmentionSender::class, $webmentionSender);

        $response = $this->post('/send-webmentions/', [
            'url' => $url,
        ]);

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('sent 2 webmentions', $payload);
    }

}
