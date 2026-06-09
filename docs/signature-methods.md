# Signature Methods

The default OAuth 1.0 signature method signs requests with shared secrets. This package also supports RSA-SH1 when OpenSSL is available.

## RSA-SH1

Pass `signature_method`, `private_key_file`, and optionally `private_key_passphrase` when creating the middleware.

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

$stack = HandlerStack::create();
$stack->push(new Oauth1([
    'consumer_key' => 'my_key',
    'consumer_secret' => 'my_secret',
    'private_key_file' => '/path/to/private-key.pem',
    'private_key_passphrase' => 'passphrase',
    'signature_method' => Oauth1::SIGNATURE_METHOD_RSA,
]));

$client = new Client(['handler' => $stack]);
$response = $client->get('https://api.example.com/resource', ['auth' => 'oauth']);
```

RSA-SH1 signing requires the `ext-openssl` PHP extension.
