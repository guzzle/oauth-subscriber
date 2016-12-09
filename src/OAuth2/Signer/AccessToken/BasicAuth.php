<?php
namespace GuzzleHttp\Subscriber\OAuth2\Signer\AccessToken;

use GuzzleHttp\Message\RequestInterface;

class BasicAuth implements SignerInterface
{
    public function sign(RequestInterface $request, $accessToken)
    {
        $request->setHeader('Authorization', 'Bearer ' . $accessToken);
    }
}
