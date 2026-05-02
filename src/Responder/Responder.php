<?php

/**
 * A generic responder
 */

declare(strict_types=1);

namespace App\Responder;

use Psr\Http\Message\{
    ResponseFactoryInterface,
    ResponseInterface
};
use Slim\{
    Interfaces\RouteParserInterface,
    Views\Twig
};

use function http_build_query;

final readonly class Responder
{
    public function __construct(private Twig $twig, private RouteParserInterface $routeParser, private ResponseFactoryInterface $responseFactory)
    {
    }

    /**
     * Create a new response.
     */
    public function createResponse(): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse()
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Output rendered template.
     */
    public function withTemplate(
        ResponseInterface $response,
        string $template,
        array $data = []
    ): ResponseInterface {
        return $this->twig->render(
            $response,
            '/pages/' . $template,
            $data
        );
    }

    /**
     * Creates a redirect for the given URL
     *
     * This method prepares the response object to return an HTTP Redirect
     * response to the client.
     */
    public function withRedirect(
        ResponseInterface $response,
        string $destination,
        array $queryParams = []
    ): ResponseInterface {
        if ($queryParams) {
            $destination = sprintf('%s?%s', $destination, http_build_query($queryParams));
        }

        return $response->withStatus(302)->withHeader('Location', $destination);
    }

    /**
     * Creates a redirect for the given route name.
     *
     * This method prepares the response object to return an HTTP Redirect
     * response to the client.
     */
    public function withRedirectFor(
        ResponseInterface $response,
        string $routeName,
        array $data = [],
        array $queryParams = []
    ): ResponseInterface {
        return $this->withRedirect(
            $response,
            $this->routeParser->urlFor($routeName, $data, $queryParams)
        );
    }

    /**
     * Write JSON to the response body.
     *
     * This method prepares the response object to return an HTTP JSON
     * response to the client.
     */
    public function withJson(
        ResponseInterface $response,
        array $data,
        int $options = 0
    ): ResponseInterface {
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write((string) json_encode($data, $options));

        return $response;
    }
}
