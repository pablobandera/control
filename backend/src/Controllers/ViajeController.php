<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Chofer;
use App\Models\MedioPago;
use App\Models\TipoViaje;
use App\Models\Turno;
use App\Models\Viaje;

final class ViajeController
{
    public function crear(): void
    {
        Auth::requireRole('chofer');
        $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
        $data = Request::json();
        Validator::requireFields($data, ['tipo', 'medio', 'monto']);

        $turno = (new Turno())->activoDeChofer((int) $chofer['id']);
        if ($turno === null) {
            Response::error('No tenés un turno activo.', 409);
        }

        $tipo = (new TipoViaje())->findByNombre((string) $data['tipo']);
        $medio = (new MedioPago())->findByNombre((string) $data['medio']);
        if ($tipo === null || $medio === null) {
            Response::error('Tipo de viaje o medio de pago inválido.', 422);
        }

        $monto = Validator::positiveNumber($data['monto'], 'El monto');
        $descuento = Validator::nonNegativeNumber($data['descuento'] ?? 0, 'El descuento');
        if ($descuento > $monto) {
            $descuento = $monto;
        }

        $id = (new Viaje())->insert([
            'turno_id' => $turno['id'],
            'tipo_viaje_id' => $tipo['id'],
            'medio_pago_id' => $medio['id'],
            'monto' => $monto,
            'descuento' => $descuento,
            'comentario' => isset($data['comentario']) && $data['comentario'] !== '' ? (string) $data['comentario'] : null,
            'hora' => date('Y-m-d H:i:s'),
        ]);

        Response::json(['viaje' => (new Viaje())->conTurno($id)], 201);
    }

    public function actualizar(string $id): void
    {
        Auth::requireRole('chofer');
        $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
        $viajeModel = new Viaje();
        $viaje = $viajeModel->conTurno((int) $id);
        if ($viaje === null || (int) $viaje['chofer_id'] !== (int) $chofer['id']) {
            Response::error('Viaje no encontrado.', 404);
        }
        if ($viaje['turno_estado'] !== 'activo') {
            Response::error('No se puede editar un viaje de un turno ya cerrado.', 409);
        }

        $data = Request::json();
        $update = [];
        if (isset($data['monto'])) {
            $update['monto'] = Validator::positiveNumber($data['monto'], 'El monto');
        }
        if (isset($data['descuento'])) {
            $update['descuento'] = Validator::nonNegativeNumber($data['descuento'], 'El descuento');
        }
        if (array_key_exists('comentario', $data)) {
            $update['comentario'] = $data['comentario'] !== '' ? (string) $data['comentario'] : null;
        }

        $montoFinal = $update['monto'] ?? (float) $viaje['monto'];
        $descuentoFinal = $update['descuento'] ?? (float) $viaje['descuento'];
        if ($descuentoFinal > $montoFinal) {
            $update['descuento'] = $montoFinal;
        }

        $viajeModel->update((int) $id, $update);
        Response::json(['viaje' => $viajeModel->conTurno((int) $id)]);
    }
}
