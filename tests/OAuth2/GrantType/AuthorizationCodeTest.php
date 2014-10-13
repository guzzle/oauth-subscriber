<?php

namespace GuzzleHttp\Subscriber\OAuth2\Tests\GrantType;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Post\PostBody;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\History;
use GuzzleHttp\Subscriber\Mock as MockResponder;
use GuzzleHttp\Subscriber\OAuth2\GrantType\AuthorizationCode;
use GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials\BasicAuth;
use PHPUnit_Framework_TestCase;

class AuthorizationCodeTest extends PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $grant = new AuthorizationCode(new Client(), [
            'client_id' => 'foo',
            'code' => 'bar',
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Config is missing the following keys
     */
    public function testConstructThrowsForMissing()
    {
        $grant = new AuthorizationCode(new Client(), []);
    }

    public function testGetRawData()
    {
        $response_data = [
            'foo' => 'bar',
            'key' => 'value',
        ];
        $response = new Response(200, [], Stream::factory(json_encode($response_data)));

        $responder = new MockResponder([$response]);
        $history = new History();

        $client = new Client();
        $client->getEmitter()->attach($responder);
        $client->getEmitter()->attach($history);

        $grant = new AuthorizationCode($client, [
            'client_id' => 'foo',
            'client_secret' => 'bar',
            'code' => 'mycode',
        ]);

        $signer = $this->getMockBuilder('\GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials\BasicAuth')
            ->setMethods(['sign'])
            ->getMock();

        $signer->expects($this->once())
            ->method('sign')
            ->with($this->anything(), 'foo', 'bar');

        $data = $grant->getRawData($signer);
        $request_body = $history->getLastRequest()->getBody();

        $this->assertEquals($response_data, $data);
        $this->assertEquals('mycode', $request_body->getField('code'));
        $this->assertEquals('authorization_code', $request_body->getField('grant_type'));
    }
}
