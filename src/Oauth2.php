<?php

namespace GuzzleHttp\Subscriber\Oauth;

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\RequestInterface;
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
        return [
            'before' => ['onBefore', RequestEvents::SIGN_REQUEST],
            'error'  => ['onError', RequestEvents::EARLY],
        ];
    }

    public function onBefore(BeforeEvent $event)
    {
        $request = $event->getRequest();

        // Only sign requests using "auth"="oauth2"
        if ($request->getConfig()->get('auth') != 'oauth2') {
            return;
        }

        $token = $this->getAccessToken();
        $header = $this->getAuthorizationHeader($token);

        $request->setHeader('Authorization', $header);
    }

    public function onError(ErrorEvent $event)
    {
        if (401 == $event->getResponse()->getStatusCode()) {
            $request = $event->getRequest();
            if (!$request->getConfig()->get('retried')) {
                if ($this->acquireAccessToken()) {
                    $request->getConfig()->set('retried', true);
                    $this->setHeader($request);
                    $event->intercept($event->getClient()->send($request));
                }
            }
        }
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
            $this->acquireAccessToken();
        }

        return $this->accessToken;
    }

    private function setHeader(RequestInterface $request)
    {
        $token = $this->getAccessToken();
        $header = $this->getAuthorizationHeader($token);
        $request->setHeader('Authorization', $header);
    }

    private function getAuthorizationHeader(AccessToken $token)
    {
        return sprintf('Bearer %s', $token->getToken());
    }

    private function acquireAccessToken()
    {
        if ($this->grantType) {
            $this->accessToken = $this->grantType->getToken();
        }

        return $this->accessToken;
    }
}
