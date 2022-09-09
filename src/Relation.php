<?php

namespace Dmpty\PdOrm;

class Relation
{
    public const TYPE_HAS_ONE = 0;
    public const TYPE_HAS_MANY = 1;
    public const TYPE_BELONGS_TO = 2;

    public int $type;

    private Model $model;

    public Model $target;

    public string $foreignKey;

    public string $ownerKey;

    public function __construct(int $type, Model $model, string $target, string $foreignKey, string $ownerKey)
    {
        $target = new $target();
        if (!($target instanceof Model)) {
            throw new PdOrmException('Relation definition need class instanceof PdOrm\Model');
        }
        $this->type = $type;
        $this->model = $model;
        $this->target = $target;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    public function get(): Model|Collection|CollectionItem|null
    {
        return match ($this->type) {
            static::TYPE_HAS_ONE => $this->getHasOne(),
            static::TYPE_HAS_MANY => $this->getHasMany(),
            static::TYPE_BELONGS_TO => $this->getBelongsTo(),
        };
    }

    private function getHasOne(): Model|CollectionItem|null
    {
        return $this->getHasMany()->first();
    }

    private function getHasMany(): Collection
    {
        return $this->target->newQuery()->where($this->foreignKey, $this->model[$this->ownerKey])->get();
    }

    private function getBelongsTo(): Model|CollectionItem|null
    {
        return $this->target->newQuery()->where($this->ownerKey, $this->model[$this->foreignKey])->first();
    }
}
