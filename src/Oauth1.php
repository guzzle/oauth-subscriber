<?php

declare(strict_types=1);

namespace GuzzleHttp\Subscriber\Oauth;

use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\RequestInterface;

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
    private $config;

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
     *
     * @param array $config Configuration array.
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

    /**
     * Called when the middleware is handled.
     *
     * @return \Closure
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function __invoke(callable $handler)
    {
        return function ($request, array $options) use ($handler) {
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
    private static function getEffectiveConfig(array $config, array $options): array
    {
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
    private static function onBefore(RequestInterface $request, array $config): RequestInterface
    {
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
                throw new \InvalidArgumentException(sprintf(
                    'Invalid consumer method "%s"',
                    $config['request_method']
                ));
        }

        return $request;
    }

    /**
     * Calculate signature for request
     *
     * @param RequestInterface $request Request to generate a signature for
     * @param array            $params  Oauth parameters.
     *
     * @throws \RuntimeException
     */
    public function getSignature(RequestInterface $request, array $params): string
    {
        return self::getSignatureWithConfig($request, $params, $this->config);
    }

    /**
     * Calculate signature for request using the given configuration.
     *
     * @param RequestInterface $request Request to generate a signature for
     * @param array            $params  Oauth parameters
     * @param array            $config  Configuration settings for this request
     *
     * @throws \RuntimeException
     */
    private static function getSignatureWithConfig(RequestInterface $request, array $params, array $config): string
    {
        // Add POST fields if the request uses POST fields and no files
        $contentType = $request->getHeaderLine('Content-Type');
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

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
                throw new \RuntimeException('Unknown signature method: '.$config['signature_method']);
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
    private static function createBaseString(RequestInterface $request, array $params): string
    {
        // Remove query params from URL. Ref: Spec: 9.1.2.
        return strtoupper($request->getMethod())
            .'&'.rawurlencode((string) $request->getUri()->withQuery(''))
            .'&'.rawurlencode(Query::build($params));
    }

    /**
     * @param array $data The data array
     */
    private static function prepareParameters(array $data): array
    {
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
                        $data[$key][$index] = self::normalizeNonFiniteFloat($nestedValue);
                    }
                }

                usort($data[$key], static function ($left, $right): int {
                    return strcmp(
                        self::encodeParameterValue($left),
                        self::encodeParameterValue($right)
                    );
                });

                continue;
            }

            $data[$key] = self::normalizeNonFiniteFloat($value);
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

        return rawurlencode((string) self::normalizeNonFiniteFloat($value));
    }

    /**
     * Converts non-finite floats to the strings PHP coerces them to, as
     * implicit coercion of NAN emits a warning on PHP 8.5.
     *
     * @param mixed $value Parameter value
     *
     * @return mixed
     */
    private static function normalizeNonFiniteFloat($value)
    {
        if (is_float($value) && !is_finite($value)) {
            return is_nan($value) ? 'NAN' : ($value > 0 ? 'INF' : '-INF');
        }

        return $value;
    }

    /**
     * @param string $algo   Name of selected hashing algorithm (i.e. "md5", "sha256", "haval160,4", etc..)
     * @param array  $config Configuration settings for this request
     */
    private static function signUsingHmac(string $algo, string $baseString, array $config): string
    {
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
    private static function signUsingRsaSha1(string $baseString, array $config): string
    {
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
            throw new \RuntimeException(sprintf(
                'Unable to read RSA private key file: %s',
                $config['private_key_file']
            ));
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

    /**
     * @return string
     */
    private static function signUsingPlaintext(string $baseString)
    {
        return $baseString;
    }

    /**
     * Builds the Authorization header for a request
     *
     * @param array $params Associative array of authorization parameters.
     * @param array $config Configuration settings for this request
     */
    private static function buildAuthorizationHeader(array $params, array $config): array
    {
        foreach ($params as $key => $value) {
            $params[$key] = $key.'="'.rawurlencode((string) self::normalizeNonFiniteFloat($value)).'"';
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
    private static function getOauthParams(array $config): array
    {
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

        return array_map([self::class, 'normalizeNonFiniteFloat'], $params);
    }
}
