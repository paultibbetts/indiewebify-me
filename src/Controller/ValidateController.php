<?php

declare(strict_types=1);

namespace App\Controller;

use IndieWeb;
use App\Responder\Responder;
use App\Service\Microformats;
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

final readonly class ValidateController
{
    public function __construct(private Responder $responder)
    {
    }

    /**
     * Check that url2 links back to url1 with rel=me
     */
    public function rel_me_check(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Microformats $mfService
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

        $url1 = $this->normalizeFullUrl($url1);
        $url2 = $this->normalizeFullUrl($url2);
        $is_url_https = (parse_url((string) $url1, PHP_URL_SCHEME) == 'https') ? true : false;

        [$inbound_url, $secure, $previous] = IndieWeb\relMeDocumentUrl($url2);

        $httpResponse = $mfService->httpGet($inbound_url);
        $response_data['status'] = $httpResponse['status'];

        if ($httpResponse['error']) {
            $response_data['response'] = 'Error: ' . $httpResponse['error'];
            return $this->responder->withJson($response, $response_data);
        }

        $relMeLinks = IndieWeb\relMeLinks($httpResponse['body'], $inbound_url);

        foreach ($relMeLinks as $inboundRelMeUrl) {
            [$matches, $secure, $previous] = IndieWeb\backlinkingRelMeUrlMatches($inboundRelMeUrl, $url1);
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

    public function rel_me(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Microformats $mfService
    ) {
        $input_url = $request->getQueryParams()['url'] ?? null;

        if ($input_url) {
            # validate rel-me for URL in query parameter
            $url = $this->normalizeFullUrl($input_url);
            if (!$url) {
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
                    compact('url')
                );
            }

            # resolve any redirects and whether redirect chain is secure
            [$url, $secure, $previous] = IndieWeb\relMeDocumentUrl($url);

            if (!$secure) {
                $error = sprintf(
                    'Insecure redirect between %s and %s',
                    $url,
                    array_pop($previous)
                );
                return $this->responder->withTemplate(
                    $response,
                    'validate-rel-me.twig',
                    compact('url', 'error')
                );
            }

            $httpResponse = $mfService->httpGet($url);
            if ($httpResponse['error']) {
                $error = $httpResponse['error'];
                return $this->responder->withTemplate(
                    $response,
                    'validate-rel-me.twig',
                    compact('url', 'error')
                );
            }

            $rels = IndieWeb\relMeLinks($httpResponse['body'], $url);

            return $this->responder->withTemplate(
                $response,
                'validate-rel-me.twig',
                compact('url', 'rels')
            );
        }

        return $this->responder->withTemplate(
            $response,
            'validate-rel-me.twig'
        );
    }

    public function h_card(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Microformats $mfService
    ) {
        $input_url = $request->getQueryParams()['url'] ?? null;

        if ($input_url) {
            # validate h-card for URL in query parameter
            $url = $this->normalizeFullUrl($input_url);

            if ($input_url !== $url) {
                # ensure normalized URL in query parameter by redirecting
                return $this->responder->withRedirectFor(
                    $response,
                    'validate_h_card',
                    [],
                    compact('url')
                );
            }

            ## parse h-cards

            $cards_response = $mfService->findHCards($url);

            if (!($cards_response['cards'] || $cards_response['representative'])) {
                $error = 'No h-card was found on that page';
                return $this->responder->withTemplate(
                    $response,
                    'validate-h-card.twig',
                    compact('url', 'error')
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
                ]
            );
        }

        return $this->responder->withTemplate(
            $response,
            'validate-h-card.twig'
        );
    }

    public function h_entry(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Microformats $mfService,
        ValidateHEntry $validator
    ) {
        $input_url = $request->getQueryParams()['url'] ?? null;

        if ($input_url) {
            # validate h-card for URL in query parameter
            $url = $this->normalizeFullUrl($input_url);

            if ($input_url !== $url) {
                # ensure normalized URL in query parameter by redirecting
                return $this->responder->withRedirectFor(
                    $response,
                    'validate_h_entry',
                    [],
                    compact('url')
                );
            }

            /**
             * Note: below code was initially using the Microformats
             * Service class, but I later started switching to a separate
             * validator service for each type, e.g. ValidatorHEntry.
             * -- Gregor Morrill 2024-12-08
             *
             * @todo finish migrating service
             */

            ## parse h-entries
            $entries = $validator->findEntries($url);
            if (!$entries) {
                $error = 'No h-entry was found on that page';
                return $this->responder->withTemplate(
                    $response,
                    'validate-h-entry.twig',
                    compact('url', 'error')
                );
            }

            $entry = $entries[0];
            $postType = $validator->getPostType($entry);

            $properties = $mfService->parseHEntryProperties($entry);

            // @todo
            $showResult = true;

            return $this->responder->withTemplate(
                $response,
                'validate-h-entry.twig',
                compact(
                    'showResult',
                    'properties',
                    'postType',
                    'url'
                )
            );
        }

        return $this->responder->withTemplate(
            $response,
            'validate-h-entry.twig'
        );
    }

    /**
     * Normalize a full URL
     * Adds default scheme "http://" if no scheme in $url
     *
     * @todo this could be moved into a helper class?
     */
    private function normalizeFullUrl(string $url): ?string
    {
        $starts_http = stripos($url, 'http://') === 0;
        $starts_https = stripos($url, 'https://') === 0;
        if (!($starts_http || $starts_https)) {
            $url = 'http://' . $url;
        }

        return IndieWeb\normaliseUrl($url);
    }
}
