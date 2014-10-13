<?php

namespace GuzzleHttp\Subscriber\OAuth2\Persistence;

use GuzzleHttp\Subscriber\OAuth2\RawToken;

interface TokenPersistenceInterface
{
    /**
     * Restore the token data.
     *
     * @param  callable $tokenFactory
     * @return RawToken
     */
    public function restoreToken(callable $tokenFactory);

    /**
     * Save the token data.
     *
     * @param RawToken $token
     */
    public function saveToken(RawToken $token);

    /**
     * Delete the saved token data.
     */
    public function deleteToken();
}
