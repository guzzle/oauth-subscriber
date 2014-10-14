<?php

namespace GuzzleHttp\Subscriber\OAuth2\Tests;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Adapter\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\OAuth2\OAuth2Subscriber;
use GuzzleHttp\Subscriber\OAuth2\Token\RawToken;

/**
 * OAuth2 plugin.
 *
 * @author Steve Kamerman <stevekamerman@gmail.com>
 * @author Matthieu Moquet <matthieu@moquet.net>
 *
 * @link http://tools.ietf.org/html/rfc6749 OAuth2 specification
 */
class OAuth2SubscriberTest extends PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $grant = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\GrantType\ClientCredentials')
            //->setConstructorArgs([$client, []])
            ->disableOriginalConstructor()
            ->getMock();

        $sub = new OAuth2Subscriber();
        $sub = new OAuth2Subscriber($grant);
    }

    public function testSetAccessTokenAsArray()
    {
        $tokenData = [
            'access_token' => '01234567890123456789abcdef',
            'refresh_token' => '01234567890123456789abcdef',
            'expires_in' => 3600,
        ];

        $sub = new OAuth2Subscriber();

        $sub->setAccessToken($tokenData);
        $this->assertEquals($tokenData['access_token'], $sub->getAccessToken());
    }

    public function testSetAccessTokenAsString()
    {
        $tokenData = [
            'access_token' => '01234567890123456789abcdef',
            'refresh_token' => '01234567890123456789abcdef',
            'expires_in' => 3600,
        ];

        $sub = new OAuth2Subscriber();

        $sub->setAccessToken($tokenData['access_token']);
        $this->assertEquals($tokenData['access_token'], $sub->getAccessToken());
    }

    public function testSetAccessTokenAsObject()
    {
        $tokenData = [
            'access_token' => '01234567890123456789abcdef',
            'refresh_token' => '01234567890123456789abcdef',
            'expires_in' => 3600,
            'expires_at' => time() + 3600,
        ];

        $sub = new OAuth2Subscriber();

        $token = new RawToken($tokenData['access_token'], $tokenData['refresh_token'], $tokenData['expires_at']);
        $sub->setAccessToken($token);
        $this->assertEquals($tokenData['access_token'], $sub->getAccessToken());
    }

    public function testGetAccessTokenCausesReauth()
    {
        $sub = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\OAuth2Subscriber')
            ->setMethods(['requestNewAccessToken'])
            ->getMock();

        $sub->expects($this->once())
            ->method('requestNewAccessToken')
            ->will($this->returnValue(null));

        $sub->getAccessToken();
    }

    public function testExpiredTokenCausesReauth()
    {
        $tokenData = [
            'access_token' => '01234567890123456789abcdef',
            'refresh_token' => '01234567890123456789abcdef',
            'expires_in' => -3600,
        ];

        $sub = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\OAuth2Subscriber')
            ->setMethods(['requestNewAccessToken'])
            ->getMock();

        $sub->expects($this->once())
            ->method('requestNewAccessToken')
            ->will($this->returnValue(null));

        $sub->setAccessToken($tokenData);
        $sub->getAccessToken();
    }

    public function testValidTokenDoesNotCauseReauth()
    {
        $tokenData = [
            'access_token' => '01234567890123456789abcdef',
            'refresh_token' => '01234567890123456789abcdef',
            'expires_in' => 3600,
        ];

        $sub = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\OAuth2Subscriber')
            ->setMethods(['requestNewAccessToken'])
            ->getMock();

        $sub->expects($this->exactly(0))
            ->method('requestNewAccessToken');

        $sub->setAccessToken($tokenData);
        $sub->getAccessToken();
    }

    public function testOnBeforeDoesNotTriggerForNonOAuthRequests()
    {
        // Setup Access Token Signer
        $signer = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\Signer\AccessToken\BasicAuth')
            ->setMethods(['sign'])
            ->getMock();

        $signer->expects($this->exactly(0))
            ->method('sign');

        // Setup OAuth2Subscriber
        $sub = new OAuth2Subscriber();
        $sub->setAccessTokenSigner($signer);

        $client = new Client();
        $request = new Request('GET', '/');
        $event = new BeforeEvent(new Transaction($client, $request));

        // Force an onBefore event, which triggers the signer and grant data processor
        $sub->onBefore($event);
    }

    public function testOnBeforeTriggersSignerAndGrantDataProcessor()
    {
        // Setup Access Token Signer
        $signer = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\Signer\AccessToken\BasicAuth')
            ->setMethods(['sign'])
            ->getMock();

        $signer->expects($this->once())
            ->method('sign')
            ->will($this->returnValue('foo'));

        // Setup Grant Type
        $grant = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\GrantType\ClientCredentials')
            ->setMethods(['getRawData'])
            ->disableOriginalConstructor()
            ->getMock();

        $grant->expects($this->once())
            ->method('getRawData')
            ->will($this->returnValue([
                'access_token' => '01234567890123456789abcdef',
                'refresh_token' => '01234567890123456789abcdef',
                'expires_in' => 3600,
            ]));

        // Setup OAuth2Subscriber
        $sub = new OAuth2Subscriber($grant);
        $sub->setAccessTokenSigner($signer);

        $client = new Client();
        $request = new Request('GET', '/', [], null, ['auth' => 'oauth']);
        $event = new BeforeEvent(new Transaction($client, $request));

        // Force an onBefore event, which triggers the signer and grant data processor
        $sub->onBefore($event);
    }

    public function testOnErrorDoesNotTriggerForNonOAuthRequests()
    {
        // Setup Grant Type
        $grant = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\GrantType\ClientCredentials')
            ->setMethods(['getRawData'])
            ->disableOriginalConstructor()
            ->getMock();

        $grant->expects($this->exactly(0))
            ->method('getRawData');

        // Setup OAuth2Subscriber
        $sub = new OAuth2Subscriber($grant);

        $client = new Client();
        $request = new Request('GET', '/');
        $response = new Response(401);
        $transaction = new Transaction($client, $request);
        $except = new RequestException('foo', $request, $response);
        $event = new ErrorEvent($transaction, $except);

        // Force an onError event, which triggers the signer and grant data processor
        $sub->onError($event);
    }

    public function testOnErrorDoesNotTriggerForNon401Requests()
    {
        // Setup Grant Type
        $grant = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\GrantType\ClientCredentials')
            ->setMethods(['getRawData'])
            ->disableOriginalConstructor()
            ->getMock();

        $grant->expects($this->exactly(0))
            ->method('getRawData');

        // Setup OAuth2Subscriber
        $sub = new OAuth2Subscriber($grant);

        $client = new Client();
        $request = new Request('GET', '/', [], null, ['auth' => 'oauth']);
        $response = new Response(404);
        $transaction = new Transaction($client, $request);
        $except = new RequestException('foo', $request, $response);
        $event = new ErrorEvent($transaction, $except);

        // Force an onError event, which triggers the signer and grant data processor
        $sub->onError($event);
    }

    public function testOnErrorDoesNotLoop()
    {
        // Setup Grant Type
        $grant = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\GrantType\ClientCredentials')
            ->setMethods(['getRawData'])
            ->disableOriginalConstructor()
            ->getMock();

        $grant->expects($this->exactly(0))
            ->method('getRawData');

        // Setup OAuth2Subscriber
        $sub = new OAuth2Subscriber($grant);

        $client = new Client();
        $request = new Request('GET', '/', [], null, ['auth' => 'oauth']);
        // This header keeps the subscriber from trying to reauth a reauth request (infinte loop)
        $request->setHeader('X-Guzzle-Retry', 1);
        $response = new Response(401);
        $transaction = new Transaction($client, $request);
        $except = new RequestException('foo', $request, $response);
        $event = new ErrorEvent($transaction, $except);

        // Force an onError event, which triggers the signer and grant data processor
        $sub->onError($event);
    }

    public function testOnErrorTriggersSignerAndGrantDataProcessor()
    {
        // Setup Access Token Signer
        $signer = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\Signer\AccessToken\BasicAuth')
            ->setMethods(['sign'])
            ->getMock();

        $signer->expects($this->once())
            ->method('sign')
            ->will($this->returnValue('foo'));

        // Setup Grant Type
        $grant = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\GrantType\ClientCredentials')
            ->setMethods(['getRawData'])
            ->disableOriginalConstructor()
            ->getMock();

        $grant->expects($this->once())
            ->method('getRawData')
            ->will($this->returnValue([
                'access_token' => '01234567890123456789abcdef',
                'refresh_token' => '01234567890123456789abcdef',
                'expires_in' => 3600,
            ]));

        // Setup OAuth2Subscriber
        $sub = new OAuth2Subscriber($grant);
        $sub->setAccessTokenSigner($signer);

        $request = new Request('GET', '/', [], null, ['auth' => 'oauth']);
        $response = new Response(401);

        $client = $this->getMockBuilder('\GuzzleHttp\Client')
            ->setMethods(['send'])
            ->getMock();

        $client->expects($this->once())
            ->method('send')
            ->will($this->returnValue($response));

        $transaction = new Transaction($client, $request);
        $except = new RequestException('foo', $request, $response);
        $event = new ErrorEvent($transaction, $except);

        // Force an onError event, which triggers the signer and grant data processor
        $sub->onError($event);
    }

}
