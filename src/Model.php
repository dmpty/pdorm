<?php

namespace Dmpty\PdOrm;

abstract class Model extends CollectionItem
{
    protected string $connection = '';

    protected string $writeConnection = '';

    protected string $table = '';

    protected string $primaryKey = 'id';

    protected array $jsonFields = [];

    private array $originalAttr;

    public function __construct(array $attributes = [])
    {
        $this->originalAttr = $this->serializeAttr($attributes);
        $attributes = $this->formatAttr($attributes);
        parent::__construct($attributes);
    }

    public static function query(): QueryBuilder
    {
        return (new static)->newQuery();
    }

    public static function create(array $data): bool|static
    {
        return static::query()->insert($data);
    }

    public function newQuery(): QueryBuilder
    {
        return new QueryBuilder([
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
            if (in_array($key, $this->jsonFields)) {
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
