<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class TipoViaje extends Model
{
    protected string $table = 'tipos_viaje';

    public function findByNombre(string $nombre): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tipos_viaje WHERE nombre = :n LIMIT 1');
        $stmt->execute(['n' => $nombre]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM tipos_viaje ORDER BY id ASC')->fetchAll();
    }
}
