<?php

namespace PublicSquare\Tests\Functional;

use PublicSquare\Tests\BaseTest;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class SearchTest extends BaseTest
{
    public function testSearchMethods(): void
    {
        // create client
        $postMethod = static::createClient();

        // create post endpoint with json body data
        $postMethod->request(
            'POST',
            $this->getDesiredUrl('search'),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'search' => 'someData1'
            ])
        );

        $this->assertEquals(200, $postMethod->getResponse()->getStatusCode());
    }

    public function testDisallowedSearchMethods(): void
    {
        $desiredUrl = $this->getDesiredUrl('search');

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

    public function testSearchResponses(): void
    {
        // create client
        $postMethod = static::createClient();

        // create post endpoint with json body data
        $postMethod->request(
            'POST',
            $this->getDesiredUrl('search'),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'search' => 'someData1'
            ])
        );

        // get responses in json form
        $postData = json_decode($postMethod->getResponse()->getContent(), true);

        // check json response structure for all methods
        $this->checkArrayKeys($postData, 'POST', 'searchResults');

        // verify if post data is not null
        $this->assertNotNull($postData['searchResults']);
    }
}
