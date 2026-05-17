<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/Server.php';

GuzzleHttp\Tests\Oauth1\Server::start();

register_shutdown_function(static function (): void {
    GuzzleHttp\Tests\Oauth1\Server::stop();
});
