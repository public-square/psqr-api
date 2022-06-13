<?php

namespace PublicSquare\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class FeedTest extends WebTestCase
{
    public function testFeedMethods(): void
    {
        $desiredUrl = '/api/feed';

        // create client
        $postMethod = static::createClient();

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

        $this->assertEquals(200, $postMethod->getResponse()->getStatusCode());
    }

    public function testDisallowedFeedMethods(): void
    {
        $desiredUrl = '/api/feed';

        // create client
        $client = static::createClient();

        $client->catchExceptions(false);

        // create insulated method clients
        $getMethod     = clone $client;
        $headMethod    = clone $client;
        $putMethod     = clone $client;
        $deleteMethod  = clone $client;
        $connectMethod = clone $client;
        $optionsMethod = clone $client;
        $traceMethod   = clone $client;
        $patchMethod   = clone $client;

        // try catch for MethodNotAllowedHttpException
        try {
            $getMethod->request('GET', $desiredUrl);
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertEquals(405, $e->getStatusCode());
        }

        try {
            $headMethod->request('HEAD', $desiredUrl);
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertEquals(405, $e->getStatusCode());
        }

        try {
            $putMethod->request('PUT', $desiredUrl);
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertEquals(405, $e->getStatusCode());
        }

        try {
            $deleteMethod->request('DELETE', $desiredUrl);
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertEquals(405, $e->getStatusCode());
        }

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

    public function testFeedResponses(): void
    {
        $desiredUrl = '/api/feed';

        // create client
        $postMethod = static::createClient();

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
        $postData = json_decode($postMethod->getResponse()->getContent(), true);

        // check json response structure for all methods
        $this->checkArrayKeys($postData, 'POST');

        // verify if post data is not null
        $this->assertNotNull($postData['requestData']);
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
}
