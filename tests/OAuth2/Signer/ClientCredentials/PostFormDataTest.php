<?php
namespace GuzzleHttp\Subscriber\OAuth2\Tests\Signer\ClientCredentials;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Post\PostBody;
use GuzzleHttp\Subscriber\OAuth2\Signer\ClientCredentials\PostFormData;

class PostFormDataTest extends PHPUnit_Framework_TestCase
{
    public function testSign()
    {
        $clientId = 'foo';
        $clientSecret = 'bar';

        $clientIdFieldName = 'client_id';
        $clientSecretFieldName = 'client_secret';

        $request = new Request('GET', '/');
        $request->setBody(new PostBody());

        $signer = new PostFormData();
        $signer->sign($request, $clientId, $clientSecret);

        $this->assertEquals($clientId, $request->getBody()->getField($clientIdFieldName));
        $this->assertEquals($clientSecret, $request->getBody()->getField($clientSecretFieldName));
    }

    public function testSignCustomFields()
    {
        $clientId = 'foo';
        $clientSecret = 'bar';

        $clientIdFieldName = 'foo_id';
        $clientSecretFieldName = 'foo_secret';

        $request = new Request('GET', '/');
        $request->setBody(new PostBody());

        $signer = new PostFormData($clientIdFieldName, $clientSecretFieldName);
        $signer->sign($request, $clientId, $clientSecret);

        $this->assertEquals($clientId, $request->getBody()->getField($clientIdFieldName));
        $this->assertEquals($clientSecret, $request->getBody()->getField($clientSecretFieldName));
    }
}
