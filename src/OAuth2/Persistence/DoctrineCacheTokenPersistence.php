<?php

namespace GuzzleHttp\Subscriber\OAuth2\Persistence;

use Doctrine\Common\Cache\Cache;
use GuzzleHttp\Subscriber\OAuth2\Token\RawToken;
use GuzzleHttp\Subscriber\OAuth2\Token\TokenInterface;

class DoctrineCacheTokenPersistence implements TokenPersistenceInterface
{
    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var string
     */
    protected $cacheKey;

    public function __construct(Cache $cache, $cacheKey = 'guzzle-oauth2-token')
    {
        $this->cache = $cache;
        $this->cacheKey = $cacheKey;
    }

    public function saveToken(TokenInterface $token)
    {
        $this->cache->save($this->cacheKey, $token->serialize());
    }

    public function restoreToken(TokenInterface $token)
    {
        $data = $this->cache->fetch($this->cacheKey);

        if (false === $data) {
            return null;
        }

        return $token->unserialize($data);
    }

    public function deleteToken()
    {
        $this->cache->delete($this->cacheKey);
    }
}
