<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Oauth1;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Controls a local node.js server that returns queued responses.
 */
class Server
{
    /** @var Client|null */
    private static $client;

    /** @var bool */
    private static $configured = false;

    /** @var bool */
    private static $started = false;

    /** @var string */
    public static $url = 'http://127.0.0.1:8126/';

    /** @var int */
    public static $port = 8126;

    /**
     * Flush the received requests from the server.
     */
    public static function flush()
    {
        return self::getClient()->request('DELETE', 'guzzle-server/requests');
    }

    /**
     * Queue an array of responses or a single response on the server.
     *
     * @param array|ResponseInterface $responses A single or array of responses
     *
     * @throws \InvalidArgumentException
     */
    public static function enqueue($responses): void
    {
        $data = [];
        foreach ((array) $responses as $response) {
            if (!$response instanceof ResponseInterface) {
                throw new \InvalidArgumentException('Invalid response given.');
            }

            $headers = array_map(static function ($header): string {
                return implode(' ,', $header);
            }, $response->getHeaders());

            $data[] = [
                'status' => (string) $response->getStatusCode(),
                'reason' => $response->getReasonPhrase(),
                'headers' => $headers,
                'body' => base64_encode((string) $response->getBody()),
            ];
        }

        self::getClient()->request('PUT', 'guzzle-server/responses', [
            'json' => $data,
        ]);
    }

    /**
     * Get all of the received requests.
     *
     * @return Request[]
     */
    public static function received(): array
    {
        if (!self::$started) {
            return [];
        }

        $response = self::getClient()->request('GET', 'guzzle-server/requests');
        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data)) {
            return [];
        }

        return array_map(
            static function (array $message): Request {
                $uri = $message['uri'];
                if (isset($message['query_string'])) {
                    $uri .= '?'.$message['query_string'];
                }

                $request = new Request(
                    $message['http_method'],
                    $uri,
                    $message['headers'],
                    $message['body'],
                    $message['version']
                );

                return $request->withUri(
                    $request->getUri()
                        ->withScheme('http')
                        ->withHost($request->getHeaderLine('host'))
                );
            },
            $data
        );
    }

    /**
     * Stop running the node.js server.
     */
    public static function stop(): void
    {
        if (!self::$started) {
            return;
        }

        try {
            self::getClient()->request('DELETE', 'guzzle-server');
        } catch (\Exception $e) {
            // The process may already have been stopped by a local developer.
        }

        self::$started = false;
    }

    /**
     * Wait for the local node.js server to accept requests.
     */
    public static function wait($maxTries = 10): void
    {
        $tries = 0;
        while (!self::isListening() && ++$tries < $maxTries) {
            usleep(50000 * $tries ** 2);
        }

        if (!self::isListening()) {
            throw new \RuntimeException('Unable to contact node.js server');
        }
    }

    /**
     * Start the local node.js server if it is not already running.
     */
    public static function start(): void
    {
        self::configure();

        if (self::$started) {
            return;
        }

        if (!self::isListening()) {
            $logFile = sys_get_temp_dir().'/oauth-subscriber-server.log';
            exec('node '.escapeshellarg(__DIR__.'/server.js').' '.self::$port.' >> '.escapeshellarg($logFile).' 2>&1 &');
            self::wait();
        }

        self::$started = true;
    }

    private static function configure(): void
    {
        if (self::$configured) {
            return;
        }

        $port = getenv('OAUTH_SUBSCRIBER_TEST_SERVER_PORT');
        if ($port !== false && $port !== '') {
            self::$port = (int) $port;
        }

        self::$url = 'http://127.0.0.1:'.self::$port.'/';
        self::$configured = true;
    }

    private static function isListening(): bool
    {
        try {
            self::getClient()->request('GET', 'guzzle-server/perf', [
                'connect_timeout' => 5,
                'timeout' => 5,
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function getClient(): Client
    {
        self::configure();

        if (!self::$client) {
            self::$client = new Client([
                'base_uri' => self::$url,
                'sync' => true,
            ]);
        }

        return self::$client;
    }
}
