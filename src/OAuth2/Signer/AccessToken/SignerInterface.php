<?php

namespace GuzzleHttp\Subscriber\OAuth2\Signer\AccessToken;

use GuzzleHttp\Message\RequestInterface;

interface SignerInterface
{
    /**
     * @param RequestInterface $request
     * @param string           $accessToken
     */
    public function sign(RequestInterface $request, $accessToken);
}
