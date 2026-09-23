<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Gasto extends Model
{
    protected string $table = 'gastos';

    public function allDeTurno(int $turnoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM gastos WHERE turno_id = :tid ORDER BY hora DESC');
        $stmt->execute(['tid' => $turnoId]);
        return $stmt->fetchAll();
    }

    public function conTurno(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT g.*, t.chofer_id, t.estado AS turno_estado FROM gastos g JOIN turnos t ON t.id = g.turno_id WHERE g.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
