<?php

declare(strict_types=1);

namespace GuzzleHttp\Subscriber\Oauth;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\DiagnosticValue;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * OAuth 1.0 signature plugin.
 *
 * Portions of this code comes from HWIOAuthBundle and a Guzzle 3 pull request:
 *
 * @author Alexander <iam.asm89@gmail.com>
 * @author Joseph Bielawski <stloyd@gmail.com>
 * @author Francisco Facioni <fran6co@gmail.com>
 *
 * @see https://github.com/hwi/HWIOAuthBundle
 * @see https://github.com/guzzle/guzzle/pull/563 Original Guzzle 3 pull req.
 * @see https://oauth.net/core/1.0/#rfc.section.9.1.1 OAuth specification
 */
class Oauth1
{
    /**
     * Consumer request method constants. See https://oauth.net/core/1.0/#consumer_req_param
     */
    public const REQUEST_METHOD_HEADER = 'header';
    public const REQUEST_METHOD_QUERY = 'query';

    public const SIGNATURE_METHOD_HMAC = 'HMAC-SHA1';
    public const SIGNATURE_METHOD_HMACSHA256 = 'HMAC-SHA256';
    public const SIGNATURE_METHOD_RSA = 'RSA-SHA1';
    public const SIGNATURE_METHOD_PLAINTEXT = 'PLAINTEXT';

    /** @var array Configuration settings */
    private array $config;

    /**
     * Create a new OAuth 1.0 plugin.
     *
     * The configuration array accepts the following options:
     *
     * - request_method: Consumer request method. One of 'header' or 'query'.
     *   Defaults to 'header'.
     * - callback: OAuth callback
     * - consumer_key: Consumer key string. Defaults to "anonymous".
     * - consumer_secret: Consumer secret. Defaults to "anonymous".
     * - private_key_file: The location of your private key file (RSA-SHA1
     *   signature method only)
     * - private_key_passphrase: The passphrase for your private key file
     *   (RSA-SHA1 signature method only)
     * - token: Client token
     * - token_secret: Client secret token
     * - verifier: OAuth verifier.
     * - version: OAuth version. Defaults to '1.0'.
     * - realm: OAuth realm.
     * - signature_method: Signature method. One of 'HMAC-SHA1', 'RSA-SHA1',
     *   'HMAC-SHA256', or 'PLAINTEXT'. Defaults to 'HMAC-SHA1'.
     * - bodyhash: OAuth body hash.
     *
     * @param array{
     *     request_method?: 'header'|'query',
     *     callback?: string,
     *     consumer_key?: string,
     *     consumer_secret?: string,
     *     private_key_file?: string,
     *     private_key_passphrase?: string,
     *     token?: string,
     *     token_secret?: string,
     *     verifier?: string,
     *     version?: string,
     *     realm?: string,
     *     signature_method?: 'HMAC-SHA1'|'RSA-SHA1'|'HMAC-SHA256'|'PLAINTEXT',
     *     bodyhash?: string,
     *     ...
     * } $config Configuration array.
     */
    public function __construct(array $config)
    {
        $this->config = [
            'version' => '1.0',
            'request_method' => self::REQUEST_METHOD_HEADER,
            'consumer_key' => 'anonymous',
            'consumer_secret' => 'anonymous',
            'signature_method' => self::SIGNATURE_METHOD_HMAC,
        ];

        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }
    }

    public function __serialize(): array
    {
        throw new \LogicException(static::class.' should never be serialized');
    }

    public function __unserialize(array $data): void
    {
        throw new \LogicException(static::class.' should never be unserialized');
    }

    /**
     * Called when the middleware is handled.
     *
     * @param callable(RequestInterface, array<array-key, mixed>): PromiseInterface<ResponseInterface, mixed> $handler
     *
     * @return \Closure(RequestInterface, array<array-key, mixed>): PromiseInterface<ResponseInterface, mixed>
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function __invoke(callable $handler): \Closure
    {
        return function (
            #[\SensitiveParameter]
            RequestInterface $request,
            #[\SensitiveParameter]
            array $options
        ) use ($handler): PromiseInterface {
            if (($options['auth'] ?? null) === 'oauth') {
                $config = self::getEffectiveConfig($this->config, $options);
                unset($options['oauth']);

                $request = self::onBefore($request, $config);
            }

            return $handler($request, $options);
        };
    }

    /**
     * Returns the configuration to use for a single request.
     *
     * Only token credential overrides are supported in request options.
     *
     * @param array $config  Base configuration settings
     * @param array $options Request options
     *
     * @throws \InvalidArgumentException
     */
    private static function getEffectiveConfig(
        #[\SensitiveParameter]
        array $config,
        #[\SensitiveParameter]
        array $options
    ): array {
        if (!array_key_exists('oauth', $options) || $options['oauth'] === null) {
            return $config;
        }

        if (!is_array($options['oauth'])) {
            throw new \InvalidArgumentException('The oauth request option must be an array.');
        }

        foreach (['token', 'token_secret'] as $key) {
            if (!array_key_exists($key, $options['oauth'])) {
                continue;
            }

            if ($options['oauth'][$key] === null) {
                unset($config[$key]);
                continue;
            }

            $config[$key] = $options['oauth'][$key];
        }

        return $config;
    }

    /**
     * @param array $config Configuration settings for this request
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    private static function onBefore(
        #[\SensitiveParameter]
        RequestInterface $request,
        #[\SensitiveParameter]
        array $config
    ): RequestInterface {
        $oauthparams = self::getOauthParams($config);

        $oauthparams['oauth_signature'] = self::getSignatureWithConfig($request, $oauthparams, $config);
        uksort($oauthparams, 'strcmp');

        switch ($config['request_method']) {
            case self::REQUEST_METHOD_HEADER:
                list($header, $value) = self::buildAuthorizationHeader($oauthparams, $config);
                $request = $request->withHeader($header, $value);
                break;
            case self::REQUEST_METHOD_QUERY:
                $queryParams = Query::parse($request->getUri()->getQuery());
                $preparedParams = Query::build($oauthparams + $queryParams);
                $request = $request->withUri($request->getUri()->withQuery($preparedParams));
                break;
            default:
                throw new \InvalidArgumentException(\sprintf('Invalid consumer method: %s', DiagnosticValue::escape((string) $config['request_method'])));
        }

        return $request;
    }

    /**
     * Calculate signature for request
     *
     * @param RequestInterface $request Request to generate a signature for
     * @param array            $params  Oauth parameters.
     *
     * @throws \InvalidArgumentException If OAuth parameters contain non-finite floats.
     * @throws \RuntimeException
     */
    public function getSignature(
        #[\SensitiveParameter]
        RequestInterface $request,
        #[\SensitiveParameter]
        array $params
    ): string {
        return self::getSignatureWithConfig($request, $params, $this->config);
    }

    /**
     * Calculate signature for request using the given configuration.
     *
     * @param RequestInterface $request Request to generate a signature for
     * @param array            $params  Oauth parameters
     * @param array            $config  Configuration settings for this request
     *
     * @throws \InvalidArgumentException If OAuth parameters contain non-finite floats.
     * @throws \RuntimeException
     */
    private static function getSignatureWithConfig(
        #[\SensitiveParameter]
        RequestInterface $request,
        #[\SensitiveParameter]
        array $params,
        #[\SensitiveParameter]
        array $config
    ): string {
        // Add POST fields if the request uses POST fields and no files
        $contentType = $request->getHeaderLine('Content-Type');
        $mediaType = Utils::asciiToLower(trim(explode(';', $contentType, 2)[0], " \t"));

        if ($mediaType === 'application/x-www-form-urlencoded') {
            $body = Query::parse($request->getBody()->getContents());
            $params += $body;
        }

        // Parse & add query string parameters as base string parameters
        $query = $request->getUri()->getQuery();
        $params += Query::parse($query);

        // Remove oauth_signature if present
        // Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
        unset($params['oauth_signature']);

        $baseString = self::createBaseString(
            $request,
            self::prepareParameters($params)
        );

        // Implements double-dispatch to sign requests
        switch ($config['signature_method']) {
            case Oauth1::SIGNATURE_METHOD_HMAC:
                $signature = self::signUsingHmac('sha1', $baseString, $config);
                break;
            case Oauth1::SIGNATURE_METHOD_HMACSHA256:
                $signature = self::signUsingHmac('sha256', $baseString, $config);
                break;
            case Oauth1::SIGNATURE_METHOD_RSA:
                $signature = self::signUsingRsaSha1($baseString, $config);
                break;
            case Oauth1::SIGNATURE_METHOD_PLAINTEXT:
                $signature = self::signUsingPlaintext($baseString);
                break;
            default:
                throw new \RuntimeException(\sprintf('Unknown signature method: %s', DiagnosticValue::escape((string) $config['signature_method'])));
        }

        return base64_encode($signature);
    }

    /**
     * Creates the Signature Base String.
     *
     * The Signature Base String is a consistent reproducible concatenation of
     * the request elements into a single string. The string is used as an
     * input in hashing or signing algorithms.
     *
     * @param RequestInterface $request Request being signed
     * @param array            $params  Associative array of OAuth parameters
     *
     * @see https://oauth.net/core/1.0/#sig_base_example
     */
    private static function createBaseString(
        #[\SensitiveParameter]
        RequestInterface $request,
        #[\SensitiveParameter]
        array $params
    ): string {
        // Remove query params from URL. Ref: Spec: 9.1.2.
        return Utils::asciiToUpper($request->getMethod())
            .'&'.rawurlencode((string) $request->getUri()->withQuery(''))
            .'&'.rawurlencode(Query::build($params));
    }

    /**
     * @param array $data The data array
     */
    private static function prepareParameters(
        #[\SensitiveParameter]
        array $data
    ): array {
        // Parameters are sorted by name, using lexicographical byte value
        // ordering. Ref: Spec: 9.1.1 (1).
        uksort($data, 'strcmp');

        foreach ($data as $key => $value) {
            if ($value === null) {
                $data[$key] = '';
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $index => $nestedValue) {
                    if ($nestedValue === null) {
                        $data[$key][$index] = '';
                    } else {
                        self::assertFiniteFloat($nestedValue);
                    }
                }

                usort($data[$key], static function (
                    #[\SensitiveParameter]
                    $left,
                    #[\SensitiveParameter]
                    $right
                ): int {
                    return strcmp(
                        self::encodeParameterValue($left),
                        self::encodeParameterValue($right)
                    );
                });

                continue;
            }

            self::assertFiniteFloat($value);
        }

        return $data;
    }

    /**
     * @param mixed $value Parameter value
     */
    private static function encodeParameterValue($value): string
    {
        if ($value === null) {
            $value = '';
        }

        if (is_bool($value)) {
            $value = (int) $value;
        }

        self::assertFiniteFloat($value);

        return rawurlencode((string) $value);
    }

    /**
     * @param mixed $value Parameter value
     */
    private static function assertFiniteFloat($value): void
    {
        if (is_float($value) && !is_finite($value)) {
            throw new \InvalidArgumentException('Non-finite floats are not supported in OAuth parameters.');
        }
    }

    /**
     * @param string $algo   Name of selected hashing algorithm (i.e. "md5", "sha256", "haval160,4", etc..)
     * @param array  $config Configuration settings for this request
     */
    private static function signUsingHmac(
        string $algo,
        #[\SensitiveParameter]
        string $baseString,
        #[\SensitiveParameter]
        array $config
    ): string {
        $key = rawurlencode($config['consumer_secret']).'&';
        if (isset($config['token_secret'])) {
            $key .= rawurlencode($config['token_secret']);
        }

        return hash_hmac($algo, $baseString, $key, true);
    }

    /**
     * @param array $config Configuration settings for this request
     *
     * @throws \RuntimeException
     */
    private static function signUsingRsaSha1(
        #[\SensitiveParameter]
        string $baseString,
        #[\SensitiveParameter]
        array $config
    ): string {
        if (!function_exists('openssl_pkey_get_private')) {
            throw new \RuntimeException('RSA-SHA1 signature method requires the OpenSSL extension.');
        }

        if (!isset($config['private_key_file'])
            || !is_string($config['private_key_file'])
            || $config['private_key_file'] === '') {
            throw new \RuntimeException('RSA-SHA1 signature method requires a private_key_file option.');
        }

        $keyContents = @file_get_contents($config['private_key_file']);
        if ($keyContents === false) {
            throw new \RuntimeException(\sprintf('Unable to read RSA private key file: %s', DiagnosticValue::escape($config['private_key_file'])));
        }

        if (isset($config['private_key_passphrase'])) {
            $privateKey = @openssl_pkey_get_private($keyContents, $config['private_key_passphrase']);
        } else {
            $privateKey = @openssl_pkey_get_private($keyContents);
        }

        if ($privateKey === false) {
            throw new \RuntimeException('Unable to parse RSA private key.');
        }

        $signature = '';
        if (!@openssl_sign($baseString, $signature, $privateKey, OPENSSL_ALGO_SHA1)) {
            throw new \RuntimeException('Unable to sign using RSA-SHA1.');
        }
        unset($privateKey);

        return $signature;
    }

    private static function signUsingPlaintext(string $baseString): string
    {
        return $baseString;
    }

    /**
     * Builds the Authorization header for a request
     *
     * @param array $params Associative array of authorization parameters.
     * @param array $config Configuration settings for this request
     */
    private static function buildAuthorizationHeader(
        #[\SensitiveParameter]
        array $params,
        #[\SensitiveParameter]
        array $config
    ): array {
        foreach ($params as $key => $value) {
            self::assertFiniteFloat($value);
            $params[$key] = $key.'="'.rawurlencode((string) $value).'"';
        }

        if (isset($config['realm'])) {
            array_unshift(
                $params,
                'realm="'.rawurlencode($config['realm']).'"'
            );
        }

        return ['Authorization', 'OAuth '.implode(', ', $params)];
    }

    /**
     * Get the oauth parameters as named by the oauth spec
     *
     * @param array $config Configuration options of the plugin.
     */
    private static function getOauthParams(
        #[\SensitiveParameter]
        array $config
    ): array {
        $params = [
            'oauth_consumer_key' => $config['consumer_key'],
            'oauth_nonce' => bin2hex(random_bytes(20)),
            'oauth_signature_method' => $config['signature_method'],
            'oauth_timestamp' => time(),
        ];

        // Optional parameters should not be set if they have not been set in
        // the config as the parameter may be considered invalid by the Oauth
        // service.
        $optionalParams = [
            'callback' => 'oauth_callback',
            'token' => 'oauth_token',
            'verifier' => 'oauth_verifier',
            'version' => 'oauth_version',
            'bodyhash' => 'oauth_body_hash',
        ];

        foreach ($optionalParams as $optionName => $oauthName) {
            if (isset($config[$optionName])) {
                $params[$oauthName] = $config[$optionName];
            }
        }

        foreach ($params as $value) {
            self::assertFiniteFloat($value);
        }

        return $params;
    }
}
