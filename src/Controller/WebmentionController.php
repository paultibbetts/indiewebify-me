<?php

declare(strict_types=1);

namespace App\Controller;

use App\Responder\Responder;
use App\Service\MentionSender;
use App\Service\ValidateHEntry;
use App\Support\UrlNormalizer;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

final readonly class WebmentionController
{
    public function __construct(private Responder $responder)
    {
    }

    /**
     * Shows the send webmentions form.
     */
    public function showForm(
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

        $validation = $hEntryValidator->validate($url);
        if (!$validation['found']) {
            return $this->responder->withTemplate(
                $response,
                'send-webmentions.twig',
                [
                    'url' => $url,
                    'error' => 'no-h-entry',
                ]
            );
        }

        $numSent = $mentionSender->send(
            UrlNormalizer::normalize($url)
        );

        return $this->responder->withTemplate(
            $response,
            'send-webmentions.twig',
            [
                'url' => $url,
                'numSent' => $numSent,
            ]
        );
    }
}
