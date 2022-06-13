<?php

namespace PublicSquare\Tests\Functional;

use PublicSquare\Command\TemplateCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends KernelTestCase
{
    public function testExecute(): void
    {
        $kernel = static::createKernel();

        $application = new Application($kernel);

        $command = $application->find('app:template');

        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'arg1' => 'testValue1'
        ]);

        $output = $commandTester->getDisplay();

        // test passed argument
        $this->assertStringContainsString('testValue1', $output);

        // test okay message
        $this->assertStringContainsString('[OK] You have a new command!', $output);

        // test exit status
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
