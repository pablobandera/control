<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chofer;
use App\Models\Empresa;
use App\Models\Turno;
use DateTimeImmutable;
use PDO;

final class ReporteService
{
    public function __construct(
        private PDO $db,
        private LiquidacionService $liquidacion,
    ) {
    }

    /**
     * @return array{0: string, 1: string} [desde, hasta) en formato 'Y-m-d H:i:s'
     */
    public function rangoDeFechas(string $periodo, ?string $fechaRef = null): array
    {
        $ref = $fechaRef !== null && $fechaRef !== '' ? new DateTimeImmutable($fechaRef) : new DateTimeImmutable('now');

        $desde = match ($periodo) {
            'semana' => $ref->modify('monday this week')->setTime(0, 0),
            'mes' => $ref->modify('first day of this month')->setTime(0, 0),
            default => $ref->setTime(0, 0),
        };
        $hasta = match ($periodo) {
            'semana' => $desde->modify('+7 days'),
            'mes' => $desde->modify('+1 month'),
            default => $desde->modify('+1 day'),
        };

        return [$desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')];
    }

    public function kpis(int $empresaId, string $periodo, ?string $fechaRef = null): array
    {
        [$desde, $hasta] = $this->rangoDeFechas($periodo, $fechaRef);
        $empresa = (new Empresa())->primera();
        $turnoModel = new Turno();
        $turnoIds = $turnoModel->idsDeEmpresaEnRango($empresaId, $desde, $hasta);
        $totales = $this->liquidacion->calcularParaTurnos($turnoIds, (float) $empresa['comision_chofer_pct']);

        $facturacion = $totales['total_bruto'];
        $totales['combustible_pct'] = $facturacion > 0 ? round($totales['gastos_combustible'] / $facturacion * 100, 1) : 0.0;
        $totales['descuentos_pct'] = $facturacion > 0 ? round($totales['descuentos_total'] / $facturacion * 100, 1) : 0.0;
        $totales['cc_pct'] = $facturacion > 0 ? round($totales['cc_total'] / $facturacion * 100, 1) : 0.0;
        $totales['transferencia_pct'] = $facturacion > 0 ? round($totales['ingreso_transf'] / $facturacion * 100, 1) : 0.0;

        $choferesActivos = $turnoModel->activosDeEmpresa($empresaId);
        $choferModel = new Chofer();

        $totales['choferes_activos'] = count($choferesActivos);
        $totales['choferes_total'] = count($choferModel->allDeEmpresa($empresaId));
        $totales['ranking_choferes'] = $this->rankingChoferes($empresaId, $desde, $hasta, $empresa);
        $totales['periodo'] = $periodo;
        $totales['desde'] = $desde;
        $totales['hasta'] = $hasta;

        return $totales;
    }

    public function rankingChoferes(int $empresaId, string $desde, string $hasta, ?array $empresa = null): array
    {
        $empresa ??= (new Empresa())->primera();
        $choferModel = new Chofer();
        $turnoModel = new Turno();

        $ranking = [];
        foreach ($choferModel->allDeEmpresa($empresaId) as $chofer) {
            $turnoIds = $turnoModel->deChoferEnRango((int) $chofer['id'], $desde, $hasta);
            if ($turnoIds === []) {
                continue;
            }
            $pct = $this->liquidacion->comisionPctDeChofer($chofer, $empresa);
            $calc = $this->liquidacion->calcularParaTurnos($turnoIds, $pct);
            $ranking[] = [
                'chofer_id' => (int) $chofer['id'],
                'nombre' => $chofer['nombre'],
                'total_bruto' => $calc['total_bruto'],
                'comision_chofer' => $calc['comision_chofer'],
                'a_rendir' => $calc['a_rendir'],
                'viajes_count' => $calc['viajes_count'],
                'gastos_total' => $calc['gastos_total'],
                'cc_pendiente' => $calc['cc_pendiente'],
            ];
        }

        usort($ranking, static fn (array $a, array $b): int => $b['total_bruto'] <=> $a['total_bruto']);
        return $ranking;
    }
}
