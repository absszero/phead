<?php
namespace Absszero\Head;

use Symfony\Component\Yaml\Yaml;

class Layout
{
    public array $data;
    public array $files;

    public static function parse(string $file)
    {
        $layout = new self;

        $layout->data = Yaml::parseFile($file);
        $layout->files = array_key_exists('files', $layout->data) ? $layout->data['files'] : [];

        return $layout;
    }
}
