<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\MentionSender;
use App\Service\ValidateHEntry;
use App\Tests\WebTestCase;

final class SendWebmentionsTest extends WebTestCase
{
    public function testSendWebmentionsPageShowsForm(): void
    {
        $response = $this->get('/send-webmentions');
        $body = (string) $response->getBody();
        $xpath = self::xpathFor($body);
        $query = '//form[contains(@action, "/send-webmentions")]//input[@name="url"]';

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $xpath->query($query)->length);
    }

    public function testSendWebmentionsRequiresAUrl(): void
    {
        $url = '';

        $response = $this->post('/send-webmentions', [
            'url' => $url,
        ]);

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Empty URLs lead nowhere!', $payload);
    }

    public function testSendWebmentionsFindsHEntries(): void
    {
        $url = 'https://example.com/post-with-no-h-entry-markup';

        $hEntryValidator = $this->createMock(ValidateHEntry::class);

        $hEntryValidator->expects(self::once())
            ->method('validate')
            ->with($url)
            ->willReturn([
                'url' => $url,
                'found' => false,
                'postType' => null,
                'checks' => [],
            ]);

        $this->container()->set(ValidateHEntry::class, $hEntryValidator);

        $response = $this->post('/send-webmentions', [
            'url' => $url,
        ]);

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString("No h-entry was found on {$url}", $payload);
    }

    public function testSendWebmentionsSendsMentions(): void
    {
        $url = 'https://example.com/post';

        $hEntryValidator = $this->createMock(ValidateHEntry::class);
        $mentionSender = $this->createMock(MentionSender::class);

        $hEntryValidator->expects(self::once())
            ->method('validate')
            ->with($url)
            ->willReturn([
                'url' => $url,
                'found' => true,
                'postType' => 'article',
                'checks' => [],
            ]);

        $mentionSender->expects(self::once())
            ->method('send')
            ->with($url)
            ->willReturn(2);

        $this->container()->set(ValidateHEntry::class, $hEntryValidator);
        $this->container()->set(MentionSender::class, $mentionSender);

        $response = $this->post('/send-webmentions', [
            'url' => $url,
        ]);

        $payload = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('sent 2 webmentions', $payload);
    }

}
