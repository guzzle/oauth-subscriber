<?php

namespace GuzzleHttp\Subscriber\Oauth;

class AccessToken
{
    private $token;
    private $expires;
    private $type;
    private $scope;

    public function __construct($token, $expiresIn, $type, $scope)
    {
        $this->token = $token;
        $this->expires = new \DateTime();
        $this->expires->add(new \DateInterval(sprintf('PT%sS', $expiresIn)));
        $this->type = $type;
        $this->scope = $scope;
    }

    public function getExpires()
    {
        return $this->expires;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getType()
    {
        return $this->type;
    }
}
