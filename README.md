# Guzzle OAuth Subscriber

`guzzlehttp/oauth-subscriber` is OAuth 1.0 middleware for Guzzle. It signs outgoing Guzzle requests with OAuth credentials when the request uses the `auth` option value `oauth`.

Use this package when an API requires OAuth 1.0 request signing. If an API uses OAuth 2.0 bearer tokens, you usually do not need this package; send an `Authorization: Bearer ...` header with Guzzle instead.

## Installation

```bash
composer require guzzlehttp/oauth-subscriber
```

## Quick Start

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

You can also set `'auth' => 'oauth'` as a client default when every request sent by that client should be signed.

## Documentation

- [Full documentation](docs/index.md)
- [Using the subscriber](docs/index.md#using-the-subscriber)
- [RSA-SH1 signatures](docs/index.md#using-the-rsa-sh1-signature-method)
- [Upgrade guide](UPGRADING.md)

## Version Guidance

| Version | Status       | PHP Version  |
|---------|--------------|--------------|
| 1.x     | Experimental | >=7.4,<8.6   |
| 0.9     | Latest       | >=7.2.5,<8.6 |

## Security

OAuth credentials are secrets. Avoid logging request options that contain `oauth` values, and make sure retry middleware re-enters this middleware when refreshed credentials must be used.

If you discover a security vulnerability within this package, please send an email to security@tidelift.com. All security vulnerabilities will be promptly addressed. Please do not disclose security-related issues publicly until a fix has been announced. Please see [Security Policy](https://github.com/guzzle/oauth-subscriber/security/policy) for more information.

## License

Guzzle OAuth Subscriber is made available under the MIT License (MIT). Please see [License File](LICENSE) for more information.
