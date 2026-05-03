<?php

declare(strict_types=1);

namespace App\Service;

use IndieWeb\MentionClient;

class MentionSender
{
    /**
    * Sends webmentions.
    *
    * @return int of mentions sent.
    */
    public function send(string $url): int
    {
        $client = new MentionClient();

        return $client->sendMentions($url);
    }

}
