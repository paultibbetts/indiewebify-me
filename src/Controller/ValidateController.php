<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\PostTypeDiscovery;
use App\Domain\SiteHintsDetector;
use App\Http\Client;
use App\Responder\Responder;
use App\Service\Microformats;
use App\Service\RelMe;
use App\Service\ValidateHEntry;
use App\Support\UrlNormalizer;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

final readonly class ValidateController
{
    public function __construct(private Responder $responder, private Client $client)
    {
    }

    /**
     * Check that url2 links back to url1 with rel=me
     */
    public function rel_me_check(
        ServerRequestInterface $request,
        ResponseInterface $response,
        RelMe $relMe
    ) {
        $response_data = [
            'pass' => false,
            'response' => '',
            'status' => null,
            'secure' => null,
        ];

        $url1 = $request->getQueryParams()['url1'] ?? null;
        $url2 = $request->getQueryParams()['url2'] ?? null;
        if (!($url1 && $url2)) {
            $response_data['response'] = 'Please provide both url1 and url2 parameters';
            $response = $response->withStatus(400);

            return $this->responder->withJson($response, $response_data);
        }

        foreach (['url1' => $url1, 'url2' => $url2] as $name => $url) {
            if (!$this->isHttpUrl($url)) {
                $response = $response->withStatus(400);
                $response_data['response'] = "Error: Only http and https URLs can be checked. Invalid parameter: {$name}";

                return $this->responder->withJson($response, $response_data);
            }
        }

        $url1 = UrlNormalizer::normalize($url1);
        $url2 = UrlNormalizer::normalize($url2);
        $is_url_https = parse_url((string) $url1, PHP_URL_SCHEME) == 'https';

        [$inbound_url, $secure, $previous] = $relMe->documentUrl($url2);

        $httpResponse = $this->client->get($inbound_url);
        $response_data['status'] = $httpResponse['status'];

        if ($httpResponse['error']) {
            $response_data['response'] = 'Error: ' . $httpResponse['error'];

            return $this->responder->withJson($response, $response_data);
        }

        $relMeLinks = $relMe->links($httpResponse['body'], $inbound_url);

        foreach ($relMeLinks as $inboundRelMeUrl) {
            [$matches, $secure, $previous] = $relMe->backlinkMatches($inboundRelMeUrl, $url1);
            if ($matches) {
                $response_data['pass'] = true;
                $response_data['response'] = ($is_url_https && !$secure)
                    ? 'Link back is to http:// not https://'
                    : 'Works perfectly';
                $response_data['secure'] = $secure;

                return $this->responder->withJson($response, $response_data);
            }
        }

        $response_data['response'] = 'Does not link back with rel=me';

        return $this->responder->withJson($response, $response_data);
    }

    private function isHttpURL(string $url): bool
    {
        $scheme = parse_url(trim($url), PHP_URL_SCHEME);

        return $scheme === null || in_array($scheme, ['http', 'https'], true);
    }

    /**
     * rel-me validator.
     */
    public function rel_me(
        ServerRequestInterface $request,
        ResponseInterface $response,
        RelMe $relMe,
        SiteHintsDetector $siteHints,
    ) {
        $input_url = $request->getQueryParams()['url'] ?? null;

        if (!$input_url) {
            return $this->responder->withTemplate(
                $response,
                'validate-rel-me.twig'
            );
        }

        # validate rel-me for URL in query parameter
        $url = UrlNormalizer::normalize($input_url);
        if ($url === '' || $url === '0') {
            return $this->responder->withTemplate(
                $response,
                'validate-rel-me.twig',
                ['error' => 'Could not parse the entered URL']
            );
        }

        if ($input_url !== $url) {
            # ensure normalized URL in query parameter by redirecting
            return $this->responder->withRedirectFor(
                $response,
                'validate_rel_me',
                [],
                ['url' => $url]
            );
        }

        # resolve any redirects and whether redirect chain is secure
        [$url, $secure, $previous] = $relMe->documentUrl($url);

        if (!$secure) {
            $error = sprintf(
                'Insecure redirect between %s and %s',
                $url,
                array_pop($previous)
            );

            return $this->responder->withTemplate(
                $response,
                'validate-rel-me.twig',
                ['url' => $url, 'error' => $error]
            );
        }

        $httpResponse = $this->client->get($url);
        if ($httpResponse['error']) {
            $error = $httpResponse['error'];

            return $this->responder->withTemplate(
                $response,
                'validate-rel-me.twig',
                ['url' => $url, 'error' => $error]
            );
        }

        $rels = $relMe->links($httpResponse['body'], $url);
        $hints = $siteHints->hintsFor($url, $httpResponse['body']);

        return $this->responder->withTemplate(
            $response,
            'validate-rel-me.twig',
            [
                'siteHints' => $hints,
                'rels' => $rels,
                'url' => $url,
            ]
        );
    }

    /**
     * h-card validator.
     */
    public function h_card(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Microformats $mfService,
        SiteHintsDetector $siteHints,
    ) {
        $input_url = $request->getQueryParams()['url'] ?? null;

        if ($input_url === '') {
            $error = 'Empty URLs lead nowhere!';

            return $this->responder->withTemplate(
                $response,
                'validate-h-card.twig',
                [
                    'error' => $error,
                ]
            );
        }

        if (!$input_url) {
            return $this->responder->withTemplate(
                $response,
                'validate-h-card.twig'
            );

        }

        # validate h-card for URL in query parameter
        $url = UrlNormalizer::normalize($input_url);

        if ($input_url !== $url) {
            # ensure normalized URL in query parameter by redirecting
            return $this->responder->withRedirectFor(
                $response,
                'validate_h_card',
                [],
                ['url' => $url]
            );
        }

        $httpResponse = $this->client->get($url);
        if ($httpResponse['error']) {
            return $this->responder->withTemplate(
                $response,
                'validate-h-card.twig',
                [
                    'error' => $httpResponse['error'],
                ]
            );
        }

        $html = $httpResponse['body'];

        ## parse h-cards
        $microformats = $mfService->parse($html, $url);
        $cards_response = $mfService->findHCards($microformats, $url);

        if (!$cards_response['cards'] && !$cards_response['representative']) {
            return $this->responder->withTemplate(
                $response,
                'validate-h-card.twig',
                [
                    'url' => $url,
                    'showResult' => true,
                    'core' => [],
                    'additional' => [],
                    'cards' => [],
                    'representative' => [],
                ]
            );
        }

        ## parse properties

        # use the first h-card by default
        $card = $cards_response['cards'][0];

        if ($cards_response['representative']) {
            # use the representative h-card, if found
            $card = $cards_response['representative'];
        }

        $properties = $mfService->parseHCardProperties($card);

        $hints = $siteHints->hintsFor($url, $html);

        return $this->responder->withTemplate(
            $response,
            'validate-h-card.twig',
            [
                'url' => $url,
                'showResult' => true,
                'core' => $properties['core'],
                'additional' => $properties['additional'],
                'cards' => $cards_response['cards'],
                'representative' => $cards_response['representative'],
                'siteHints' => $hints,
            ]
        );
    }

    /**
     * h-entry validator.
     */
    public function h_entry(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ValidateHEntry $validator,
        SiteHintsDetector $siteHints,
        Microformats $microformats,
        PostTypeDiscovery $ptd,
    ) {
        $input_url = $request->getQueryParams()['url'] ?? null;

        if ($input_url === '') {
            return $this->responder->withTemplate(
                $response,
                'validate-h-entry.twig',
                [
                    'error' => 'Empty URLs lead nowhere!',
                ]
            );
        }

        if (!$input_url) {
            return $this->responder->withTemplate(
                $response,
                'validate-h-entry.twig'
            );
        }

        # validate h-card for URL in query parameter
        $url = UrlNormalizer::normalize($input_url);

        if ($input_url !== $url) {
            # ensure normalized URL in query parameter by redirecting
            return $this->responder->withRedirectFor(
                $response,
                'validate_h_entry',
                data: [],
                queryParams: ['url' => $url]
            );
        }

        $httpResponse = $this->client->get($url);

        if ($httpResponse['error']) {
            return $this->responder->withTemplate(
                $response,
                'validate-h-entry.twig',
                [
                    'url' => $url,
                    'error' => $httpResponse['error'],
                ]
            );
        }

        $html = $httpResponse['body'];

        $mf = $microformats->parse($html, $url);
        $entries = $microformats->findHEntries($mf);

        $report = $validator->validate($url, $entries, $html);

        $postType = $report['found'] ? $ptd->discover($entries[0])->value : null;

        $hints = $siteHints->hintsFor($url, $html);

        return $this->responder->withTemplate(
            $response,
            'validate-h-entry.twig',
            [
                'postType' => $postType,
                'report' => $report,
                'siteHints' => $hints,
                'url' => $url,
            ]
        );
    }
}
