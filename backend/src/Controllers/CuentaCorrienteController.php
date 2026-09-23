<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Chofer;
use App\Models\CuentaCorriente;
use App\Models\Turno;

final class CuentaCorrienteController
{
    public function crear(): void
    {
        Auth::requireRole('chofer');
        $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
        $data = Request::json();
        Validator::requireFields($data, ['cliente_nombre', 'monto']);

        $turno = (new Turno())->activoDeChofer((int) $chofer['id']);
        if ($turno === null) {
            Response::error('No tenés un turno activo.', 409);
        }

        $monto = Validator::positiveNumber($data['monto'], 'El monto');
        $descuento = Validator::nonNegativeNumber($data['descuento'] ?? 0, 'El descuento');
        if ($descuento > $monto) {
            $descuento = $monto;
        }

        $id = (new CuentaCorriente())->insert([
            'turno_id' => $turno['id'],
            'cliente_nombre' => trim((string) $data['cliente_nombre']),
            'monto' => $monto,
            'descuento' => $descuento,
            'hora' => date('Y-m-d H:i:s'),
        ]);

        Response::json(['cuenta_corriente' => (new CuentaCorriente())->find($id)], 201);
    }

    public function actualizar(string $id): void
    {
        Auth::requireRole('chofer');
        $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
        $ccModel = new CuentaCorriente();
        $cc = $ccModel->conTurno((int) $id);
        if ($cc === null || (int) $cc['chofer_id'] !== (int) $chofer['id']) {
            Response::error('Registro no encontrado.', 404);
        }
        if ($cc['turno_estado'] !== 'activo') {
            Response::error('No se puede editar un registro de un turno ya cerrado.', 409);
        }

        $data = Request::json();
        $update = [];
        if (isset($data['cliente_nombre'])) {
            $update['cliente_nombre'] = trim((string) $data['cliente_nombre']);
        }
        if (isset($data['monto'])) {
            $update['monto'] = Validator::positiveNumber($data['monto'], 'El monto');
        }
        if (isset($data['descuento'])) {
            $update['descuento'] = Validator::nonNegativeNumber($data['descuento'], 'El descuento');
        }

        $montoFinal = $update['monto'] ?? (float) $cc['monto'];
        $descuentoFinal = $update['descuento'] ?? (float) $cc['descuento'];
        if ($descuentoFinal > $montoFinal) {
            $update['descuento'] = $montoFinal;
        }

        $ccModel->update((int) $id, $update);
        Response::json(['cuenta_corriente' => $ccModel->find((int) $id)]);
    }

    public function cobrar(string $id): void
    {
        Auth::requireAuth();
        $ccModel = new CuentaCorriente();
        $cc = $ccModel->conTurno((int) $id);
        if ($cc === null) {
            Response::error('Registro no encontrado.', 404);
        }

        if (Auth::role() === 'chofer') {
            $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
            if ((int) $cc['chofer_id'] !== (int) $chofer['id']) {
                Response::error('No autorizado.', 403);
            }
        } elseif ((int) $cc['empresa_id'] !== (int) Auth::empresaId()) {
            Response::error('No autorizado.', 403);
        }

        $ccModel->marcarCobrado((int) $id);
        Response::json(['cuenta_corriente' => $ccModel->find((int) $id)]);
    }
}
