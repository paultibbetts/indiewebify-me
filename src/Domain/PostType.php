<?php

declare(strict_types=1);

namespace App\Domain;

enum PostType: string
{
    case Article = 'article';
    case Like = 'like';
    case Note = 'note';
    case Photo = 'photo';
    case Reply = 'reply';
    case Repost = 'repost';
    case RSVP = 'rsvp';
    case Video = 'video';

    // under consideration
    // TODO: consider these
    case Audio = 'audio';
    case Bookmark = 'bookmark';
    case CheckIn = 'checkin';
    case Delete = 'delete';
    case Event = 'event';
    case Invitation = 'invitation';
    case Jam = 'jam';
    case Quotation = 'quotation';

    // not implemented
    // case TagOf = 'tag-reply'; // TODO: is this Tag, TagOf, or TagReply?
}
