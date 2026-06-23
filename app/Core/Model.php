<?php

namespace App\Core;

use InvalidArgumentException;
use PDO;

abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected bool $usesSoftDeletes = true;

    protected function db(): PDO
    {
        return Database::connection();
    }

    public function all(): array
    {
        $sql = sprintf(
            'SELECT * FROM %s %s ORDER BY %s DESC',
            $this->tableName(),
            $this->activeRecordClause(),
            $this->columnName($this->primaryKey)
        );

        return Database::query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = :id %s LIMIT 1',
            $this->tableName(),
            $this->columnName($this->primaryKey),
            $this->activeRecordCondition()
        );

        $statement = Database::query($sql, ['id' => $id]);
        $record = $statement->fetch();

        return $record ?: null;
    }

    public function create(array $data): int
    {
        $data = $this->filterData($data);

        if ($data === []) {
            throw new InvalidArgumentException('Aucune donnee a inserer.');
        }

        $columns = array_keys($data);
        $columnSql = implode(', ', array_map([$this, 'columnName'], $columns));
        $placeholderSql = implode(', ', array_map(static function (string $column): string {
            return ':' . $column;
        }, $columns));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->tableName(),
            $columnSql,
            $placeholderSql
        );

        Database::query($sql, $data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->filterData($data);

        if ($data === []) {
            return false;
        }

        $sets = [];

        foreach (array_keys($data) as $column) {
            $sets[] = $this->columnName($column) . ' = :' . $column;
        }

        $data['id'] = $id;

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :id %s',
            $this->tableName(),
            implode(', ', $sets),
            $this->columnName($this->primaryKey),
            $this->activeRecordCondition()
        );

        return Database::query($sql, $data)->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s = :id',
            $this->tableName(),
            $this->columnName($this->primaryKey)
        );

        return Database::query($sql, ['id' => $id])->rowCount() > 0;
    }

    public function softDelete(int $id): bool
    {
        if (!$this->usesSoftDeletes) {
            return $this->delete($id);
        }

        $sql = sprintf(
            'UPDATE %s SET deleted_at = NOW() WHERE %s = :id AND deleted_at IS NULL',
            $this->tableName(),
            $this->columnName($this->primaryKey)
        );

        return Database::query($sql, ['id' => $id])->rowCount() > 0;
    }

    public function where(string $column, $value): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = :value %s ORDER BY %s DESC',
            $this->tableName(),
            $this->columnName($column),
            $this->activeRecordCondition(),
            $this->columnName($this->primaryKey)
        );

        return Database::query($sql, ['value' => $value])->fetchAll();
    }

    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = $this->activeRecordClause();
        $countSql = sprintf('SELECT COUNT(*) FROM %s %s', $this->tableName(), $where);
        $total = (int) Database::query($countSql)->fetchColumn();

        $sql = sprintf(
            'SELECT * FROM %s %s ORDER BY %s DESC LIMIT :limit OFFSET :offset',
            $this->tableName(),
            $where,
            $this->columnName($this->primaryKey)
        );

        $statement = $this->db()->prepare($sql);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'data' => $statement->fetchAll(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    protected function filterData(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    protected function tableName(): string
    {
        return $this->identifier($this->table);
    }

    protected function columnName(string $column): string
    {
        return $this->identifier($column);
    }

    protected function identifier(string $identifier): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException('Identifiant SQL invalide: ' . $identifier);
        }

        return '`' . $identifier . '`';
    }

    protected function activeRecordClause(): string
    {
        return $this->usesSoftDeletes ? 'WHERE deleted_at IS NULL' : '';
    }

    protected function activeRecordCondition(): string
    {
        return $this->usesSoftDeletes ? 'AND deleted_at IS NULL' : '';
    }
}
