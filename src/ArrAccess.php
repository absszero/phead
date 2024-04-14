<?php

namespace Absszero\Phead;

class ArrAccess
{
        /**
     * Get data by dot notation.
     * @param array<string, mixed> $data
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(array $data, string $key, $default = null)
    {
        $nodes = explode('.', $key);
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
     * @param array<string, mixed> $data
     * @param   string  $key    [$key description]
     * @param   mixed  $value  [$value description]
     *
     * @return  void            [return description]
     */
    public static function set(&$data, string $key, $value): void
    {
        $nodes = explode('.', $key);
        /**
         * @psalm-suppress UnsupportedPropertyReferenceUsage
         */
        foreach ($nodes as $node) {
            if (!array_key_exists($node, $data) || !is_array($data[$node])) {
                $data[$node] = [];
            }

            $data = & $data[$node];
        }

        $data = $value;
    }
}
