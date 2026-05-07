<?php

declare(strict_types=1);

namespace App\Tests;

use DI\Container;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

abstract class WebTestCase extends TestCase
{
    protected App $app;

    /**
    * Runs before every test.
    *
    * Sets $app to a fresh instance of the application.
    */
    protected function setUp(): void
    {
        $this->app = require dirname(__DIR__, 1) . '/config/bootstrap.php';
    }

    /**
    * HTTP get for web tests.
    */
    protected function get(string $uri): ResponseInterface
    {
        $request = new ServerRequestFactory()
            ->createServerRequest('GET', $uri);

        return $this->app->handle($request);
    }

    protected function getFollowingRedirects(string $uri, int $limit = 5): ResponseInterface
    {
        $response = $this->get($uri);

        while (
            $limit > 0
                && $response->getStatusCode() >= 300
                && $response->getStatusCode() < 400
                && $response->hasHeader('Location')
        ) {
            $uri = $response->getHeaderLine('Location');
            $response = $this->get($uri);
            $limit--;
        }

        return $response;
    }

    protected function post(string $uri, array $data = []): ResponseInterface
    {
        $request = new ServerRequestFactory()
            ->createServerRequest('POST', $uri)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody($data);

        return $this->app->handle($request);
    }

    protected static function xpathFor(string $html): DOMXPath
    {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    protected function container(): Container
    {
        $container = $this->app->getContainer();

        if (!$container instanceof Container) {
            self::fail('Expected PHP-DI container.');
        }

        return $container;
    }

}
