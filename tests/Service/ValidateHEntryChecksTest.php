<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Microformats;
use App\Service\PostTypeDiscovery;
use App\Service\ValidateHEntry;
use PHPUnit\Framework\TestCase;

final class ValidateHEntryChecksTest extends TestCase
{
    public function testMissingAuthor(): void
    {
        $check = $this->checkFor($this->validate([
            'name' => ['Example post'],
            'content' => [['html' => '<p>Hello</p>', 'value' => 'Hello']],
        ]), 'author');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.missing', $check['state']);
        self::assertSame('Add an author!', $check['help']['html']);
    }

    public function testStringAuthor(): void
    {
        $check = $this->checkFor($this->validate([
            'name' => ['Example post'],
            'author' => ['Example Person'],
            'content' => [['html' => '<p>Hello</p>', 'value' => 'Hello']],
        ]), 'author');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.string', $check['state']);
        self::assertSame('text', $check['value']['type']);
        self::assertStringContainsString('add <code>h-card</code>', $check['help']['html']);
        self::assertSame('<a class="p-author h-card" href="…">Example Person</a>', $check['help']['example']);
    }

    public function testCompleteHCardAuthor(): void
    {
        $check = $this->checkFor($this->validate([
            'name' => ['Example post'],
            'author' => [$this->authorCard(photo: 'https://example.com/photo.jpg')],
            'content' => [['html' => '<p>Hello</p>', 'value' => 'Hello']],
        ]), 'author');

        self::assertSame('found', $check['status']);
        self::assertSame('author.h-card.complete', $check['state']);
        self::assertSame('author-card', $check['value']['type']);
    }

    public function testPartialHCardAuthor(): void
    {
        $check = $this->checkFor($this->validate([
            'name' => ['Example post'],
            'author' => [$this->authorCard(photo: null)],
            'content' => [['html' => '<p>Hello</p>', 'value' => 'Hello']],
        ]), 'author');
        $photo = $this->childFor($check, 'author.photo');

        self::assertSame('warning', $check['status']);
        self::assertSame('author.h-card.partial', $check['state']);
        self::assertSame('author.photo.missing', $photo['state']);
        self::assertSame('Add a photo!', $photo['help']['html']);
    }

    public function testMissingNameIsNeutral(): void
    {
        $check = $this->checkFor($this->validate([
            'author' => [$this->authorCard(photo: 'https://example.com/photo.jpg')],
            'content' => [['html' => '<p>Hello</p>', 'value' => 'Hello']],
        ]), 'name');

        self::assertSame('info', $check['status']);
        self::assertSame('name.missing', $check['state']);
        self::assertNull($check['help']);
    }

    public function testMissingNameOnArticleIsStillNeutral(): void
    {
        $check = $this->checkFor($this->validate(
            [
                'author' => [$this->authorCard(photo: 'https://example.com/photo.jpg')],
                'content' => [['html' => '<p>Event details</p>', 'value' => 'Event details']],
            ],
            types: ['h-entry', 'h-event'],
        ), 'name');

        self::assertSame('info', $check['status']);
        self::assertSame('name.missing', $check['state']);
        self::assertNull($check['help']);
    }

    public function testReplyTargetUrlPresent(): void
    {
        $check = $this->checkFor($this->validate([
            'name' => ['Reply'],
            'author' => [$this->authorCard(photo: 'https://example.com/photo.jpg')],
            'content' => [['html' => '<p>Hello</p>', 'value' => 'Hello']],
            'in-reply-to' => ['https://example.net/post'],
        ]), 'in-reply-to');

        self::assertSame('found', $check['status']);
        self::assertSame('interaction.target.valid', $check['state']);
        self::assertSame('url', $check['value']['type']);
        self::assertSame([], $check['children']);
    }

    public function testReplyTargetKeepsWarningChildForNestedNonHCite(): void
    {
        $check = $this->checkFor($this->validate([
            'name' => ['Reply'],
            'author' => [$this->authorCard(photo: 'https://example.com/photo.jpg')],
            'content' => [['html' => '<p>Hello</p>', 'value' => 'Hello']],
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
        $check = $this->checkFor($this->validate(
            [
                'name' => ['Example post'],
                'author' => [$this->authorCard(photo: 'https://example.com/photo.jpg')],
                'content' => [['html' => '<p>Hello</p>', 'value' => 'Hello']],
            ],
            '<article class="h-entry"><span class="u-in-reply-to">https://example.net/post</span></article>',
        ), 'in-reply-to');

        self::assertSame('warning', $check['status']);
        self::assertSame('interaction.intent-detected-but-no-parsed-value', $check['state']);
        self::assertStringContainsString('no value was parsed', $check['help']['html']);
    }

    public function testValidRsvpValue(): void
    {
        $check = $this->checkFor($this->validate([
            'rsvp' => ['yes'],
            'author' => [$this->authorCard(photo: 'https://example.com/photo.jpg')],
            'content' => [['html' => '<p>I will be there</p>', 'value' => 'I will be there']],
        ]), 'rsvp');

        self::assertSame('found', $check['status']);
        self::assertSame('rsvp.valid', $check['state']);
        self::assertSame('yes', $check['value']['text']);
    }

    public function testInvalidRsvpValue(): void
    {
        $check = $this->checkFor($this->validate([
            'rsvp' => ['definitely'],
            'author' => [$this->authorCard(photo: 'https://example.com/photo.jpg')],
            'content' => [['html' => '<p>I will be there</p>', 'value' => 'I will be there']],
        ]), 'rsvp');

        self::assertSame('warning', $check['status']);
        self::assertSame('rsvp.invalid', $check['state']);
        self::assertSame('definitely', $check['value']['text']);
        self::assertStringContainsString('RSVP should be one of', $check['help']['html']);
    }

    public function testRsvpIntentDetectedButNoParsedValue(): void
    {
        $check = $this->checkFor($this->validate(
            [
                'author' => [$this->authorCard(photo: 'https://example.com/photo.jpg')],
                'content' => [['html' => '<p>I will be there</p>', 'value' => 'I will be there']],
            ],
            '<article class="h-entry"><span class="p-rsvp"></span></article>',
        ), 'rsvp');

        self::assertSame('warning', $check['status']);
        self::assertSame('rsvp.missing', $check['state']);
        self::assertStringContainsString('no RSVP value was parsed', $check['help']['html']);
    }

    /**
     * @param array<string, list<mixed>> $properties
     * @return list<array<string, mixed>>
     */
    private function validate(
        array $properties,
        string $rawHtml = '<article class="h-entry"></article>',
        array $types = ['h-entry'],
    ): array {
        $microformats = $this->getMockBuilder(Microformats::class)
            ->onlyMethods(['findHEntriesWithHtml'])
            ->getMock();
        $validator = new ValidateHEntry($microformats, new PostTypeDiscovery());
        $entry = [
            'type' => $types,
            'properties' => $properties,
        ];

        $microformats->expects(self::once())
            ->method('findHEntriesWithHtml')
            ->with('https://example.com/post')
            ->willReturn([
                'entries' => [$entry],
                'html' => $rawHtml,
            ]);

        return $validator->validate('https://example.com/post')['checks'];
    }

    /**
     * @param list<array<string, mixed>> $checks
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
     * @param array<string, mixed> $check
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
