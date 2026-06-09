# OAuth 1.0 Middleware Usage

`GuzzleHttp\Subscriber\Oauth\Oauth1` signs outgoing Guzzle requests with OAuth 1.0 credentials. This page covers the middleware setup, request options, credential overrides, signature methods, and retry considerations for this package.

Use this package for APIs that require OAuth 1.0 request signing. It does not implement an OAuth 2.0 authorization flow or bearer-token client.

## OAuth 1.0 vs OAuth 2.0

OAuth 1.0 signs each HTTP request with a consumer key, consumer secret, optional token credentials, nonce, timestamp, and signature. `Oauth1` adds those OAuth parameters to the `Authorization` header by default, or to the query string when configured to do so.

OAuth 2.0 bearer-token APIs usually do not need this package. For those APIs, send the bearer token using normal Guzzle request options, for example `['headers' => ['Authorization' => 'Bearer ...']]`.

## Attaching Middleware

`Oauth1` is invokable [Guzzle middleware](https://github.com/guzzle/guzzle/blob/8.0/docs/handlers-and-middleware.md). Push it onto the client handler stack before sending signed requests:

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
    'base_uri' => 'https://api.example.com/',
    'handler' => $stack,
]);

$response = $client->get('resource', ['auth' => 'oauth']);
```

## Signing Requests

The OAuth middleware only signs a request when the Guzzle [`auth` request option](https://github.com/guzzle/guzzle/blob/8.0/docs/request-options.md#auth) is exactly `oauth`.

```php
$response = $client->post('resource', [
    'auth' => 'oauth',
    'form_params' => [
        'status' => 'example',
    ],
]);
```

Do not pass OAuth credentials using Guzzle's array-based `auth` option. Array-based `auth` is reserved for Guzzle's built-in HTTP authentication handlers.

By default, OAuth parameters are sent in the `Authorization` header. Set `request_method` to `Oauth1::REQUEST_METHOD_QUERY` to add OAuth parameters to the query string instead.

```php
$middleware = new Oauth1([
    'consumer_key'    => 'my_key',
    'consumer_secret' => 'my_secret',
    'token'           => 'my_token',
    'token_secret'    => 'my_token_secret',
    'request_method'  => Oauth1::REQUEST_METHOD_QUERY,
]);
```

## Client Default Auth

You can set `auth` to `oauth` as a client default when every request sent by that client should be signed.

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
    'base_uri' => 'https://api.example.com/',
    'handler' => $stack,
    'auth'    => 'oauth',
]);

$response = $client->get('resource');
```

Set `auth` to `null` on an individual request to disable a client default `auth => oauth` value for that request.

```php
$response = $client->get('public-resource', ['auth' => null]);
```

## Per-Request Credentials

You can override `token` and `token_secret` for an individual request using the `oauth` request option. The request must still use `auth => oauth` directly or through a client default.

```php
$response = $client->get('resource', [
    'auth' => 'oauth',
    'oauth' => [
        'token'        => 'request_token',
        'token_secret' => 'request_token_secret',
    ],
]);
```

Only `token` and `token_secret` are supported in the per-request `oauth` option. Unknown `oauth` keys are ignored. Pass both values when switching to a different credential pair because `token_secret` affects the signature but is never sent as an OAuth parameter.

Client default request options are honored. If a client has a default `oauth` array, a request-level `oauth` array replaces that default array. Set request `oauth` to `null` to ignore a client default `oauth` array and fall back to the constructor configuration.

Set request `oauth.token` or `oauth.token_secret` to `null` to remove that configured token value for the request. Empty strings are preserved and sent or used as empty credential values.

## Request Options

These request options control whether the middleware signs a request and whether token credentials are overridden:

| Option | Description |
|--------|-------------|
| `auth` | Must be exactly `oauth` for this middleware to sign the request. Any other value, including `null`, leaves the request unsigned by this middleware. |
| `oauth` | Optional array with `token` and `token_secret` overrides. Unknown keys are ignored. A non-array value throws an `InvalidArgumentException`. `null` falls back to constructor configuration. |

## Two-Legged OAuth

For two-legged OAuth, omit `token` and `token_secret`, set them to `null` with the per-request `oauth` option, or set them to empty strings if the service expects an empty `oauth_token` parameter.

```php
$middleware = new Oauth1([
    'consumer_key'    => 'my_key',
    'consumer_secret' => 'my_secret',
]);
```

## Constructor Options

The `Oauth1` constructor accepts these options:

| Option | Description | Default |
|--------|-------------|---------|
| `request_method` | Where OAuth parameters are added. Use `Oauth1::REQUEST_METHOD_HEADER` (`header`) or `Oauth1::REQUEST_METHOD_QUERY` (`query`). | `header` |
| `consumer_key` | OAuth consumer key. | `anonymous` |
| `consumer_secret` | OAuth consumer secret used to sign requests. | `anonymous` |
| `token` | OAuth token sent as `oauth_token` when set. | Not set |
| `token_secret` | OAuth token secret used for HMAC signatures when set. This value is not sent. | Not set |
| `callback` | OAuth callback sent as `oauth_callback` when set. | Not set |
| `verifier` | OAuth verifier sent as `oauth_verifier` when set. | Not set |
| `version` | OAuth version sent as `oauth_version`. | `1.0` |
| `realm` | Realm added to the `Authorization` header when using header mode. | Not set |
| `bodyhash` | OAuth body hash sent as `oauth_body_hash` when set. | Not set |
| `signature_method` | Signature method. Use `HMAC-SHA1`, `HMAC-SHA256`, `RSA-SHA1`, or `PLAINTEXT`. | `HMAC-SHA1` |
| `private_key_file` | Path to an RSA private key file. Required only when `signature_method` is `RSA-SHA1`. | Not set |
| `private_key_passphrase` | Passphrase for `private_key_file`. Used only with `RSA-SHA1`. | Not set |

Unknown constructor options are stored but are not sent as OAuth parameters.

## Signature Methods

Set the `signature_method` constructor option to change how requests are signed.

### HMAC-SHA1 and HMAC-SHA256

`HMAC-SHA1` is the default signature method. `HMAC-SHA256` is also supported for services that require it.

```php
$middleware = new Oauth1([
    'consumer_key'     => 'my_key',
    'consumer_secret'  => 'my_secret',
    'token'            => 'my_token',
    'token_secret'     => 'my_token_secret',
    'signature_method' => Oauth1::SIGNATURE_METHOD_HMACSHA256,
]);
```

HMAC signatures use `consumer_secret` and, when set, `token_secret` as the signing key material.

### RSA-SHA1

Use `RSA-SHA1` when the service expects signatures generated with an RSA private key. The PHP OpenSSL extension must be available, and `private_key_file` must point to a readable private key.

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

$stack = HandlerStack::create();
$stack->push(new Oauth1([
    'consumer_key'           => 'my_key',
    'consumer_secret'        => 'my_secret',
    'private_key_file'       => '/path/to/private-key.pem',
    'private_key_passphrase' => 'my_passphrase',
    'signature_method'       => Oauth1::SIGNATURE_METHOD_RSA,
]));

$client = new Client([
    'base_uri' => 'https://api.example.com/',
    'handler'  => $stack,
]);

$response = $client->get('resource', ['auth' => 'oauth']);
```

`private_key_passphrase` is optional. Omit it for unencrypted private key files.

### PLAINTEXT

`PLAINTEXT` uses the prepared OAuth signature base string as the signature input. Use it only when required by the service and only over TLS.

```php
$middleware = new Oauth1([
    'consumer_key'     => 'my_key',
    'consumer_secret'  => 'my_secret',
    'token'            => 'my_token',
    'token_secret'     => 'my_token_secret',
    'signature_method' => Oauth1::SIGNATURE_METHOD_PLAINTEXT,
]);
```

## Secret Handling and Retries

OAuth credentials are secrets. Avoid logging request options that contain `oauth`, `consumer_secret`, `token`, or `token_secret` values.

For signed requests, the middleware removes the per-request `oauth` option before passing the request to the next handler, but custom middleware that runs before `Oauth1` can still inspect it. If a request includes `oauth` options without `'auth' => 'oauth'`, those options are not consumed by this middleware. Place logging middleware so it does not record sensitive request options.

If custom retry middleware refreshes credentials, make sure each retry re-enters this middleware. Otherwise the retried request might reuse an old signature, nonce, timestamp, or token value.

## Related

- [Guzzle handlers and middleware](https://github.com/guzzle/guzzle/blob/8.0/docs/handlers-and-middleware.md)
- [Guzzle `auth` request option](https://github.com/guzzle/guzzle/blob/8.0/docs/request-options.md#auth)
- [Upgrade Guide](../UPGRADING.md)
- [Changelog](../CHANGELOG.md)
