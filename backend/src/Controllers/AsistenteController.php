<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\ChatService;
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

    public function chat(): void
    {
        Auth::requireRole('dueno');
        $data = Request::json();
        Validator::requireFields($data, ['pregunta']);
        $pregunta = trim((string) $data['pregunta']);
        if ($pregunta === '') {
            Response::error('Escribí una pregunta.', 422);
        }

        $db = Database::connection();
        $chat = new ChatService(new ReporteService($db, new LiquidacionService($db)));
        Response::json(['respuesta' => $chat->responder($pregunta, (int) Auth::empresaId())]);
    }
}
