<?php

declare(strict_types=1);

namespace App\Controller;

use IndieWeb;
use App\Responder\Responder;
use App\Service\Microformats;
use App\Service\RelMe;
use App\Service\ValidateHEntry;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use RuntimeException;

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
        Microformats $mfService,
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

        $url1 = $this->normalizeFullUrl($url1);
        $url2 = $this->normalizeFullUrl($url2);
        $is_url_https = parse_url((string) $url1, PHP_URL_SCHEME) == 'https';

        [$inbound_url, $secure, $previous] = $relMe->documentUrl($url2);

        $httpResponse = $mfService->httpGet($inbound_url);
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

    /**
     * rel-me validator.
     */
    public function rel_me(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Microformats $mfService,
        RelMe $relMe
    ) {
        $input_url = $request->getQueryParams()['url'] ?? null;

        if (!$input_url) {
            return $this->responder->withTemplate(
                $response,
                'validate-rel-me.twig'
            );
        }

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

        $httpResponse = $mfService->httpGet($url);
        if ($httpResponse['error']) {
            $error = $httpResponse['error'];
            return $this->responder->withTemplate(
                $response,
                'validate-rel-me.twig',
                ['url' => $url, 'error' => $error]
            );
        }

        $rels = $relMe->links($httpResponse['body'], $url);

        return $this->responder->withTemplate(
            $response,
            'validate-rel-me.twig',
            ['url' => $url, 'rels' => $rels]
        );
    }

    /**
     * h-card validator.
     */
    public function h_card(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Microformats $mfService
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

        if ($input_url) {
            # validate h-card for URL in query parameter
            $url = $this->normalizeFullUrl($input_url);

            if ($input_url !== $url) {
                # ensure normalized URL in query parameter by redirecting
                return $this->responder->withRedirectFor(
                    $response,
                    'validate_h_card',
                    [],
                    ['url' => $url]
                );
            }

            ## parse h-cards

            try {
                $cards_response = $mfService->findHCards($url);
            } catch (RuntimeException $e) {
                return $this->responder->withTemplate(
                    $response,
                    'validate-h-card.twig',
                    [
                        'error' => $e->getMessage(),
                    ]
                );
            }

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

    /**
     * h-entry validator.
     */
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
                    ['url' => $url]
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
            if ($entries === []) {
                $error = 'No h-entry was found on that page';
                return $this->responder->withTemplate(
                    $response,
                    'validate-h-entry.twig',
                    ['url' => $url, 'error' => $error]
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
                ['showResult' => $showResult, 'properties' => $properties, 'postType' => $postType, 'url' => $url]
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
        if (!$starts_http && !$starts_https) {
            $url = 'http://' . $url;
        }

        return IndieWeb\normaliseUrl($url);
    }
}
