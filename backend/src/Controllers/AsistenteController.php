<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Core\Response;
use App\Services\InformeService;
use App\Services\LiquidacionService;
use App\Services\ReporteService;

final class AsistenteController
{
    public function informe(): void
    {
        Auth::requireRole('dueno');
        $db = Database::connection();
        $reportes = new ReporteService($db, new LiquidacionService($db));
        $informe = new InformeService($reportes);
        Response::json($informe->generar((int) Auth::empresaId()));
    }
}
