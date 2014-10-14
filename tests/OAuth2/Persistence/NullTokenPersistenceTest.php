<?php

namespace GuzzleHttp\Subscriber\OAuth2\Tests\Persistence;

use GuzzleHttp\Subscriber\OAuth2\Persistence\NullTokenPersistence;
use GuzzleHttp\Subscriber\OAuth2\Token\RawToken;

class NullTokenPersistenceTest extends TokenPersistenceTestBase
{
    public function getInstance()
    {
        return new NullTokenPersistence();
    }

    public function testRestoreToken()
    {
        $this->testSaveToken();
        $this->assertNull($this->getInstance()->restoreToken(new RawToken));
    }

    public function testDeleteToken()
    {
        $this->testSaveToken();
        $this->getInstance()->deleteToken();
        $this->assertNull($this->getInstance()->restoreToken(new RawToken));
    }
}
