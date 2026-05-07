<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\WebTestCase;

final class IndexTest extends WebTestCase
{
    public function testIndexLoads(): void
    {
        $response = $this->get('/');
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('IndieWebify.Me', $body);
        self::assertStringContainsString('What is the IndieWeb?', $body);
    }

    public function testIndexIncludesAllValidators(): void
    {
        $response = $this->get('/');
        $body = (string) $response->getBody();
        $xpath = self::xpathFor($body);

        $forms = [
            'validate-rel-me',
            'validate-h-card',
            'validate-h-entry',
        ];

        foreach ($forms as $form) {
            $query = "//form[contains(@action, \"/$form\")]//input[@name=\"url\"]";
            self::assertSame(1, $xpath->query($query)->length);
        }
    }
}
