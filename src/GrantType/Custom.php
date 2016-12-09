<?php

namespace GuzzleHttp\Subscriber\Oauth\GrantType;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Subscriber\Oauth\AccessToken;

class Custom implements GrantTypeInterface
{
    public function __construct(ClientInterface $client, $config)
    {
        $this->client = $client;
        $this->config = Collection::fromConfig(
            $config,
            [
                'client_secret' => '',
                'scope'         => '',
            ],
            ['client_id', 'grant_type']
        );
    }

    public function getToken()
    {
        $body = $this->config->toArray();
        $response = $this->client->post(null, ['body' => $body]);
        $data = $response->json();

        return new AccessToken(
            $data['access_token'],
            $data['expires_in'],
            $data['token_type'],
            $data['scope']
        );
    }
}
