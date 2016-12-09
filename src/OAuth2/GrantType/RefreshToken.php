<?php
namespace GuzzleHttp\Subscriber\OAuth2\GrantType;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Post\PostBody;
use GuzzleHttp\Subscriber\OAuth2\Exception\ReauthorizationException;
use GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials\SignerInterface;
use GuzzleHttp\Subscriber\OAuth2\TokenData;
use GuzzleHttp\Subscriber\OAuth2\Utils;

/**
 * Refresh token grant type.
 *
 * @link http://tools.ietf.org/html/rfc6749#section-6
 */
class RefreshToken implements GrantTypeInterface
{
    /**
     * The token endpoint client.
     *
     * @var ClientInterface
     */
    private $client;

    /**
     * Configuration settings.
     *
     * @var Collection
     */
    private $config;

    public function __construct(ClientInterface $client, $config)
    {
        $this->client = $client;
        $this->config = Collection::fromConfig($config,
            // Defaults
            [
                'client_secret' => '',
                'refresh_token' => '',
                'scope' => '',
            ],
            // Required
            [
                'client_id',
            ]
        );
    }

    public function getRawData(SignerInterface $clientCredentialsSigner, $refreshToken = null)
    {
        $postBody = new PostBody();
        $postBody->replaceFields([
            'grant_type' => 'refresh_token',
            // If no refresh token was provided to the method, use the one
            // provided to the constructor.
            'refresh_token' => $refreshToken ?: $this->config['refresh_token'],
        ]);

        if ($this->config['scope']) {
            $postBody->setField('scope', $this->config['scope']);
        }

        $request = $this->client->createRequest('POST', null);
        $request->setBody($postBody);
        $clientCredentialsSigner->sign(
            $request,
            $this->config['client_id'],
            $this->config['client_secret']
        );

        $response = $this->client->send($request);

        return $response->json();
    }
}
