<?php

namespace GuzzleHttp\Subscriber\OAuth2\Persistence;

use GuzzleHttp\Subscriber\OAuth2\Token\RawToken;
use GuzzleHttp\Subscriber\OAuth2\Token\TokenInterface;

class NullTokenPersistence implements TokenPersistenceInterface
{
    public function saveToken(TokenInterface $token)
    {
        return;
    }

    public function restoreToken(TokenInterface $token)
    {
        return null;
    }

    public function deleteToken()
    {
        return;
    }
}
