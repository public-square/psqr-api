<?php

namespace PublicSquare\Tests\Unit\Utility;

use PublicSquare\Utility\JWSHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JWSHelperTest extends KernelTestCase
{
    public function testBadContentForUnpackAndValidateJWS(): void
    {
        self::bootKernel();
        $jwsHelper = static::$container->get(JWSHelper::class);
        $request = new Request();

        $this->expectExceptionMessage('{"apiTarget":"\/","httpVerb":"GET","success":false,"error":"JSON does not contain token property."}');

        $brokenContent = '{"notToken": "' . bin2hex(random_bytes(10)) . '"}';

        $jwsHelper->unpackAndValidateJWS($request, json_decode($brokenContent, true));
    }

    public function testValidateJWSStrictTypes(): void
    {
        self::bootKernel();
        $jwsHelper = static::$container->get(JWSHelper::class);
        $request = new Request();

        $this->expectException(\TypeError::class);

        $invalidString = bin2hex(random_bytes(10));

        $jwsHelper->unpackAndValidateJWS($request, $invalidString);
    }

    public function testBadContentForUnpackAndSerializeJWS(): void
    {
        self::bootKernel();
        $jwsHelper = static::$container->get(JWSHelper::class);
        $request = new Request();

        $this->expectExceptionMessage('{"apiTarget":"\/","httpVerb":"GET","success":false,"error":"JSON does not contain token property."}');

        $brokenContent = '{"notToken": "' . bin2hex(random_bytes(10)) . '"}';

        $jwsHelper->unpackAndSerializeJWS($request, json_decode($brokenContent, true));
    }

    public function testBadJWSForUnpackAndSerializeJWS(): void
    {
        self::bootKernel();
        $jwsHelper = static::$container->get(JWSHelper::class);
        $request = new Request();

        $this->expectExceptionMessage('{"apiTarget":"\/","httpVerb":"GET","success":false,"error":"Unsupported input."}');

        $badJWS = '{"token": "' . bin2hex(random_bytes(10)) . '"}';

        $jwsHelper->unpackAndSerializeJWS($request, json_decode($badJWS, true));
    }

    public function testValidJWSForUnpackAndSerializeJWS(): void
    {
        self::bootKernel();
        $jwsHelper = static::$container->get(JWSHelper::class);
        $request = new Request();

        $jws = '{"token": "' . $_ENV['TEST_JWS'] . '"}';

        $unpackedJws = $jwsHelper->unpackAndSerializeJWS($request, json_decode($jws, true));

        $this->assertIsObject($unpackedJws);
        $this->assertObjectHasAttribute('payload', $unpackedJws);
        $this->assertObjectHasAttribute('signatures', $unpackedJws);
    }

    public function testSerializeJWSStrictTypes(): void
    {
        self::bootKernel();
        $jwsHelper = static::$container->get(JWSHelper::class);
        $request = new Request();

        $this->expectException(\TypeError::class);

        $invalidString = bin2hex(random_bytes(10));

        $jwsHelper->unpackAndSerializeJWS($request, $invalidString);
    }
}
