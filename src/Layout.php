<?php
namespace Absszero\Head;

use Symfony\Component\Yaml\Yaml;

class Layout
{
    const BRACKET_PATTERN = '/{{ *([^ ]+) *}}/';

    public int $indent = 4;
    public string $indentChar = ' ';
    public array $data = [];
    public static function parse(string $file)
    {
        $layout = new self;
        $data = Yaml::parseFile($file);
        $layout->data = $layout->filter($data);

        $files = $layout->replaceAllPaths($data['files']);
        $layout->set('files', $files);

        $files = $layout->getWithPlaceholders($layout->get('files'));
        $layout->set('files', $files);

        $files = $layout->getMethodPlacehoders($files);
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

    /**
     * set data by dot notation
     *
     * @param   string  $key    [$key description]
     * @param   mixed  $value  [$value description]
     *
     * @return  void            [return description]
     */
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
     * filter files
     *
     * @param array $data
     * @return array
     */
    public function filter(array $data): array
    {
        $files = [];
        if (!array_key_exists('files', $data) || !is_array($data['files'])) {
            $data['files'] = [];
            return $data;
        }

        foreach ($data['files'] as $fileKey => $file) {
            if (!array_key_exists('from', $file)) {
                continue;
            }
            if (!array_key_exists('to', $file)) {
                continue;
            }
            $files[$fileKey] = $file;
        }
        $data['files'] = $files;

        return $data;
    }

    /**
     * get files with placeholders
     *
     * @param   array  $files  [$files description]
     *
     * @return  array
     */
    public function getWithPlaceholders(array $files): array
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

    /**
     * Get method placehoders to file['placeholders']
     *
     * @param   array  $files  [$files description]
     *
     * @return  array          [return description]
     */
    public function getMethodPlacehoders(array $files): array
    {
        // fetch all brackets vars and replace with real classes
        foreach ($files as $key => $file) {
            if (!array_key_exists('methods', $file)) {
                continue;
            }
            foreach ($file['methods'] as $method) {
                $matches = [ 0 => [] ];
                preg_match_all(self::BRACKET_PATTERN, $method, $matches, PREG_PATTERN_ORDER, 0);
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

    /**
     * replace placeholders
     *
     * @param   string  $source  [$source description]
     * @param   array   $file    [$file description]
     *
     * @return  string           [return description]
     */
    public function replacePlaceholders(string $source, array $placeholders):  string
    {
        $search = array_keys($placeholders);
        $search = array_map(function ($placeholder) {
            if ($placeholder[0] === '{') {
                return $placeholder;
            }

            return '{{ ' . $placeholder . ' }}';
        }, $search);
        $replace = array_values($placeholders);

        return str_replace($search, $replace, $source);
    }

    /**
     * append methods to file
     *
     * @param   array  $file  [$file description]
     *
     * @return  string         [return description]
     */
    public function appendMethods(array $file): string
    {
        if (!array_key_exists('methods', $file) || !is_array($file['methods'])) {
            return $file['from'];
        }

        foreach ($file['methods'] as $index => $method) {
            $methods[$index] = $this->replacePlaceholders($method, $file['placeholders']);
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

    /**
     * replace all paths placeholders
     *
     * @param   array  $files  [$files description]
     *
     * @return  array          [return description]
     */
    public function replaceAllPaths(array $files): array
    {
        foreach ($files as $index => $file) {
            $file['to'] = $this->replacePathPlaceholders($file['to']);

            // no new line character, it could b a path
            if (strpos($file['from'], PHP_EOL) === false) {
                $file['from'] = $this->replacePathPlaceholders($file['from']);
            }
            $files[$index] = $file;
        }

        return $files;
    }

    /**
     * replace a path's placeholders
     *
     * @param   string  $fullPath  [$fullPath description]
     *
     * @return  string             [return description]
     */
    protected function replacePathPlaceholders(string $fullPath): string
    {
        $matches = [ 0 => [] ];
        preg_match_all(self::BRACKET_PATTERN, $fullPath, $matches, PREG_PATTERN_ORDER, 0);

        if (!$matches[0]) {
            return $fullPath;
        }

        foreach ($matches[1] as $index => $key) {
            $path = $this->get($key);
            if (!$path) {
                continue;
            }

            $matches[1][$index] = $path;
        }

        $fullPath = str_replace($matches[0], $matches[1], $fullPath);
        return $fullPath;
    }
}
