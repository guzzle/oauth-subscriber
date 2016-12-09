<?php
namespace GuzzleHttp\Subscriber\OAuth2\GrantType;

use GuzzleHttp\Subscriber\OAuth2\TokenData;
use GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials\SignerInterface;

interface GrantTypeInterface
{
    /**
     * Get the token data returned by the OAuth2 server.
     *
     * @param SignerInterface $clientCredentialsSigner
     * @param string          $refreshToken
     *
     * @return array
     */
    public function getRawData(SignerInterface $clientCredentialsSigner, $refreshToken = null);
}
