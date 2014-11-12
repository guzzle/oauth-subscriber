<?php
namespace GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Post\PostBodyInterface;

class PostFormData implements SignerInterface
{
    private $clientIdField;
    private $clientSecretField;

    public function __construct($clientIdField = 'client_id', $clientSecretField = 'client_secret')
    {
        $this->clientIdField = $clientIdField;
        $this->clientSecretField = $clientSecretField;
    }

    public function sign(RequestInterface $request, $clientId, $clientSecret)
    {
        $body = $request->getBody();

        if (!$body instanceof PostBodyInterface) {
            throw new \RuntimeException('Unable to set fields in request body');
        }

        $body->setField($this->clientIdField, $clientId);
        $body->setField($this->clientSecretField, $clientSecret);
    }
}
