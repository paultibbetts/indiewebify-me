<?php

declare(strict_types=1);

namespace App\Tests;

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

}
