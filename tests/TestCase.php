<?php
namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class TestCase extends PHPUnitTestCase
{
    protected $application;

    protected function setUp(): void
    {
        $this->application = new Application();
    }

    protected function addCommand($class)
    {
        if (is_string($class)) {
            $class = new $class;
        }

        $this->application->add($class);
        $commands = $this->application->all();
        return end($commands);
    }

    protected function executeCommand($command, $input = [], $clourse = null)
    {
        $input['command'] = $command->getName();
        $commandTester = new CommandTester($command);

        if ($clourse) {
            $clourse($commandTester);
        }

        $commandTester->execute($input);
        return $commandTester;
    }
}
