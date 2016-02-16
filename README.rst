[![Latest Stable Version](https://poser.pugx.org/serendipity_hq/guzzle-oauth1-middleware/v/stable)](https://packagist.org/packages/serendipity_hq/guzzle-oauth1-middleware)

[![Total Downloads](https://poser.pugx.org/serendipity_hq/guzzle-oauth1-middleware/downloads)](https://packagist.org/packages/serendipity_hq/guzzle-oauth1-middleware)
[![License](https://poser.pugx.org/serendipity_hq/guzzle-oauth1-middleware/license)](https://packagist.org/packages/serendipity_hq/guzzle-oauth1-middleware)

=======================
Guzzle OAuth Subscriber
=======================

Signs HTTP requests using OAuth 1.0. Requests are signed using a consumer key,
consumer secret, OAuth token, and OAuth secret.

This version only works with Guzzle 6.0 and up!

Installing
==========

This project can be installed using Composer. Add the following to your
composer.json:

.. code-block:: javascript

    {
        "require": {
            "serendipity_hq/guzzle-oauth1-middleware": "~0.3"
        }
    }



Using the Subscriber
====================

Here's an example showing how to send an authenticated request to the Twitter
REST API:

.. code-block:: php

    use GuzzleHttp\Client;
    use GuzzleHttp\HandlerStack;
    use GuzzleHttp\Subscriber\Oauth\Oauth1;

    $stack = HandlerStack::create();

    $middleware = new Oauth1([
        'consumer_key'    => 'my_key',
        'consumer_secret' => 'my_secret',
        'token'           => 'my_token',
        'token_secret'    => 'my_token_secret'
    ]);
    $stack->push($middleware);

    $client = new Client([
        'base_uri' => 'https://api.twitter.com/1.1/',
        'handler' => $stack
    ]);

    // Set the "auth" request option to "oauth" to sign using oauth
    $res = $client->get('statuses/home_timeline.json', ['auth' => 'oauth']);

You can set the ``auth`` request option to ``oauth`` for all requests sent by
the client by extending the array you feed to ``new Client`` with auth => oauth.

.. code-block:: php

    use GuzzleHttp\Client;
    use GuzzleHttp\HandlerStack;
    use GuzzleHttp\Subscriber\Oauth\Oauth1;

    $stack = HandlerStack::create();

    $middleware = new Oauth1([
        'consumer_key'    => 'my_key',
        'consumer_secret' => 'my_secret',
        'token'           => 'my_token',
        'token_secret'    => 'my_token_secret'
    ]);
    $stack->push($middleware);

    $client = new Client([
        'base_uri' => 'https://api.twitter.com/1.1/',
        'handler' => $stack,
        'auth' => 'oauth'
    ]);

    // Now you don't need to add the auth parameter
    $res = $client->get('statuses/home_timeline.json');

.. note::

    You can omit the token and token_secret options to use two-legged OAuth.

Using the RSA-SH1 signature method
==================================

.. code-block:: php

    use GuzzleHttp\Subscriber\Oauth\Oauth1;

    $stack = HandlerStack::create();

    $middleware = new Oauth1([
        'consumer_key'    => 'my_key',
        'consumer_secret' => 'my_secret',
        'private_key_file' => 'my_path_to_private_key_file',
        'private_key_passphrase' => 'my_passphrase',
        'signature_method' => Oauth1::SIGNATURE_METHOD_RSA,
    ]);
    $stack->push($middleware);

    $client = new Client([
        'handler' => $stack
    ]);

    $response = $client->get('http://httpbin.org', ['auth' => 'oauth']);
