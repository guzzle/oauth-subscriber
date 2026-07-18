<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Oauth1;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use PHPUnit\Framework\TestCase;

class SensitiveParameterTest extends TestCase
{
    /**
     * @dataProvider sensitiveNamedParameterProvider
     */
    public function testNamedParameterHasSensitiveAttribute(string $method, string $parameter): void
    {
        if (PHP_VERSION_ID < 80000) {
            self::markTestSkipped('Attributes are not reflected before PHP 8.0.');
        }

        $reflection = new \ReflectionParameter([Oauth1::class, $method], $parameter);
        $attributes = $reflection->getAttributes(\SensitiveParameter::class);

        self::assertCount(1, $attributes);
        self::assertInstanceOf(\SensitiveParameter::class, $attributes[0]->newInstance());
    }

    public static function sensitiveNamedParameterProvider(): iterable
    {
        yield 'effective base config' => ['getEffectiveConfig', 'config'];
        yield 'effective request options' => ['getEffectiveConfig', 'options'];
        yield 'signing request' => ['onBefore', 'request'];
        yield 'signing config' => ['onBefore', 'config'];
        yield 'public signature request' => ['getSignature', 'request'];
        yield 'public signature parameters' => ['getSignature', 'params'];
        yield 'configured signature request' => ['getSignatureWithConfig', 'request'];
        yield 'configured signature parameters' => ['getSignatureWithConfig', 'params'];
        yield 'configured signature config' => ['getSignatureWithConfig', 'config'];
        yield 'base string request' => ['createBaseString', 'request'];
        yield 'base string parameters' => ['createBaseString', 'params'];
        yield 'prepared parameters' => ['prepareParameters', 'data'];
        yield 'HMAC base string' => ['signUsingHmac', 'baseString'];
        yield 'HMAC config' => ['signUsingHmac', 'config'];
        yield 'RSA base string' => ['signUsingRsaSha1', 'baseString'];
        yield 'RSA config' => ['signUsingRsaSha1', 'config'];
        yield 'authorization parameters' => ['buildAuthorizationHeader', 'params'];
        yield 'authorization config' => ['buildAuthorizationHeader', 'config'];
        yield 'OAuth parameters config' => ['getOauthParams', 'config'];
    }

    public function testSourceContainsExactSensitiveAttributeInventory(): void
    {
        $source = file_get_contents(__DIR__.'/../src/Oauth1.php');

        self::assertIsString($source);
        self::assertSame(23, preg_match_all('/#\[\\\\SensitiveParameter\]/', $source));
    }

    public function testMiddlewareParametersHaveSensitiveAttribute(): void
    {
        if (PHP_VERSION_ID < 80000) {
            self::markTestSkipped('Attributes are not reflected before PHP 8.0.');
        }

        $oauth = new Oauth1([]);
        $middleware = $oauth(static function () {
            return Create::promiseFor(null);
        });
        foreach (['request', 'options'] as $parameter) {
            $reflection = new \ReflectionParameter($middleware, $parameter);
            $attributes = $reflection->getAttributes(\SensitiveParameter::class);

            self::assertCount(1, $attributes);
            self::assertInstanceOf(\SensitiveParameter::class, $attributes[0]->newInstance());
        }
    }

    public function testAssignmentOnlyAndPlaintextParametersAreNotSensitive(): void
    {
        if (PHP_VERSION_ID < 80000) {
            self::markTestSkipped('Attributes are not reflected before PHP 8.0.');
        }

        $constructor = new \ReflectionParameter([Oauth1::class, '__construct'], 'config');
        $plaintext = new \ReflectionParameter([Oauth1::class, 'signUsingPlaintext'], 'baseString');

        self::assertCount(0, $constructor->getAttributes(\SensitiveParameter::class));
        self::assertCount(0, $plaintext->getAttributes(\SensitiveParameter::class));
    }

    public function testComparatorArgumentsAreRedactedFromTrace(): void
    {
        if (PHP_VERSION_ID < 80200) {
            self::markTestSkipped('Native sensitive-parameter redaction requires PHP 8.2.');
        }

        $previous = ini_set('zend.exception_ignore_args', '0');
        if (ini_get('zend.exception_ignore_args') !== '0') {
            self::markTestSkipped('Trace arguments cannot be enabled.');
        }

        $method = new \ReflectionMethod(Oauth1::class, 'prepareParameters');

        try {
            $method->invoke(null, [
                'oauth_token' => [
                    'oauth-token-secret',
                    new ThrowingStringable(),
                ],
            ]);
            self::fail('Expected string conversion to throw.');
        } catch (\RuntimeException $exception) {
            $frame = null;
            foreach ($exception->getTrace() as $candidate) {
                if (($candidate['class'] ?? null) === Oauth1::class
                    && strpos($candidate['function'] ?? '', '{closure') !== false) {
                    $frame = $candidate;
                    break;
                }
            }

            self::assertNotNull($frame);
            self::assertCount(2, $frame['args']);
            self::assertContainsOnlyInstancesOf(\SensitiveParameterValue::class, $frame['args']);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }
}

final class ThrowingStringable
{
    public function __toString(): string
    {
        throw new \RuntimeException('String conversion failed.');
    }
}
