<?php
namespace GuzzleHttp\Subscriber\OAuth2\Tests\Token;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Subscriber\OAuth2\Token\RawToken;

class RawTokenTest extends PHPUnit_Framework_TestCase
{
    protected static $tokenData = [
        'access_token' => '01234567890123456789abcdef',
        'refresh_token' => '01234567890123456789abcdef',
        'expires_at' => 123456789,
    ];

    public function testConstruct()
    {
        $token = new RawToken(
            self::$tokenData['access_token'],
            self::$tokenData['refresh_token'],
            self::$tokenData['expires_at']
        );
    }

    public function testSerialize()
    {
        $token = new RawToken(
            self::$tokenData['access_token'],
            self::$tokenData['refresh_token'],
            self::$tokenData['expires_at']
        );

        $this->assertEquals(self::$tokenData, $token->serialize());
    }

    public function testUnserialize()
    {
        $token = new RawToken(
            self::$tokenData['access_token'],
            self::$tokenData['refresh_token'],
            self::$tokenData['expires_at']
        );

        $serialized = $token->serialize();
        $restored_token = (new RawToken())->unserialize($serialized);

        $this->assertEquals($token->getAccessToken(), $restored_token->getAccessToken());
        $this->assertEquals($token->getRefreshToken(), $restored_token->getRefreshToken());
        $this->assertEquals($token->getExpiresAt(), $restored_token->getExpiresAt());
        $this->assertEquals($token->isExpired(), $restored_token->isExpired());
    }

    public function testGetters()
    {
        $token = new RawToken(
            self::$tokenData['access_token'],
            self::$tokenData['refresh_token'],
            self::$tokenData['expires_at']
        );

        $this->assertEquals(self::$tokenData['access_token'], $token->getAccessToken());
        $this->assertEquals(self::$tokenData['refresh_token'], $token->getRefreshToken());
        $this->assertEquals(self::$tokenData['expires_at'], $token->getExpiresAt());
    }

    public function testIsExpired()
    {
        $token = new RawToken(
            self::$tokenData['access_token'],
            self::$tokenData['refresh_token'],
            time() + 3600
        );

        $this->assertFalse($token->isExpired());

        $token = new RawToken(
            self::$tokenData['access_token'],
            self::$tokenData['refresh_token'],
            time() - 3600
        );

        $this->assertTrue($token->isExpired());
    }
}
