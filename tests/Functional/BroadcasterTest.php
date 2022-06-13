<?php

namespace PublicSquare\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class BroadcasterTest extends WebTestCase
{
    public function testBroadcasterMethods(): void
    {
        // create sha from string, and get desired endpoint
        $testSha    = sha1('test string');
        $desiredUrl = '/api/broadcast/' . $testSha;

        // create client
        $client = static::createClient();

        // create insulated method clients
        $getMethod    = clone $client;
        $postMethod   = clone $client;
        $putMethod    = clone $client;
        $deleteMethod = clone $client;

        // head is technically a get method and should work
        $headMethod = clone $client;

        // get requested endpoints
        $getMethod->request('GET', $desiredUrl);
        $putMethod->request('PUT', $desiredUrl);
        $deleteMethod->request('DELETE', $desiredUrl);
        $headMethod->request('HEAD', $desiredUrl);

        // create post endpoint with json body data
        $postMethod->request(
            'POST',
            $desiredUrl,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'testData1' => 'someData1',
                'testData2' => 'someData2'
            ])
        );

        $this->assertEquals(200, $getMethod->getResponse()->getStatusCode());
        $this->assertEquals(200, $postMethod->getResponse()->getStatusCode());
        $this->assertEquals(200, $putMethod->getResponse()->getStatusCode());
        $this->assertEquals(200, $deleteMethod->getResponse()->getStatusCode());
        $this->assertEquals(200, $headMethod->getResponse()->getStatusCode());
    }

    public function testDisallowedBroadcasterMethods(): void
    {
        // create sha from string, and get desired endpoint
        $testSha    = sha1('test string');
        $desiredUrl = '/api/broadcast/' . $testSha;

        // create client
        $client = static::createClient();

        // disable catch exceptions
        $client->catchExceptions(false);

        // create insulated method clients
        $connectMethod = clone $client;
        $optionsMethod = clone $client;
        $traceMethod   = clone $client;
        $patchMethod   = clone $client;

        // try catch for MethodNotAllowedHttpException
        try {
            $connectMethod->request('CONNECT', $desiredUrl);
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertEquals(405, $e->getStatusCode());
        }

        try {
            $optionsMethod->request('OPTIONS', $desiredUrl);
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertEquals(405, $e->getStatusCode());
        }

        try {
            $traceMethod->request('TRACE', $desiredUrl);
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertEquals(405, $e->getStatusCode());
        }

        try {
            $patchMethod->request('PATCH', $desiredUrl);
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertEquals(405, $e->getStatusCode());
        }
    }

    public function testBroadcasterResponses(): void
    {
        // create sha from string, and get desired endpoint
        $testSha    = sha1('test string');
        $desiredUrl = '/api/broadcast/' . $testSha;

        // create client
        $client = static::createClient();

        // create insulated method clients
        $getMethod    = clone $client;
        $postMethod   = clone $client;
        $putMethod    = clone $client;
        $deleteMethod = clone $client;

        // get requested endpoints
        $getMethod->request('GET', $desiredUrl);
        $putMethod->request('PUT', $desiredUrl);
        $deleteMethod->request('DELETE', $desiredUrl);

        // create post endpoint with json body data
        $postMethod->request(
            'POST',
            $desiredUrl,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'testData1' => 'someData1',
                'testData2' => 'someData2'
            ])
        );

        // get responses in json form
        $getData    = json_decode($getMethod->getResponse()->getContent(), true);
        $postData   = json_decode($postMethod->getResponse()->getContent(), true);
        $putData    = json_decode($putMethod->getResponse()->getContent(), true);
        $deleteData = json_decode($deleteMethod->getResponse()->getContent(), true);

        // check json response structure for all methods
        $this->checkArrayKeys($getData);
        $this->checkArrayKeys($postData, 'POST');
        $this->checkArrayKeys($putData);
        $this->checkArrayKeys($deleteData);

        // verify if post data is not null
        $this->assertNotNull($postData['requestData']);
    }

    public function testInfoHash(): void
    {
        // create sha from string, and get desired endpoint
        $testWorkingSha = sha1('test string');
        $testNonSha     = 'test string';

        // get boolean validation values
        $testCaseWorking    = $this->validateInfoHash($testWorkingSha);
        $testCaseNotWorking = $this->validateInfoHash($testNonSha);

        // assertions about shas
        $this->assertFalse($testCaseNotWorking);
        $this->assertTrue($testCaseWorking);

        // assertions about working routes using get only
        $desiredUrlWorking    = '/api/broadcast/' . $testWorkingSha;
        $desiredUrlNotWorking = '/api/broadcast/' . $testNonSha;

        // create client
        $client = static::createClient();

        // create insulated method clients
        $workingGet    = clone $client;
        $nonWorkingGet = clone $client;

        // get requested endpoints
        $workingGet->request('GET', $desiredUrlWorking);
        $nonWorkingGet->request('GET', $desiredUrlNotWorking);

        // assert working and non working infohashes
        $this->assertEquals(200, $workingGet->getResponse()->getStatusCode());
        $this->assertEquals(400, $nonWorkingGet->getResponse()->getStatusCode());
    }

    private function checkArrayKeys($array, $type = 'GET')
    {
        $this->assertArrayHasKey('apiTarget', $array);
        $this->assertArrayHasKey('httpVerb', $array);
        $this->assertArrayHasKey('success', $array);

        if ($type == 'POST') {
            $this->assertArrayHasKey('requestData', $array);
        }

        return;
    }

    private function validateInfoHash($hash)
    {
        return (bool) preg_match('/^[0-9a-f]{40}$/i', $hash);
    }
}
