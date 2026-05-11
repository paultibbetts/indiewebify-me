<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\PostType;
use BarnabyWalters\Mf2;

class PostTypeDiscovery
{
    private const array VALID_RSVP_VALUES = ['yes', 'no', 'maybe', 'interested'];

    public function __construct()
    {
    }

    public function discover(array $entry): PostType
    {
        // TODO: confirm
        if ($this->firstNonEmptyValue($entry, 'deleted') !== null) {
            return PostType::Delete;
        }

        // TODO: confirm
        if ($this->hasType($entry, 'h-event')) {
            return PostType::Event;
        }

        if ($this->hasValidRsvp($entry)) {
            return PostType::RSVP;
        }

        $propertyPostTypes = [
            // TODO: confirm
            'invitee' => PostType::Invitation,
            'in-reply-to' => PostType::Reply,
            'repost-of' => PostType::Repost,
            'like-of' => PostType::Like,
            // TODO: confirm
            'bookmark-of' => PostType::Bookmark,
            // TODO: confirm
            'quotation-of' => PostType::Quotation,
            'video' => PostType::Video,
            'photo' => PostType::Photo,
            // TODO: confirm
            'audio' => PostType::Audio,
            // TODO: confirm
            // there is no standard for this
            'jam-of' => PostType::Jam, // ?
            // TODO: confirm
            'checkin' => PostType::CheckIn,
        ];

        foreach ($propertyPostTypes as $property => $postType) {
            if ($this->hasProperty($entry, $property)) {
                return $postType;
            }
        }

        $content = $this->firstNonEmptyValue($entry, 'content')
            ?? $this->firstNonEmptyValue($entry, 'summary');

        if ($content === null) {
            return PostType::Note;
        }

        $name = $this->firstNonEmptyValue($entry, 'name');

        if ($name === null) {
            return PostType::Note;
        }

        if (!str_starts_with(
            $this->normalizePostTypeText($content),
            $this->normalizePostTypeText($name)
        )) {
            return PostType::Article;
        }

        return PostType::Note;
    }

    private function hasProperty(array $entry, string $property): bool
    {
        return Mf2\hasProp($entry, $property);
    }

    private function hasType(array $entry, string $type): bool
    {
        return isset($entry['type'])
            && is_array($entry['type'])
            && in_array($type, $entry['type'], true);
    }

    private function hasValidRsvp(array $entry): bool
    {
        return array_any(
            $this->propertyValues($entry, 'rsvp'),
            fn ($value) => in_array(
                strtolower((string) $value),
                self::VALID_RSVP_VALUES,
                true
            )
        );
    }

    /**
     * @return list<string>
     */
    private function propertyValues(array $entry, string $property): array
    {
        return $this->normalizePlaintextValues(Mf2\getPlaintextArray($entry, $property, []));
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private function normalizePlaintextValues(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $plaintext = [];

        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $plaintext[] = trim((string) $value);
            }
        }

        return $plaintext;
    }

    private function firstNonEmptyValue(array $entry, string $property): ?string
    {
        return $this->propertyValues($entry, $property)[0] ?? null;
    }

    private function normalizePostTypeText(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

}
