<?php

namespace PublicSquare\Tests\Unit\Utility;

use PublicSquare\Utility\BCFHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BCFHelperTest extends KernelTestCase
{
    public function testBCFStrictTypes(): void
    {
        self::bootKernel();
        $request = new Request();
        $bcfHelper = new BCFHelper();

        $this->expectException(\TypeError::class);

        $invalidArray = rand();

        $bcfHelper->BCFQueryResults($request, $invalidArray);
    }
}
