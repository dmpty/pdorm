<?php

namespace Dmpty\PdOrm;

use Closure;

class Relation
{
    public const TYPE_HAS_ONE = 0;
    public const TYPE_HAS_MANY = 1;
    public const TYPE_BELONGS_TO = 2;

    public const WITH_MODE_IN = 1;
    public const WITH_MODE_EXISTS = 2;

    public int $type;

    public int $withMode = self::WITH_MODE_IN;

    public Model $model;

    public Model $target;

    public string $foreignKey;

    public string $ownerKey;

    public ?Closure $queryCallback = null;

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
    }

    public function get(): Model|Collection|CollectionItem|null
    {
        $query = $this->getQuery();
        if ($callback = $this->queryCallback) {
            $callback($query);
        }
        return match ($this->type) {
            static::TYPE_HAS_ONE,
            static::TYPE_BELONGS_TO => $query->first(),
            static::TYPE_HAS_MANY => $query->get(),
        };
    }

    public function queryCallback(Closure $closure): static
    {
        $this->queryCallback = $closure;
        return $this;
    }

    public function subQueryCallback(Closure $closure): static
    {
        $this->withQueryCallback = $closure;
        return $this;
    }

    public function withMode(int $mode): static
    {
        if (in_array($mode, [self::WITH_MODE_IN, self::WITH_MODE_EXISTS])) {
            $this->withMode = $mode;
        }
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
