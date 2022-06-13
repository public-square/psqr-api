<?php

declare(strict_types=1);

namespace PublicSquare\Tests\Unit\Utility;

use PublicSquare\Utility\ValidationHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ValidationHelperTest extends KernelTestCase
{
    public function testInfoHash(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $sha = sha1(bin2hex(random_bytes(10)));

        $this->assertTrue($validationHelper->validateInfoHash($request, $sha));
    }

    public function testInvalidInfoHash(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $this->expectExceptionMessage('{"apiTarget":"\/","httpVerb":"GET","success":false,"error":"Infohash provided is not a valid SHA-1."}');

        $invalidSha = bin2hex(random_bytes(10));

        $validationHelper->validateInfoHash($request, $invalidSha);
    }

    public function testInfoHashFunctionStrictTypes(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $this->expectException(\TypeError::class);

        $invalidInfoHashType = rand();

        $validationHelper->validateInfoHash($request, $invalidInfoHashType);
    }

    public function testValidDIDSubdomain(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $did = $_ENV['TEST_DID'];

        $this->assertFileExists($validationHelper->validateAcceptableDIDSubdomain($request, $did));
    }

    public function testInvalidDIDSubdomain(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $this->expectExceptionMessage('{"apiTarget":"\/","httpVerb":"GET","success":false,"error":"This is not an acceptable DID subdomain."}');

        $invalidDID = bin2hex(random_bytes(10));

        $validationHelper->validateAcceptableDIDSubdomain($request, $invalidDID);
    }

    public function testDIDSubdomainFunctionStrictTypes(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $this->expectException(\TypeError::class);

        $invalidDID = rand();

        $validationHelper->validateAcceptableDIDSubdomain($request, $invalidDID);
    }

    public function testVerifyDIDExists(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $didFile = $validationHelper->validateAcceptableDIDSubdomain($request, $_ENV['TEST_DID']);

        $this->assertIsArray($validationHelper->verifyDIDExists($request, $didFile));
    }

    public function testVerifyDIDDoesNotExist(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $this->expectExceptionMessage('{"apiTarget":"\/","httpVerb":"GET","success":false,"error":"DID File Does Not Exist."}');

        $badFileLocation = bin2hex(random_bytes(10));

        $validationHelper->verifyDIDExists($request, $badFileLocation);
    }

    public function testVerifyDIDThrowExceptionFlag(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $badFileLocation = bin2hex(random_bytes(10));

        $newDIDFlagSet = $validationHelper->verifyDIDExists($request, $badFileLocation, false);

        $this->assertFalse($newDIDFlagSet);
    }

    public function testDIDExistsFunctionStrictTypes(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $this->expectException(\TypeError::class);

        $invalidFilename = rand();
        $invalidBool = bin2hex(random_bytes(10));

        $validationHelper->verifyDIDExists($request, $invalidFilename, $invalidBool);
    }

    public function testValidKIDPermissions(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $didFile = $validationHelper->validateAcceptableDIDSubdomain($request, $_ENV['TEST_DID']);
        $kid     = $_ENV['TEST_DID'] . '#admin';

        $this->assertTrue($validationHelper->validateKIDPermissions($request, 'admin', $kid, json_decode(file_get_contents($didFile), true)));
    }

    public function testInvalidKIDPermissions(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $didFile = $validationHelper->validateAcceptableDIDSubdomain($request, $_ENV['TEST_DID']);
        $invalidKID     = $_ENV['TEST_DID'] . '#' . bin2hex(random_bytes(10));

        $this->assertFalse($validationHelper->validateKIDPermissions($request, bin2hex(random_bytes(10)), $invalidKID, json_decode(file_get_contents($didFile), true)));
    }

    public function testKIDPermissionsFunctionStrictTypes(): void
    {
        self::bootKernel();
        $request = new Request();
        $validationHelper = new ValidationHelper();

        $this->expectException(\TypeError::class);

        $invalidString = rand();
        $invalidArray = bin2hex(random_bytes(10));

        $validationHelper->validateKIDPermissions($request, $invalidString, $invalidString, $invalidArray);
    }
}
