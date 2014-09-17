<?php

namespace GuzzleHttp\Subscriber\OAuth2\Factory;

use GuzzleHttp\Subscriber\OAuth2\RawToken;

class GenericTokenFactory implements TokenFactoryInterface
{
    /**
     * {@inheticdoc}
     */
    public function createRawToken(array $data)
    {
        $accessToken = null;
        $refreshToken = null;
        $expiresAt = null;

        // Read "access_token" attribute
        if (isset($data['access_token'])) {
            $accessToken = $data['access_token'];
        }

        // Read "refresh_token" attribute
        if (isset($data['refresh_token'])) {
            $refreshToken = $data['refresh_token'];
        } elseif (null !== $previousToken) {
            // When requesting a new access token with a refresh token, the
            // server may not resend a new refresh token. In that case we
            // should keep the previous refresh token as valid.
            //
            // See http://tools.ietf.org/html/rfc6749#section-6
            $refreshToken = $previousToken->getRefreshToken();
        }

        // Read the "expires_in" attribute
        if (isset($data['expires_in'])) {
            $expiresAt = time() + (int) $data['expires_in'];
        } elseif (isset($data['expires'])) {
            // Facebook unfortunately breaks the spec by using 'expires' instead of 'expires_in'
            $expiresAt = time() + (int) $data['expires'];
        }

        return new RawToken($accessToken, $refreshToken, $expiresAt);
    }
}
