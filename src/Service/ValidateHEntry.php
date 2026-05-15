<?php

/**
 * Validate h-entry service
 */

declare(strict_types=1);

namespace App\Service;

use BarnabyWalters\Mf2 as Mf2Helper;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

class ValidateHEntry
{
    private const string FOUND = 'found';
    private const string WARNING = 'warning';
    private const string INFO = 'info';
    private const array VALID_RSVP_VALUES = ['yes', 'no', 'maybe', 'interested'];

    public function __construct(
    ) {
    }

    /**
     * @return array{url: string, found: bool, checks: list<array<string, mixed>>}
     */
    public function validate(string $url, array $entries, string $html): array
    {
        if ($entries === []) {
            return [
                'url' => $url,
                'found' => false,
                'checks' => [],
            ];
        }

        $entry = $entries[0];

        $checks = [
            $this->checkName($entry),
            $this->checkAuthor($entry),
        ];

        $interactionChecks = [
            ['class' => 'bookmark-of', 'label' => 'Bookmark Of',],
            ['class' => 'in-reply-to', 'label' => 'In Reply To',],
            ['class' => 'like-of', 'label' => 'Like Of',],
            ['class' => 'repost-of', 'label' => 'Repost Of',],
        ];

        foreach ($interactionChecks as $check) {
            if ($this->hasInteractionEvidence($entry, $html, $check['class'])) {
                $checks[] = $this->checkInteractionTarget(
                    $entry,
                    $check['class'],
                    $check['label'],
                );
            }
        }

        if ($this->hasRsvpEvidence($entry, $html)) {
            $checks[] = $this->checkRSVP($entry);
        }

        $checks[] = $this->checkContent($entry);
        $checks[] = $this->checkPublished($entry);
        $checks[] = $this->checkUrl($entry);
        $checks[] = $this->checkSyndication($entry, $html);
        $checks[] = $this->checkCategory($entry);

        return [
            'url' => $url,
            'found' => true,
            'checks' => $checks,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     *
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
            return $this->checkResult(
                'name',
                'Name',
                self::WARNING,
                'name.probably-implicit',
                $this->textValue($name),
                [
                    'html' => 'The parsed <code>name</code> is longer than the content, which is usually a sign it is malformed due to being implicitly rather than explicitly parsed.',
                ],
            );
        }

        return $this->checkResult('name', 'Name', self::FOUND, 'name.present', $this->textValue($name));
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function checkAuthor(array $entry): array
    {
        $author = $this->firstProperty($entry, 'author');

        if ($author === null) {
            return $this->checkResult(
                'author',
                'Author',
                self::WARNING,
                'author.missing',
                help: [
                    'html' => 'Add an author!',
                    'example' => '<a rel="author" class="p-author h-card" href="…">Your Name</a>',
                ],
            );
        }

        if (is_scalar($author) && trim((string) $author) !== '') {
            $authorName = trim((string) $author);

            return $this->checkResult(
                'author',
                'Author',
                self::WARNING,
                'author.string',
                $this->textValue($authorName),
                [
                    'html' => "You’re marking up your post's author as a string — add <code>h-card</code> to make it a full h-card!",
                    'example' => sprintf('<a class="p-author h-card" href="…">%s</a>', $authorName),
                ],
                [
                    $this->checkResult('author.name', 'Author Name', self::FOUND, 'author.name.present', $this->textValue($authorName)),
                    $this->checkResult('author.url', 'Author URL', self::INFO, 'author.url.unavailable-from-string'),
                    $this->checkResult('author.photo', 'Author Photo', self::INFO, 'author.photo.unavailable-from-string'),
                ],
            );
        }

        if (!Mf2Helper\isMicroformat($author)) {
            return $this->checkResult(
                'author',
                'Author',
                self::WARNING,
                'author.unrecognized',
                help: [
                    'html' => 'The author value was present, but it was not a readable string or nested microformat.',
                ],
            );
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
                [
                    'html' => 'The author is a nested microformat, but authors should be marked up as an <code>h-card</code>.',
                    'example' => '<a class="p-author h-card" href="/">Your Name</a>',
                ],
            );
        }

        $complete = $name !== null && $authorUrl !== null && $photo !== null;

        return $this->checkResult(
            'author',
            'Author',
            $complete ? self::FOUND : self::WARNING,
            $complete ? 'author.h-card.complete' : 'author.h-card.partial',
            $value,
            children: [
                $name === null
                    ? $this->checkResult(
                        'author.name',
                        'Author Name',
                        self::WARNING,
                        'author.name.missing',
                        help: [
                            'html' => 'Add a name inside the author <code>h-card</code>.',
                            'example' => '<span class="p-name">Your Name</span>',
                        ],
                    )
                    : $this->checkResult('author.name', 'Author Name', self::FOUND, 'author.name.present', $this->textValue($name)),
                $authorUrl === null
                    ? $this->checkResult(
                        'author.url',
                        'Author URL',
                        self::WARNING,
                        'author.url.missing',
                        help: [
                            'html' => 'Add a URL inside the author <code>h-card</code>. You can combine it with the author name.',
                            'example' => '<a class="p-name u-url" href="/">Your Name</a>',
                        ],
                    )
                    : $this->checkResult('author.url', 'Author URL', self::FOUND, 'author.url.present', $this->urlValue($authorUrl)),
                $photo === null
                    ? $this->checkResult(
                        'author.photo',
                        'Author Photo',
                        self::WARNING,
                        'author.photo.missing',
                        help: [
                            'html' => 'Add a photo!',
                            'example' => '<img class="u-photo" src="…" />',
                        ],
                    )
                    : $this->checkResult('author.photo', 'Author Photo', self::FOUND, 'author.photo.present', ['type' => 'image', 'url' => $photo]),
            ],
        );
    }

    private function checkRSVP(array $entry): array
    {
        $rsvp = $this->firstPlaintext($entry, 'rsvp');

        if ($rsvp === null) {
            return $this->checkResult(
                'rsvp',
                'RSVP',
                self::WARNING,
                'rsvp.missing',
                help: [
                    'html' => 'The post looks like an RSVP, but no RSVP value was parsed.',
                    'example' => '<data class="p-rsvp" value="yes">I’m going</data>',
                ],
            );
        }

        $normalized = strtolower($rsvp);
        $valid = in_array($normalized, self::VALID_RSVP_VALUES, true);

        if (!$valid) {
            return $this->checkResult(
                'rsvp',
                'RSVP',
                self::WARNING,
                'rsvp.invalid',
                $this->textValue($rsvp),
                [
                    'html' => 'RSVP should be one of <code>yes</code>, <code>no</code>, <code>maybe</code>, or <code>interested</code>.',
                    'example' => '<data class="p-rsvp" value="yes">I’m going</data>',
                ],
            );
        }

        return $this->checkResult('rsvp', 'RSVP', self::FOUND, 'rsvp.valid', $this->textValue($normalized));
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function checkContent(array $entry): array
    {
        $content = $this->firstProperty($entry, 'content');

        if (is_array($content)) {
            $text = isset($content['value']) && is_scalar($content['value'])
                ? trim((string) $content['value'])
                : $this->firstPlaintext($entry, 'content');
            $html = isset($content['html']) && is_scalar($content['html'])
                ? (string) $content['html']
                : null;

            return $this->checkResult('content', 'Content', self::FOUND, 'content.html', [
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
                [
                    'html' => <<<'HTML'
                    It looks like your content is marked up as a plain property
                    — consider using <code>class="e-content"</code> so that
                    consumers can parse rich text (i.e. with images and formatting)
                    HTML,
                ],
            );
        }

        return $this->checkResult(
            'content',
            'Content',
            self::WARNING,
            'content.missing',
            help: [
                'html' => 'Add some content!',
                'example' => '<p class="e-content">…</p>',
            ],
        );
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function checkPublished(array $entry): array
    {
        $published = $this->firstPlaintext($entry, 'published');

        if ($published === null) {
            return $this->checkResult(
                'published',
                'Published',
                self::WARNING,
                'published.missing',
                help: [
                    'html' => 'Add a publication datetime!',
                    'example' => '<time class="dt-published" datetime="YYYY-MM-DDTHH:MM:SS+00:00">The Date</time>',
                ],
            );
        }

        if (!$this->isValidPublishedDate($published)) {
            return $this->checkResult(
                'published',
                'Published',
                self::WARNING,
                'published.malformed',
                $this->textValue($published),
                [
                    'html' => 'The publication date is not valid.',
                    'example' => '<time class="dt-published" datetime="YYYY-MM-DDTHH:MM:SS+00:00">The Date</time>',
                ],
            );
        }

        return $this->checkResult('published', 'Published', self::FOUND, 'published.valid', $this->textValue($published));
    }

    private function isValidPublishedDate(string $value): bool
    {
        $value = trim($value);

        return $this->isValidDateOnly($value) || $this->isValidDateTime($value);
    }

    private function isValidDateOnly(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value); // ! resets the time to 00:00:00

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function isValidDateTime(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $value)) {
            return false;
        }

        $endsWithZ = str_ends_with($value, 'Z');
        $dateTimeWithUTCDesignator = 'Y-m-d\TH:i:s\Z';
        $dateTimeWithTimezoneOffset = 'Y-m-d\TH:i:sP';

        $format = $endsWithZ
            ? $dateTimeWithUTCDesignator
            : $dateTimeWithTimezoneOffset;

        $date = DateTimeImmutable::createFromFormat($format, $value);

        if ($date === false) {
            return false;
        }

        if ($endsWithZ) {
            return $date->setTimezone(new DateTimeZone('UTC'))->format($dateTimeWithUTCDesignator) === $value;
        }

        return $date->format($dateTimeWithTimezoneOffset) === $value;
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function checkUrl(array $entry): array
    {
        $url = $this->firstPlaintext($entry, 'url');

        if ($url === null) {
            return $this->checkResult(
                'url',
                'URL',
                self::WARNING,
                'url.missing',
                help: [
                    'html' => 'Add a URL!',
                    'example' => '<a class="u-url" href="…">…</a>',
                ],
            );
        }

        if (!$this->looksLikeUrl($url)) {
            return $this->checkResult(
                'url',
                'URL',
                self::WARNING,
                'url.malformed',
                $this->textValue($url),
                [
                    'html' => 'The parsed post URL does not look like an absolute URL.',
                ],
            );
        }

        return $this->checkResult('url', 'URL', self::FOUND, 'url.valid', $this->urlValue($url));
    }

    private function checkSyndication(array $entry, string $rawHtml): array
    {
        $id = 'syndication';
        $label = 'Syndicated Copies';

        $syndication = $this->allProperties($entry, 'syndication');
        $intentDetected = $this->hasClassIntent($rawHtml, 'u-syndication');

        $example = '<a rel="syndication" class="u-syndication" href="…">…</a>';

        if ($syndication === []) {
            if ($intentDetected) {
                return $this->checkResult(
                    $id,
                    $label,
                    self::WARNING,
                    'syndication.intent-detected-but-no-parsed-value',
                    help: [
                        'html' => 'The HTML suggests this includes syndicated copies, but no values were parsed.',
                        'example' => $example,
                    ],
                );
            }

            return $this->checkResult(
                $id,
                $label,
                self::INFO,
                'syndication.missing',
                help: [
                    'html' => 'Add URLs of <a href="https://indieweb.org/POSSE">POSSEd</a> copies!',
                    'example' => $example,
                ],
            );
        }

        $children = [];
        $hasWarnings = false;
        $urls = [];

        foreach ($syndication as $index => $value) {
            $childId = sprintf('syndication.%d', $index + 1);
            $url = is_scalar($value) ? trim((string) $value) : null;

            if (!$this->looksLikeUrl($url)) {
                $hasWarnings = true;
                $children[] = $this->checkResult(
                    $childId,
                    'Syndicated Copy',
                    self::WARNING,
                    'syndication.url.malformed',
                    help: [
                        'html' => 'This value is not a URL',
                        'example' => $example,
                    ]
                );
                continue;
            }
            $urls[] = $value;
            $children[] = $this->checkResult(
                $childId,
                'Syndicated Copy',
                self::FOUND,
                'syndication.url.valid',
                $this->urlValue($value),
            );
        }

        return $this->checkResult(
            $id,
            $label,
            $hasWarnings ? self::WARNING : self::FOUND,
            $hasWarnings ? 'syndication.malformed' : 'syndication.valid',
            $this->urlListValue($urls),
            children: $children,
        );
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function checkCategory(array $entry): array
    {
        $categories = $this->allPlaintext($entry, 'category');

        if ($categories === []) {
            return $this->checkResult(
                'category',
                'Categories',
                self::INFO,
                'category.missing',
                help: [
                    'html' => 'Add some categories!',
                    'example' => '<a class="p-category" href="…">…</a>',
                ],
            );
        }

        return $this->checkResult('category', 'Categories', self::FOUND, 'category.present', [
            'type' => 'text-list',
            'items' => $categories,
        ]);
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function checkInteractionTarget(
        array $entry,
        string $propertyName,
        string $label,
    ): array {
        $values = $this->allProperties($entry, $propertyName);

        if ($values === []) {
            return $this->checkResult(
                $propertyName,
                $label,
                self::WARNING,
                'interaction.intent-detected-but-no-parsed-value',
                help: [
                    'html' => "The HTML suggests this is a {$label}, but no value was parsed. Check that the class is on a URL-bearing element.",
                ],
            );
        }

        $children = [];
        $urls = [];
        $hasWarnings = false;

        foreach ($values as $index => $value) {
            $child = $this->checkInteractionTargetValue($value, $propertyName, $index + 1);

            if ($child['status'] !== self::FOUND || $child['children'] !== []) {
                $hasWarnings = true;
                $children[] = $child;
            }

            if (($child['value']['type'] ?? null) === 'url' && isset($child['value']['url'])) {
                $urls[] = $child['value']['url'];
            }
        }

        return $this->checkResult(
            $propertyName,
            $label,
            $hasWarnings ? self::WARNING : self::FOUND,
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
                return $this->checkResult(
                    $id,
                    $childLabel,
                    self::WARNING,
                    'interaction.target.missing-url',
                    help: [
                        'html' => 'Give the nested microformat a URL property!',
                        'example' => '<a class="u-url" href="…"></a>',
                    ],
                );
            }

            if (!$this->looksLikeUrl($url)) {
                return $this->checkResult(
                    $id,
                    $childLabel,
                    self::WARNING,
                    'interaction.target.malformed-url',
                    $this->textValue($url),
                    [
                        'html' => 'The value for this property should be a URL or an embedded <a href="http://microformats.org/wiki/h-cite"><code>h-cite</code></a>.',
                    ],
                );
            }

            return $this->checkResult($id, $childLabel, self::FOUND, 'interaction.url.valid', $this->urlValue($url));
        }

        if (!Mf2Helper\isMicroformat($value)) {
            return $this->checkResult(
                $id,
                $childLabel,
                self::WARNING,
                'interaction.target.unrecognized',
                help: [
                    'html' => 'Give the nested microformat a URL property!',
                    'example' => '<a class="u-url" href="…"></a>',
                ],
            );
        }

        /** @var array<string, mixed> $value */
        $url = $this->firstPlaintext($value, 'url');
        $children = [];

        if (!$this->hasType($value, 'h-cite')) {
            $children[] = $this->checkResult(
                $id . '.h-cite',
                'h-cite',
                self::WARNING,
                'interaction.target.not-h-cite',
                help: [
                    'html' => 'The nested microformat should be an <a href="http://microformats.org/wiki/h-cite"><code>h-cite</code></a> as it refers to off-site content.',
                ],
            );
        }

        if ($url === null) {
            $children[] = $this->checkResult(
                $id . '.url',
                'Target URL',
                self::WARNING,
                'interaction.target.missing-url',
                help: [
                    'html' => 'Give the nested microformat a URL property!',
                    'example' => '<a class="u-url" href="…"></a>',
                ],
            );
        } elseif (!$this->looksLikeUrl($url)) {
            $children[] = $this->checkResult(
                $id . '.url',
                'Target URL',
                self::WARNING,
                'interaction.target.malformed-url',
                $this->textValue($url),
                [
                    'html' => 'The value for this property should be a URL or an embedded <a href="http://microformats.org/wiki/h-cite"><code>h-cite</code></a>.',
                ],
            );
        }

        return $this->checkResult(
            $id,
            $childLabel,
            $children === [] ? self::FOUND : self::WARNING,
            $children === [] ? 'interaction.microformat.valid' : 'interaction.microformat.has-warnings',
            $url === null ? null : ($this->looksLikeUrl($url) ? $this->urlValue($url) : $this->textValue($url)),
            children: $children,
        );
    }

    /**
     * @param array<string, mixed> $entry
     *
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
     *
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

    /**
     * @param array<string, mixed> $value
     */
    private function hasType(array $value, string $type): bool
    {
        return isset($value['type'])
            && is_array($value['type'])
            && in_array($type, $value['type'], true);
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
        ?array $help = null,
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
     *
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
