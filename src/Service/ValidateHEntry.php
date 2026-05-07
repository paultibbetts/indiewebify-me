<?php

/**
 * Validate h-entry service
 */

declare(strict_types=1);

namespace App\Service;

use BarnabyWalters\Mf2 as Mf2Helper;
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

    public function __construct(private Microformats $microformats)
    {
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
            'postType' => $this->getPostType($entry),
            'properties' => $this->microformats->parseHEntryProperties($entry),
        ];
    }

    /**
     * @see https://indieweb.org/ptd
     */
    public function getPostType(array $entry): string
    {
        $type = 'post';
        if (Mf2Helper\hasProp($entry, 'in-reply-to')) {
            $type = 'reply';
        } elseif (Mf2Helper\hasProp($entry, 'like-of')) {
            $type = 'like';
        } elseif (Mf2Helper\hasProp($entry, 'repost-of')) {
            $type = 'repost';
        }

        return $type;
    }
}
