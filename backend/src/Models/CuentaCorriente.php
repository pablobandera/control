<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class CuentaCorriente extends Model
{
    protected string $table = 'cuentas_corrientes';

    public function allDeTurno(int $turnoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM cuentas_corrientes WHERE turno_id = :tid ORDER BY hora DESC');
        $stmt->execute(['tid' => $turnoId]);
        return $stmt->fetchAll();
    }

    public function conTurno(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT cc.*, t.chofer_id, t.estado AS turno_estado, c.empresa_id
             FROM cuentas_corrientes cc
             JOIN turnos t ON t.id = cc.turno_id
             JOIN choferes c ON c.id = t.chofer_id
             WHERE cc.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function marcarCobrado(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE cuentas_corrientes SET cobrado = 1, fecha_cobro = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function pendientesDeEmpresa(int $empresaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT cc.*, c.nombre AS chofer_nombre FROM cuentas_corrientes cc
             JOIN turnos t ON t.id = cc.turno_id
             JOIN choferes c ON c.id = t.chofer_id
             WHERE c.empresa_id = :eid AND cc.cobrado = 0
             ORDER BY cc.hora DESC'
        );
        $stmt->execute(['eid' => $empresaId]);
        return $stmt->fetchAll();
    }
}
