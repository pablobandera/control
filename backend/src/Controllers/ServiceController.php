<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Chofer;
use App\Models\Movil;
use App\Models\Service;
use App\Models\Turno;

final class ServiceController
{
    public function crear(): void
    {
        Auth::requireRole('chofer');
        $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
        $data = Request::json();
        Validator::requireFields($data, ['descripcion']);

        $turno = (new Turno())->activoDeChofer((int) $chofer['id']);
        if ($turno === null) {
            Response::error('Necesitás un turno activo para registrar un service.', 409);
        }

        $id = (new Service())->insert([
            'movil_id' => $turno['movil_id'],
            'chofer_id' => $chofer['id'],
            'turno_id' => $turno['id'],
            'descripcion' => trim((string) $data['descripcion']),
            'fecha' => date('Y-m-d H:i:s'),
        ]);

        Response::json(['service' => (new Service())->find($id)], 201);
    }

    public function listar(): void
    {
        Auth::requireAuth();
        $movilId = Request::query('movil_id');

        if ($movilId !== null) {
            $movil = (new Movil())->find((int) $movilId);
            if ($movil === null || (int) $movil['empresa_id'] !== (int) Auth::empresaId()) {
                Response::error('Móvil no encontrado.', 404);
            }
            Response::json(['services' => (new Service())->allDeMovil((int) $movilId)]);
        }

        if (Auth::role() === 'chofer') {
            $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
            $turno = (new Turno())->activoDeChofer((int) $chofer['id']);
            if ($turno === null) {
                Response::json(['services' => []]);
            }
            Response::json(['services' => (new Service())->allDeMovil((int) $turno['movil_id'])]);
        }

        Response::error('Falta indicar movil_id.', 422);
    }
}
