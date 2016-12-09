<?php
namespace GuzzleHttp\Subscriber\OAuth2\GrantType\Specific;

use GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials\SignerInterface;
use GuzzleHttp\Subscriber\OAuth2\TokenData;
use HWI\Bundle\OAuthBundle\Security\Http\ResourceOwnerMap;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * HWIOAuthBundle Aware Refresh token grant type.
 *
 * @link http://tools.ietf.org/html/rfc6749#section-6
 */
class HWIOAuthBundleRefreshToken implements GrantTypeInterface
{
    /**
     * Symfony2 security component.
     *
     * @var SecurityContextInterface
     */
    private $securityContext;

    /**
     * HWIOAuthBundle OAuth2 ResourceOwnerMap.
     *
     * @var ResourceOwnerMap
     */
    private $resourceOwnerMap;

    public function __construct(ResourceOwnerMap $resourceOwnerMap, SecurityContextInterface $securityContext)
    {
        $this->securityContext = $securityContext;
        $this->resourceOwnerMap = $resourceOwnerMap;
    }

    public function getRawData(SignerInterface $clientCredentialsSigner, $refreshToken = null)
    {
        $token = $this->securityContext->getToken();
        $resourceName = $token->getResourceOwnerName();
        $resourceOwner = $this->resourceOwnerMap->getResourceOwnerByName($resourceName);

        $data = $resourceOwner->refreshAccessToken($refreshToken);
        $token->setRawToken($data);

        return $data;
    }
}
