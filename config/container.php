<?php

declare(strict_types=1);

use App\Twig\HtmlExampleExtension;
use DI\Bridge\Slim\Bridge;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\{
    App,
    Factory\AppFactory,
    Interfaces\RouteParserInterface,
    Views\Twig,
    Views\TwigMiddleware
};

return [
    'settings' => fn () => require __DIR__ . '/settings.php',

    App::class => Bridge::create(...),

    ResponseFactoryInterface::class => fn (ContainerInterface $container) => AppFactory::determineResponseFactory(),

    // The Slim RouterParser
    RouteParserInterface::class => fn (ContainerInterface $container) => $container->get(App::class)->getRouteCollector()->getRouteParser(),

    // Twig templates
    Twig::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['twig'];
        $twig = Twig::create($settings['paths'], $settings['options']);

        $environment = $twig->getEnvironment();

        $environment->addExtension(new HtmlExampleExtension());

        return $twig;
    },

    TwigMiddleware::class => fn (ContainerInterface $container) => TwigMiddleware::createFromContainer($container->get(App::class), Twig::class),
];
