<?php

/**
 * Validate h-entry service
 */

declare(strict_types=1);

namespace App\Service;

use App\Service\Microformats;

class ValidateHEntry
{
    // @phpstan-ignore property.onlyWritten (kept for pending migration state)
    private array $messages = [];

    // @phpstan-ignore property.onlyWritten (kept for pending migration state)
    private array $properties = [
        'name',
        'content',
        'author',
        'published',
        'url',
        'categories',
    ];

    public function __construct(
        private readonly Microformats $microformats,
        private readonly PostTypeDiscovery $ptd,
    ) {
    }

    /**
     * @todo
     */
    public function validate(string $url): array
    {
        $entries = $this->microformats->findHEntries($url);

        if ($entries === []) {
            return [
                'entries' => [],
                'postType' => null,
                'properties' => [],
            ];
        }

        $entry = $entries[0];

        return [
            'entries' => $entries,
            'postType' => $this->ptd->discover($entry)->value,
            'properties' => $this->microformats->parseHEntryProperties($entry),
        ];
    }

}
