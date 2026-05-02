<?php

/**
 * Microformats service
 */

declare(strict_types=1);

namespace App\Service;

use DateTime;
use Exception;
use BarnabyWalters\Mf2 as Mf2Helper;
use GuzzleHttp\{
    Client,
    Exception\RequestException,
    Exception\TransferException,
    RequestOptions
};
use Mf2;
use Mf2\Parser;

class Microformats
{
    /**
     * The core h-card properties we will look for and recommend
     */
    private $core_card_properties = [
        'name',
        'photo',
        'logo',
        'url',
        'email',
        'note',
    ];

    /**
     * Additional h-card properties we will look for
     * and display only if they're found
     *
     * Array index is the property name, value is the human-friendly label
     */
    private $additional_card_properties = [
        'honorific-prefix' => 'Honorific prefix',
        'given-name' => 'Given (often first) name',
        'additional-name' => 'Other/middle name',
        'family-name' => 'Family (often last) name',
        'sort-string' => 'String to sort by',
        'honorific-suffix' => 'Honorific suffix',
        'nickname' => 'Nickname',
        'email' => 'Email address',
        'logo' => 'Logo',
        'uid' => 'Unique identifier',
        'category' => 'Category/tag',
        'adr' => 'Postal Address',
        'post-office-box' => 'Post Office Box',
        'street-address' => 'Street number and name',
        'extended-address' => 'Extended address',
        'locality' => 'City/town/village',
        'region' => 'State/province/county',
        'postal-code' => 'Postal code',
        'country-name' => 'Country',
        'label' => 'Label',
        'geo' => 'Geo',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'altitude' => 'Altitude',
        'tel' => 'Telephone',
        'bday' => 'Birth Date',
        'key' => 'Cryptographic public key',
        'org' => 'Organization',
        'job-title' => 'Job title',
        'role' => 'Description of role',
        'impp' => 'Instant Messaging and Presence Protocol',
        'sex' => 'Biological sex',
        'gender-identity' => 'Gender identity',
        'anniversary' => 'Anniversary',
    ];

    /**
     * The core h-entry properties we will look for and recommend
     */
    private $core_entry_properties = [
        'name',
        'content',
        'author',
        'published',
        'url',
        'categories',
    ];

    public function httpGet(string $url): array
    {
        $response = null;

        $output = array_fill_keys([
            'status',
            'body',
            'error',
            'redirects',
        ], null);

        try {
            $client = new Client([
                RequestOptions::ALLOW_REDIRECTS => [
                    'track_redirects' => true,
                ],
            ]);

            $response = $client->get($url);
        } catch (RequestException $e) {
            $output['error'] = $e->getMessage();

            if ($e->hasResponse()) {
                $response = $e->getResponse();
            }
        } catch (TransferException $e) {
            $output['error'] = $e->getMessage();
        }

        if ($response) {
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
            array_push($redirectStatus, $response->getStatusCode());

            $redirects = [];
            foreach ($redirectHistory as $key => $value) {
                $redirects[$key] = [
                    'location' => $value,
                    'status' => $redirectStatus[$key],
                ];
            }

            $output['redirects'] = $redirects;
        }

        return $output;
    }

    /**
     * Fetch a URL and parse for h-card
     * Return an array with index `represntative` that has
     * the representative h-card, or null if none found; as
     * well as index `cards` which is an array of all h-cards
     * found on the page
     */
    public function findHCards(string $url): array
    {
        $microformats = Mf2\fetch($url, true);
        $cards = Mf2Helper\findMicroformatsByType($microformats, 'h-card');
        $representative = Mf2Helper\getRepresentativeHCard($microformats, $url);

        return compact('cards', 'representative');
    }

    /**
     * Fetch a URL and parse for h-entry
     */
    public function findHEntries(string $url): array
    {
        $microformats = Mf2\fetch($url, true);
        return Mf2Helper\findMicroformatsByType($microformats, 'h-entry');
    }

    public function parseHCardProperties(array $h_card): array
    {
        # default each of the core properties to empty string
        $core = array_fill_keys($this->core_card_properties, '');

        # default the additional properties
        $additional = [];

        foreach ($this->core_card_properties as $name) {
            $core[$name] = Mf2Helper\getPlaintextArray($h_card, $name);
        }

        foreach ($this->additional_card_properties as $name) {
            $additional[$name] = Mf2Helper\getPlaintextArray($h_card, $name);
        }

        $core = array_filter($core);
        $additional = array_filter($additional);

        return compact('core', 'additional');
    }

    public function parseHEntryProperties(array $h_entry): array
    {
        $core = array_fill_keys($this->core_entry_properties, '');

        $core['name'] = Mf2Helper\getPlaintext($h_entry, 'name');

        if (Mf2Helper\hasProp($h_entry, 'content')) {
            $core['content'] = Mf2Helper\getPlaintext($h_entry, 'content');
            $core['is_content_html'] = Mf2Helper\isEmbeddedHtml(
                $h_entry['properties']['content'][0]
            );
        }

        $name_state = null;
        if ($core['content'] && $core['name']) {
            $name_state = mb_strlen((string) $core['name']) > mb_strlen((string) $core['content'])
                ? 'invalid'
                : 'valid';
        }

        $core['name_state'] = $name_state;

        $core['author'] = $this->parseAuthor($h_entry);

        if (Mf2Helper\hasProp($h_entry, 'published')) {
            $core['published'] = Mf2Helper\getPlaintext($h_entry, 'published');
            $core['is_published_valid'] = $this->isDateTimeValid($core['published']);
        }

        $core['url'] = Mf2Helper\getPlaintext($h_entry, 'url');
        $core['categories'] = Mf2Helper\getPlaintextArray($h_entry, 'category');
        $core['syndications'] = Mf2Helper\getPlaintextArray($h_entry, 'syndication');

        if (Mf2Helper\hasProp($h_entry, 'in-reply-to')) {
            $core['in_reply_to'] = $this->parseInReplyTo($h_entry);
        }

        return $core;
    }

    public function isDateTimeValid(string $date): bool
    {
        try {
            $dt = new DateTime($date);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function parseAuthor(array $h_entry): ?array
    {
        if (!Mf2Helper\hasProp($h_entry, 'author')) {
            return null;
        }

        $author = Mf2Helper\getAuthor($h_entry);
        $is_h_card = false;

        if (Mf2Helper\isMicroformat($author)) {
            $is_h_card = true;
            $name = Mf2Helper\getPlaintext($author, 'name');
            $photo = Mf2Helper\getPlaintext($author, 'photo');
            $url = Mf2Helper\getPlaintext($author, 'url');

            return compact('name', 'photo', 'url', 'is_h_card');
        } elseif (is_string($author)) {
            $name = $author;
            return compact('name', 'is_h_card');
        }

        return null;
    }

    public function parseInReplyTo(array $h_entry): ?array
    {
        if (!Mf2Helper\hasProp($h_entry, 'in-reply-to')) {
            return null;
        }

        $response = [];
        foreach ($h_entry['properties']['in-reply-to'] as $item) {
            $is_microformat = $is_h_cite = false;
            if (Mf2Helper\isMicroformat($item)) {
                $is_h_cite = in_array('h-cite', $item['type']);
                $is_microformat = true;
                $url = Mf2Helper\getPlaintext($item, 'url');
            } else {
                $url = $item;
            }

            $response[] = compact('url', 'is_microformat', 'is_h_cite');
        }

        return $response;
    }

    /**
     * Provided a parsed h-entry microformat, determine the post type
     *
     * @see https://indieweb.org/ptd
     */
    public function discoverPostType(array $h_entry): string
    {
        $type = 'post';
        if (Mf2Helper\hasProp($h_entry, 'in-reply-to')) {
            $type = 'reply';
        } elseif (Mf2Helper\hasProp($h_entry, 'like-of')) {
            $type = 'like';
        } elseif (Mf2Helper\hasProp($h_entry, 'repost-of')) {
            $type = 'repost';
        }

        return $type;
    }
}
