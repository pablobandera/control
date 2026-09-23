<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Turno extends Model
{
    protected string $table = 'turnos';

    public function activoDeChofer(int $choferId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM turnos WHERE chofer_id = :cid AND estado = 'activo' LIMIT 1");
        $stmt->execute(['cid' => $choferId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function cerrar(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE turnos SET estado = 'cerrado', fecha_fin = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function historialDeChofer(int $choferId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, m.numero AS movil_numero FROM turnos t
             JOIN moviles m ON m.id = t.movil_id
             WHERE t.chofer_id = :cid AND t.estado = 'cerrado'
             ORDER BY t.fecha_inicio DESC LIMIT :lim"
        );
        $stmt->bindValue(':cid', $choferId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Trae un turno con datos del movil, del chofer y de la empresa (para chequeos de permisos).
     */
    public function conMovil(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, m.numero AS movil_numero, c.nombre AS chofer_nombre, c.empresa_id
             FROM turnos t
             JOIN moviles m ON m.id = t.movil_id
             JOIN choferes c ON c.id = t.chofer_id
             WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array> turnos activos de la empresa, indexados por chofer_id
     */
    public function activosDeEmpresa(int $empresaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, m.numero AS movil_numero FROM turnos t
             JOIN moviles m ON m.id = t.movil_id
             JOIN choferes c ON c.id = t.chofer_id
             WHERE c.empresa_id = :eid AND t.estado = 'activo'"
        );
        $stmt->execute(['eid' => $empresaId]);

        $byChofer = [];
        foreach ($stmt->fetchAll() as $row) {
            $byChofer[(int) $row['chofer_id']] = $row;
        }
        return $byChofer;
    }

    /** @return int[] */
    public function idsDeEmpresaEnRango(int $empresaId, string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.id FROM turnos t
             JOIN choferes c ON c.id = t.chofer_id
             WHERE c.empresa_id = :eid AND t.fecha_inicio >= :desde AND t.fecha_inicio < :hasta'
        );
        $stmt->execute(['eid' => $empresaId, 'desde' => $desde, 'hasta' => $hasta]);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    /** @return int[] */
    public function deChoferEnRango(int $choferId, string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM turnos WHERE chofer_id = :cid AND fecha_inicio >= :desde AND fecha_inicio < :hasta'
        );
        $stmt->execute(['cid' => $choferId, 'desde' => $desde, 'hasta' => $hasta]);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }
}
