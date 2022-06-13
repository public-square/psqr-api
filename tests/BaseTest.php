<?php

namespace PublicSquare\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BaseTest extends WebTestCase
{
    protected function checkArrayKeys($array, $type = 'GET', $namedData = null)
    {
        $this->assertArrayHasKey('apiTarget', $array);
        $this->assertArrayHasKey('httpVerb', $array);
        $this->assertArrayHasKey('success', $array);

        if ($type == 'POST') {
            (!empty($namedData)) ? $this->assertArrayHasKey($namedData, $array) : $this->assertArrayHasKey('requestData', $array);
        }

        return;
    }

    protected function getDesiredUrl($endpoint)
    {
        return $_ENV['TEST_ENDPOINT'] . '/api/' . $endpoint;
    }
}
