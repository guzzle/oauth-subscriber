<?php

namespace GuzzleHttp\Subscriber\OAuth2\Persistence;

use Doctrine\Common\Cache\Cache;
use GuzzleHttp\Subscriber\OAuth2\RawToken;

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

    /**
     * {@inheritdoc}
     */
    public function saveToken(RawToken $token)
    {
        $this->cache->save($this->cacheKey, $token->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function restoreToken()
    {
        $data = $this->cache->fetch($this->cacheKey);

        if (false === $data) {
            return null;
        }

        return RawToken::fromArray($data);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteToken()
    {
        $this->cache->delete($this->cacheKey);
    }
}
