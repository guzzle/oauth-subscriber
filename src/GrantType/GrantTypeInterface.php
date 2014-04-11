<?php

namespace GuzzleHttp\Subscriber\Oauth\GrantType;

use GuzzleHttp\Subscriber\Oauth\AccessToken;

/**
 * OAuth2 grant type
 */
interface GrantTypeInterface
{
    /**
     * Get access token
     *
     * @return AccessToken
     */
    public function getToken();
}
