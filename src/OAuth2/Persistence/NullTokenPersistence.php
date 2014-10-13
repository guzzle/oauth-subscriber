<?php

namespace GuzzleHttp\Subscriber\OAuth2\Persistence;

use GuzzleHttp\Subscriber\OAuth2\RawToken;

class NullTokenPersistence implements TokenPersistenceInterface
{
    public function saveToken(RawToken $token)
    {
        return;
    }

    public function restoreToken(callable $tokenFactory)
    {
        return null;
    }

    public function deleteToken()
    {
        return;
    }
}
