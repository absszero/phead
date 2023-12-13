<?php
namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Command\Command;
use Closure;

class TestCase extends PHPUnitTestCase
{
    protected Application $application;

    protected function setUp(): void
    {
        $this->application = new Application();
    }

    /**
     * [addCommand description]
     *
     * @param   string|Command  $class  [$class description]
     *
     * @return  Command         [return description]
     */
    protected function addCommand($class): Command
    {
        if (is_string($class)) {
            $class = new $class;
        }

        $this->application->add($class);
        $commands = $this->application->all();
        return end($commands);
    }

    /**
     * [executeCommand description]
     *
     * @param   Command        $command  [$command description]
     * @param   array<string, mixed>          $input    [$input description]
     * @param   Closure        $clourse  [$clourse description]
     *
     * @return  CommandTester            [return description]
     */
    protected function executeCommand(Command $command, array $input = [], Closure $clourse = null): CommandTester
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
