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

    protected function setUp(): void
    {
        $this->app = require dirname(__DIR__, 1) . '/config/bootstrap.php';
    }

    protected function get(string $uri): ResponseInterface
    {
        $request = new ServerRequestFactory()->createServerRequest('GET', $uri);

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
