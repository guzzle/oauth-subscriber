<?php
namespace GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials;

use GuzzleHttp\Message\RequestInterface;

interface SignerInterface
{
    /**
     * @param RequestInterface $request
     * @param string           $clientId
     * @param string           $clientSecret
     */
    public function sign(RequestInterface $request, $clientId, $clientSecret);
}
