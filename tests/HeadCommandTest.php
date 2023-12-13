<?php

namespace Tests;

use Absszero\Head\HeadCommand;

class HeadCommandTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../src/BuildInFunction.php';
    }

    // test HeadCommand
    public function testLayout(): void
    {
        // file not found
        $command = $this->addCommand(HeadCommand::class);
        $input = ['layout' => '/abc.yaml'];
        $tester = $this->executeCommand($command, $input);
        $this->assertEquals(1, $tester->getStatusCode());

        $input = ['layout' => __DIR__  . '/../config/layout.yaml'];
        $tester = $this->executeCommand($command, $input);
        $this->assertEquals(0, $tester->getStatusCode());
    }

    // test only option
    public function testOnly(): void
    {
        $input = [
            'layout' => __DIR__  . '/../config/layout.yaml',
            '--only' => 'dto_test, ',
        ];

        $command = $this->addCommand(HeadCommand::class);
        $tester = $this->executeCommand($command, $input);

        $this->assertStringContainsString('UpdateDtoTest.php', $tester->getDisplay());
    }

    // test dry run option
    public function testDry(): void
    {
        $input = [
            'layout' => __DIR__  . '/../config/layout.yaml',
            '--dry' => true,
        ];

        $command = $this->addCommand(HeadCommand::class);
        $tester = $this->executeCommand($command, $input);
        $this->assertStringContainsString('(dry run)', $tester->getDisplay());
    }

    // test force option
    public function testForce(): void
    {
        $input = [
            'layout' => __DIR__  . '/../config/layout.yaml',
            '--force' => true,
        ];

        $command = $this->addCommand(HeadCommand::class);
        $tester = $this->executeCommand($command, $input);
        $this->assertStringContainsString('(force)', $tester->getDisplay());
    }
}
