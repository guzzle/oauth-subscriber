<?php

namespace GuzzleHttp\Subscriber\OAuth2\Persistence;

use GuzzleHttp\Subscriber\OAuth2\RawToken;

class NullTokenPersistence implements TokenPersistenceInterface
{
    /**
     * {@inheritdoc}
     */
    public function saveToken(RawToken $token)
    {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function restoreToken()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteToken()
    {
        return;
    }
}
