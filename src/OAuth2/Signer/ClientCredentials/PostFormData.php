<?php

namespace GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials;

use GuzzleHttp\Message\RequestInterface;

class PostFormData implements SignerInterface
{
    protected $clientIdField;
    protected $clientSecretField;

    public function __construct($clientIdField = 'client_id', $clientSecretField = 'client_secret')
    {
        $this->clientIdField = $clientIdField;
        $this->clientSecretField = $clientSecretField;
    }

    public function sign(RequestInterface $request, $clientId, $clientSecret)
    {
        $body = $request->getBody();
        $body->setField($this->clientIdField, $clientId);
        $body->setField($this->clientSecretField, $clientSecret);
    }
}
