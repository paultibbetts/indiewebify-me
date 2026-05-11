<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Client;
use App\Responder\Responder;
use App\Service\Microformats;
use App\Service\ValidateHEntry;
use App\Service\WebmentionSender;
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
        Client $client,
        Microformats $microformats,
        ValidateHEntry $hEntryValidator,
        WebmentionSender $webmentionSender,
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

        $url = UrlNormalizer::normalize($url);
        $httpResponse = $client->get($url);
        if ($httpResponse['error']) {
            return $this->responder->withTemplate(
                $response,
                'send-webmentions.twig',
                [
                    'url' => $url,
                    'error' => $httpResponse['error'],
                ]
            );
        }

        $html = $httpResponse['body'];
        $mf = $microformats->parse($html, $url);
        $entries = $microformats->findHEntries($mf);

        $validation = $hEntryValidator->validate($url, $entries, $html);
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

        $numSent = $webmentionSender->send($url);

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
