<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Usuario extends Model
{
    protected string $table = 'usuarios';

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function existsUsername(string $username): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE username = :u');
        $stmt->execute(['u' => $username]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
