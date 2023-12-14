<?php

namespace Tests;

use Absszero\Head\Layout;

class LayoutTest extends TestCase
{
    //test filter
    public function testFilter(): void
    {
        $data = [
            'files' => [
                'a' => [
                    'from' => 'xx',
                ],
                'b' => [
                    'from' => 'yy',
                    'to' => 'yy'
                ],
            ]
        ];

        $layout = new Layout;
        $data = $layout->filter($data);
        $this->assertArrayNotHasKey('a', $data['files']);
        $this->assertArrayHasKey('b', $data['files']);
        $this->assertTrue($data['files']['b']['is_file']);
    }

    public function testReplaceEnvs(): void
    {
        putenv('FOO=BAR');
        $vars = [
            'bar' => [
                'foo' => '{{ $env.FOO }}',
            ],
            'foo' => '{{ $env.FOO }}',
        ];

        $layout = new Layout;
        $vars = $layout->replaceEnvs($vars);

        $this->assertEquals('BAR', $vars['bar']['foo']);
        $this->assertEquals('BAR', $vars['foo']);
    }


    public function testReplaceVars(): void
    {
        $vars = [
            'bar' => [
                'foo' => '{{ vars.foo }}',
            ],
            'foo' => 'BAR',
        ];
        $data = [
            'vars' => $vars,
            'files' => [
                'a' => [
                    'from' => '{{ vars.foo }}',
                    'to' => '',
                    'is_file' => true,
                    'methods' => [
                        '{{ vars.foo }}'
                    ],
                ],
                'b' => [
                    'from' => '
                    class {
                        {{ vars.foo }}
                    }',
                    'to' => '',
                    'is_file' => true,
                ],
            ]
        ];

        $layout = new Layout;
        $data = $layout->replaceVars($data);

        $vars = $data['vars'];
        $this->assertEquals('BAR', $vars['bar']['foo']);
        $this->assertEquals('BAR', $vars['foo']);

        $files = $data['files'];
        $this->assertEquals('BAR', $files['a']['from']);
        $this->assertStringContainsString('{{ vars.foo }}', $files['a']['methods'][0]);
        $this->assertStringContainsString('{{ vars.foo }}', $files['b']['from']);
    }

    // test getFileVars
    public function testgetFileVars(): void
    {
        $files = [
            'a' => [
                'from' => 'xxx',
                'to' => 'app/Http/Requests/Hello/UpdateRequest.php',
            ],
        ];

        $layout = new Layout;
        $files = $layout->getFileVars($files);
        $this->assertEquals('UpdateRequest', $files['a']['vars']['class']);
        $this->assertEquals('App\Http\Requests\Hello', $files['a']['vars']['namespace']);
    }

    public function testGetMethodVars(): void
    {
        $files = [
            'dto' => [
                'vars' => [
                    'class' => 'UpdateDto',
                    'namespace' => 'App\Dtos',
                ]
            ],
            'a' => [
                'vars' => [],
                'methods' => [
                    'public toDto(): {{ files.dto }})',
                ],
            ],
        ];

        $layout = new Layout;
        $data = [
            'files' => $files,
        ];

        $files = $layout->getMethodVars($files, $data);
        $this->assertEquals('\App\Dtos\UpdateDto', $files['a']['vars']['{{ files.dto }}']);
    }

    public function testReplaceWithFileVars(): void
    {
        $layout = new Layout;
        $placeholders = [
            'class' => 'UpdateRequest',
            'namespace' => 'App\Http\Requests\Hello',
            '{{ files.dto }}' => 'UpdateDto',
        ];
        $context = $layout->replaceWithFileVars('{{ class }} {{ namespace }} {{ files.dto }}', $placeholders);
        $this->assertEquals('UpdateRequest App\Http\Requests\Hello UpdateDto', $context);
    }

    public function testAppendMethods(): void
    {
        $layout = new Layout;
        $file = [
            'from' => 'class Hello
            {

            }',
            'vars' => [
                '{{ files.dto }}' => '\App\UpdateDto',
            ],
            'methods' => ['public toDto(): {{ files.dto }}) {}',
            ]
        ];
        $context = $layout->appendMethods($file);
        $this->assertStringContainsString('public toDto(): \App\UpdateDto) {}', $context);
    }
}
