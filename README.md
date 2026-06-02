# Guzzle OAuth Subscriber

Signs HTTP requests using OAuth 1.0. Requests are signed using a
consumer key, consumer secret, OAuth token, and OAuth secret.

This version works with Guzzle 7.11+ and PHP 7.2.5+.

## Installing

This project can be installed using Composer. Add the following to your
`composer.json`:

```json
{
    "require": {
        "guzzlehttp/oauth-subscriber": "^0.9.1"
    }
}
```

## Using the Subscriber

Here's an example showing how to send an authenticated request to the
Twitter REST API:

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

$stack = HandlerStack::create();

$middleware = new Oauth1([
    'consumer_key'    => 'my_key',
    'consumer_secret' => 'my_secret',
    'token'           => 'my_token',
    'token_secret'    => 'my_token_secret',
]);
$stack->push($middleware);

$client = new Client([
    'base_uri' => 'https://api.twitter.com/1.1/',
    'handler' => $stack,
]);

// Set the "auth" request option to "oauth" to sign using oauth
$res = $client->get('statuses/home_timeline.json', ['auth' => 'oauth']);
```

You can set the `auth` request option to `oauth` for all requests sent
by the client by extending the array you feed to `new Client` with auth
=> oauth.

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

$stack = HandlerStack::create();

$middleware = new Oauth1([
    'consumer_key'    => 'my_key',
    'consumer_secret' => 'my_secret',
    'token'           => 'my_token',
    'token_secret'    => 'my_token_secret',
]);
$stack->push($middleware);

$client = new Client([
    'base_uri' => 'https://api.twitter.com/1.1/',
    'handler' => $stack,
    'auth' => 'oauth',
]);

// Now you don't need to add the auth parameter
$res = $client->get('statuses/home_timeline.json');
```

You can override the `token` and `token_secret` values for an individual
request using the `oauth` request option. The `auth` request option must
still be set to `oauth` to enable signing for the request.

```php
$res = $client->get('statuses/home_timeline.json', [
    'auth' => 'oauth',
    'oauth' => [
        'token'        => 'request_token',
        'token_secret' => 'request_token_secret',
    ],
]);
```

Only `token` and `token_secret` are supported in the `oauth` request
option. Pass both values when switching to a different credential pair.
Do not pass OAuth credentials using Guzzle's array-based `auth` option,
which is reserved for Guzzle's built-in HTTP authentication handlers. If
you use custom retry middleware to refresh credentials, make sure retries
re-enter this middleware so each retry is signed with fresh OAuth
parameters. Custom middleware that runs before this middleware can still
see the `oauth` request option, so avoid logging request options that
contain secrets.

You can set the `token` and `token_secret` options to an empty string to
use two-legged OAuth.

## Using the RSA-SH1 signature method

```php
use GuzzleHttp\Subscriber\Oauth\Oauth1;

$stack = HandlerStack::create();

$middleware = new Oauth1([
    'consumer_key'           => 'my_key',
    'consumer_secret'        => 'my_secret',
    'private_key_file'       => 'my_path_to_private_key_file',
    'private_key_passphrase' => 'my_passphrase',
    'signature_method'       => Oauth1::SIGNATURE_METHOD_RSA,
]);
$stack->push($middleware);

$client = new Client([
    'handler' => $stack,
]);

$response = $client->get('https://httpbin.org/', ['auth' => 'oauth']);
```
