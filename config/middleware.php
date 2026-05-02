<?php

declare(strict_types=1);

use Slim\{
    App,
    Views\TwigMiddleware
};

return function (App $app) {
    $app->addBodyParsingMiddleware();

    $app->addRoutingMiddleware();

    $app->add(TwigMiddleware::class . '::process');

    // $app->add(ErrorMiddleware::class);
};
