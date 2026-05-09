<?php

/**
 * Validate h-entry service
 */

declare(strict_types=1);

namespace App\Service;

use BarnabyWalters\Mf2 as Mf2Helper;
use DateTimeImmutable;
use Exception;

class ValidateHEntry
{
    private const string PASS = 'pass';
    private const string WARNING = 'warning';
    private const string INFO = 'info';
    private const array VALID_RSVP_VALUES = ['yes', 'no', 'maybe', 'interested'];

    public function __construct(
        private readonly Microformats $microformats,
        private readonly PostTypeDiscovery $ptd,
    ) {
    }

    /**
     * @return array{url: string, found: bool, postType: string|null, checks: list<array<string, mixed>>}
     */
    public function validate(string $url): array
    {
        $result = $this->microformats->findHEntriesWithHtml($url);

        if ($result['entries'] === []) {
            return [
                'url' => $url,
                'found' => false,
                'postType' => null,
                'checks' => [],
            ];
        }

        $entry = $result['entries'][0];
        $postType = $this->ptd->discover($entry);

        $checks = [
            $this->checkName($entry),
            $this->checkAuthor($entry),
        ];

        $interactionChecks = [
            [
                'class' => 'bookmark-of',
                'label' => 'Bookmark Of',
            ],
            [
                'class' => 'in-reply-to',
                'label' => 'In Reply To',
            ],
            [
                'class' => 'like-of',
                'label' => 'Like Of',
            ],
            [
                'class' => 'repost-of',
                'label' => 'Repost Of',
            ],
        ];

        foreach ($interactionChecks as $check) {
            if ($this->hasInteractionEvidence($entry, $result['html'], $check['class'])) {
                $checks[] = $this->checkInteractionTarget(
                    $entry,
                    $result['html'],
                    $check['class'],
                    $check['label'],
                );
            }
        }

        if ($this->hasRsvpEvidence($entry, $result['html'])) {
            $checks[] = $this->checkRSVP($entry);
        }

        $checks[] = $this->checkContent($entry);
        $checks[] = $this->checkPublished($entry);
        $checks[] = $this->checkUrl($entry);
        $checks[] = $this->checkCategory($entry);

        return [
            'url' => $url,
            'found' => true,
            'postType' => $postType->value,
            'checks' => $checks,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function checkName(array $entry): array
    {
        $name = $this->firstPlaintext($entry, 'name');

        if ($name === null) {
            return $this->checkResult('name', 'Name', self::INFO, 'name.missing');
        }

        $content = $this->firstPlaintext($entry, 'content');

        if ($content !== null && mb_strlen($name) > mb_strlen($content)) {
            return $this->checkResult('name', 'Name', self::WARNING, 'name.probably-implicit', $this->textValue($name), 'name_probably_implicit');
        }

        return $this->checkResult('name', 'Name', self::PASS, 'name.present', $this->textValue($name));
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function checkAuthor(array $entry): array
    {
        $author = $this->firstProperty($entry, 'author');

        if ($author === null) {
            return $this->checkResult('author', 'Author', self::WARNING, 'author.missing', help: 'author_missing');
        }

        if (is_scalar($author) && trim((string) $author) !== '') {
            $authorName = trim((string) $author);

            return $this->checkResult(
                'author',
                'Author',
                self::WARNING,
                'author.string',
                $this->textValue($authorName),
                'author_string_needs_hcard',
                [
                    $this->checkResult('author.name', 'Author Name', self::PASS, 'author.name.present', $this->textValue($authorName)),
                    $this->checkResult('author.url', 'Author URL', self::INFO, 'author.url.unavailable-from-string'),
                    $this->checkResult('author.photo', 'Author Photo', self::INFO, 'author.photo.unavailable-from-string'),
                ],
            );
        }

        if (!$this->isMicroformat($author)) {
            return $this->checkResult('author', 'Author', self::WARNING, 'author.unrecognized', help: 'author_unrecognized');
        }

        /** @var array<string, mixed> $author */
        $name = $this->firstPlaintext($author, 'name');
        $authorUrl = $this->firstPlaintext($author, 'url');
        $photo = $this->firstPlaintext($author, 'photo');
        $value = [
            'type' => 'author-card',
            'name' => $name,
            'url' => $authorUrl,
            'photo' => $photo,
        ];

        if (!$this->hasType($author, 'h-card')) {
            return $this->checkResult(
                'author',
                'Author',
                self::WARNING,
                'author.microformat.not-h-card',
                $value,
                'author_microformat_should_be_hcard',
            );
        }

        $complete = $name !== null && $authorUrl !== null && $photo !== null;

        return $this->checkResult(
            'author',
            'Author',
            $complete ? self::PASS : self::WARNING,
            $complete ? 'author.h-card.complete' : 'author.h-card.partial',
            $value,
            children: [
                $name === null
                    ? $this->checkResult('author.name', 'Author Name', self::WARNING, 'author.name.missing', help: 'author_name_missing')
                    : $this->checkResult('author.name', 'Author Name', self::PASS, 'author.name.present', $this->textValue($name)),
                $authorUrl === null
                    ? $this->checkResult('author.url', 'Author URL', self::WARNING, 'author.url.missing', help: 'author_url_missing')
                    : $this->checkResult('author.url', 'Author URL', self::PASS, 'author.url.present', $this->urlValue($authorUrl)),
                $photo === null
                    ? $this->checkResult('author.photo', 'Author Photo', self::WARNING, 'author.photo.missing', help: 'author_photo_missing')
                    : $this->checkResult('author.photo', 'Author Photo', self::PASS, 'author.photo.present', ['type' => 'image', 'url' => $photo]),
            ],
        );
    }

    private function checkRSVP(array $entry): array
    {
        $rsvp = $this->firstPlaintext($entry, 'rsvp');

        if ($rsvp === null) {
            return $this->checkResult('rsvp', 'RSVP', self::WARNING, 'rsvp.missing', help: 'rsvp_missing');
        }

        $normalized = strtolower($rsvp);
        $valid = in_array($normalized, self::VALID_RSVP_VALUES, true);

        if (!$valid) {
            return $this->checkResult('rsvp', 'RSVP', self::WARNING, 'rsvp.invalid', $this->textValue($rsvp), 'rsvp_invalid');
        }

        return $this->checkResult('rsvp', 'RSVP', self::PASS, 'rsvp.valid', $this->textValue($normalized));
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function checkContent(array $entry): array
    {
        $content = $this->firstProperty($entry, 'content');

        if ($content === null) {
            return $this->checkResult('content', 'Content', self::WARNING, 'content.missing', help: 'content_missing');
        }

        if (is_array($content)) {
            $text = isset($content['value']) && is_scalar($content['value'])
                ? trim((string) $content['value'])
                : $this->firstPlaintext($entry, 'content');
            $html = isset($content['html']) && is_scalar($content['html'])
                ? (string) $content['html']
                : null;

            return $this->checkResult('content', 'Content', self::PASS, 'content.html', [
                'type' => 'html-content',
                'html' => $html,
                'text' => $text,
            ]);
        }

        if (is_scalar($content) && trim((string) $content) !== '') {
            return $this->checkResult(
                'content',
                'Content',
                self::WARNING,
                'content.plain',
                $this->textValue(trim((string) $content)),
                'content_prefer_e_content',
            );
        }

        return $this->checkResult('content', 'Content', self::WARNING, 'content.missing', help: 'content_missing');
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function checkPublished(array $entry): array
    {
        $published = $this->firstPlaintext($entry, 'published');

        if ($published === null) {
            return $this->checkResult('published', 'Published', self::WARNING, 'published.missing', help: 'published_missing');
        }

        if (!$this->isDateTimeValid($published)) {
            return $this->checkResult(
                'published',
                'Published',
                self::WARNING,
                'published.malformed',
                $this->textValue($published),
                'published_malformed',
            );
        }

        return $this->checkResult('published', 'Published', self::PASS, 'published.valid', $this->textValue($published));
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function checkUrl(array $entry): array
    {
        $url = $this->firstPlaintext($entry, 'url');

        if ($url === null) {
            return $this->checkResult('url', 'URL', self::WARNING, 'url.missing', help: 'url_missing');
        }

        if (!$this->looksLikeUrl($url)) {
            return $this->checkResult('url', 'URL', self::WARNING, 'url.malformed', $this->textValue($url), 'url_malformed');
        }

        return $this->checkResult('url', 'URL', self::PASS, 'url.valid', $this->urlValue($url));
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function checkCategory(array $entry): array
    {
        $categories = $this->allPlaintext($entry, 'category');

        if ($categories === []) {
            return $this->checkResult('category', 'Categories', self::INFO, 'category.missing', help: 'category_missing');
        }

        return $this->checkResult('category', 'Categories', self::PASS, 'category.present', [
            'type' => 'text-list',
            'items' => $categories,
        ]);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function checkInteractionTarget(
        array $entry,
        ?string $rawHtml,
        string $propertyName,
        string $label,
    ): array {
        $values = $this->allProperties($entry, $propertyName);
        $intentDetected = $this->hasClassIntent($rawHtml, 'u-' . $propertyName);

        if ($values === []) {
            if ($intentDetected) {
                return $this->checkResult(
                    $propertyName,
                    $label,
                    self::WARNING,
                    'interaction.intent-detected-but-no-parsed-value',
                    help: 'interaction_intent_detected_no_value',
                );
            }

            return $this->checkResult($propertyName, $label, self::WARNING, 'interaction.target.missing', help: 'interaction_target_missing_url');
        }

        $children = [];
        $urls = [];
        $hasWarnings = false;

        foreach ($values as $index => $value) {
            $child = $this->checkInteractionTargetValue($value, $propertyName, $label, $index + 1);
            $children[] = $child;

            if ($child['status'] !== self::PASS) {
                $hasWarnings = true;
            }

            if (($child['value']['type'] ?? null) === 'url' && isset($child['value']['url'])) {
                $urls[] = $child['value']['url'];
            }
        }

        return $this->checkResult(
            $propertyName,
            $label,
            $hasWarnings ? self::WARNING : self::PASS,
            $hasWarnings ? 'interaction.target.has-warnings' : 'interaction.target.valid',
            $this->urlListValue($urls),
            children: $children,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkInteractionTargetValue(
        mixed $value,
        string $propertyName,
        string $label,
        int $position
    ): array {
        $id = sprintf('%s.%d', $propertyName, $position);

        $childLabel = 'Target';
        if ($position > 1) {
            $childLabel = sprintf('Target %d', $position);
        }

        if (is_scalar($value)) {
            $url = trim((string) $value);

            if ($url === '') {
                return $this->checkResult($id, $childLabel, self::WARNING, 'interaction.target.missing-url', help: 'interaction_target_missing_url');
            }

            if (!$this->looksLikeUrl($url)) {
                return $this->checkResult($id, $childLabel, self::WARNING, 'interaction.target.malformed-url', $this->textValue($url), 'interaction_target_malformed_url');
            }

            return $this->checkResult($id, $childLabel, self::PASS, 'interaction.url.valid', $this->urlValue($url));
        }

        if (!$this->isMicroformat($value)) {
            return $this->checkResult($id, $childLabel, self::WARNING, 'interaction.target.unrecognized', help: 'interaction_target_missing_url');
        }

        /** @var array<string, mixed> $value */
        $url = $this->firstPlaintext($value, 'url');
        $children = [];

        if (!$this->hasType($value, 'h-cite')) {
            $children[] = $this->checkResult($id . '.h-cite', 'h-cite', self::WARNING, 'interaction.target.not-h-cite', help: 'interaction_target_should_be_h_cite');
        }

        if ($url === null) {
            $children[] = $this->checkResult($id . '.url', 'Target URL', self::WARNING, 'interaction.target.missing-url', help: 'interaction_target_missing_url');
        } elseif (!$this->looksLikeUrl($url)) {
            $children[] = $this->checkResult($id . '.url', 'Target URL', self::WARNING, 'interaction.target.malformed-url', $this->textValue($url), 'interaction_target_malformed_url');
        }

        return $this->checkResult(
            $id,
            $childLabel,
            $children === [] ? self::PASS : self::WARNING,
            $children === [] ? 'interaction.microformat.valid' : 'interaction.microformat.has-warnings',
            $url === null ? null : ($this->looksLikeUrl($url) ? $this->urlValue($url) : $this->textValue($url)),
            children: $children,
        );
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<mixed>
     */
    private function allProperties(array $entry, string $name): array
    {
        $values = $entry['properties'][$name] ?? [];

        return is_array($values) ? array_values($values) : [];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function firstProperty(array $entry, string $name): mixed
    {
        return $this->allProperties($entry, $name)[0] ?? null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function firstPlaintext(array $entry, string $name): ?string
    {
        $value = Mf2Helper\getPlaintext($entry, $name);

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private function allPlaintext(array $entry, string $name): array
    {
        $values = Mf2Helper\getPlaintextArray($entry, $name, []);

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

    private function isMicroformat(mixed $value): bool
    {
        return is_array($value) && Mf2Helper\isMicroformat($value);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function hasType(array $value, string $type): bool
    {
        return isset($value['type'])
            && is_array($value['type'])
            && in_array($type, $value['type'], true);
    }

    private function isDateTimeValid(string $date): bool
    {
        try {
            new DateTimeImmutable($date);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    private function looksLikeUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function hasClassIntent(?string $rawHtml, string $className): bool
    {
        if ($rawHtml === null || trim($rawHtml) === '') {
            return false;
        }

        preg_match_all('/class\s*=\s*(["\'])(.*?)\1/is', $rawHtml, $matches);

        foreach ($matches[2] as $classAttribute) {
            $classes = preg_split('/\s+/', trim($classAttribute)) ?: [];

            if (in_array($className, $classes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function hasInteractionEvidence(array $entry, ?string $rawHtml, string $propertyName): bool
    {
        return $this->allProperties($entry, $propertyName) !== []
            || $this->hasClassIntent($rawHtml, 'u-' . $propertyName);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function hasRsvpEvidence(array $entry, ?string $rawHtml): bool
    {
        return $this->allProperties($entry, 'rsvp') !== []
            || $this->hasClassIntent($rawHtml, 'p-rsvp');
    }

    /**
     * @return array<string, mixed>
     */
    private function checkResult(
        string $id,
        string $label,
        string $status,
        string $state,
        ?array $value = null,
        ?string $help = null,
        array $children = [],
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'status' => $status,
            'state' => $state,
            'value' => $value,
            'help' => $help,
            'children' => $children,
        ];
    }

    /**
     * @return array{type: string, text: string}
     */
    private function textValue(string $text): array
    {
        return [
            'type' => 'text',
            'text' => $text,
        ];
    }

    /**
     * @return array{type: string, url: string}
     */
    private function urlValue(string $url): array
    {
        return [
            'type' => 'url',
            'url' => $url,
        ];
    }

    /**
     * @param list<string> $urls
     * @return array<string, mixed>|null
     */
    private function urlListValue(array $urls): ?array
    {
        if ($urls === []) {
            return null;
        }

        if (count($urls) === 1) {
            return $this->urlValue($urls[0]);
        }

        return [
            'type' => 'url-list',
            'urls' => $urls,
        ];
    }
}
