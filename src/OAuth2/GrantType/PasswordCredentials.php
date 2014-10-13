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
 * Resource owner password credentials grant type.
 *
 * @link http://tools.ietf.org/html/rfc6749#section-4.3
 */
class PasswordCredentials implements GrantTypeInterface
{
    /**
     * The token endpoint client.
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * Configuration settings.
     *
     * @var Collection
     */
    protected $config;

    public function __construct(ClientInterface $client, array $config)
    {
        $this->client = $client;
        $this->config = Collection::fromConfig($config,
            // Default
            [
                'client_secret' => '',
                'scope' => '',
            ],
            // Required
            [
                'client_id',
                'username',
                'password',
            ]
        );
    }

    public function getRawData(SignerInterface $clientCredentialsSigner, $refreshToken = null)
    {
        $request = $this->client->createRequest('POST', null);
        $request->setBody($this->getPostBody());

        $clientCredentialsSigner->sign(
            $request,
            $this->config['client_id'],
            $this->config['client_secret']
        );

        $response = $this->client->send($request);

        return $response->json();
    }

     /**
     * @return PostBody
     */
    protected function getPostBody()
    {
        $postBody = [
            'grant_type' => 'password',
            'username'   => $this->config['username'],
            'password'   => $this->config['password'],
        ];

        if ($this->config['scope']) {
            $postBody['scope'] = $this->config['scope'];
        }

        return (new PostBody())->replaceFields($postBody);
    }
}
