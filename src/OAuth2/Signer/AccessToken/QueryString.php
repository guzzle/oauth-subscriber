<?php

namespace GuzzleHttp\Subscriber\OAuth2\Signer\AccessToken;

use GuzzleHttp\Message\RequestInterface;

class QueryString implements SignerInterface
{
    protected $fieldName;

    public function __construct($fieldName = 'access_token')
    {
        $this->fieldName = $fieldName;
    }

    public function sign(RequestInterface $request, $accessToken)
    {
        $request->getQuery()->set($this->fieldName, $accessToken);
    }
}
