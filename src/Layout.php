<?php
namespace Absszero\Head;

use Symfony\Component\Yaml\Yaml;

class Layout
{
    public int $indent = 4;
    public string $indentChar = ' ';
    public array $data = [];
    public static function parse(string $file)
    {
        $layout = new self;
        $data = Yaml::parseFile($file);
        $layout->data = $layout->filter($data);

        $files = $layout->getWithPlaceholders($layout->get('files'));
        $layout->set('files', $files);

        $files = $layout->matchMethodPlacehoders($files);
        $layout->set('files', $files);

        return $layout;
    }

    /**
     * Get data by dot notation.
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $nodes = explode('.', $key);
        $data = $this->data;
        foreach ($nodes as $node) {
            if (!array_key_exists($node, $data)) {
                return $default;
            }
            $data = $data[$node];
        }

        return $data;
    }

    public function set(string $key, $value): void
    {
        $nodes = explode('.', $key);
        $data = & $this->data;
        foreach ($nodes as $node) {
            if (!array_key_exists($node, $data) || !is_array($data[$node])) {
                $data[$node] = [];
            }

            $data = & $data[$node];
        }

        $data = $value;
    }

    /**
     *
     * @param array $data
     * @return array
     */
    public function filter(array $data): array
    {
        $files = [];
        if (!array_key_exists('files', $data) || !is_array($data['files'])) {
            return $data;
        }

        foreach ($data['files'] as $index => $file) {
            if (!array_key_exists('from', $file)) {
                continue;
            }
            if (!array_key_exists('to', $file)) {
                continue;
            }
            $files[$index] = $file;
        }
        $data['files'] = $files;

        return $data;
    }

    public function getWithPlaceholders(array $files)
    {
        foreach ($files as $key => $file) {
            $placeholders = [];
            $classFile = basename($file['to']);
            $placeholders['class'] = ucfirst(strstr($classFile, '.', true));

            // make a PSR-4 namespace
            $namespace = strstr($file['to'], $classFile, true);
            $namespace = str_replace('/', '\\', $namespace);
            $namespace = trim($namespace, '\\');
            $namespace = ucfirst($namespace);
            $length = strlen($namespace);
            $i = 0;
            do {
                if ($i && $namespace[$i - 1] === '\\' && isset($namespace[$i])) {
                    $namespace[$i] = strtoupper($namespace[$i]);
                }
                $i++;
            } while ($length >= $i);
            $placeholders['namespace'] = $namespace;

            if (array_key_exists('placeholders', $file)) {
                $placeholders = array_merge($file['placeholders'], $placeholders);
            };
            $files[$key]['placeholders'] = $placeholders;
        }

        return $files;
    }

    public function matchMethodPlacehoders(array $files): array
    {
        $pattern = '/{{ ([^ ]+) }}/';

        // fetch all braces vars and replace with real classes
        foreach ($files as $key => $file) {
            if (!array_key_exists('methods', $file)) {
                continue;
            }
            foreach ($file['methods'] as $method) {
                $matches = [ 0 => [] ];
                preg_match_all($pattern, $method, $matches, PREG_PATTERN_ORDER, 0);
                if (!$matches[0]) {
                    continue;
                }

                foreach ($matches[1] as $index => $fileKey) {
                    $refFile = $this->get($fileKey);
                    if (!$refFile) {
                        continue;
                    }

                    $matches[1][$index] = '\\' . $refFile['placeholders']['namespace'] . '\\' . $refFile['placeholders']['class'];
                }

                $matches = array_combine($matches[0], $matches[1]);

                $placeholders = array_merge($matches, $file['placeholders']);
                $files[$key]['placeholders'] = $placeholders;
            }
        }

        return $files;
    }

    public function replacePlaceholders(string $source, array $file):  string
    {
        $search = array_keys($file['placeholders']);
        $search = array_map(function ($placeholder) {
            if ($placeholder[0] === '{') {
                return $placeholder;
            }

            return '{{ ' . $placeholder . ' }}';
        }, $search);
        $replace = array_values($file['placeholders']);

        return str_replace($search, $replace, $source);
    }

    // appendMethods
    public function appendMethods(array $file)
    {
        if (!array_key_exists('methods', $file) || !is_array($file['methods'])) {
            return $file['from'];
        }

        foreach ($file['methods'] as $index => $method) {
            $methods[$index] = $this->replacePlaceholders($method, $file);
        }
        foreach ($methods as $index => $method) {
            $lines = array_filter(explode(PHP_EOL, $method));
            $lines = array_map(fn($line) => str_pad('', $this->indent, $this->indentChar) . $line, $lines);
            $methods[$index] = PHP_EOL . implode(PHP_EOL, $lines);
        }
        $methods = implode(PHP_EOL, $methods);

        $length = strlen($file['from']) - 1;
        while ($length > 0) {
            if ($file['from'][$length] === '}') {
                $file['from'] = substr_replace($file['from'], $methods, $length, 1);
                $file['from'] = rtrim($file['from']) . PHP_EOL . '}' . PHP_EOL;
                break;
            }
            $length--;
        }

        return $file['from'];
    }
}
