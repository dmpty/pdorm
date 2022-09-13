<?php

namespace Dmpty\PdOrm;

use Closure;

class Relation
{
    public const TYPE_HAS_ONE = 0;
    public const TYPE_HAS_MANY = 1;
    public const TYPE_BELONGS_TO = 2;

    public int $type;

    public Model $model;

    public Model $target;

    public string $foreignKey;

    public string $ownerKey;

    private Query $query;

    public ?Closure $withQueryCallback = null;

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
        $this->query = $this->getQuery();
    }

    public function get(): Model|Collection|CollectionItem|null
    {
        return match ($this->type) {
            static::TYPE_HAS_ONE,
            static::TYPE_BELONGS_TO => $this->query->first(),
            static::TYPE_HAS_MANY => $this->query->get(),
        };
    }

    public function query(Closure $closure): static
    {
        $closure($this->query);
        return $this;
    }

    public function withQueryCallback(Closure $closure): static
    {
        $this->withQueryCallback = $closure;
        return $this;
    }

    private function getQuery(): Query
    {
        $query = $this->target->newQuery();
        if ($this->model->attributes) {
            match ($this->type) {
                static::TYPE_HAS_ONE,
                static::TYPE_HAS_MANY => $query->where($this->foreignKey, $this->model[$this->ownerKey]),
                static::TYPE_BELONGS_TO => $query->where($this->ownerKey, $this->model[$this->foreignKey]),
            };
        }
        return $query;
    }
}
