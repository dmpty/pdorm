<?php

namespace Dmpty\PdOrm;

use ArrayAccess;
use Exception;
use JsonSerializable;

class CollectionItem implements ArrayAccess, ArrayAble, JsonSerializable
{
    public array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function jsonSerialize()
    {
        return $this->attributes;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset)
    {
        if (isset($this->attributes[$offset])) {
            return $this->attributes[$offset];
        }
        $keys = explode('.', $offset);
        if (count($keys) > 1) {
            return $this->getArrayValue($this->attributes, $keys);
        }
        return null;
    }

    public function offsetSet(mixed $offset, mixed $value)
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset)
    {
        unset($this->attributes[$offset]);
    }

    protected function json2Arr(string $json): array
    {
        try {
            $res = json_decode($json, true);
            if (!is_array($res)) {
                return [];
            }
            return $res;
        } /** @noinspection PhpUnusedLocalVariableInspection */ catch (Exception $e) {
            return [];
        }
    }

    private function getArrayValue(array $data, array $keys)
    {
        $parentKey = $keys[0];
        array_shift($keys);
        if (isset($data[$parentKey]) && (is_array($data[$parentKey]) || $data[$parentKey] instanceof ArrayAccess)) {
            $array = $data[$parentKey];
            if (count($keys) === 1) {
                return $array[$keys[0]] ?? null;
            }
            return $this->getArrayValue($array, $keys);
        }
        return null;
    }
}
