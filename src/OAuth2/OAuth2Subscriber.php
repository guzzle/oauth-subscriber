<?php
namespace GuzzleHttp\Subscriber\OAuth2;

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Subscriber\OAuth2\GrantType\GrantTypeInterface;

/**
 * OAuth2 plugin.
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
     * @var TokenInterface
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
    public function __construct(GrantType\GrantTypeInterface $grantType = null, GrantType\GrantTypeInterface $refreshTokenGrantType = null)
    {
        // Tokens
        $this->grantType = $grantType;
        $this->refreshTokenGrantType = $refreshTokenGrantType;

        // Default services
        $this->clientCredentialsSigner = new Signer\ClientCredentials\BasicAuth();
        $this->accessTokenSigner = new Signer\AccessToken\BasicAuth();
        $this->tokenPersistence = new Persistence\NullTokenPersistence();
        $this->tokenFactory = new Token\RawTokenFactory();
    }

    /**
     * @param Signer\ClientCredentials\SignerInterface $signer
     */
    public function setClientCredentialsSigner(Signer\ClientCredentials\SignerInterface $signer)
    {
        $this->clientCredentialsSigner = $signer;

        return $this;
    }

    /**
     * @param AccessToken\SignerInterface $signer
     */
    public function setAccessTokenSigner(Signer\AccessToken\SignerInterface $signer)
    {
        $this->accessTokenSigner = $signer;

        return $this;
    }

    /**
     * @param Persistence\TokenPersistenceInterface $tokenPersistence
     */
    public function setTokenPersistence(Persistence\TokenPersistenceInterface $tokenPersistence)
    {
        $this->tokenPersistence = $tokenPersistence;

        return $this;
    }

    /**
     * @param callable $tokenFactory
     */
    public function setTokenFactory(callable $tokenFactory)
    {
        $this->tokenFactory = $tokenFactory;

        return $this;
    }

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
        if ($request->getConfig()['auth'] !== 'oauth') {
            return;
        }

        // Only deal with Unauthorized response.
        if ($response && $response->getStatusCode() != 401) {
            return;
        }

        // If we already retried once, give up.
        if ($request->getHeader('X-Guzzle-Retry')) {
            return;
        }

        // If there is a previous access token, it must have been used and failed
        // so we will delete it from persistence so a new token will be requested.
        // This happens when a key is invalidated before the expiration
        if ($this->rawToken !== null) {
            $this->tokenPersistence->deleteToken();
            $this->rawToken = null;
        }

        // Acquire a new access token, and retry the request.
        $accessToken = $this->getAccessToken();
        if ($accessToken !== null) {
            $newRequest = clone $request;
            $newRequest->setHeader('X-Guzzle-Retry', '1');

            $this->accessTokenSigner->sign($newRequest, $accessToken);

            $event->intercept(
                $event->getClient()->send($newRequest)
            );
        }
    }

    /**
     * Manually set the access token.
     *
     * @param string|array|TokenInterface $token An array of token data, an access token string, or a TokenInterface object
     */
    public function setAccessToken($token)
    {
        if ($token instanceOf Token\TokenInterface) {
            $this->rawToken = $token;
        } else {
            $this->rawToken = is_array($token) ?
                $this->tokenFactory($token) :
                $this->tokenFactory(['access_token' => $token]);
        }

        if ($this->rawToken === null) {
            throw new Exception\OAuth2Exception("setAccessToken() takes a string, array or TokenInterface object");
        }

        return $this;
    }

    /**
     * Get a valid access token.
     *
     * @return string|null A valid access token or null if unable to get one
     *
     * @throws AccessTokenRequestException while trying to run `requestNewAccessToken` method
     */
    public function getAccessToken()
    {
        // If token is not set try to get it from the persistent storage.
        if (null === $this->rawToken) {
            $this->rawToken = $this->tokenPersistence->restoreToken(new Token\RawToken());
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
     * Helper method for (callable)tokenFactory
     *
     * @return TokenInterface
     */
    protected function tokenFactory()
    {
        return call_user_func_array($this->tokenFactory, func_get_args());
    }

    /**
     * Acquire a new access token from the server.
     *
     * @return TokenInterface|null
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

                $this->rawToken = $this->tokenFactory($rawData, $this->rawToken);

                return;
            } catch (BadResponseException $e) {
                // If the refresh token is invalid, then clear the entire token information.
                $this->rawToken = null;
            }
        }

        if ($this->grantType === null) {
            throw new Exception\ReauthorizationException('You must specify a grantType class to request an access token');
        }

        try {
            // Request an access token using the main grant type.
            $rawData = $this->grantType->getRawData($this->clientCredentialsSigner);

            $this->rawToken = $this->tokenFactory($rawData);
        } catch (BadResponseException $e) {
            throw new Exception\AccessTokenRequestException('Unable to request a new access token', $e);
        }
    }
}
