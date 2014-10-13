<?php

namespace GuzzleHttp\Subscriber\OAuth2\Persistence;

use GuzzleHttp\Subscriber\OAuth2\RawToken;

class FileTokenPersistence implements TokenPersistenceInterface
{
    /**
     * @var string
     */
    protected $filepath;

    public function __construct($filepath)
    {
        $this->filepath = $filepath;
    }

    public function saveToken(RawToken $token)
    {
        file_put_contents($this->filepath, json_encode($token->toArray()));
    }

    public function restoreToken(callable $tokenFactory)
    {
        if (!file_exists($this->filepath)) {
            return null;
        }

        $data = json_decode(file_get_contents($this->filepath), true);

        if (false === $data) {
            return null;
        }

        return $tokenFactory($data);
    }

    public function deleteToken()
    {
        if (file_exists($this->filepath)) {
            unlink($this->filepath);
        }
    }
}
