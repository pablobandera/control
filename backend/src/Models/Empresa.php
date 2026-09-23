<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Empresa extends Model
{
    protected string $table = 'empresas';

    public function primera(): array
    {
        $row = $this->db->query('SELECT * FROM empresas ORDER BY id ASC LIMIT 1')->fetch();
        return $row === false ? [] : $row;
    }
}
