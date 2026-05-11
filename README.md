# IndieWebify.me

This is an in-progress migration of the existing IndieWebify.me code from Silex to SlimPHP.

## Installation

Requirements:

- PHP 8.5

Installation

- Set the domain's document root to the `/public` directory
- Configure the server to route requests through `/public/index.php` if they don’t match a file
    - If you are running Apache, do this by renaming `/public/htaccess.txt` to `/public/.htaccess`
- Run `composer install`

### Docker

You can alternatively run this using Docker.

Install Docker first:

- macOS: install Docker Desktop or [Colima](https://colima.run/#quick-start) (recommended).
- Linux: install [Docker Engine](https://docs.docker.com/engine/install/), it should include the Docker Compose plugin.

Then run the app:

```sh
docker compose up -d
```

The site will be available at [http://localhost:8080](http://localhost:8080).

Set `APP_PORT` to use a different host port, for example `APP_PORT=8081 docker compose up --build`.
You can set this in an `.env` file, copy the example to get started: `cp .env.example .env`.

The compose setup will mount the local directories into the container so changes to code are synced.

If you change any of the following:

```
Dockerfile
composer.json
composer.lock
patches/
docker/apache/000-default.conf
```

Then you should run

```sh
docker compose up -d --build
```

so the changes are included in the container.

## Development Notes

> written by Gregor

I chose [SlimPHP Framework v4](https://www.slimframework.com/) since it feels lighter weight than alternatives like Laravel and Symfony. Slim implements several of the [PHP FIG](https://www.php-fig.org/) interop standards, which I think will make the code more portable in the future. I have developed other projects in Slim like indiebookclub.biz and some projects for work. I've found it pretty easy to work with.

For templating, I've used [Twig](https://twig.symfony.com/doc/3.x/). The previous version of indiewebify.me has some complex conditional logic in the PHP templates, so one of my goals was to split off as much of that possible into the Slim app instad of in templates. I also like the auto-escaping that Twig offers.

### App structure

I started with an MVC setup, though we don't currently have need for a database so I guess it's just VC (no no, not _that_ kind).

I used [PHP-DI](https://php-di.org/) for dependency injection, which makes it easier to inject dependencies wherever they are needed.

The `/config` directory is where all the app configuration and startup happens:

- bootstrap.php: build the PHP-DI container, create an instance of the Slim app, register URL routes, and register middleware
- container.php: used for DI. This file returns an array of classes and how they're instantiated when they're injected. Not changed often, unless a custom Twig templating function needs to be added.
- middleware.php: See Slim documentation for Middleware. Not changed often.
- routes.php: Register URL routes, the methods they accept (GET, POST, etc.), and the Controller methods that handle each route.
- settings.php: returns an array of general app settings. These are commonly used in container.php

The `/src` directory has the bulk of the app code. The folders and filenames in there follow [PSR-4](https://www.php-fig.org/psr/psr-4/) so they can be autoloaded. Currently these directories are `Controllers`, `Responder`, and `Service` since that was the design pattern I was following. Or, uh, hybrid design pattern? :) My point being, it's fine to use different naming schemes for the folders as long as it logically follows the design pattern you're using, and the classes within them use corresponding namespaces.

The `/templates` directory has the Twig templates. The `/templates/pages` contains individual page templates. I followed this blog post for setting up the template file structure: https://nystudio107.com/blog/an-effective-twig-base-templating-setup

### Further migration work

> written by Paul

After reading a message Gregor posted in the #indieweb-dev channel, I ([paultibbetts.uk](https://paultibbetts.uk/)) spent this weekend continuing the migration.

My aims are:

- migrate indiewebify-me to a maintained PHP framework
- update to the latest version of PHP
- make it easier for new contributors to get this up and running on their machine
- create a test suite
- - to verify this migration is feature-complete
- - to provide guardrails for new contributors
- maintain frontend styling so it doesn't look any different to before
- make indiewebify-me a good target for a future [IndieWeb Hackathon](https://indieweb.org/IndieWeb_Hackathon#Requested_Projects)

I am not aiming to add new features or fix any of the (currently) 50 issues open on the repo, but I would like this migration to make it easier for future work to happen that does do those things.

#### Progress so far

- add composer.json
- update to PHP 8.5
- - repo currently includes patches for dependencies not updated yet
- - I will submit these patches as PRs closer to completion
- add tooling to aid migration
- - see scripts in composer.json
- add a docker compose setup for local development
- - this is really simple right now
- - and is not intended for production
- port Flat UI to a Bootstrap 5 theme
- - not perfect, but (mostly) maintains previous style
- - changing the frontend is not a goal of this migration
- - - but facilitating a potential future change is
- add the index page back
- ported /send-webmentions from the old version
- add the "pagination" back
- - accidentally fixed level 3 not being included in the pagination
- started a test suite
- - /validate-rel-me
- - /send-webmentions
- fixed a few minor errors with the original version
- - typos and whitespace
- - removed mention of `rel="in-reply-to"` (#97)

See [here](https://github.com/gRegorLove/indiewebify-me/compare/slim-migration...paultibbetts:indiewebify-me:slim-migration) for all changes.

#### Planned

- [x] finish validate-h-entry
- - with minimal post type discovery
- [x] tests for validate-h-card
- [x] tests for validate-h-entry
- [x] tests for index page
- [ ] tests for edge-cases
- [ ] ensure frontend matches old version as much as possible
- [ ] beginner-friendly documentation
- [ ] merge into indieweb/indiewebify-me
- [ ] host an IndieWeb Hackathon?

## Migration Questions

### http as default scheme?

Should default scheme be `https` now?

pros:
- https is the new standard (?)

cons:
- http -> https redirect should be working on all sites

### what is a minimal h-card?

The example in the template looks old. is that mf1?

The [wiki](https://indieweb.org/h-card#How_to_markup) does not include `u-url`?

I am leaving the content as it is, but leaving this here to remind me.

### does this need /rel-me-links and /rel-me-links-info ?

I want to maintain features from the silex version for backwards compatability/bookmarked URLs, but I'm not sure about these two endpoints.

### should the webmention sender block if no h-entry found?

The old version sent webmentions and then warned that no h-entry was found on the page. I have changed it to require a valid h-entry before sending webmentions.

pros:
- this step comes after "have a valid h-entry" step, and as part of the validator it makes more sense (to me) to require the previous step to be complete
- looking at the issues on indiewebify-me there is discussion about sending users to other webmention sending services anyway

cons:
- this change does not preserve the behaviour of the old app
- users might want to use this to send webmentions even if they do not implement h-entry
- - but that does not fit into the intent of indiewebify-me?

## Migration Observations

### hugo does not inject the generator on pages that aren't the homepage

Which means the h-entry site hint check for hugo (and possibly others) doesn't do anything.

## Credits

Originally made by Brennan Novak, Barnaby Walters, and others at the 2013 IndieWebCamps in [Reykjavik](http://indieweb.org/2013/#Remote_Indiewebcamp_Parties) and [Brighton](http://indieweb.org/2013/UK).
