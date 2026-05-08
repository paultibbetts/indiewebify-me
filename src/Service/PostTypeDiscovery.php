<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\PostType;
use BarnabyWalters\Mf2;

class PostTypeDiscovery
{
    public function __construct()
    {
    }

    public function discover(array $entry): PostType
    {
        // TODO: confirm how strict this should be
        // because this app is meant to assist with invalid entries
        // example:
        // a. 'u-like-of' prop = 'like'
        // vs
        // b. 'u-like-of' prop + valid url = 'like' (current)

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

        // TODO: confirm
        if ($this->hasProperty($entry, 'invitee')) {
            return PostType::Invitation;
        }

        if ($this->hasValidUrl($entry, 'in-reply-to')) {
            return PostType::Reply;
        }

        if ($this->hasValidUrl($entry, 'repost-of')) {
            return PostType::Repost;
        }

        if ($this->hasValidUrl($entry, 'like-of')) {
            return PostType::Like;
        }

        // TODO: confirm
        if ($this->hasValidUrl($entry, 'bookmark-of')) {
            return PostType::Bookmark;
        }

        // TODO: confirm
        if ($this->hasValidUrl($entry, 'quotation-of')) {
            return PostType::Quotation;
        }

        if ($this->hasValidUrl($entry, 'video')) {
            return PostType::Video;
        }

        if ($this->hasValidUrl($entry, 'photo')) {
            return PostType::Photo;
        }

        // TODO: confirm
        if ($this->hasValidUrl($entry, 'audio')) {
            return PostType::Audio;
        }

        // TODO: confirm
        if ($this->hasValidUrl($entry, 'jam-of')) {
            return PostType::Jam;
        }

        // TODO: confirm
        if ($this->hasProperty($entry, 'checkin')) {
            return PostType::CheckIn;
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
            // TODO: confirm strictness
            fn ($value) => in_array(
                strtolower((string) $value),
                ['yes', 'no', 'maybe', 'interested'],
                true
            )
        );
    }

    private function hasValidUrl(array $entry, string $property): bool
    {
        if (!$this->hasProperty($entry, $property)) {
            return false;
        }

        foreach ($entry['properties'][$property] as $value) {
            foreach ($this->urlValues($value) as $url) {
                // TODO: confirm strictness of valid URL
                // considering this app is meant to assist when things are invalid
                if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
                    return true;
                }
            }
        }

        return false;
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

    /**
     * @return list<string>
     */
    private function urlValues(mixed $value): array
    {
        if (Mf2\isMicroformat($value) && $this->hasProperty($value, 'url')) {
            return $this->normalizePlaintextValues(
                Mf2\getPlaintextArray($value, 'url', [])
            );
        }

        if (Mf2\isMicroformat($value)) {
            return $this->normalizePlaintextValues(
                isset($value['value'])
                    ? [$value['value']]
                    : []
            );
        }

        return $this->normalizePlaintextValues([Mf2\toPlaintext($value)]);
    }

    private function normalizePostTypeText(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

}
