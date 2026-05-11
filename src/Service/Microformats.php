<?php

/**
 * Microformats service
 */

declare(strict_types=1);

namespace App\Service;

use BarnabyWalters\Mf2 as Mf2Helper;
use Mf2;

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

    public function parse(string $html, string $url): array
    {
        return Mf2\parse($html, $url, convertClassic: true);
    }

    /**
     * Finds h-cards.
     *
     * Returns an array with index `representative` that has
     * the representative h-card, or null if none found; as
     * well as index `cards` which is an array of all h-cards
     * found on the page
     *
     * @return array{
     *  cards: list<array<string, mixed>>,
     *  representative: array<string, mixed>|null
     *  }
     */
    public function findHCards(array $microformats, string $url): array
    {
        $cards = Mf2Helper\findMicroformatsByType($microformats, 'h-card');
        $representative = Mf2Helper\getRepresentativeHCard($microformats, $url);

        return ['cards' => $cards, 'representative' => $representative];
    }

    /**
     * Find the h-entries in the provided microformats
     */
    public function findHEntries(array $microformats): array
    {
        return Mf2Helper\findMicroformatsByType($microformats, 'h-entry');
    }

    /**
    *  @return array{
    *       core: array<string, list<string>>,
    *       additional: array<string, list<string>>
    *   }
    */
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

        return ['core' => $core, 'additional' => $additional];
    }
}
