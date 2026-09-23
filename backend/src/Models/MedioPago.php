<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class MedioPago extends Model
{
    protected string $table = 'medios_pago';

    public function findByNombre(string $nombre): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM medios_pago WHERE nombre = :n LIMIT 1');
        $stmt->execute(['n' => $nombre]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM medios_pago ORDER BY id ASC')->fetchAll();
    }
}
