<?php
namespace GuzzleHttp\Subscriber\OAuth2\Tests\GrantType;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock as MockResponder;
use GuzzleHttp\Subscriber\History;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Post\PostBody;
use GuzzleHttp\Subscriber\OAuth2\GrantType\RefreshToken;
use GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials\BasicAuth;

class RefreshTokenTest extends PHPUnit_Framework_TestCase
{

    public function testConstruct()
    {
        $grant = new RefreshToken(new Client(), [
            'client_id' => 'foo',
            'username' => 'bilbo',
            'password' => 'baggins',
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Config is missing the following keys
     */
    public function testConstructThrowsForMissing()
    {
        $grant = new RefreshToken(new Client(), []);
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


        $grant = new RefreshToken($client, [
            'client_id' => 'foo',
            'client_secret' => 'bar',
            'scope' => 'bilbo',
            'refresh_token' => 'baggins',
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
        $this->assertEquals('bilbo', $request_body->getField('scope'));
        $this->assertEquals('baggins', $request_body->getField('refresh_token'));
        $this->assertEquals('refresh_token', $request_body->getField('grant_type'));
    }
}
