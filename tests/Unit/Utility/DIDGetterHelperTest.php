<?php

namespace PublicSquare\Tests\Unit\Utility;

use PublicSquare\Utility\DIDGetterHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use PublicSquare\Service\DIDGetter;

class DIDGetterHelperTest extends KernelTestCase
{
    public function testInvalidDistributedIdentity(): void
    {
        self::bootKernel();

        $didGetterHelper = static::$container->get(DIDGetterHelper::class);

        $request = new Request();

        $this->expectExceptionMessage('{"apiTarget":"\/","httpVerb":"GET","success":false,"error":"DID cannot contain the characters ! or ^"}');

        $brokenDid = bin2hex(random_bytes(10)) . '!^';

        $didGetterHelper->validateDistributedIdentity($request, $brokenDid);
    }

    public function testDIDStrictTypes(): void
    {
        self::bootKernel();

        $didGetterHelper = static::$container->get(DIDGetterHelper::class);

        $request = new Request();

        $this->expectException(\TypeError::class);

        $invalidString = [];

        $didGetterHelper->validateDistributedIdentity($request, $invalidString);
    }

    public function testConvertDid(): void
    {
        self::bootKernel();
        $didGetter = static::$container->get(DIDGetter::class);

        $convertDID = $didGetter->convertDid($_ENV['TEST_DID']);

        $this->assertIsString($convertDID);
        $this->assertStringContainsString('!', $convertDID);
    }

    public function testUnconvertedDIDOnParseURL(): void
    {
        self::bootKernel();
        $didGetter = static::$container->get(DIDGetter::class);

        $this->expectExceptionMessage('Use convertDid to convert a standard did into a redis compatible did');

        $didGetter->parseDidUrl($_ENV['TEST_DID']);
    }

    public function testBadDIDOnParseDidUrl(): void
    {
        self::bootKernel();
        $didGetter = static::$container->get(DIDGetter::class);

        $this->expectExceptionMessage('Unable to parse DID. Expected format: did:psqr:{hostname}/{path}');

        $badDID = bin2hex(random_bytes(10)) . '^' . bin2hex(random_bytes(10));

        $didGetter->parseDidUrl($badDID);
    }
}
