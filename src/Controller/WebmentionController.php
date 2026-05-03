<?php

declare(strict_types=1);

namespace App\Controller;

use IndieWeb;
use App\Responder\Responder;
use App\Service\Microformats;
use App\Service\MentionSender;
use App\Service\RelMe;
use App\Service\ValidateHEntry;
use BarnabyWalters\Mf2 as Mf2Helper;
use Mf2;
use Mf2\Parser;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface,
    StreamInterface
};
use Slim\Views\Twig;

final readonly class WebmentionController
{
    public function __construct(private Responder $responder)
    {
    }

    /**
     * Shows the send webmentions form.
     */
    public function showForm(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ) {
        return $this->responder->withTemplate(
            $response,
            'send-webmentions.twig',
        );
    }

    /**
    *  Sends webmentions.
    */
    public function send(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ValidateHEntry $hEntryValidator,
        MentionSender $mentionSender,
    ) {
        $body = $request->getParsedBody();
        $url = $body['url'] ?? null;

        if (!$url || $url == '') {
            return $this->responder->withTemplate(
                $response,
                'send-webmentions.twig',
                [
                    'url' => $url,
                    'error' => 'no-url',
                ]
            );
        }

        $entries = $hEntryValidator->findEntries($url);
        if (!$entries) {
            return $this->responder->withTemplate(
                $response,
                'send-webmentions.twig',
                [
                    'url' => $url,
                    'error' => 'no-h-entry',
                ]
            );
        }

        $numSent = $mentionSender->send($url);

        $result = [
            'hEntriesFound' => count($entries),
            'numSent' => $numSent,
        ];

        return $this->responder->withTemplate(
            $response,
            'send-webmentions.twig',
            [
                'url' => $url,
                'result' => $result,
            ]
        );
    }
}
