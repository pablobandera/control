<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Movil extends Model
{
    protected string $table = 'moviles';

    public function findOrCreate(int $empresaId, string $numero): array
    {
        $stmt = $this->db->prepare('SELECT * FROM moviles WHERE empresa_id = :eid AND numero = :n LIMIT 1');
        $stmt->execute(['eid' => $empresaId, 'n' => $numero]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }

        $id = $this->insert(['empresa_id' => $empresaId, 'numero' => $numero]);
        return $this->find($id) ?? [];
    }

    public function allDeEmpresa(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM moviles WHERE empresa_id = :eid ORDER BY numero ASC');
        $stmt->execute(['eid' => $empresaId]);
        return $stmt->fetchAll();
    }
}
