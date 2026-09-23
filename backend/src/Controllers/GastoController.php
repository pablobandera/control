<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Chofer;
use App\Models\Gasto;
use App\Models\Turno;

final class GastoController
{
    public function crear(): void
    {
        Auth::requireRole('chofer');
        $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
        $data = Request::json();
        Validator::requireFields($data, ['concepto', 'monto']);

        $turno = (new Turno())->activoDeChofer((int) $chofer['id']);
        if ($turno === null) {
            Response::error('No tenés un turno activo.', 409);
        }

        $monto = Validator::positiveNumber($data['monto'], 'El monto');
        $categoria = ($data['categoria'] ?? 'otro') === 'combustible' ? 'combustible' : 'otro';

        $id = (new Gasto())->insert([
            'turno_id' => $turno['id'],
            'concepto' => trim((string) $data['concepto']),
            'categoria' => $categoria,
            'monto' => $monto,
            'hora' => date('Y-m-d H:i:s'),
        ]);

        Response::json(['gasto' => (new Gasto())->find($id)], 201);
    }

    public function actualizar(string $id): void
    {
        Auth::requireRole('chofer');
        $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
        $gastoModel = new Gasto();
        $gasto = $gastoModel->conTurno((int) $id);
        if ($gasto === null || (int) $gasto['chofer_id'] !== (int) $chofer['id']) {
            Response::error('Gasto no encontrado.', 404);
        }
        if ($gasto['turno_estado'] !== 'activo') {
            Response::error('No se puede editar un gasto de un turno ya cerrado.', 409);
        }

        $data = Request::json();
        $update = [];
        if (isset($data['concepto'])) {
            $update['concepto'] = trim((string) $data['concepto']);
        }
        if (isset($data['monto'])) {
            $update['monto'] = Validator::positiveNumber($data['monto'], 'El monto');
        }
        if (isset($data['categoria'])) {
            $update['categoria'] = $data['categoria'] === 'combustible' ? 'combustible' : 'otro';
        }

        $gastoModel->update((int) $id, $update);
        Response::json(['gasto' => $gastoModel->find((int) $id)]);
    }
}
