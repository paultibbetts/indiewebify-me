<?php

declare(strict_types=1);

namespace App\Controller;

use App\Responder\Responder;
use Psr\Http\Message\{
    ResponseInterface,
};

final readonly class PageController
{
    public function __construct(private Responder $responder)
    {
    }

    /**
     * Index page.
     */
    public function index(
        ResponseInterface $response,
    ) {
        return $this->responder->withTemplate(
            $response,
            'index.twig'
        );
    }

    /**
     * Federated conversations page.
     */
    public function federatedConversations(
        ResponseInterface $response,
    ) {
        return $this->responder->withTemplate(
            $response,
            'federated-conversations.twig'
        );
    }
}
