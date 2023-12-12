<?php

namespace Tests;

use Absszero\Head\Layout;

class LayoutTest extends TestCase
{
    //test filter
    public function testFilter()
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
                'c' => [
                    'disabled' => true,
                    'from' => 'zz',
                    'to' => 'zz'
                ],

            ]
        ];

        $layout = new Layout;
        $data = $layout->filter($data);
        $this->assertArrayNotHasKey('a', $data['files']);
        $this->assertArrayHasKey('b', $data['files']);
        $this->assertArrayNotHasKey('c', $data['files']);
    }

    //test replaceAllPaths
    public function testReplaceAllPaths()
    {
        $layout = new Layout;
        $layout->data = [
            'dir' => 'Hello',
        ];

        $files = [
            'a' => [
                'from' => '{{  dir }}/Request.stub',
                'to' => '{{ dir  }}/Request.php',
            ],
            'b' => [
                'from' => '
                class Hello
                {
                    {{ dir }}
                }',
                'to' => '{{ dir  }}/Request.php',
            ],
        ];

        $files = $layout->replaceAllPaths($files);
        $this->assertEquals('Hello/Request.stub', $files['a']['from']);
        $this->assertEquals('Hello/Request.php', $files['a']['to']);
        $this->assertStringContainsString('{{ dir }}', $files['b']['from']);
        $this->assertEquals('Hello/Request.php', $files['b']['to']);
    }

    // test getWithPlaceholders
    public function testGetWithPlaceholders()
    {
        $files = [
            'a' => [
                'from' => 'xxx',
                'to' => 'app/Http/Requests/Hello/UpdateRequest.php',
            ],
        ];

        $layout = new Layout;
        $files = $layout->getWithPlaceholders($files);
        $this->assertEquals('UpdateRequest', $files['a']['placeholders']['class']);
        $this->assertEquals('App\Http\Requests\Hello', $files['a']['placeholders']['namespace']);
    }

    public function testGetMethodPlacehoders()
    {
        $files = [
            'dto' => [
                'placeholders' => [
                    'class' => 'UpdateDto',
                    'namespace' => 'App\Dtos',
                ]
            ],
            'a' => [
                'placeholders' => [],
                'methods' => [
                    'public toDto(): {{ files.dto }})',
                ],
            ],
        ];

        $layout = new Layout;
        $layout->data = [
            'files' => $files,
        ];

        $files = $layout->getMethodPlacehoders($files);
        $this->assertEquals('\App\Dtos\UpdateDto', $files['a']['placeholders']['{{ files.dto }}']);
    }

    public function testGet()
    {
        $layout = new Layout;
        $layout->data = [
            'files' => [
                'a' => [
                    'from' => 'xx',
                ]
            ]
        ];

        $this->assertEquals('hi', $layout->get('files.b', 'hi'));
        $this->assertArrayHasKey('a', $layout->get('files'));
        $this->assertEquals('xx', $layout->get('files.a.from'));
    }

    public function testSet()
    {
        $layout = new Layout;
        $layout->data = [
            'files' => [
                'a' => [
                    'from' => 'xx',
                ]
            ]
        ];
        $layout->set('files.b', 'hi');
        $this->assertEquals('hi', $layout->get('files.b'));

        $layout->set('files.a', 'hi');
        $this->assertEquals('hi', $layout->get('files.a'));

        $layout->set('files', 'hi');
        $this->assertEquals('hi', $layout->get('files'));
    }

    public function testReplacePlaceholders()
    {
        $layout = new Layout;
        $placeholders = [
            'class' => 'UpdateRequest',
            'namespace' => 'App\Http\Requests\Hello',
            '{{ files.dto }}' => 'UpdateDto',
        ];
        $context = $layout->replacePlaceholders('{{ class }} {{ namespace }} {{ files.dto }}', $placeholders);
        $this->assertEquals('UpdateRequest App\Http\Requests\Hello UpdateDto', $context);
    }

    public function testAppendMethods()
    {
        $layout = new Layout;
        $file = [
            'from' => 'class Hello
            {

            }',
            'placeholders' => [
                '{{ files.dto }}' => '\App\UpdateDto',
            ],
            'methods' => ['public toDto(): {{ files.dto }}) {}',
            ]
        ];
        $context = $layout->appendMethods($file);
        $this->assertStringContainsString('public toDto(): \App\UpdateDto) {}', $context);
    }
}
