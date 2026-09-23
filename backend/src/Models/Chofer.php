<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Chofer extends Model
{
    protected string $table = 'choferes';

    public function findByUsuarioId(int $usuarioId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM choferes WHERE usuario_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $usuarioId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function allDeEmpresa(int $empresaId, bool $soloActivos = true): array
    {
        $sql = 'SELECT c.*, u.username FROM choferes c JOIN usuarios u ON u.id = c.usuario_id WHERE c.empresa_id = :eid';
        if ($soloActivos) {
            $sql .= ' AND c.activo = 1';
        }
        $sql .= ' ORDER BY c.nombre ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['eid' => $empresaId]);
        return $stmt->fetchAll();
    }

    public function findConUsuario(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, u.username FROM choferes c JOIN usuarios u ON u.id = c.usuario_id WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
