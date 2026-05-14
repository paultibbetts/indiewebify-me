<?php

declare(strict_types=1);

namespace App\Http;

use GuzzleHttp\{
    Client as GuzzleClient,
    Exception\RequestException,
    Exception\TransferException,
    RequestOptions
};

class Client
{
    public function __construct(private readonly GuzzleClient $client)
    {
    }

    public function get(string $url): array
    {
        $response = null;

        $output = array_fill_keys([
            'status',
            'body',
            'error',
            'redirects',
        ], null);

        try {
            $response = $this->client->get($url, [
                RequestOptions::ALLOW_REDIRECTS => [
                    'track_redirects' => true,
                ],
            ]);
        } catch (RequestException $e) {
            $output['error'] = $e->getMessage();

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $output['error'] = sprintf(
                    'The site %s returned %d %s when we tried to fetch it.',
                    $url,
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                );
            } else {
                $output['error'] = "We could not fetch {$url}. Check that the site is reachable and try again.";
            }
        } catch (TransferException) {
            $output['error'] = "We could not fetch {$url}. Check that the site is reachable and try again.";
        }

        if ($response instanceof \Psr\Http\Message\ResponseInterface) {
            $output['status'] = $response->getStatusCode();
            $output['body'] = (string) $response->getBody();

            # track redirect history
            # See https://docs.guzzlephp.org/en/stable/faq.html?highlight=redirects#how-can-i-track-redirected-requests

            // Retrieve both Redirect History headers
            $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
            $redirectStatus = $response->getHeader('X-Guzzle-Redirect-Status-History');

            // Add the initial URI requested to the (beginning of) URI history
            array_unshift($redirectHistory, $url);

            // Add the final HTTP status code to the end of HTTP response history
            $redirectStatus[] = $response->getStatusCode();

            $redirects = [];
            foreach ($redirectHistory as $key => $value) {
                $redirects[$key] = [
                    'location' => $value,
                    'status' => (int) $redirectStatus[$key],
                ];
            }

            $output['redirects'] = $redirects;
        }

        return $output;
    }
}
