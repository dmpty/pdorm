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
        if (!$data = $this->getModifiedAttr()) {
            return;
        }
        $pk = $this->primaryKey;
        $this->newQuery()->where([$pk => $this->attributes[$pk]])->update($data);
    }

    public function __get($name)
    {
        if ($value = parent::__get($name)) {
            return $value;
        }
        if ($relation = $this->getRelation($name)) {
            return $relation->get();
        }
        return null;
    }

    public function offsetGet(mixed $offset)
    {
        if ($value = parent::__get($offset)) {
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
        foreach ($current as $key => $value) {
            if (isset($this->originalAttr[$key])) {
                $originalValue = $this->originalAttr[$key];
                if (in_array($key, $this->jsonFields)) {
                    $originalValue = json_encode($this->json2Arr($originalValue));
                }
                if ($originalValue === $value) {
                    continue;
                }
                if ($originalValue === null && $value === null) {
                    continue;
                }
            }
            $data[$key] = $value;
        }
        return $data;
    }
}
