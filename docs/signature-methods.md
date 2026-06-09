# Signature Methods

## Using the RSA-SH1 Signature Method

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
