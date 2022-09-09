<?php

namespace Dmpty\PdOrm;

use ArrayAccess;
use Closure;
use Iterator;
use JsonSerializable;

class Collection implements ArrayAccess, ArrayAble, Iterator, JsonSerializable
{
    private int $iteratorIndex = 0;

    private array $items = [];

    public function __construct(array|Collection $data)
    {
        $this->initData($data);
    }

    private function initData(array|Collection $data)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $this->items[] = new CollectionItem($item);
                }
                if ($item instanceof CollectionItem) {
                    $this->items[] = $item;
                }
            }
        }
        if ($data instanceof Collection) {
            $this->items = $data->items;
        }
    }

    public function jsonSerialize()
    {
        return $this->items;
    }

    public function toArray(): array
    {
        $array = [];
        foreach ($this->items as $item) {
            if ($item instanceof ArrayAble) {
                $item = $item->toArray();
            }
            $array[] = $item;
        }
        return $array;
    }

    public function current()
    {
        return $this->items[$this->iteratorIndex];
    }

    public function next()
    {
        $this->iteratorIndex++;
    }

    public function key()
    {
        return $this->iteratorIndex;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->iteratorIndex]);
    }

    public function rewind()
    {
        $this->iteratorIndex = 0;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset)
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value)
    {
        $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset)
    {
        unset($this->items[$offset]);
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function sum(string $key): float
    {
        $res = 0;
        foreach ($this->items as $item) {
            $res += (float)$item[$key];
        }
        return $res;
    }

    public function avg(string $key): float
    {
        return $this->sum($key) / $this->count();
    }

    public function pluck(string $key): array
    {
        $res = [];
        foreach ($this->items as $item) {
            $res[] = $item[$key];
        }
        return $res;
    }

    public function implode(string $key, string $separator): string
    {
        $array = $this->pluck($key);
        return implode($separator, $array);
    }

    public function contains(string $key, $value): bool
    {
        foreach ($this->items as $item) {
            if ($item[$key] == $value) {
                return true;
            }
        }
        return false;
    }

    public function every(Closure $callback): bool
    {
        foreach ($this->items as $item) {
            if ($callback($item) === false) {
                return false;
            }
        }
        return true;
    }

    public function paginate(int $perPage, int $currentPage): Page
    {
        $count = $this->count();
        $currentPage = $currentPage > 0 ? $currentPage : 1;
        $offset = ($currentPage - 1) * $perPage;
        $items = array_slice($this->items, $offset, $perPage);
        return new Page($items, $count, $perPage, $currentPage);
    }

    public function first(): CollectionItem|null
    {
        return $this->items[0] ?? null;
    }

    public function pop(): CollectionItem
    {
        $item = $this->items[$this->count() - 1];
        array_pop($this->items);
        return $item;
    }

    public function shift(): CollectionItem
    {
        $item = $this->items[0];
        array_shift($this->items);
        return $item;
    }

    public function random(): CollectionItem
    {
        $index = rand(0, $this->count() - 1);
        return $this->items[$index];
    }

    public function add(array|CollectionItem $item): static
    {
        if (is_array($item)) {
            $item = new CollectionItem($item);
        }
        $this->items[] = $item;
        return $this;
    }

    public function unshift(array|CollectionItem $item): static
    {
        if (is_array($item)) {
            $item = new CollectionItem($item);
        }
        array_unshift($this->items, $item);
        return $this;
    }

    public function take(int $num): static
    {
        $items = array_slice($this->items, 0, $num);
        return new static($items);
    }

    public function filter(Closure $callback): static
    {
        $items = array_filter($this->items, $callback);
        return new static($items);
    }

    public function tap(Closure $callback): static
    {
        foreach ($this->items as $item) {
            $callback($item);
        }
        return $this;
    }

    public function each(Closure $callback): static
    {
        $items = [];
        foreach ($this->items as $item) {
            $item = $callback($item);
            $items[] = $item;
        }
        return new static($items);
    }

    public function except(array|string $keys): static
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        $items = [];
        foreach ($this->items as $item) {
            foreach ($keys as $key) {
                unset($item[$key]);
            }
            $items[] = $item;
        }
        return new static($items);
    }

    public function only(array|string $keys): static
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        $items = [];
        foreach ($this->items as $item) {
            $newItem = [];
            foreach ($keys as $key) {
                $newItem[$key] = $item[$key];
            }
            $items[] = new CollectionItem($newItem);
        }
        return new static($items);
    }

    public function sort(Closure $callback): static
    {
        $items = $this->items;
        usort($items, $callback);
        return new static($items);
    }

    public function sortBy(string $key, bool $desc = false): static
    {
        $callback = $desc
            ? function ($left, $right) use ($key) {
                if ($left[$key] === $right[$key]) {
                    return 0;
                }
                return ($left[$key] > $right[$key]) ? -1 : 1;
            }
            : function ($left, $right) use ($key) {
                if ($left[$key] === $right[$key]) {
                    return 0;
                }
                return ($left[$key] < $right[$key]) ? -1 : 1;
            };
        return $this->sort($callback);
    }

    public function shuffle(): static
    {
        $items = $this->items;
        shuffle($items);
        return new static($items);
    }

    public function where(array|string $field, $op = null, $value = null): static
    {
        $items = $this->items;
        if (is_array($field)) {
            foreach ($field as $key => $value) {
                if (is_array($value)) {
                    $items = $this->getWhereResult($items, ...$value);
                } else {
                    $items = $this->getWhereResult($items, $key, $value);
                }
            }
        } else {
            $items = $this->getWhereResult($items, $field, $op, $value);
        }
        return new static($items);
    }

    public function whereIn(string $key, array $values): static
    {
        return $this->filter(function ($item) use ($key, $values) {
            return in_array($item[$key], $values);
        });
    }

    public function whereNotIn(string $key, array $values): static
    {
        return $this->filter(function ($item) use ($key, $values) {
            return !in_array($item[$key], $values);
        });
    }

    public function whereBetween(string $key, $min, $max): static
    {
        return $this->filter(function ($item) use ($key, $min, $max) {
            return $item[$key] >= $min && $item[$key] <= $max;
        });
    }

    public function whereNotBetween(string $key, $min, $max): static
    {
        return $this->filter(function ($item) use ($key, $min, $max) {
            return $item[$key] < $min || $item[$key] > $max;
        });
    }

    public function whereStrContains(string $key, string $value): static
    {
        return $this->filter(function ($item) use ($key, $value) {
            return str_contains($item[$key], $value);
        });
    }

    private function getWhereResult(array $items, array|string $key, $op = null, $value = null): array
    {
        if ($value === null && $op !== null) {
            $value = $op;
            $op = '=';
        }
        $callback = match ($op) {
            '=' => function ($item) use ($key, $value) {
                if ($value === null && $item[$key] === null) {
                    return true;
                }
                return $item[$key] == $value;
            },
            '<>' => function ($item) use ($key, $value) {
                if ($value === null && $item[$key] !== null) {
                    return true;
                }
                return $item[$key] != $value;
            },
            '>' => function ($item) use ($key, $value) {
                return $item[$key] > $value;
            },
            '>=' => function ($item) use ($key, $value) {
                return $item[$key] >= $value;
            },
            '<' => function ($item) use ($key, $value) {
                return $item[$key] < $value;
            },
            '<=' => function ($item) use ($key, $value) {
                return $item[$key] <= $value;
            },
            default => throw new PdOrmException("Operator: $op not supported"),
        };
        return array_filter($items, $callback);
    }
}
