<?php

namespace Absszero\Phead;

use Symfony\Component\Yaml\Yaml;

class Layout
{
    public const BRACKET_PATTERN = '/{{ *([^ ]+) *}}/';

    public int $indent = 4;
    public string $indentChar = ' ';
    /**
     * @var array<string, mixed>
     */
    public array $data = [];
    public static function parse(string $file): self
    {
        $layout = new self();
        $data = (array)Yaml::parseFile($file);
        $data = $layout->filter($data);
        $data = $layout->replaceEnvs($data);
        $data = $layout->replaceGlobalVars($data);
        $data = $layout->collectFilesVars($data);
        $data = $layout->replaceFilesVars($data);
        $data['$files'] = $layout->replaceLocalVars($data['$files']);
        $data['$files'] = $layout->appendMethods($data['$files']);
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
        if (!array_key_exists('$globals', $data) || !is_array($data['$globals'])) {
            $data['$globals'] = [];
        }

        $files = [];
        if (!array_key_exists('$files', $data) || !is_array($data['$files'])) {
            $data['$files'] = [];
            return $data;
        }

        foreach ($data['$files'] as $fileKey => $file) {
            if (!array_key_exists('from', $file)) {
                continue;
            }
            if (!array_key_exists('to', $file)) {
                continue;
            }

            if (is_file($file['from']) and is_readable($file['from'])) {
                $file['from'] = file_get_contents($file['from']);
            }
            $files[$fileKey] = $file;
        }
        $data['$files'] = $files;

        return $data;
    }

    /**
     * get files' vars
     *
     * @param   array<string, mixed>  $data
     * @param   array<string, mixed>  $refs
     *
     * @return  array<string, mixed>
     */
    public function replaceFilesVars(array $data, array $refs = []): array
    {
        if (!$refs) {
            $refs = $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->replaceFilesVars($value, $refs);
                continue;
            }

            $matches = [ 0 => [], 1 => [] ];
            preg_match_all(self::BRACKET_PATTERN, $value, $matches, PREG_PATTERN_ORDER, 0);
            if (!$matches[0]) {
                continue;
            }

            foreach ($matches[1] as $index => $matchedKey) {
                // if not found, use default
                $matches[1][$index] = $matches[0][$index];

                $isFiles = strstr($matchedKey, '$files.', true) === '';
                if (!$isFiles) {
                    continue;
                }

                $namespace = ArrAccess::get($refs, $matchedKey . '.vars.namespace');
                $class = ArrAccess::get($refs, $matchedKey . '.vars.class');
                if ($namespace && $class) {
                    $matches[1][$index] = '\\' . $namespace . '\\' . $class;
                }
            }

            $data[$key] = str_replace($matches[0], $matches[1], $value);
        }

        return $data;
    }

    /**
     * collect files' vars
     *
     * @param array<string, mixed> $data
     *
     * @return  array<string, mixed>
     */
    public function collectFilesVars(array $data): array
    {
        foreach ($data['$files'] as $key => $file) {
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
            $data['$files'][$key]['vars'] = $vars;
        }

        return $data;
    }

    /**
     * append methods to file
     *
     * @param   array<string, string|mixed>  $files
     *
     * @return  array<string, string|mixed>
     */
    public function appendMethods(array $files): array
    {
        foreach ($files as $key => $file) {
            if (!array_key_exists('methods', $file)) {
                continue;
            }

            // add indentation
            $methods = [];
            foreach ((array)$file['methods'] as $index => $method) {
                $lines = array_filter(explode(PHP_EOL, $method));
                $lines = array_map(fn($line) => str_pad('', $this->indent, $this->indentChar) . $line, $lines);
                $methods[$index] = PHP_EOL . join(PHP_EOL, $lines);
            }
            $methods = join(PHP_EOL, $methods);

            // insert methods on the bottom of the class file
            $length = strlen($file['from']) - 1;
            while ($length > 0) {
                if ($file['from'][$length] === '}') {
                    $file['from'] = substr_replace($file['from'], $methods, $length, 1);
                    $file['from'] = rtrim($file['from']) . PHP_EOL . '}' . PHP_EOL;
                    break;
                }
                $length--;
            }

            $files[$key] = $file;
        }

        return $files;
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
                // if not found, use default
                $matches[1][$index] = $matches[0][$index];

                // it's an environment variable
                $isStartsWithEnv = strstr($matchedKey, '$env.', true) === '';
                if ($isStartsWithEnv) {
                    $matchedKey = substr($matchedKey, 5);
                    $found = getenv($matchedKey);


                    if ($found) {
                        $matches[1][$index] = $found;
                    }
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
     * @param   array<string, mixed>  $globalVars
     *
     * @return  array<string, mixed>          [return description]
     */
    public function replaceGlobalVars(array $vars, ?array $globalVars = []): array
    {
        if (!$globalVars) {
            $globalVars = $vars;
        }

        foreach ($vars as $key => $value) {
            if (is_array($value)) {
                $vars[$key] = $this->replaceGlobalVars($value, $globalVars);
                continue;
            }

            $matches = [ 0 => [], 1 => [] ];
            preg_match_all(self::BRACKET_PATTERN, $value, $matches, PREG_PATTERN_ORDER, 0);
            if (!$matches[0]) {
                continue;
            }

            foreach ($matches[1] as $index => $matchedKey) {
                // if not found, use default
                $matches[1][$index] = $matches[0][$index];

                $isDollar = strstr($matchedKey, '$', true) === '';
                if (!$isDollar) {
                    continue;
                }
                // do it later, we need to know namespace and class name
                $isFiles = strstr($matchedKey, '$files.', true) === '';
                if ($isFiles) {
                    continue;
                }

                $found = ArrAccess::get($globalVars, $matchedKey);
                if ($found) {
                    $matches[1][$index] = $found;
                }
            }

            $vars[$key] = str_replace($matches[0], $matches[1], $value);
        }

        return $vars;
    }

    /**
     * replace with variables
     *
     * @param   array<string, mixed>  $data
     * @param   null|array<string, mixed>  $vars
     *
     * @return  array<string, mixed>          [return description]
     */
    public function replaceLocalVars(array $data, ?array $vars = null): array
    {
        foreach ($data as $key => $value) {
            if ($key === 'vars') {
                continue;
            }

            if (is_null($vars)) {
                $vars = $value['vars'];
            }

            if (is_array($value)) {
                $data[$key] = $this->replaceLocalVars($value, $vars);
                continue;
            }

            $matches = [ 0 => [], 1 => [] ];
            preg_match_all(self::BRACKET_PATTERN, $value, $matches, PREG_PATTERN_ORDER, 0);
            if (!$matches[0]) {
                continue;
            }

            foreach ($matches[1] as $index => $matchedKey) {
                // if not found, use default
                $matches[1][$index] = $matches[0][$index];

                $found = ArrAccess::get($vars, $matchedKey);
                if ($found) {
                    $matches[1][$index] = $found;
                }
            }

            $data[$key] = str_replace($matches[0], $matches[1], $value);
        }

        return $data;
    }
}
