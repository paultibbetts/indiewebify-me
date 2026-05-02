<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;

final class HCardTest extends TestCase
{
    public function testHCardPageLoadsWithoutQuery(): void
    {
        // TODO: show a message like the live h-entry validator does
        // "Empty URLs lead nowhere!"
        // (the live verson of this h-card validator 500s, and does not show a message)
        $this->markTestIncomplete('Cover GET /validate-h-card with no url query.');
    }

    public function testHCardPageAcceptsBareDomain(): void
    {
        $this->markTestIncomplete('Cover bare domain submitted h-card URLs.');
    }

    public function testHCardPageRendersFoundHCardProperties(): void
    {
        $this->markTestIncomplete('Cover rendering representative h-card properties.');
    }

    public function testHCardPageShowsNoHCardError(): void
    {
        $this->markTestIncomplete('Cover pages where no h-card is found.');
    }

    public function testHCardPageShowsFetchOrParseError(): void
    {
        $this->markTestIncomplete('Cover h-card fetch or parse error edge cases.');
    }
}
