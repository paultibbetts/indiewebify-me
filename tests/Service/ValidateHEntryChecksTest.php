<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ValidateHEntry;
use PHPUnit\Framework\TestCase;

final class ValidateHEntryChecksTest extends TestCase
{
    public function testMissingAuthor(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry(), 'author');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.missing', $check['state']);
        self::assertSame('Add an author!', $check['help']['html']);
    }

    public function testStringAuthor(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'author' => ['Example Person'],
        ]), 'author');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.string', $check['state']);
        self::assertSame('text', $check['value']['type']);
        self::assertStringContainsString('add <code>h-card</code>', $check['help']['html']);
        self::assertStringContainsString('h-card', $check['help']['example']);
    }

    public function testCompleteHCardAuthor(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'author' => [$this->authorCard(photo: 'https://example.com/photo.jpg')],
        ]), 'author');

        self::assertSame('found', $check['status']);
        self::assertSame('author.h-card.complete', $check['state']);
        self::assertSame('author-card', $check['value']['type']);
    }

    public function testPartialHCardAuthor(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'author' => [$this->authorCard(photo: null)],
        ]), 'author');
        $photo = $this->childFor($check, 'author.photo');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.h-card.partial', $check['state']);
        self::assertSame('author.photo.missing', $photo['state']);
    }

    public function testMissingNameIsNeutral(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry(), 'name');

        self::assertSame('info', $check['status']);
        self::assertSame('name.missing', $check['state']);
        self::assertNull($check['help']);
    }

    public function testMissingNameOnArticleIsStillNeutral(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry(
            types: ['h-entry', 'h-event'],
        ), 'name');

        self::assertSame('info', $check['status']);
        self::assertSame('name.missing', $check['state']);
        self::assertNull($check['help']);
    }

    public function testReplyTargetUrlPresent(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'in-reply-to' => ['https://example.net/post'],
        ]), 'in-reply-to');

        self::assertSame('found', $check['status']);
        self::assertSame('interaction.target.valid', $check['state']);
        self::assertSame('url', $check['value']['type']);
        self::assertSame([], $check['children']);
    }

    public function testReplyTargetKeepsWarningChildForNestedNonHCite(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'in-reply-to' => [
                [
                    'type' => ['h-entry'],
                    'properties' => [
                        'url' => ['https://example.net/post'],
                    ],
                ],
            ],
        ]), 'in-reply-to');

        self::assertSame('warning', $check['status']);
        self::assertSame('url', $check['value']['type']);
        self::assertCount(1, $check['children']);
        self::assertSame('interaction.microformat.has-warnings', $check['children'][0]['state']);
    }

    public function testReplyIntentDetectedButNoParsedUrl(): void
    {
        $check = $this->checkFor($this->checksForHtmlEvidence(
            '<article class="h-entry"><span class="u-in-reply-to">https://example.net/post</span></article>',
        ), 'in-reply-to');

        self::assertSame('warning', $check['status']);
        self::assertSame('interaction.intent-detected-but-no-parsed-value', $check['state']);
    }

    public function testValidRsvpValue(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'rsvp' => ['yes'],
        ]), 'rsvp');

        self::assertSame('found', $check['status']);
        self::assertSame('rsvp.valid', $check['state']);
        self::assertSame('yes', $check['value']['text']);
    }

    public function testInvalidRsvpValue(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'rsvp' => ['definitely'],
        ]), 'rsvp');

        self::assertSame('warning', $check['status']);
        self::assertSame('rsvp.invalid', $check['state']);
        self::assertSame('definitely', $check['value']['text']);
    }

    public function testRsvpIntentDetectedButNoParsedValue(): void
    {
        $check = $this->checkFor($this->checksForHtmlEvidence(
            '<article class="h-entry"><span class="p-rsvp"></span></article>',
        ), 'rsvp');

        self::assertSame('warning', $check['status']);
        self::assertSame('rsvp.missing', $check['state']);
    }

    public function testMissingContent(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry(), 'content');

        self::assertSame('warning', $check['status']);
        self::assertSame('content.missing', $check['state']);
    }

    public function testMissingPublished(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry(), 'published');

        self::assertSame('warning', $check['status']);
        self::assertSame('published.missing', $check['state']);
    }

    public function testPublishedDateOnly(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'published' => ['2026-05-13'],
        ]), 'published');

        self::assertSame('found', $check['status']);
        self::assertSame('published.valid', $check['state']);
    }

    public function testPublishedDateTime(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'published' => ['2026-05-13T11:57:46+01:00'],
        ]), 'published');

        self::assertSame('found', $check['status'], json_encode($check));
        self::assertSame('published.valid', $check['state']);
    }

    public function testPublishedMalformed(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'published' => ['now'],
        ]), 'published');

        self::assertSame('warning', $check['status']);
        self::assertSame('published.malformed', $check['state']);
    }

    public function testMissingUrl(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry(), 'url');

        self::assertSame('warning', $check['status']);
        self::assertSame('url.missing', $check['state']);
    }

    public function testMalformedUrl(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'url' => ['malformed'],
        ]), 'url');

        self::assertSame('warning', $check['status']);
        self::assertSame('url.malformed', $check['state']);
    }

    /**
     * Returns the check for parsed microformats data.
     *
     * @param array<string, list<mixed>> $properties
     *
     * @return list<array<string, mixed>>
     */
    private function checksForParsedEntry(
        array $properties = [],
        array $types = ['h-entry'],
    ): array {
        return $this->checksForEntry(
            properties: $properties,
            rawHtml: '<article class="h-entry"></article>',
            types: $types,
        );
    }

    /**
     * Returns the check for raw HTML.
     *
     * @return list<array<string, mixed>>
     */
    private function checksForHtmlEvidence(string $rawHtml): array
    {
        return $this->checksForEntry(
            properties: [],
            rawHtml: $rawHtml,
            types: ['h-entry'],
        );
    }

    /**
     * Returns the check for a h-entry.
     *
     * @param array<string, list<mixed>> $properties
     * @param list<string> $types
     *
     * @return list<array<string, mixed>>
     */
    private function checksForEntry(
        array $properties,
        string $rawHtml,
        array $types,
    ): array {
        $validator = new ValidateHEntry();
        $entry = [
            'type' => $types,
            'properties' => $properties,
        ];

        return $validator->validate('https://example.com/post', [$entry], $rawHtml)['checks'];
    }

    /**
     * Returns the check with the given id.
     *
     * @param list<array<string, mixed>> $checks
     *
     * @return array<string, mixed>
     */
    private function checkFor(array $checks, string $id): array
    {
        foreach ($checks as $check) {
            if ($check['id'] === $id) {
                return $check;
            }
        }

        self::fail("Missing check {$id}");
    }

    /**
     * Returns the child check.
     *
     * @param array<string, mixed> $check
     *
     * @return array<string, mixed>
     */
    private function childFor(array $check, string $id): array
    {
        foreach ($check['children'] as $child) {
            if ($child['id'] === $id) {
                return $child;
            }
        }

        self::fail("Missing child check {$id}");
    }

    /**
     * Creates an author card.
     *
     * @return array{type: list<string>, properties: array<string, list<string>>}
     */
    private function authorCard(?string $photo): array
    {
        $properties = [
            'name' => ['Example Person'],
            'url' => ['https://example.com/'],
        ];

        if ($photo !== null) {
            $properties['photo'] = [$photo];
        }

        return [
            'type' => ['h-card'],
            'properties' => $properties,
        ];
    }
}
