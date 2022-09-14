<?php

namespace Dmpty\PdOrm;

use Closure;
use PDO;
use PDOException;
use Throwable;

class Query
{
    private string $resultClass;

    private string $connection;

    private string $writeConnection;

    private string $primaryKey;

    private string $table;

    private string $sql;

    private array $values = [];

    private string $method = 'SELECT';

    private array $selectFields = [];

    private string $from = '';

    private array $insertFields = [];

    private array $updatePlaceholders = [];

    private array $writeValues = [];

    private array $wherePlaceholders = [];

    private array $whereValues = [];

    private array $orderBys = [];

    private string $groupBy = '';

    private string $limit = '';

    private array $with = [];

    public function __construct(array $options = [])
    {
        $this->resultClass = $options['resultClass'] ?? CollectionItem::class;
        $this->connection = $options['connection'] ?? '';
        $this->writeConnection = $options['writeConnection'] ?? '';
        $this->primaryKey = $options['primaryKey'] ?? 'id';
        $this->table = $options['table'] ?? '';
    }

    public function transaction(Closure $callback)
    {
        $connection = $this->writeConnection ?: $this->connection;
        $pdo = DB::getPdo($connection);
        $pdo->beginTransaction();
        try {
            $res = $callback();
            $pdo->commit();
            return $res;
        } catch (Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    public function table($table): static
    {
        $this->table = $table;
        return $this;
    }

    public function first(): CollectionItem|Model|null
    {
        return $this->limit(1)->get()->first();
    }

    public function find($value): CollectionItem|Model|null
    {
        return $this->where([$this->primaryKey => $value])->get()->first();
    }

    public function get(): Collection
    {
        $this->build();
        return $this->getSelectResult();
    }

    public function paginate(int $perPage, int $currentPage): Page
    {
        $countQuery = clone $this;
        $count = $countQuery->count();
        $currentPage = $currentPage > 0 ? $currentPage : 1;
        $offset = ($currentPage - 1) * $perPage;
        $this->limit($offset, $perPage);
        $this->build();
        return new Page($this->getSelectResult(), $count, $perPage, $currentPage);
    }

    public function count(): int
    {
        $this->selectRaw('COUNT(1) as count');
        $this->build();
        $res = $this->executeRead();
        return $res[0]['count'];
    }

    public function sum(string $field): int
    {
        $field = $this->getFormattedField($field);
        $this->selectRaw("SUM($field) as sum");
        $this->build();
        $res = $this->executeRead();
        return $res[0]['sum'];
    }

    public function avg(string $field): int
    {
        $field = $this->getFormattedField($field);
        $this->selectRaw("AVG($field) as avg");
        $this->build();
        $res = $this->executeRead();
        return $res[0]['avg'];
    }

    public function insert(array $data): bool|CollectionItem|Model
    {
        if (!$data) {
            throw new PdOrmException('Insert with empty data');
        }
        $this->method = 'INSERT';
        foreach ($data as $key => $value) {
            $this->insertFields[] = $this->getFormattedField($key);
            $this->pushValue($value);
        }
        $this->build();
        $pk = $this->executeWrite();
        if ($pk === false) {
            return false;
        }
        $data = [$this->primaryKey => $pk] + $data;
        return new $this->resultClass($data);
    }

    public function update(array $data): bool|int
    {
        if (!$data) {
            throw new PdOrmException('Insert with empty data');
        }
        $this->method = 'UPDATE';
        foreach ($data as $key => $value) {
            $field = $this->getFormattedField($key);
            $this->updatePlaceholders[] = "$field = ?";
            $this->pushValue($value);
        }
        $this->build();
        return $this->executeWrite();
    }

    public function delete(): bool
    {
        $this->method = 'DELETE';
        $this->build();
        return $this->executeWrite();
    }

    public function select(array|string $fields): static
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        foreach ($fields as $field) {
            $this->selectFields[] = $this->getFormattedField($field);
        }
        return $this;
    }

    public function selectRaw(string $raw): static
    {
        $this->selectFields[] = $raw;
        return $this;
    }

    public function fromRaw(string $raw): static
    {
        $this->from = $raw;
        return $this;
    }

    public function tableAlias(string $alias): static
    {
        $this->fromRaw("$this->table as $alias");
        return $this;
    }

    public function where(array|string $field, $op = null, $value = null): static
    {
        if (is_array($field)) {
            foreach ($field as $key => $value) {
                if (is_array($value)) {
                    $this->where(...$value);
                } else {
                    $this->where($key, $value);
                }
            }
        } else {
            if ($value === null) {
                if ($op === null) {
                    return $this->whereNull($field);
                }
                $value = $op;
                $op = '=';
            }
            if (!$this->isOperator($op)) {
                throw new PdOrmException("Operator: $op not supported");
            }
            $field = $this->getFormattedField($field);
            $this->wherePlaceholders[] = "$field $op ?";
            $this->whereValues[] = $value;
        }
        return $this;
    }

    public function whereNull(string $field): static
    {
        $field = $this->getFormattedField($field);
        $this->wherePlaceholders[] = "$field IS NULL";
        return $this;
    }

    public function whereNotNull(string $field): static
    {
        $field = $this->getFormattedField($field);
        $this->wherePlaceholders[] = "$field IS NOT NULL";
        return $this;
    }

    public function whereIn(string $field, array $values): static
    {
        $field = $this->getFormattedField($field);
        $placeholders = [];
        for ($i = 0; $i < count($values); $i++) {
            $placeholders[] = '?';
        }
        $placeholders = implode(', ', $placeholders);
        $this->wherePlaceholders[] = "$field IN ($placeholders)";
        $this->whereValues = array_merge($this->whereValues, $values);
        return $this;
    }

    public function whereNotIn(string $field, array $values): static
    {
        $field = $this->getFormattedField($field);
        $placeholders = [];
        for ($i = 0; $i < count($values); $i++) {
            $placeholders[] = '?';
        }
        $placeholders = implode(', ', $placeholders);
        $this->wherePlaceholders[] = "$field NOT IN ($placeholders)";
        $this->whereValues = array_merge($this->whereValues, $values);
        return $this;
    }

    public function whereBetween(string $filed, $min, $max): static
    {
        $field = $this->getFormattedField($filed);
        $this->wherePlaceholders[] = "$field BETWEEN ? AND ?";
        $this->whereValues[] = $min;
        $this->whereValues[] = $max;
        return $this;
    }

    public function whereNotBetween(string $filed, $min, $max): static
    {
        $field = $this->getFormattedField($filed);
        $this->wherePlaceholders[] = "$field NOT BETWEEN ? AND ?";
        $this->whereValues[] = $min;
        $this->whereValues[] = $max;
        return $this;
    }

    public function whereExists(Query $query): static
    {
        list($sql, $values) = $query->getSqlAndBinds();
        $sql = "EXISTS ($sql)";
        $this->wherePlaceholders[] = $sql;
        $this->whereValues = array_merge($this->whereValues, $values);
        return $this;
    }

    public function whereRaw(string $raw, $value = null): static
    {
        $this->wherePlaceholders[] = $raw;
        if ($value !== null) {
            if (is_array($value)) {
                $this->whereValues = array_merge($this->whereValues, $value);
            } else {
                $this->whereValues[] = $value;
            }
        }
        return $this;
    }

    public function orderBy(string $field, bool $desc = false): static
    {
        $field = $this->getFormattedField($field);
        if ($desc) {
            $field .= ' DESC';
        }
        $this->orderBys[] = $field;
        return $this;
    }

    public function orderByDesc(string $field): static
    {
        return $this->orderBy($field, true);
    }

    public function groupBy(array|string $fields, bool $withRollup = false): static
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        foreach ($fields as &$field) {
            $field = $this->getFormattedField($field);
        }
        $fields = implode(', ', $fields);
        $this->groupBy = "GROUP BY $fields";
        if ($withRollup) {
            $this->groupBy .= ' WITH ROLLUP';
        }
        return $this;
    }

    public function limit(int $offset, int $length = null): static
    {
        if ($length) {
            $this->limit = "LIMIT $offset, $length";
        } else {
            $this->limit = "LIMIT $offset";
        }
        return $this;
    }

    public function with(array|string $relations): static
    {
        if (!is_array($relations)) {
            $relations = [$relations];
        }
        $this->with = $relations;
        return $this;
    }

    public function getSql(): string
    {
        $this->build();
        return $this->sql;
    }

    public function getSqlAndBinds(): array
    {
        $this->build();
        return [$this->sql, $this->values];
    }

    public function executeRaw(string $sql, array $values = []): Collection|bool|int|string
    {
        $this->sql = $sql;
        $this->values = $values;
        $method = strtoupper(substr(ltrim($sql), 0, 6));
        $this->method = $method;
        if ($method === 'SELECT') {
            return $this->getSelectResult();
        }
        return $this->executeWrite();
    }

    private function getSelectResult(): Collection
    {
        $res = $this->executeRead();
        $data = [];
        foreach ($res as $item) {
            $item = new $this->resultClass($item);
            $data[] = $item;
        }
        $result = new Collection($data);
        if ($this->with) {
            $result = $this->getResultWithRelations($result);
        }
        return $result;
    }

    private function getResultWithRelations(Collection $data): Collection
    {
        /** @var Model $model */
        $model = new $this->resultClass();
        foreach ($this->with as $relationField) {
            if (!$relation = $model->getRelation($relationField)) {
                continue;
            }
            $data = $this->getDataWithRelation($data, $relation, $relationField);
        }
        return $data;
    }

    private function getDataWithRelation(Collection $data, Relation $relation, string $relationField): Collection
    {
        $thisKey = $relation->ownerKey;
        $thatKey = $relation->foreignKey;
        if ($relation->type === Relation::TYPE_BELONGS_TO) {
            list($thisKey, $thatKey) = [$thatKey, $thisKey];
        }
        $thisModel = $relation->model;
        $relationModel = $relation->target;
        $existsQuery = $thisModel->newQuery()
            ->selectRaw('1')
            ->whereRaw("$relationModel->table.`$thatKey` = $this->table.`$thisKey`");
        if ($callback = $relation->withQueryCallback) {
            $callback($existsQuery);
        }
        $existsQuery->wherePlaceholders = array_merge($existsQuery->wherePlaceholders, $this->wherePlaceholders);
        $existsQuery->whereValues = array_merge($existsQuery->whereValues, $this->whereValues);
        $relationQuery = $relation->target->newQuery()->whereExists($existsQuery);
        if ($callback = $relation->queryCallback) {
            $callback($relationQuery);
        }
        $relationData = $relationQuery->get();
        return $data->each(function ($item) use ($relationData, $relation, $relationField, $thisKey, $thatKey) {
            $res = $relationData->where($thatKey, $item[$thisKey]);
            $item[$relationField] = match ($relation->type) {
                Relation::TYPE_HAS_ONE,
                Relation::TYPE_BELONGS_TO => $res->first(),
                Relation::TYPE_HAS_MANY => $res,
            };
            return $item;
        });
    }

    private function build(): void
    {
        $select = $this->getSelect();
        $table = $this->getTable();
        $where = $this->getWhere();
        $orderBy = $this->getOrderBy();
        $insert = $this->getInsert();
        $update = $this->getUpdate();
        $limit = $this->limit;
        $groupBy = $this->groupBy;
        $this->values = array_merge($this->writeValues, $this->whereValues);
        $this->sql = match ($this->method) {
            'SELECT' => "SELECT $select FROM $table $where $orderBy $limit $groupBy",
            'INSERT' => "INSERT INTO $table $insert",
            'UPDATE' => "UPDATE $table $update $where",
            'DELETE' => "DELETE FROM $table $where",
        };
        $this->sql = rtrim($this->sql);
    }

    private function getSelect(): string
    {
        return $this->selectFields ? implode(', ', $this->selectFields) : '*';
    }

    private function getTable(): string
    {
        return $this->from ?: $this->table;
    }

    private function getWhere(): string
    {
        if (!$this->wherePlaceholders) {
            return '';
        }
        return 'WHERE ' . implode(' AND ', $this->wherePlaceholders);
    }

    private function getOrderBy(): string
    {
        if (!$this->orderBys) {
            return '';
        }
        return 'ORDER BY ' . implode(', ', $this->orderBys);
    }

    private function getInsert(): string
    {
        $placeholders = [];
        for ($i = 0; $i < count($this->insertFields); $i++) {
            $placeholders[] = '?';
        }
        $placeholders = implode(', ', $placeholders);
        return '(' . implode(', ', $this->insertFields) . ') VALUES (' . $placeholders . ')';
    }

    private function getUpdate(): string
    {
        return 'SET ' . implode(', ', $this->updatePlaceholders);
    }

    private function getFormattedField($field): string
    {
        $table = null;
        $fieldArray = explode('.', $field);
        if (count($fieldArray) > 1) {
            list($table, $field) = $fieldArray;
        }
        if ($field !== '*') {
            $field = "`$field`";
        }
        if ($table) {
            $field = "$table.$field";
        }
        return $field;
    }

    private function pushValue($value): void
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $this->writeValues[] = $value;
    }

    private function isOperator($op): bool
    {
        $op = strtolower($op);
        return in_array($op, ['=', '<>', '>', '>=', '<', '<=', 'like']);
    }

    private function executeRead(): array
    {
        try {
            $logIndex = QueryLog::log($this->connection, $this->sql, $this->values);
            $queryBegin = microtime(true);
            $pdo = DB::getPdo($this->connection);
            $stmt = $pdo->prepare($this->sql);
            $stmt->execute($this->values);
            $cost = round(microtime(true) - $queryBegin, 4);
            QueryLog::logCost($logIndex, $cost);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new PdOrmException('PDO Error: ' . $e->getMessage());
        }
    }

    private function executeWrite(): bool|int|string
    {
        try {
            $connection = $this->writeConnection ?: $this->connection;
            $logIndex = QueryLog::log($connection, $this->sql, $this->values);
            $queryBegin = microtime(true);
            $pdo = DB::getPdo($connection);
            $stmt = $pdo->prepare($this->sql);
            if (!$stmt->execute($this->values)) {
                return false;
            }
            $cost = round(microtime(true) - $queryBegin, 4);
            QueryLog::logCost($logIndex, $cost);
            if ($this->method === 'INSERT') {
                return $pdo->lastInsertId();
            }
            if ($this->method === 'UPDATE') {
                return $stmt->rowCount();
            }
            return true;
        } catch (PDOException $e) {
            throw new PdOrmException('PDO Error: ' . $e->getMessage());
        }
    }
}
