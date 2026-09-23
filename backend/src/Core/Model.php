<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Database;
use PDO;

abstract class Model
{
    protected PDO $db;
    protected string $table;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data claves controladas por el código propio (nunca por input crudo del usuario)
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ":{$c}", $columns);
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data claves controladas por el código propio (nunca por input crudo del usuario)
     */
    public function update(int $id, array $data): bool
    {
        if ($data === []) {
            return true;
        }
        $sets = implode(', ', array_map(static fn (string $c): string => "{$c} = :{$c}", array_keys($data)));
        $sql = "UPDATE {$this->table} SET {$sets} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([...$data, 'id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
