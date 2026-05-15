<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\WebTestCase;

final class FederatedConversationsTest extends WebTestCase
{
    public function testLoads(): void
    {
        $response = $this->get('/federated-conversations/');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Federating IndieWeb Conversations', $body);
        self::assertStringContainsString('Level 3', $body);
    }

}
