<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Service extends Model
{
    protected string $table = 'services';

    public function allDeMovil(int $movilId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, c.nombre AS chofer_nombre FROM services s
             JOIN choferes c ON c.id = s.chofer_id
             WHERE s.movil_id = :mid ORDER BY s.fecha DESC'
        );
        $stmt->execute(['mid' => $movilId]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, array> entradas de service agrupadas por número de móvil
     */
    public function allDeEmpresaAgrupadoPorMovil(int $empresaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, c.nombre AS chofer_nombre, m.numero AS movil_numero
             FROM services s
             JOIN choferes c ON c.id = s.chofer_id
             JOIN moviles m ON m.id = s.movil_id
             WHERE m.empresa_id = :eid
             ORDER BY m.numero ASC, s.fecha DESC'
        );
        $stmt->execute(['eid' => $empresaId]);

        $porMovil = [];
        foreach ($stmt->fetchAll() as $row) {
            $porMovil[$row['movil_numero']][] = $row;
        }
        return $porMovil;
    }
}
