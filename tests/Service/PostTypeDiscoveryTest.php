<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Domain\PostType;
use App\Service\PostTypeDiscovery;
use BarnabyWalters\Mf2 as Mf2Helper;
use Mf2;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostTypeDiscoveryTest extends TestCase
{
    #[DataProvider('postTypeHtmlExamples')]
    public function testDiscoversPostTypesFromHtml(string $html, PostType $expected, bool $match = true): void
    {
        $hentry = '<article class="h-entry">' . $html . '</article>';
        $microformats = Mf2\parse($hentry, 'https://example.com/');
        $entries = Mf2Helper\findMicroformatsByType($microformats, 'h-entry');

        self::assertCount(1, $entries);

        $discovery = new PostTypeDiscovery();

        $postType = $discovery->discover($entries[0]);

        if ($match) {
            self::assertSame($expected, $postType);
        } else {
            self::assertNotSame($expected, $postType);
        }
    }

    public static function postTypeHtmlExamples(): array
    {
        return [
            'article' => [
                '<h1 class="p-name">Header</h1><div class="e-content">Content</div>',
                PostType::Article,
            ],
            'like' => [
                '<a class="u-like-of" href="https://indieweb.org/principles">I like this</a>',
                PostType::Like,
            ],
            'note' => [
                '<div class="e-content">Note</div>',
                PostType::Note,
            ],
            'photo' => [
                '<img class="u-photo" href="https://example.com/img.jpeg">',
                PostType::Photo,
            ],
            /*
            // this does not work
            // because the parser gives it a default "src" of the page it's parsing
            'photo-no-url' => [
                '<img class="u-photo">',
                PostType::Photo,
                false,
            ],
            */
            'reply' => [
                '<a class="u-in-reply-to" href="https://example.com/post">Good idea!</a>',
                PostType::Reply,
            ],
            'reply-no-url' => [
                '<a class="u-in-reply-to">I forgot to include the link</a>',
                PostType::Reply,
                false,
            ],
            'repost' => [
                '<a class="u-repost-of" href="https://example.com/post">a post</a>',
                PostType::Repost,
            ],
            'repost-nested-h-cite' => [
                '<div class="h-cite u-repost-of"><a href="https://example.com/post">a post</a></div>',
                PostType::Repost,
            ],
            'rsvp' => [
                'I hereby RSVP <span class="p-rsvp">yes</span> to <a class="u-in-reply-to">this</a>',
                PostType::RSVP,
            ],
            'video' => [
                '<video class="u-video" src="https://example.com/video.mpg">a video</video>',
                PostType::Video,
            ],

            // under consideration
            'audio' => [
                '<audio class="u-audio" src="https://example.com/audio.wav">transcript</audio>',
                PostType::Audio,
            ],
            'bookmark' => [
                '<a class="u-bookmark-of" src="https://indieweb.org/">Bookmarked</a>',
                PostType::Bookmark,
            ],
            'checkin' => [
                '<span class="p-name">Checked in to <a class="u-checkin h-card" href="https://example.com/venue">this place</a></span>',
                PostType::CheckIn,
            ],
            'deleted' => [
                '<time class="dt-deleted" datetime="2026-05-09T12:00:00+01:00">9 May 2026</time>',
                PostType::Delete,
            ],
            'quotation' => [
                '<blockquote><p>This one doesn\'t seem widely used</p><cite class="h-cite u-quotation-of"><a class="u-url" href="https://paultibbetts.uk/">Paul Tibbetts</a></cite></blockquote>',
                PostType::Quotation,
            ],

            // TODO: consider the following

            // not h-entry
            //case Event = 'event';
            //case Invitation = 'invitation';

            // no standard
            //case Jam = 'jam';
        ];
    }
}
