<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Oauth1;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class Oauth1Test extends TestCase
{
    public const TIMESTAMP = '1327274290';
    public const NONCE = 'e7aa11195ca58349bec8b5ebe351d3497eb9e603';

    private $config = [
        'consumer_key' => 'foo',
        'consumer_secret' => 'bar',
        'token' => 'count',
        'token_secret' => 'dracula',
    ];

    public function testAcceptsConfigurationData(): void
    {
        $p = new Oauth1($this->config);

        $class = new \ReflectionClass($p);

        $property = $class->getProperty('config');

        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }

        $config = $property->getValue($p);

        $this->assertEquals('foo', $config['consumer_key']);
        $this->assertEquals('bar', $config['consumer_secret']);
        $this->assertEquals('count', $config['token']);
        $this->assertEquals('dracula', $config['token_secret']);
        $this->assertEquals('1.0', $config['version']);
        $this->assertEquals('HMAC-SHA1', $config['signature_method']);
        $this->assertEquals('header', $config['request_method']);
    }

    public function testCreatesStringToSignFromPostRequest(): void
    {
        $stack = HandlerStack::create();

        $middleware = new Oauth1($this->config);
        $stack->push($middleware);

        $container = [];
        $history = Middleware::history($container);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->post('https://httpbin.org/post', [
            'auth' => 'oauth',
            'form_params' => [
                'foo' => [
                    'baz' => ['bar'],
                    'bam' => [null, true, false],
                ],
            ],
        ]);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
    }

    public function testExcludesOauthSignatureFromFormBodySignature(): void
    {
        $oauth = new Oauth1($this->config);
        $params = [
            'oauth_consumer_key' => 'foo',
            'oauth_nonce' => self::NONCE,
            'oauth_signature_method' => Oauth1::SIGNATURE_METHOD_HMAC,
            'oauth_timestamp' => self::TIMESTAMP,
            'oauth_token' => 'count',
            'oauth_version' => '1.0',
        ];

        $request = new Request(
            'POST',
            'https://httpbin.org/post',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'foo=bar'
        );
        $requestWithSignature = new Request(
            'POST',
            'https://httpbin.org/post',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'foo=bar&oauth_signature=bad'
        );

        $this->assertSame(
            $oauth->getSignature($request, $params),
            $oauth->getSignature($requestWithSignature, $params)
        );
    }

    public function testExcludesOauthSignatureFromQuerySignature(): void
    {
        $oauth = new Oauth1($this->config);
        $params = [
            'oauth_consumer_key' => 'foo',
            'oauth_nonce' => self::NONCE,
            'oauth_signature_method' => Oauth1::SIGNATURE_METHOD_HMAC,
            'oauth_timestamp' => self::TIMESTAMP,
            'oauth_token' => 'count',
            'oauth_version' => '1.0',
        ];

        $request = new Request('GET', 'https://httpbin.org/get?foo=bar');
        $requestWithSignature = new Request('GET', 'https://httpbin.org/get?foo=bar&oauth_signature=bad');

        $this->assertSame(
            $oauth->getSignature($request, $params),
            $oauth->getSignature($requestWithSignature, $params)
        );
    }

    public function testSignsPlainText(): void
    {
        $config = $this->config;
        $config['signature_method'] = Oauth1::SIGNATURE_METHOD_PLAINTEXT;

        $stack = HandlerStack::create();

        $middleware = new Oauth1($config);
        $stack->push($middleware);

        $container = [];
        $history = Middleware::history($container);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->get('https://httpbin.org', ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains('oauth_signature_method="PLAINTEXT"', false), '');
        $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains('oauth_signature="', false), '');
    }

    public function testSignsOauthRequestsInHeader(): void
    {
        $stack = HandlerStack::create();

        $middleware = new Oauth1($this->config);
        $stack->push($middleware);

        $container = [];
        $history = Middleware::history($container);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->post('https://httpbin.org/post', [
            'auth' => 'oauth',
        ]);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertCount(0, Query::parse($request->getUri()->getQuery()));
        $check = ['oauth_consumer_key', 'oauth_nonce', 'oauth_signature',
            'oauth_signature_method', 'oauth_timestamp', 'oauth_token',
            'oauth_version'];
        foreach ($check as $name) {
            $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains($name.'=', false), '');
        }
    }

    public function testSignsOauthQueryStringRequest(): void
    {
        $config = $this->config;
        $config['request_method'] = Oauth1::REQUEST_METHOD_QUERY;

        $stack = HandlerStack::create();

        $middleware = new Oauth1($config);
        $stack->push($middleware);

        $container = [];
        $history = Middleware::history($container);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->get('https://httpbin.org', ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertFalse($request->hasHeader('Authorization'));
        $check = ['oauth_consumer_key', 'oauth_nonce', 'oauth_signature',
            'oauth_signature_method', 'oauth_timestamp', 'oauth_token',
            'oauth_version'];
        foreach ($check as $name) {
            $this->assertNotEmpty(Query::parse($request->getUri()->getQuery())[$name]);
        }

        // Ensure that no extra keys were added
        $keys = array_keys(Query::parse($request->getUri()->getQuery()));
        sort($keys);
        $this->assertSame($keys, $check);
    }

    public function testOnlyTouchesWhenAuthConfigIsOauth(): void
    {
        $stack = HandlerStack::create();

        $middleware = new Oauth1($this->config);
        $stack->push($middleware);

        $container = [];
        $history = Middleware::history($container);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->get('https://httpbin.org');

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertCount(0, Query::parse($request->getUri()->getQuery()));
        $this->assertEmpty($request->getHeader('Authorization'));
    }

    public function testValidatesRequestMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        if (method_exists($this, 'expectException')) {
            $this->expectException(\InvalidArgumentException::class);
        }

        $stack = HandlerStack::create();

        $config = $this->config;
        $config['request_method'] = 'Foo';

        $middleware = new Oauth1($config);
        $stack->push($middleware);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->get('https://httpbin.org', ['auth' => 'oauth']);
    }

    public function testExceptionOnSignatureError(): void
    {
        $this->expectException(\RuntimeException::class);

        if (method_exists($this, 'expectException')) {
            $this->expectException(\RuntimeException::class);
        }

        $stack = HandlerStack::create();

        $config = $this->config;
        $config['signature_method'] = 'Foo';

        $middleware = new Oauth1($config);
        $stack->push($middleware);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->get('https://httpbin.org', ['auth' => 'oauth']);
    }

    public function testExceptionOnMissingRsaPrivateKeyFileOption(): void
    {
        if (!function_exists('openssl_pkey_get_private')) {
            $this->markTestSkipped('OpenSSL extension is not available.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RSA-SHA1 signature method requires a private_key_file option.');

        $config = $this->config;
        $config['signature_method'] = Oauth1::SIGNATURE_METHOD_RSA;

        $middleware = new Oauth1($config);

        $middleware->getSignature(new Request('GET', 'https://httpbin.org'), []);
    }

    public function testExceptionOnMissingRsaPrivateKeyFile(): void
    {
        if (!function_exists('openssl_pkey_get_private')) {
            $this->markTestSkipped('OpenSSL extension is not available.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read RSA private key file');

        $config = $this->config;
        $config['signature_method'] = Oauth1::SIGNATURE_METHOD_RSA;
        $config['private_key_file'] = __DIR__.'/missing-private-key.pem';

        $middleware = new Oauth1($config);

        $middleware->getSignature(new Request('GET', 'https://httpbin.org'), []);
    }

    public function testExceptionOnInvalidRsaPrivateKeyFile(): void
    {
        if (!function_exists('openssl_pkey_get_private')) {
            $this->markTestSkipped('OpenSSL extension is not available.');
        }

        $privateKeyFile = tempnam(sys_get_temp_dir(), 'oauth1-key-');
        $this->assertNotFalse($privateKeyFile);

        $this->assertNotFalse(file_put_contents($privateKeyFile, 'not a private key'));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unable to parse RSA private key.');

            $config = $this->config;
            $config['signature_method'] = Oauth1::SIGNATURE_METHOD_RSA;
            $config['private_key_file'] = $privateKeyFile;

            $middleware = new Oauth1($config);

            $middleware->getSignature(new Request('GET', 'https://httpbin.org'), []);
        } finally {
            @unlink($privateKeyFile);
        }
    }

    public function testSignsRsaSha1(): void
    {
        if (!function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('OpenSSL extension is not available.');
        }

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($privateKey === false) {
            $this->markTestSkipped('Unable to generate RSA private key.');
        }

        $privateKeyContents = '';
        if (!openssl_pkey_export($privateKey, $privateKeyContents)) {
            $this->markTestSkipped('Unable to export RSA private key.');
        }

        $privateKeyFile = tempnam(sys_get_temp_dir(), 'oauth1-key-');
        $this->assertNotFalse($privateKeyFile);

        $this->assertNotFalse(file_put_contents($privateKeyFile, $privateKeyContents));

        try {
            $config = $this->config;
            $config['signature_method'] = Oauth1::SIGNATURE_METHOD_RSA;
            $config['private_key_file'] = $privateKeyFile;

            $middleware = new Oauth1($config);

            $signature = $middleware->getSignature(new Request('GET', 'https://httpbin.org'), []);

            $this->assertNotSame('', $signature);
            $this->assertNotFalse(base64_decode($signature, true));
        } finally {
            @unlink($privateKeyFile);
        }
    }

    public function testDoesNotAddEmptyValuesToAuthorization(): void
    {
        $config = $this->config;
        unset($config['token']);

        $stack = HandlerStack::create();

        $middleware = new Oauth1($config);
        $stack->push($middleware);

        $container = [];
        $history = Middleware::history($container);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->get('https://httpbin.org', ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertThat($request->getHeader('Authorization')[0], Assert::logicalNot(Assert::stringContains('oauth_token=', false)), '');
    }

    public function testRandomParametersAreNotAutomaticallyAdded(): void
    {
        $config = $this->config;
        $config['foo'] = 'bar';

        $stack = HandlerStack::create();

        $middleware = new Oauth1($config);
        $stack->push($middleware);

        $container = [];
        $history = Middleware::history($container);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->get('https://httpbin.org', ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertThat($request->getHeader('Authorization')[0], Assert::logicalNot(Assert::stringContains('foo=bar', false)), '');
    }

    public function testAllowsRealm(): void
    {
        $config = $this->config;
        $config['realm'] = 'foo';

        $stack = HandlerStack::create();

        $middleware = new Oauth1($config);
        $stack->push($middleware);

        $container = [];
        $history = Middleware::history($container);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->get('https://httpbin.org', ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains('OAuth realm="foo",', false), '');
    }

    public function testSignsHmacSha256(): void
    {
        $config = $this->config;
        $config['signature_method'] = Oauth1::SIGNATURE_METHOD_HMACSHA256;

        $stack = HandlerStack::create();

        $middleware = new Oauth1($config);
        $stack->push($middleware);

        $container = [];
        $history = Middleware::history($container);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $client->get('https://httpbin.org', ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains('oauth_signature_method="HMAC-SHA256"', false), '');
        $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains('oauth_signature="', false), '');
    }
}
