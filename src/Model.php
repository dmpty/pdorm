<?php

namespace Dmpty\PdOrm;

abstract class Model extends CollectionItem
{
    public string $table = '';

    protected string $connection = '';

    protected string $writeConnection = '';

    protected string $primaryKey = 'id';

    protected array $jsonFields = [];

    private array $originalAttr;

    public function __construct(array $attributes = [])
    {
        $this->originalAttr = $this->serializeAttr($attributes);
        $attributes = $this->formatAttr($attributes);
        parent::__construct($attributes);
    }

    public static function getTable(): string
    {
        return (new static())->table;
    }

    public static function query(): Query
    {
        return (new static())->newQuery();
    }

    public static function create(array $data): bool|static
    {
        return static::query()->insert($data);
    }

    public function newQuery(): Query
    {
        return new Query([
            'resultClass' => static::class,
            'connection' => $this->connection,
            'writeConnection' => $this->writeConnection,
            'table' => $this->table,
            'primaryKey' => $this->primaryKey,
        ]);
    }

    public function save()
    {
        if (!$this->originalAttr) {
            $new = $this->newQuery()->insert($this->attributes);
            $this->attributes[$this->primaryKey] = $new[$this->primaryKey];
            $this->originalAttr = $this->serializeAttr($this->attributes);
            return;
        }
        if (!$data = $this->getModifiedAttr()) {
            return;
        }
        $pk = $this->primaryKey;
        $this->newQuery()->where([$pk => $this->attributes[$pk]])->update($data);
        $this->originalAttr = $this->serializeAttr($this->attributes);
    }

    public function __get($name)
    {
        $value = parent::__get($name);
        if ($value !== null) {
            return $value;
        }
        if ($relation = $this->getRelation($name)) {
            return $relation->get();
        }
        return null;
    }

    public function offsetGet(mixed $offset)
    {
        $value = parent::offsetGet($offset);
        if ($value !== null) {
            return $value;
        }
        if ($relation = $this->getRelation($offset)) {
            return $relation->get();
        }
        return null;
    }

    public function getRelation(string $relationKey): ?Relation
    {
        $words = explode('_', $relationKey);
        $relation = '';
        foreach ($words as $word) {
            $relation .= ucfirst($word);
        }
        $method = 'relation' . $relation;
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }

    protected function hasOne(string $class, string $foreignKey, string $ownerKey = 'id'): Relation
    {
        return new Relation(Relation::TYPE_HAS_ONE, $this, $class, $foreignKey, $ownerKey);
    }

    protected function hasMany(string $class, string $foreignKey, string $ownerKey = 'id'): Relation
    {
        return new Relation(Relation::TYPE_HAS_MANY, $this, $class, $foreignKey, $ownerKey);
    }

    protected function belongsTo(string $class, string $foreignKey, string $ownerKey = 'id'): Relation
    {
        return new Relation(Relation::TYPE_BELONGS_TO, $this, $class, $foreignKey, $ownerKey);
    }

    private function serializeAttr(array $data): array
    {
        foreach ($data as &$item) {
            if (is_array($item)) {
                $item = json_encode($item);
            }
        }
        return $data;
    }

    private function formatAttr(array $data): array
    {
        foreach ($data as $key => $value) {
            if (!is_array($value) && in_array($key, $this->jsonFields)) {
                $data[$key] = $this->json2Arr($value);
            }
        }
        return $data;
    }

    private function getModifiedAttr(): array
    {
        $data = [];
        $current = $this->serializeAttr($this->attributes);
        foreach ($this->originalAttr as $key => $value) {
            if (in_array($key, $this->jsonFields)) {
                $value = json_encode($this->json2Arr($value));
            }
            $currentValue = $current[$key] ?? null;
            if ($currentValue === $value) {
                continue;
            }
            if ($currentValue === null && $value === null) {
                continue;
            }
            $data[$key] = $currentValue;
        }
        return $data;
    }
}
