<?php

declare(strict_types=1);

namespace App\Controller;

use App\Responder\Responder;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface,
};

final readonly class IndexController
{
    public function __construct(private Responder $responder)
    {
    }

    /**
     * Check that url2 links back to url1 with rel=me
     */
    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) {
        return $this->responder->withTemplate(
            $response,
            'index.twig'
        );
    }
}

