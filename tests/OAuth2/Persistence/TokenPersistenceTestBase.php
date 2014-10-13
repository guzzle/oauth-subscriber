<?php

namespace GuzzleHttp\Subscriber\OAuth2\Tests\Persistence;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Subscriber\OAuth2\RawToken;
use GuzzleHttp\Subscriber\OAuth2\Factory\GenericTokenFactory;

abstract class TokenPersistenceTestBase extends PHPUnit_Framework_TestCase
{
    abstract public function getInstance();

    public function testSaveToken()
    {
        $factory = new GenericTokenFactory();
        $token = $factory([
            'access_token' => 'abcdefghijklmnop',
            'refresh_token' => '0123456789abcdef',
            'expires_in' => 3600,
        ]);
        $this->getInstance()->saveToken($token);
    }

    public function testRestoreToken()
    {
        $factory = new GenericTokenFactory();
        $token = $factory([
            'access_token' => 'abcdefghijklmnop',
            'refresh_token' => '0123456789abcdef',
            'expires_in' => 3600,
        ]);
        $this->getInstance()->saveToken($token);

        $restoredToken = $this->getInstance()->restoreToken($factory);
        $this->assertInstanceOf('\GuzzleHttp\Subscriber\OAuth2\RawToken', $restoredToken);

        $token_before = $token->toArray();
        $token_after = $restoredToken->toArray();

        $this->assertEquals($token_before, $token_after);
    }

    public function testDeleteToken()
    {
        $factory = new GenericTokenFactory();
        $token = $factory([
            'access_token' => 'abcdefghijklmnop',
            'refresh_token' => '0123456789abcdef',
            'expires_in' => 3600,
        ]);

        $persist = $this->getInstance();

        $persist->saveToken($token);

        $restoredToken = $persist->restoreToken($factory);
        $this->assertInstanceOf('\GuzzleHttp\Subscriber\OAuth2\RawToken', $restoredToken);

        $persist->deleteToken();

        $restoredToken = $persist->restoreToken($factory);
        $this->assertNull($restoredToken);
    }

    public function testRestoreTokenCustomFactory()
    {
        $factory = function (array $data, RawToken $previousToken = null) {
            $accessToken = strtoupper($data['access_token']);
            $expiresAt = isset($data['expires_at'])? $data['expires_at']: time() + $data['expires_in'];

            return new RawToken($accessToken, $data['refresh_token'], $expiresAt);
        };

        $token = $factory([
            'access_token' => 'abcdefghijklmnop',
            'refresh_token' => '0123456789abcdef',
            'expires_in' => 3600,
        ]);

        $this->getInstance()->saveToken($token);

        $restoredToken = $this->getInstance()->restoreToken($factory);
        $this->assertInstanceOf('\GuzzleHttp\Subscriber\OAuth2\RawToken', $restoredToken);
        $this->assertEquals('ABCDEFGHIJKLMNOP', $restoredToken->getAccessToken());
    }
}
