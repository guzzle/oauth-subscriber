<?php

namespace GuzzleHttp\Subscriber\OAuth2\Factory;

interface TokenFactoryInterface
{
    /**
     * Create a RawToken object from a raw response.
     *
     * @param array $rawData The decoded response from the server
     *
     * @return GuzzleHttp\Subscriber\OAuth2\RawToken The token data
     */
    public function createRawToken(array $data);
}
