<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;

final class HEntryTest extends TestCase
{
    public function testHEntryPageLoadsWithoutQuery(): void
    {
        // TODO: show a message
        // "Empty URLs lead nowhere!"
        // like the live h-entry validator does
        $this->markTestIncomplete('Cover GET /validate-h-entry with no url query.');
    }

    public function testHEntryPageAcceptsBareDomain(): void
    {
        $this->markTestIncomplete('Cover normalization of submitted h-entry URLs.');
    }

    public function testHEntryPageRendersFoundHEntryProperties(): void
    {
        $this->markTestIncomplete('Cover rendering h-entry properties and post type.');
    }

    public function testHEntryPageShowsNoHEntryError(): void
    {
        $this->markTestIncomplete('Cover pages where no h-entry is found.');
    }

    public function testHEntryPageCoversIncludesPostType(): void
    {
        $this->markTestIncomplete('Cover h-entry post-types.');
    }
}
