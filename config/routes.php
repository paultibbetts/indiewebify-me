<?php

declare(strict_types=1);

namespace App;

use App\Controller\{
    IndexController,
    ValidateController,
    WebmentionController
};
use Slim\App;

return function (App $app) {
    $prefix = '';

    $app->get('/', [IndexController::class, 'show'])
        ->setName('index');

    $app->get('/rel-me-check', [ValidateController::class, 'rel_me_check'])
        ->setName('rel_me_check');

    $app->get('/validate-rel-me', [ValidateController::class, 'rel_me'])
        ->setName('validate_rel_me');

    $app->get('/validate-h-card', [ValidateController::class, 'h_card'])
        ->setName('validate_h_card');

    $app->get('/validate-h-entry', [ValidateController::class, 'h_entry'])
        ->setName('validate_h_entry');

    $app->get('/send-webmentions', [WebmentionController::class, 'showForm'])
        ->setName('send_webmentions');

    $app->post('/send-webmentions', [WebmentionController::class, 'send'])
        ->setName('send_webmentions_send');
};
