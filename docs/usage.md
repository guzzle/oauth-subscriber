# OAuth 1.0 Middleware Usage

`GuzzleHttp\Subscriber\Oauth\Oauth1` is invokable Guzzle middleware. It wraps a standard Guzzle handler and signs requests when the request `auth` option is set to `oauth`.

## Per-Request Signing

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

$stack = HandlerStack::create();
$stack->push(new Oauth1([
    'consumer_key' => 'my_key',
    'consumer_secret' => 'my_secret',
    'token' => 'my_token',
    'token_secret' => 'my_token_secret',
]));

$client = new Client([
    'base_uri' => 'https://api.example.com/',
    'handler' => $stack,
]);

$response = $client->get('resource', ['auth' => 'oauth']);
```

## Client-Wide Signing

Set the `auth` request option as a client default when every request sent by the client should be signed.

```php
$client = new Client([
    'base_uri' => 'https://api.example.com/',
    'handler' => $stack,
    'auth' => 'oauth',
]);

$response = $client->get('resource');
```

## Per-Request Tokens

Override `token` and `token_secret` for an individual request using the `oauth` request option. The `auth` request option must still be set to `oauth` to enable signing.

```php
$response = $client->get('resource', [
    'auth' => 'oauth',
    'oauth' => [
        'token' => 'request_token',
        'token_secret' => 'request_token_secret',
    ],
]);
```

Only `token` and `token_secret` are supported in the `oauth` request option. Pass both values when switching to a different credential pair.

## Two-Legged OAuth

Set `token` and `token_secret` to empty strings to use two-legged OAuth.

## Security Notes

OAuth credentials are secrets. Do not pass OAuth credentials using Guzzle's array-based `auth` option, which is reserved for Guzzle's built-in HTTP authentication handlers.

If you use custom retry middleware to refresh credentials, make sure retries re-enter this middleware so each retry is signed with fresh OAuth parameters. Custom middleware that runs before this middleware can still see the `oauth` request option, so avoid logging request options that contain secrets.
