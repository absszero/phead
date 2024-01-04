<?php

namespace Tests;

use Absszero\Phead\ArrAccess;

class ArrAccessTest extends TestCase
{
    public function testGet(): void
    {
        $data = [
            'files' => [
                'a' => [
                    'from' => 'xx',
                ]
            ]
        ];

        $this->assertEquals('hi', ArrAccess::get($data, 'files.b', 'hi'));
        $this->assertArrayHasKey('a', ArrAccess::get($data, 'files'));
        $this->assertEquals('xx', ArrAccess::get($data, 'files.a.from'));
    }

    public function testSet(): void
    {
        $data = [
            'files' => [
                'a' => [
                    'from' => 'xx',
                ]
            ]
        ];
        ArrAccess::set($data, 'files.b', 'hi');
        $this->assertEquals('hi', $data['files']['b']);

        ArrAccess::set($data, 'files.a', 'hi');
        $this->assertEquals('hi', $data['files']['a']);

        ArrAccess::set($data, 'files', 'hi');
        $this->assertEquals('hi', $data['files']);
    }
}
