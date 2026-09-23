<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Chofer;
use App\Models\Empresa;
use App\Models\Movil;

final class EmpresaController
{
    public function ver(): void
    {
        Auth::requireRole('dueno');
        $empresaId = (int) Auth::empresaId();
        $empresa = (new Empresa())->find($empresaId);
        if ($empresa === null) {
            Response::error('Empresa no encontrada.', 404);
        }
        $empresa['choferes_count'] = count((new Chofer())->allDeEmpresa($empresaId));
        $empresa['moviles_count'] = count((new Movil())->allDeEmpresa($empresaId));
        Response::json(['empresa' => $empresa]);
    }

    public function actualizar(): void
    {
        Auth::requireRole('dueno');
        $data = Request::json();
        $update = [];
        foreach (['nombre', 'cuit', 'ciudad', 'telefono'] as $campo) {
            if (isset($data[$campo])) {
                $update[$campo] = trim((string) $data[$campo]);
            }
        }
        if (isset($data['comision_chofer_pct'])) {
            $pct = (float) $data['comision_chofer_pct'];
            if ($pct < 0 || $pct > 100) {
                Response::error('El porcentaje de comisión debe estar entre 0 y 100.', 422);
            }
            $update['comision_chofer_pct'] = $pct;
        }

        $empresaId = (int) Auth::empresaId();
        (new Empresa())->update($empresaId, $update);
        Response::json(['empresa' => (new Empresa())->find($empresaId)]);
    }
}
