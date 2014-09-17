<?php

namespace GuzzleHttp\Subscriber\OAuth2;

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Subscriber\OAuth2\Exception\AccessTokenRequestException;
use GuzzleHttp\Subscriber\OAuth2\Exception\RefreshTokenRequestException;
use GuzzleHttp\Subscriber\OAuth2\Factory\GenericTokenFactory;
use GuzzleHttp\Subscriber\OAuth2\Factory\TokenFactoryInterface;
use GuzzleHttp\Subscriber\OAuth2\GrantType\GrantTypeInterface;
use GuzzleHttp\Subscriber\OAuth2\Persistence\NullTokenPersistence;
use GuzzleHttp\Subscriber\OAuth2\Persistence\TokenPersistenceInterface;
use GuzzleHttp\Subscriber\OAuth2\Signer\AccessToken\BasicAuth as AccessTokenBasicAuth;
use GuzzleHttp\Subscriber\OAuth2\Signer\AccessToken\SignerInterface as AccessTokenSigner;
use GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials\BasicAuth as ClientCredentialsBasicAuth;
use GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials\SignerInterface as ClientCredentialsSigner;

/**
 * OAuth2 plugin.
 *
 * @author Steve Kamerman <stevekamerman@gmail.com>
 * @author Matthieu Moquet <matthieu@moquet.net>
 *
 * @link http://tools.ietf.org/html/rfc6749 OAuth2 specification
 */
class OAuth2Subscriber implements SubscriberInterface
{
    /**
     * The grant type implementation used to acquire access tokens.
     *
     * @var GrantTypeInterface
     */
    protected $grantType;

    /**
     * The grant type implementation used to refresh access tokens.
     *
     * @var GrantTypeInterface
     */
    protected $refreshTokenGrantType;

    /**
     * The service in charge of including client credentials into requests.
     * to get an access token.
     *
     * @var AccessTokenSigner
     */
    protected $clientCredentialsSigner;

    /**
     * The service in charge of including the access token into requests.
     *
     * @var AccessTokenSigner
     */
    protected $accessTokenSigner;

    /**
     * The object including access token.
     *
     * @var RawToken
     */
    protected $rawToken;

    /**
     * The service in charge of persisting access token.
     *
     * @var TokenPersistenceInterface
     */
    protected $tokenPersistence;

    /**
     * @param GrantTypeInterface $grantType
     * @param GrantTypeInterface $refreshTokenGrantType
     */
    public function __construct(GrantTypeInterface $grantType, GrantTypeInterface $refreshTokenGrantType = null)
    {
        // Tokens
        $this->grantType = $grantType;
        $this->refreshTokenGrantType = $refreshTokenGrantType;

        // Default services
        $this->clientCredentialsSigner = new ClientCredentialsBasicAuth();
        $this->accessTokenSigner = new AccessTokenBasicAuth();
        $this->tokenPersistence = new NullTokenPersistence();
        $this->tokenFactory = new GenericTokenFactory();
    }

    /**
     * @param ClientCredentialsSigner $signer
     */
    public function setClientCredentialsSigner(ClientCredentialsSigner $signer)
    {
        $this->clientCredentialsSigner = $signer;

        return $this;
    }

    /**
     * @param AccessTokenSigner $signer
     */
    public function setAccessTokenSigner(AccessTokenSigner $signer)
    {
        $this->accessTokenSigner = $signer;

        return $this;
    }

    /**
     * @param TokenPersistenceInterface $tokenPersistence
     */
    public function setTokenPersistence(TokenPersistenceInterface $tokenPersistence)
    {
        $this->tokenPersistence = $tokenPersistence;

        return $this;
    }

    /**
     * @param TokenFactoryInterface $tokenFactory
     */
    public function setTokenFactory(TokenFactoryInterface $tokenFactory)
    {
        $this->tokenFactory = $tokenFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents()
    {
        return [
            'before' => ['onBefore', RequestEvents::VERIFY_RESPONSE + 100],
            'error'  => ['onError', RequestEvents::EARLY - 100],
        ];
    }

   /**
     * Request before-send event handler.
     *
     * Adds the Authorization header if an access token was found.
     *
     * @param BeforeEvent $event Event received
     */
    public function onBefore(BeforeEvent $event)
    {
        $request = $event->getRequest();

        // Only sign requests using "auth"="oauth"
        if ('oauth' !== $request->getConfig()['auth']) {
            return;
        }

        if (null !== $accessToken = $this->getAccessToken()) {
            $this->accessTokenSigner->sign($request, $accessToken);
        }
    }

    /**
      * Request error event handler.
      *
      * Handles unauthorized errors by acquiring a new access token and
      * retrying the request.
      *
      * @param ErrorEvent $event Event received
      */
    public function onError(ErrorEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only sign requests using "auth"="oauth"
        if ('oauth' !== $request->getConfig()['auth']) {
            return;
        }

        // Only deal with Unauthorized response.
        if ($response && 401 != $response->getStatusCode()) {
            return;
        }

        // If we already retried once, give up.
        if ($request->getHeader('X-Guzzle-Retry')) {
            return;
        }

        // Acquire a new access token, and retry the request.
        if (null !== $accessToken = $this->getAccessToken()) {
            $newRequest = clone $request;
            $newRequest->setHeader('X-Guzzle-Retry', '1');

            $this->accessTokenSigner->sign($newRequest, $accessToken);

            $event->intercept(
                $event->getClient()->send($newRequest)
            );
        }
    }

    /**
     * Get a valid access token.
     *
     * @return string|null A valid access token or null if unable to get one
     *
     * @throws AccessTokenRequestException while trying to run `requestNewAccessToken` method
     */
    protected function getAccessToken()
    {
        // If token is not set try to get it from the persistent storage.
        if (null === $this->rawToken) {
            $this->rawToken = $this->tokenPersistence->restoreToken();
        }

        // If token is not set or expired then try to acquire a new one...
        if (null === $this->rawToken || $this->rawToken->isExpired()) {
            $this->tokenPersistence->deleteToken();

            // Hydrate `rawToken` with a new access token
            $this->requestNewAccessToken();

            // ...and save it.
            if ($this->rawToken) {
                $this->tokenPersistence->saveToken($this->rawToken);
            }
        }

        return $this->rawToken ? $this->rawToken->getAccessToken() : null;
    }

    /**
     * Acquire a new access token from the server.
     *
     * @return RawToken|null
     *
     * @throws AccessTokenRequestException
     */
    protected function requestNewAccessToken()
    {
        if ($this->refreshTokenGrantType && $this->rawToken && $this->rawToken->getRefreshToken()) {
            try {
                // Get an access token using the stored refresh token.
                $rawData = $this->refreshTokenGrantType->getTokenData(
                    $this->clientCredentialsSigner,
                    $this->rawToken->getRefreshToken()
                );

                $this->rawToken = $this->tokenFactory->createRawToken($rawData, $this->rawToken);

                return;
            } catch (BadResponseException $e) {
                // If the refresh token is invalid, then clear the entire token information.
                $this->rawToken = null;
            }
        }

        try {
            // Request an access token using the main grant type.
            $rawData = $this->grantType->getRawData($this->clientCredentialsSigner);

            $this->rawToken = $this->tokenFactory->createRawToken($rawData);
        } catch (BadResponseException $e) {
            throw new AccessTokenRequestException('Unable to request a new access token', $e);
        }
    }
}
