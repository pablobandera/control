<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Viaje extends Model
{
    protected string $table = 'viajes';

    public function allDeTurno(int $turnoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT v.*, tv.nombre AS tipo, mp.nombre AS medio
             FROM viajes v
             JOIN tipos_viaje tv ON tv.id = v.tipo_viaje_id
             JOIN medios_pago mp ON mp.id = v.medio_pago_id
             WHERE v.turno_id = :tid ORDER BY v.hora DESC'
        );
        $stmt->execute(['tid' => $turnoId]);
        return $stmt->fetchAll();
    }

    /**
     * Trae el viaje junto con el chofer dueño del turno y el estado del turno (para permisos).
     */
    public function conTurno(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT v.*, tv.nombre AS tipo, mp.nombre AS medio, t.chofer_id, t.estado AS turno_estado
             FROM viajes v
             JOIN tipos_viaje tv ON tv.id = v.tipo_viaje_id
             JOIN medios_pago mp ON mp.id = v.medio_pago_id
             JOIN turnos t ON t.id = v.turno_id
             WHERE v.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
