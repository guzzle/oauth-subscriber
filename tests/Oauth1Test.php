<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Oauth1;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Server\Server;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Oauth1Test extends TestCase
{
    public const TIMESTAMP = '1327274290';
    public const NONCE = 'e7aa11195ca58349bec8b5ebe351d3497eb9e603';

    private array $config = [
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

    public function testRejectsNativePhpSerializationWithRuntimeClassName(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(Oauth1SerializationTestDouble::class.' should never be serialized');

        serialize(new Oauth1SerializationTestDouble($this->config));
    }

    public function testRejectsNativePhpUnserializationWithRuntimeClassName(): void
    {
        $class = Oauth1SerializationTestDouble::class;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($class.' should never be unserialized');

        unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class));
    }

    public function testCreatesStringToSignFromPostRequest(): void
    {
        $container = [];
        $client = $this->createServerClientWithHistory(new Oauth1($this->config), $container);

        $client->post(Server::$url.'post', [
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

    /**
     * @dataProvider nonFiniteFloatProvider
     */
    public function testRejectsNonFiniteFloatParameters(float $value): void
    {
        $oauth = new Oauth1($this->config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-finite floats are not supported in OAuth parameters.');
        $oauth->getSignature(new Request('POST', 'http://example.com/'), ['score' => $value]);
    }

    public static function nonFiniteFloatProvider(): array
    {
        return [
            'NAN' => [\NAN],
            'INF' => [\INF],
            '-INF' => [-\INF],
        ];
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

    public function testSignsParameterizedFormContentType(): void
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
        $expected = 'E/WAxZzxBlCa7o/phy7QC3Ogp10=';

        $exact = new Request(
            'POST',
            'https://httpbin.org/post',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'foo=bar'
        );
        $parameterized = new Request(
            'POST',
            'https://httpbin.org/post',
            ['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'],
            'foo=bar'
        );
        $uppercase = new Request(
            'POST',
            'https://httpbin.org/post',
            ['Content-Type' => 'APPLICATION/X-WWW-FORM-URLENCODED; charset=UTF-8'],
            'foo=bar'
        );
        $ows = new Request(
            'POST',
            'https://httpbin.org/post',
            ['Content-Type' => "application/x-www-form-urlencoded \t; charset=UTF-8"],
            'foo=bar'
        );

        $this->assertSame($expected, $oauth->getSignature($exact, $params));
        $this->assertSame($expected, $oauth->getSignature($parameterized, $params));
        $this->assertSame($expected, $oauth->getSignature($uppercase, $params));
        $this->assertSame($expected, $oauth->getSignature($ows, $params));
    }

    public function testSignsBareFormBodyParametersAsEmptyValues(): void
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
            'field'
        );
        $requestWithEmptyValue = new Request(
            'POST',
            'https://httpbin.org/post',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'field='
        );
        $requestWithoutBody = new Request(
            'POST',
            'https://httpbin.org/post',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            ''
        );
        $signature = $oauth->getSignature($request, $params);

        $this->assertSame(
            $oauth->getSignature($requestWithEmptyValue, $params),
            $signature
        );
        $this->assertNotSame(
            $oauth->getSignature($requestWithoutBody, $params),
            $signature
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

    public function testSignsBareQueryStringParametersAsEmptyValues(): void
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

        $request = new Request('GET', 'https://httpbin.org/get?querystring');
        $requestWithEmptyValue = new Request('GET', 'https://httpbin.org/get?querystring=');
        $requestWithoutQuery = new Request('GET', 'https://httpbin.org/get');

        $this->assertSame(
            $oauth->getSignature($requestWithEmptyValue, $params),
            $oauth->getSignature($request, $params)
        );
        $this->assertNotSame(
            $oauth->getSignature($requestWithoutQuery, $params),
            $oauth->getSignature($request, $params)
        );
    }

    public function testSignsDuplicateBareQueryStringParametersAsEmptyValues(): void
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

        $request = new Request('GET', 'https://httpbin.org/get?field&field=value');
        $requestWithEmptyValue = new Request('GET', 'https://httpbin.org/get?field=&field=value');
        $requestWithReversedValues = new Request('GET', 'https://httpbin.org/get?field=value&field');

        $this->assertSame(
            $oauth->getSignature($requestWithEmptyValue, $params),
            $oauth->getSignature($request, $params)
        );
        $this->assertSame(
            $oauth->getSignature($requestWithEmptyValue, $params),
            $oauth->getSignature($requestWithReversedValues, $params)
        );
    }

    public function testSortsDuplicateNumericQueryStringParameterValuesAsEncodedStrings(): void
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

        $request = new Request('GET', 'https://httpbin.org/get?field=2&field=10&field=100');

        $this->assertSame(
            'j4XnAZ8btzl0XLximiYzknCKQiU=',
            $oauth->getSignature($request, $params)
        );
    }

    public function testSortsDuplicateQueryStringParameterValuesByEncodedValue(): void
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

        $request = new Request('GET', 'https://httpbin.org/get?field=A&field=%60');

        $this->assertSame(
            '7iuquCJ3mkhCQoCtLxLhL94+gjQ=',
            $oauth->getSignature($request, $params)
        );
    }

    public function testSortsDuplicateFormBodyParameterValuesByEncodedValue(): void
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
            'field=A&field=%60'
        );

        $this->assertSame(
            '2IP6IMFoTQcHwVmuCL/TYKC6pI8=',
            $oauth->getSignature($request, $params)
        );
    }

    public function testSignsPlainText(): void
    {
        $config = $this->config;
        $config['signature_method'] = Oauth1::SIGNATURE_METHOD_PLAINTEXT;

        $container = [];
        $client = $this->createServerClientWithHistory(new Oauth1($config), $container);

        $client->get(Server::$url, ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains('oauth_signature_method="PLAINTEXT"', false), '');
        $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains('oauth_signature="', false), '');
    }

    public function testSignsOauthRequestsInHeader(): void
    {
        $container = [];
        $client = $this->createServerClientWithHistory(new Oauth1($this->config), $container);

        $client->post(Server::$url.'post', [
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

        $container = [];
        $client = $this->createServerClientWithHistory(new Oauth1($config), $container);

        $client->get(Server::$url, ['auth' => 'oauth']);

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
        $container = [];
        $client = $this->createServerClientWithHistory(new Oauth1($this->config), $container);

        $client->get(Server::$url);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertCount(0, Query::parse($request->getUri()->getQuery()));
        $this->assertEmpty($request->getHeader('Authorization'));
    }

    public function testOnlyTouchesWhenAuthConfigIsExactlyOauth(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container);

        $client->get('https://example.com', ['auth' => 'not-oauth']);

        $request = $container[0]['request'];

        $this->assertFalse($request->hasHeader('Authorization'));
        $this->assertCount(0, Query::parse($request->getUri()->getQuery()));
    }

    public function testAllowsTokenCredentialsToBeOverriddenPerRequest(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container);

        $client->get('https://example.com', [
            'auth' => 'oauth',
            'oauth' => [
                'token' => 'override-token',
                'token_secret' => 'override-secret',
            ],
        ]);

        $request = $container[0]['request'];
        $params = $this->parseAuthorizationHeader($request);
        $header = $request->getHeaderLine('Authorization');

        $this->assertSame('override-token', $params['oauth_token']);
        $this->assertThat($header, Assert::logicalNot(Assert::stringContains('override-secret', false)), '');
        $this->assertThat($header, Assert::logicalNot(Assert::stringContains('token_secret', false)), '');
    }

    public function testPerRequestTokenSecretChangesSignature(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container);

        $client->get('https://example.com', [
            'auth' => 'oauth',
            'oauth' => [
                'token' => 'override-token',
                'token_secret' => 'override-secret',
            ],
        ]);

        $request = $container[0]['request'];
        $params = $this->parseAuthorizationHeader($request);
        $actualSignature = $params['oauth_signature'];
        unset($params['oauth_signature']);

        $effectiveConfig = $this->config;
        $effectiveConfig['token'] = 'override-token';
        $effectiveConfig['token_secret'] = 'override-secret';

        $constructorSignature = (new Oauth1($this->config))->getSignature(
            $request->withoutHeader('Authorization'),
            $params
        );
        $effectiveSignature = (new Oauth1($effectiveConfig))->getSignature(
            $request->withoutHeader('Authorization'),
            $params
        );

        $this->assertSame($effectiveSignature, $actualSignature);
        $this->assertNotSame($constructorSignature, $actualSignature);
    }

    public function testPerRequestTokenOverridesDoNotMutateMiddlewareConfiguration(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container);

        $client->get('https://example.com/one', [
            'auth' => 'oauth',
            'oauth' => [
                'token' => 'override-token',
                'token_secret' => 'override-secret',
            ],
        ]);
        $client->get('https://example.com/two', ['auth' => 'oauth']);

        $firstParams = $this->parseAuthorizationHeader($container[0]['request']);
        $secondParams = $this->parseAuthorizationHeader($container[1]['request']);

        $this->assertSame('override-token', $firstParams['oauth_token']);
        $this->assertSame('count', $secondParams['oauth_token']);
    }

    public function testUsesDefaultOauthRequestOptionFromClientConfiguration(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container, [
            'auth' => 'oauth',
            'oauth' => [
                'token' => 'default-override-token',
                'token_secret' => 'default-override-secret',
            ],
        ]);

        $client->get('https://example.com');

        $params = $this->parseAuthorizationHeader($container[0]['request']);

        $this->assertSame('default-override-token', $params['oauth_token']);
    }

    public function testRequestOauthOptionReplacesDefaultOauthRequestOption(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container, [
            'auth' => 'oauth',
            'oauth' => [
                'token' => 'default-override-token',
                'token_secret' => 'default-override-secret',
            ],
        ]);

        $client->get('https://example.com', [
            'oauth' => [
                'token' => 'request-override-token',
                'token_secret' => 'request-override-secret',
            ],
        ]);

        $params = $this->parseAuthorizationHeader($container[0]['request']);

        $this->assertSame('request-override-token', $params['oauth_token']);
    }

    public function testNullOauthRequestOptionRemovesDefaultOauthRequestOption(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container, [
            'auth' => 'oauth',
            'oauth' => [
                'token' => 'default-override-token',
                'token_secret' => 'default-override-secret',
            ],
        ]);

        $client->get('https://example.com', ['oauth' => null]);

        $params = $this->parseAuthorizationHeader($container[0]['request']);

        $this->assertSame('count', $params['oauth_token']);
    }

    public function testOauthRequestOptionDoesNotSignWithoutOauthAuth(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container);

        $client->get('https://example.com', [
            'oauth' => [
                'token' => 'override-token',
                'token_secret' => 'override-secret',
            ],
        ]);

        $request = $container[0]['request'];

        $this->assertFalse($request->hasHeader('Authorization'));
        $this->assertCount(0, Query::parse($request->getUri()->getQuery()));
    }

    public function testAuthNullDisablesDefaultOauthAuth(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container, ['auth' => 'oauth']);

        $client->get('https://example.com', [
            'auth' => null,
            'oauth' => [
                'token' => 'override-token',
                'token_secret' => 'override-secret',
            ],
        ]);

        $request = $container[0]['request'];

        $this->assertFalse($request->hasHeader('Authorization'));
        $this->assertCount(0, Query::parse($request->getUri()->getQuery()));
    }

    public function testRejectsNonArrayOauthRequestOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The oauth request option must be an array.');

        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container);

        $client->get('https://example.com', [
            'auth' => 'oauth',
            'oauth' => 'override-token',
        ]);
    }

    public function testIgnoresUnknownOauthRequestOptions(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container);

        $client->get('https://example.com', [
            'auth' => 'oauth',
            'oauth' => [
                'token' => 'override-token',
                'token_secret' => 'override-secret',
                'foo' => 'bar',
            ],
        ]);

        $header = $container[0]['request']->getHeaderLine('Authorization');
        $params = $this->parseAuthorizationHeader($container[0]['request']);

        $this->assertSame('override-token', $params['oauth_token']);
        $this->assertThat($header, Assert::logicalNot(Assert::stringContains('foo=', false)), '');
    }

    public function testNullPerRequestTokenRemovesConfiguredToken(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container);

        $client->get('https://example.com', [
            'auth' => 'oauth',
            'oauth' => [
                'token' => null,
                'token_secret' => null,
            ],
        ]);

        $header = $container[0]['request']->getHeaderLine('Authorization');

        $this->assertThat($header, Assert::logicalNot(Assert::stringContains('oauth_token=', false)), '');
        $this->assertThat($header, Assert::stringContains('oauth_signature=', false), '');
    }

    public function testEmptyPerRequestTokenIsSentAsEmptyString(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container);

        $client->get('https://example.com', [
            'auth' => 'oauth',
            'oauth' => [
                'token' => '',
                'token_secret' => '',
            ],
        ]);

        $params = $this->parseAuthorizationHeader($container[0]['request']);

        $this->assertSame('', $params['oauth_token']);
        $this->assertArrayHasKey('oauth_signature', $params);
    }

    public function testUsesPerRequestTokenInQueryString(): void
    {
        $config = $this->config;
        $config['request_method'] = Oauth1::REQUEST_METHOD_QUERY;

        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($config), $container);

        $client->get('https://example.com', [
            'auth' => 'oauth',
            'oauth' => [
                'token' => 'override-token',
                'token_secret' => 'override-secret',
            ],
        ]);

        $request = $container[0]['request'];
        $query = Query::parse($request->getUri()->getQuery());

        $this->assertFalse($request->hasHeader('Authorization'));
        $this->assertSame('override-token', $query['oauth_token']);
        $this->assertArrayHasKey('oauth_signature', $query);
        $this->assertArrayNotHasKey('token_secret', $query);
        $this->assertArrayNotHasKey('oauth_token_secret', $query);
    }

    public function testRemovesOauthRequestOptionBeforePassingToHandler(): void
    {
        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($this->config), $container);

        $client->get('https://example.com', [
            'auth' => 'oauth',
            'oauth' => [
                'token' => 'override-token',
                'token_secret' => 'override-secret',
            ],
        ]);

        $this->assertArrayNotHasKey('oauth', $container[0]['options']);
    }

    public function testValidatesRequestMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        if (method_exists($this, 'expectException')) {
            $this->expectException(\InvalidArgumentException::class);
        }

        $config = $this->config;
        $config['request_method'] = 'Foo';

        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($config), $container);

        $client->get('https://example.com', ['auth' => 'oauth']);
    }

    public function testExceptionOnSignatureError(): void
    {
        $this->expectException(\RuntimeException::class);

        if (method_exists($this, 'expectException')) {
            $this->expectException(\RuntimeException::class);
        }

        $config = $this->config;
        $config['signature_method'] = 'Foo';

        $container = [];
        $client = $this->createClientWithHistory(new Oauth1($config), $container);

        $client->get('https://example.com', ['auth' => 'oauth']);
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

        $container = [];
        $client = $this->createServerClientWithHistory(new Oauth1($config), $container);

        $client->get(Server::$url, ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertThat($request->getHeader('Authorization')[0], Assert::logicalNot(Assert::stringContains('oauth_token=', false)), '');
    }

    public function testRandomParametersAreNotAutomaticallyAdded(): void
    {
        $config = $this->config;
        $config['foo'] = 'bar';

        $container = [];
        $client = $this->createServerClientWithHistory(new Oauth1($config), $container);

        $client->get(Server::$url, ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertThat($request->getHeader('Authorization')[0], Assert::logicalNot(Assert::stringContains('foo=bar', false)), '');
    }

    public function testAllowsRealm(): void
    {
        $config = $this->config;
        $config['realm'] = 'foo';

        $container = [];
        $client = $this->createServerClientWithHistory(new Oauth1($config), $container);

        $client->get(Server::$url, ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains('OAuth realm="foo",', false), '');
    }

    public function testSignsHmacSha256(): void
    {
        $config = $this->config;
        $config['signature_method'] = Oauth1::SIGNATURE_METHOD_HMACSHA256;

        $container = [];
        $client = $this->createServerClientWithHistory(new Oauth1($config), $container);

        $client->get(Server::$url, ['auth' => 'oauth']);

        /* @var Request $request */
        $request = $container[0]['request'];

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains('oauth_signature_method="HMAC-SHA256"', false), '');
        $this->assertThat($request->getHeader('Authorization')[0], Assert::stringContains('oauth_signature="', false), '');
    }

    /**
     * @param array<array-key, array{
     *     request: RequestInterface,
     *     response: ResponseInterface|null,
     *     error: mixed,
     *     options: array<array-key, mixed>
     * }> $container History container populated by Guzzle's history middleware.
     * @param array<array-key, mixed> $config Additional Guzzle client configuration.
     */
    private function createClientWithHistory(Oauth1 $middleware, array &$container, array $config = []): Client
    {
        /** @var callable(RequestInterface, array<array-key, mixed>): PromiseInterface<ResponseInterface, mixed> $handler */
        $handler = function (RequestInterface $request, array $options): PromiseInterface {
            return Create::promiseFor(new Response(200));
        };
        $stack = HandlerStack::create($handler);
        $stack->push($middleware);
        $stack->push(Middleware::history($container));

        return new Client(['handler' => $stack] + $config);
    }

    /**
     * @param array<array-key, array{
     *     request: RequestInterface,
     *     response: ResponseInterface|null,
     *     error: mixed,
     *     options: array<array-key, mixed>
     * }> $container History container populated by Guzzle's history middleware.
     */
    private function createServerClientWithHistory(Oauth1 $middleware, array &$container): Client
    {
        Server::flush();
        Server::enqueue([new Response(200)]);

        $stack = HandlerStack::create();
        $stack->push($middleware);
        $stack->push(Middleware::history($container));

        return new Client(['handler' => $stack]);
    }

    /**
     * @return array<string, string> Authorization parameters indexed by parameter name
     */
    private function parseAuthorizationHeader(RequestInterface $request): array
    {
        $header = $request->getHeaderLine('Authorization');
        $this->assertStringStartsWith('OAuth ', $header);

        preg_match_all('/([A-Za-z_]+)="([^"]*)"/', $header, $matches, PREG_SET_ORDER);

        $params = [];
        foreach ($matches as $match) {
            $params[$match[1]] = rawurldecode($match[2]);
        }

        return $params;
    }
}

final class Oauth1SerializationTestDouble extends Oauth1
{
}
