<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Comprobante;
use App\Models\Service;
use App\Models\Turno;
use App\Services\LiquidacionService;
use App\Services\ReporteService;

final class ReporteController
{
    private function periodo(): string
    {
        $periodo = (string) Request::query('periodo', 'dia');
        return in_array($periodo, ['dia', 'semana', 'mes'], true) ? $periodo : 'dia';
    }

    private function servicio(): ReporteService
    {
        $db = Database::connection();
        return new ReporteService($db, new LiquidacionService($db));
    }

    public function kpis(): void
    {
        Auth::requireRole('dueno');
        $data = $this->servicio()->kpis((int) Auth::empresaId(), $this->periodo(), Request::query('fecha'));
        Response::json($data);
    }

    public function serie(): void
    {
        Auth::requireRole('dueno');
        $data = $this->servicio()->serieTemporal((int) Auth::empresaId(), $this->periodo());
        Response::json($data);
    }

    public function comisiones(): void
    {
        Auth::requireRole('dueno');
        $servicio = $this->servicio();
        [$desde, $hasta] = $servicio->rangoDeFechas($this->periodo(), Request::query('fecha'));
        $ranking = $servicio->rankingChoferes((int) Auth::empresaId(), $desde, $hasta);
        usort($ranking, static fn (array $a, array $b): int => $b['comision_chofer'] <=> $a['comision_chofer']);
        Response::json(['comisiones' => $ranking]);
    }

    public function services(): void
    {
        Auth::requireRole('dueno');
        Response::json(['services_por_movil' => (new Service())->allDeEmpresaAgrupadoPorMovil((int) Auth::empresaId())]);
    }

    public function comprobantes(): void
    {
        Auth::requireRole('dueno');
        $turnoId = (int) Request::query('turno_id', 0);
        if ($turnoId <= 0) {
            Response::error('Falta indicar turno_id.', 422);
        }

        $turno = (new Turno())->conMovil($turnoId);
        if ($turno === null || (int) $turno['empresa_id'] !== (int) Auth::empresaId()) {
            Response::error('Turno no encontrado.', 404);
        }

        Response::json(['comprobantes' => (new Comprobante())->allDeTurno($turnoId)]);
    }

    public function exportar(): void
    {
        Auth::requireRole('dueno');
        $servicio = $this->servicio();
        [$desde, $hasta] = $servicio->rangoDeFechas($this->periodo(), Request::query('fecha'));
        $ranking = $servicio->rankingChoferes((int) Auth::empresaId(), $desde, $hasta);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_' . $this->periodo() . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Chofer', 'Total bruto', 'Comision', 'A rendir', 'Viajes', 'Gastos', 'Cta cte pendiente']);
        foreach ($ranking as $fila) {
            fputcsv($out, [
                $fila['nombre'],
                $fila['total_bruto'],
                $fila['comision_chofer'],
                $fila['a_rendir'],
                $fila['viajes_count'],
                $fila['gastos_total'],
                $fila['cc_pendiente'],
            ]);
        }
        fclose($out);
        exit;
    }
}
