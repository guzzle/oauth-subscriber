<?php

namespace GuzzleHttp\Subscriber\OAuth2\Tests\Persistence;

use GuzzleHttp\Subscriber\OAuth2\Persistence\DoctrineCacheTokenPersistence;
use GuzzleHttp\Subscriber\OAuth2\Factory\GenericTokenFactory;
use Doctrine\Common\Cache\ArrayCache;

class DoctrineCacheTokenPersistenceTest extends TokenPersistenceTestBase
{
    protected $cache;

    public function getInstance()
    {
        return new DoctrineCacheTokenPersistence($this->cache);
    }

    public function setUp()
    {
        $this->cache = new ArrayCache();
    }

    public function testRestoreTokenCustomKey()
    {
        $doctrine = new DoctrineCacheTokenPersistence($this->cache, 'foo-bar');

        $factory = new GenericTokenFactory();
        $token = $factory([
            'access_token' => 'abcdefghijklmnop',
            'refresh_token' => '0123456789abcdef',
            'expires_in' => 3600,
        ]);
        $doctrine->saveToken($token);

        $restoredToken = $doctrine->restoreToken($factory);
        $this->assertInstanceOf('\GuzzleHttp\Subscriber\OAuth2\RawToken', $restoredToken);

        $tokenBefore = $token->toArray();
        $tokenAfter = $restoredToken->toArray();

        $this->assertEquals($tokenBefore, $tokenAfter);
    }
}
