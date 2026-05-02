<?php

declare(strict_types=1);

namespace App;

use App\Controller\{
    ValidateController
};
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use Slim\App;

return function (App $app) {
    $prefix = '';

    $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
        $content = <<< END
<h1> Under Development </h1>
<ul>
	<li> <a href="/validate-rel-me">Validate rel-me</a> </li>
	<li> <a href="/validate-h-card">Validate h-card</a> </li>
	<li> <a href="/validate-h-entry">Validate h-entry</a> </li>
</ul>
END;

        $response->getBody()->write($content);
        return $response;
    });

    $app->get('/rel-me-check', [ValidateController::class, 'rel_me_check'])
        ->setName('rel_me_check');

    $app->get('/validate-rel-me', [ValidateController::class, 'rel_me'])
        ->setName('validate_rel_me');

    $app->get('/validate-h-card', [ValidateController::class, 'h_card'])
        ->setName('validate_h_card');

    $app->get('/validate-h-entry', [ValidateController::class, 'h_entry'])
        ->setName('validate_h_entry');
};
