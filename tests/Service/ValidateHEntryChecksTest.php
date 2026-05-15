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
    }

    public function testStringAuthor(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'author' => ['Example Person'],
        ]), 'author');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.string', $check['state']);
        self::assertSame('text', $check['value']['type']);
        self::assertStringContainsString('h-card', $check['help']['example']);
    }

    public function testCompleteHCardAuthor(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'author' => [$this->authorCard('Example Person', 'https://example.com/', 'https://example.com/photo.jpg')],
        ]), 'author');

        self::assertSame('found', $check['status']);
        self::assertSame('author.h-card.complete', $check['state']);
        self::assertSame('author-card', $check['value']['type']);
    }

    public function testPartialHCardAuthorNoName(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'author' => [$this->authorCard(null, 'https://example.com/', 'https://example.com/photo.jpg')],
        ]), 'author');
        $name = $this->childFor($check, 'author.name');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.h-card.partial', $check['state']);
        self::assertSame('author.name.missing', $name['state']);
    }

    public function testPartialHCardAuthorNoUrl(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'author' => [$this->authorCard('Example Person', null, 'https://example.com/photo.jpg')],
        ]), 'author');
        $url = $this->childFor($check, 'author.url');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.h-card.partial', $check['state']);
        self::assertSame('author.url.missing', $url['state']);
    }

    public function testPartialHCardAuthorNoPhoto(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'author' => [$this->authorCard('Example Person', 'https://example.com', null)],
        ]), 'author');
        $photo = $this->childFor($check, 'author.photo');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.h-card.partial', $check['state']);
        self::assertSame('author.photo.missing', $photo['state']);
    }

    public function testUnrecognisedAuthor(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'author' => [
                ['unexpected' => 'shape'],
            ],
        ]), 'author');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.unrecognized', $check['state']);
    }

    public function testNestedAuthorMicoformatsNotHCard(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'author' => [
                [
                    'type' => 'h-entry',
                    'properties' => [
                        'name' => ['Example Person'],
                        'url' => ['https://example.com/'],
                    ],
                ],
            ],
        ]), 'author');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.microformat.not-h-card', $check['state']);
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

    public function testNameLongerThanContentGetsAWarning(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'name' => ['This is a long name'],
            'content' => ['Content'],
        ]), 'name');

        self::assertSame('warning', $check['status']);
        self::assertSame('name.probably-implicit', $check['state']);
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

    public function testReplyTargetUrlMissing(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'in-reply-to' => ['     '],
        ]), 'in-reply-to');

        self::assertSame('warning', $check['status']);
        self::assertSame('interaction.target.has-warnings', $check['state']);
        self::assertSame('interaction.target.missing-url', $check['children'][0]['state']);
        self::assertStringContainsString('u-in-reply-to', $check['children'][0]['help']['example']);
    }

    public function testReplyTargetUrlMalformed(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'in-reply-to' => ['a post'],
        ]), 'in-reply-to');

        self::assertSame('warning', $check['status']);
        self::assertSame('interaction.target.has-warnings', $check['state']);
        self::assertSame('interaction.target.malformed-url', $check['children'][0]['state']);
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

        self::assertSame('found', $check['status']);
        self::assertSame('published.valid', $check['state']);
    }

    public function testPublishedDateTimeWithUtcDesignator(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'published' => ['2026-05-15T12:34:56Z'],
        ]), 'published');

        self::assertSame('found', $check['status']);
        self::assertSame('published.valid', $check['state']);
    }

    public function testPublishedDateTimeCannotBeInvalidDate()
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'published' => ['2026-99-01T00:00:00+01:00'],
        ]), 'published');

        self::assertSame('warning', $check['status']);
        self::assertSame('published.malformed', $check['state']);
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

    public function testMissingCategories(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry(), 'category');

        self::assertSame('info', $check['status']);
        self::assertSame('category.missing', $check['state']);
    }

    public function testValidCategories(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'category' => ['indieweb', 'testing'],
        ]), 'category');

        self::assertSame('found', $check['status']);
        self::assertSame('category.present', $check['state']);
    }

    public function testMissingSyndication(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry(), 'syndication');

        self::assertSame('info', $check['status']);
        self::assertSame('syndication.missing', $check['state']);
    }

    public function testValidSyndication(): void
    {
        $mastodon = 'https://indieweb.social/@example/123';
        $check = $this->checkFor($this->checksForParsedEntry([
            'syndication' => [
                $mastodon,
                'https://bsky.app/profile/example.person/post/abc',
            ],
        ]), 'syndication');

        self::assertSame('found', $check['status']);
        self::assertSame('syndication.valid', $check['state']);
        self::assertNull($check['help']);
        self::assertSame('url-list', $check['value']['type']);
        self::assertSame('syndication.url.valid', $check['children'][0]['state']);
        self::assertSame('url', $check['children'][0]['value']['type']);
        self::assertSame($mastodon, $check['children'][0]['value']['url']);
    }

    public function testMalformedSyndication(): void
    {
        $check = $this->checkFor($this->checksForParsedEntry([
            'syndication' => [
                'mastodon',
                'bluesky',
            ],
        ]), 'syndication');

        self::assertSame('warning', $check['status']);
        self::assertSame('syndication.malformed', $check['state']);
        self::assertCount(2, $check['children']);
        self::assertSame('syndication.url.malformed', $check['children'][0]['state']);
        self::assertSame('syndication.url.malformed', $check['children'][1]['state']);
    }

    public function testSyndicationIntent(): void
    {
        $check = $this->checkFor($this->checksForHtmlEvidence(
            '<article class="h-entry"><a class="u-syndication">https://indieweb.social/post</a></article>',
        ), 'syndication');

        self::assertSame('warning', $check['status']);
        self::assertSame('syndication.intent-detected-but-no-parsed-value', $check['state']);
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
    private function authorCard(?string $name, ?string $url, ?string $photo): array
    {
        $properties = [];

        if ($name !== null) {
            $properties['name'] = [$name];
        }

        if ($url !== null) {
            $properties['url'] = [$url];
        }

        if ($photo !== null) {
            $properties['photo'] = [$photo];
        }

        return [
            'type' => ['h-card'],
            'properties' => $properties,
        ];
    }
}
