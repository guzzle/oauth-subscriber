=======================
Guzzle OAuth Subscriber
=======================

Signs HTTP requests using OAuth 1.0. Requests are signed using a consumer key,
consumer secret, OAuth token, and OAuth secret.

Here's an example showing how to send an authenticated request to the Twitter
REST API:

.. code-block:: php

    use GuzzleHttp\Client;
    use GuzzleHttp\Subscriber\Oauth\OauthSubscriber;

    $client = new Client(['base_url' => 'http://api.twitter.com/1.1']);

    $oauth = new OauthSubscriber([
        'consumer_key'    => 'my_key',
        'consumer_secret' => 'my_secret',
        'token'           => 'my_token',
        'token_secret'    => 'my_token_secret'
    ]);

    $client->getEmitter()->addSubscriber($oauth);

    // Set the "auth" request option to "oauth" to sign using oauth
    $res = $client->get('statuses/home_timeline.json', ['auth' => 'oauth']);

You can set the ``auth`` request option to ``oauth`` for all requests sent by
the client using the client's ``defaults`` constructor option.

.. code-block:: php

    use GuzzleHttp\Client;

    $client = new Client([
        'base_url' => 'http://api.twitter.com/1.1',
        'defaults' => ['auth' => 'oauth']
    ]);

    $client->getEmitter()->addSubscriber($oauth);

    // Now you don't need to add the auth parameter
    $res = $client->get('statuses/home_timeline.json');

.. note::

    You can omit the token and token_secret options to use two-legged OAuth.
