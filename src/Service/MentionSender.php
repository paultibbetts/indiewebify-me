<?php

declare(strict_types=1);

namespace App\Service;

use IndieWeb\MentionClient;

class MentionSender
{
    public function __construct(private MentionClient $client)
    {
    }

    /**
    * Sends webmentions.
    *
    * @return int mentions sent
    */
    public function send(string $url): int
    {
        return $this->client->sendMentions($url);
    }

}
