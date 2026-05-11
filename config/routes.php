<?php

declare(strict_types=1);

namespace App;

use App\Controller\{
    PageController,
    ValidateController,
    WebmentionController
};
use App\Middleware\PublicCorsMiddleware;
use Slim\App;

return function (App $app) {
    $app->get('/', [PageController::class, 'index'])
        ->setName('index');

    $app->get('/rel-me-check/', [ValidateController::class, 'rel_me_check'])
        ->setName('rel_me_check')
        ->add(PublicCorsMiddleware::class);

    $app->get('/validate-rel-me/', [ValidateController::class, 'rel_me'])
        ->setName('validate_rel_me');

    $app->get('/validate-h-card/', [ValidateController::class, 'h_card'])
        ->setName('validate_h_card');

    $app->get('/validate-h-entry/', [ValidateController::class, 'h_entry'])
        ->setName('validate_h_entry');

    $app->get('/send-webmentions/', [WebmentionController::class, 'showForm'])
        ->setName('send_webmentions');

    $app->post('/send-webmentions/', [WebmentionController::class, 'send'])
        ->setName('send_webmentions_send');

    $app->get('/federated-conversations/', [PageController::class, 'federatedConversations'])
        ->setName('federated_conversations');

};
