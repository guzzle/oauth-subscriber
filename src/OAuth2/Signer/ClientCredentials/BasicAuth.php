<?php

namespace GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials;

use GuzzleHttp\Message\RequestInterface;

class BasicAuth implements SignerInterface
{
    /**
     * {@inheritdoc}
     */
    public function sign(RequestInterface $request, $clientId, $clientSecret)
    {
        $request->getConfig()->set('auth', 'basic');
        $request->setHeader('Authorization', 'Basic '.base64_encode($clientId.':'.$clientSecret));
    }
}
