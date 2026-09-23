<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Comprobante extends Model
{
    protected string $table = 'comprobantes';

    public function allDeTurno(int $turnoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM comprobantes WHERE turno_id = :tid ORDER BY id ASC');
        $stmt->execute(['tid' => $turnoId]);
        return $stmt->fetchAll();
    }
}
