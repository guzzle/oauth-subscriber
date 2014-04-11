<?php

namespace GuzzleHttp\Subscriber\Oauth;

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Subscriber\Oauth\GrantType\GrantTypeInterface;

class Oauth2 implements SubscriberInterface
{
    private $accessToken;
    private $grantType;

    public function __construct(GrantTypeInterface $grantType = null)
    {
        $this->grantType = $grantType;
    }

    public function getEvents()
    {
        return ['before' => ['onBefore', RequestEvents::SIGN_REQUEST]];
    }

    public function onBefore(BeforeEvent $event)
    {
        $request = $event->getRequest();

        // Only sign requests using "auth"="oauth"
        if ($request->getConfig()->get('auth') != 'oauth2') {
            return;
        }

        $token = $this->getAccessToken();
        $header = $this->getAuthorizationHeader($token);

        $request->setHeader('Authorization', $header);
    }

    /**
     * Set access token
     *
     * @param string $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Get access token
     *
     * @return string
     */
    public function getAccessToken()
    {
        if (null === $this->accessToken) {
            if ($this->grantType) {
                $this->accessToken = $this->grantType->getToken();
            }
        }

        return $this->accessToken;
    }

    private function getAuthorizationHeader(AccessToken $token)
    {
        return sprintf('Bearer %s', $token->getToken());
    }
}
