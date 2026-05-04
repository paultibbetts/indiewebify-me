<?php

/**
 * Validate h-entry service
 */

declare(strict_types=1);

namespace App\Service;

use BarnabyWalters\Mf2 as Mf2Helper;
use Mf2;

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

    public function __construct()
    {
    }

    /**
     * Fetch a URL and parse for all h-entry
     */
    public function findEntries(string $url): array
    {
        $microformats = Mf2\fetch($url, true);
        return Mf2Helper\findMicroformatsByType($microformats, 'h-entry');
    }

    /**
     * @todo
     */
    public function validate(array $entry)
    {
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
