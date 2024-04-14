<?php

namespace Tests;

use Absszero\Phead\Layout;

class LayoutTest extends TestCase
{
    public function testParse(): void
    {
        $file = __DIR__ . '/../config/layout.yaml';
        $layout = Layout::parse($file);
        $this->assertInstanceOf(Layout::class, $layout);
    }

    //test filter
    public function testFilter(): void
    {
        $data = [
            '$files' => [
                'a' => [
                    'from' => 'xx',
                ],
                'b' => [
                    'from' => 'yy',
                    'to' => 'yy'
                ],
                'c' => [
                    'from' => __DIR__ . '/../config/example.stub',
                    'to' => 'yy'
                ],
            ]
        ];

        $layout = new Layout();
        $data = $layout->filter($data);
        $this->assertArrayNotHasKey('a', $data['$files']);
        $this->assertArrayHasKey('b', $data['$files']);
        $this->assertNotEquals(__DIR__ . '/../config/example.stub', $data['$files']['c']['from']);
        $this->assertArrayHasKey('$globals', $data);
    }

    // test replace environment vars
    public function testReplaceEnvs(): void
    {
        putenv('FOO=BAR');
        $vars = [
            'bar' => [
                'foo' => '{{ $env.FOO }}',
                'bar' => '{{ $env.BAR }}',
            ],
            'foo' => '{{ $env.FOO }}',
            'foo2' => '{{ $globals.FOO }}',
        ];

        $layout = new Layout();
        $vars = $layout->replaceEnvs($vars);

        $this->assertEquals('BAR', $vars['bar']['foo']);
        $this->assertEquals('{{ $env.BAR }}', $vars['bar']['bar']);
        $this->assertEquals('BAR', $vars['foo']);
        $this->assertEquals('{{ $globals.FOO }}', $vars['foo2']);
    }

    // test replace with $globals vars
    public function testReplaceGlobalVars(): void
    {
        $data = [
            '$globals' => [
                'foo' => 'FOO'
            ],
            '$files' => [
                'a' => [
                    'vars' => [
                        'bar'  => 'BAR',
                    ],
                    'methods' => [
                        '{{ bar }}',
                        '{{ $globals.foo }}',
                        '{{ $files.a }}'
                    ],
                ],
            ]
        ];

        $layout = new Layout();
        $methods = $layout->replaceGlobalVars($data)['$files']['a']['methods'];

        $this->assertEquals('{{ bar }}', $methods[0]);
        $this->assertEquals('FOO', $methods[1]);
        $this->assertEquals('{{ $files.a }}', $methods[2]);
    }

    // test replace with files' vars
    public function testReplaceLocalVars(): void
    {
        $files = [
            'a' => [
                'vars' => [
                    'bar'  => 'BAR',
                ],
                'from' => '{{ bar }}',
                'methods' => [
                    '{{ bar }}',
                    '{{ $globals.foo }}',
                ],
            ],
        ];

        $layout = new Layout();
        $file = $layout->replaceLocalVars($files)['a'];

        $this->assertEquals('BAR', $file['from']);
        $this->assertEquals('BAR', $file['methods'][0]);
        $this->assertEquals('{{ $globals.foo }}', $file['methods'][1]);
    }

    // test collect files' vars
    public function testCollectFilesVars(): void
    {
        $files = [
            'a' => [
                'from' => 'xxx',
                'to' => 'Foo/app/Http/Requests/Hello/UpdateRequest.php',
            ],
        ];
        $data = [
            '$files' => $files,
        ];

        $layout = new Layout();
        $data = $layout->collectFilesVars($data);
        $files = $data['$files'];
        $this->assertEquals('UpdateRequest', $files['a']['vars']['class']);
        $this->assertEquals('Foo\App\Http\Requests\Hello', $files['a']['vars']['namespace']);
    }

    // test append methods to class
    public function testAppendMethods(): void
    {
        $layout = new Layout();
        $files = [
            'a' =>  [
                'from' => 'class Hello
                    {

                    }',
                    'methods' => 'public toDto(): \App\UpdateDto) {}',
            ]
        ];
        $files = $layout->appendMethods($files);
        $this->assertStringContainsString('public toDto(): \App\UpdateDto) {}', $files['a']['from']);
    }
}
