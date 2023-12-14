<?php
namespace Absszero\Head;

use Symfony\Component\Yaml\Yaml;

class Layout
{
    const BRACKET_PATTERN = '/{{ *([^ ]+) *}}/';

    public int $indent = 4;
    public string $indentChar = ' ';
    /**
     * @var array<string, mixed>
     */
    public array $data = ['files' => []];
    public static function parse(string $file): self
    {
        $layout = new self;
        $data = (array)Yaml::parseFile($file);
        $data = $layout->filter($data);

        $data['vars'] = $layout->replaceEnvs($data['vars'] ?? []);
        $data = $layout->replaceVars($data);

        $data['files'] = $layout->getFileVars($data['files']);
        $data['files'] = $layout->getMethodVars($data['files']);

        $layout->data = $data;

        return $layout;
    }

    /**
     * filter files
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
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

            $file['is_file'] = true;
            $files[$fileKey] = $file;
        }
        $data['files'] = $files;

        return $data;
    }

    /**
     * get files' vars
     *
     * @param   array<string, mixed>  $files  [$files description]
     *
     * @return  array<string, mixed>
     */
    public function getFileVars(array $files): array
    {
        foreach ($files as $key => $file) {
            $vars = [];
            $classFile = basename($file['to']);
            $vars['class'] = ucfirst(strstr($classFile, '.', true));

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
            $vars['namespace'] = $namespace;

            if (array_key_exists('vars', $file)) {
                $vars = array_merge($file['vars'], $vars);
            };
            $files[$key]['vars'] = $vars;
        }

        return $files;
    }

    /**
     * Get methods' vars to file['vars']
     *
     * @param   array<string, mixed>  $files  [$files description]
     * @param   array<string, mixed>  $refs
     *
     * @return  array<string, mixed>          [return description]
     */
    public function getMethodVars(array $files, array $refs = []): array
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
                    $refFile = ArrAccess::get($refs, $fileKey);
                    if (!$refFile) {
                        continue;
                    }

                    $matches[1][$index] = '\\' . $refFile['vars']['namespace'] . '\\' . $refFile['vars']['class'];
                }

                $matches = array_combine($matches[0], $matches[1]);

                $vars = array_merge($matches, $file['vars']);
                $files[$key]['vars'] = $vars;
            }
        }

        return $files;
    }

    /**
     * replace with file's vars
     *
     * @param   string  $source  [$source description]
     * @param   array<string, string>   $vars
     *
     * @return  string           [return description]
     */
    public function replaceWithFileVars(string $source, array $vars):  string
    {
        $search = array_keys($vars);
        $search = array_map(function ($placeholder) {
            if ($placeholder[0] === '{') {
                return $placeholder;
            }

            return '{{ ' . $placeholder . ' }}';
        }, $search);
        $replace = array_values($vars);

        return str_replace($search, $replace, $source);
    }

    /**
     * append methods to file
     *
     * @param   array<string, string|mixed>  $file  [$file description]
     *
     * @return  string         [return description]
     */
    public function appendMethods(array $file): string
    {
        if (!array_key_exists('methods', $file) || !is_array($file['methods'])) {
            return $file['from'];
        }

        $methods = [];
        foreach ($file['methods'] as $index => $method) {
            $methods[$index] = $this->replaceWithFileVars($method, (array)$file['vars']);
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
     * replace with environment variables
     *
     * @param   array<string, mixed>  $vars
     *
     * @return  array<string, mixed>          [return description]
     */
    public function replaceEnvs(array $vars): array
    {
        foreach ($vars as $key => $value) {
            if (is_array($value)) {
                $vars[$key] = $this->replaceEnvs($value);
                continue;
            }

            $matches = [ 0 => [], 1 => [] ];
            preg_match_all(self::BRACKET_PATTERN, $value, $matches, PREG_PATTERN_ORDER, 0);
            if (!$matches[0]) {
                continue;
            }

            foreach ($matches[1] as $index => $matchedKey) {
                // it's an environment variable
                $isStartsWith = strstr($matchedKey, '$env.', true) === '';
                if ($isStartsWith) {
                    $matchedKey = substr($matchedKey, 5);
                    $matches[1][$index] = getenv($matchedKey);
                }
            }

            $vars[$key] = str_replace($matches[0], $matches[1], $value);
        }

        return $vars;
    }

    /**
     * replace with variables
     *
     * @param   array<string, mixed>  $vars
     * @param   array<string, mixed>  $refs
     *
     * @return  array<string, mixed>          [return description]
     */
    public function replaceVars(array $vars, ?array $refs = []): array
    {
        if (!$refs) {
            $refs = $vars;
        }

        $isFile = $vars['is_file'] ?? false;
        foreach ($vars as $key => $value) {
            if ($isFile) {
                if ('methods' === $key) {
                    continue;
                }
                if ('from' === $key && (strpos($value, PHP_EOL) !== false)) {
                    continue;
                }
            }

            if (is_array($value)) {
                $vars[$key] = $this->replaceVars($value, $refs);
                continue;
            }

            $matches = [ 0 => [], 1 => [] ];
            preg_match_all(self::BRACKET_PATTERN, $value, $matches, PREG_PATTERN_ORDER, 0);
            if (!$matches[0]) {
                continue;
            }

            foreach ($matches[1] as $index => $matchedKey) {
                // if not found, keep the original value
                $found = ArrAccess::get($refs, $matchedKey);
                if (null === $found) {
                    $matches[1][$index] = $matches[0][$index];
                    continue;
                }
                $matches[1][$index] = $found;
            }

            $vars[$key] = str_replace($matches[0], $matches[1], $value);
        }

        return $vars;
    }
}
