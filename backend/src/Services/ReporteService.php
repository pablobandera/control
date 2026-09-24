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

    /**
     * Serie temporal real para los gráficos de Reportes: agrupa viajes, gastos
     * y cuenta corriente por franja horaria (día), día de la semana (semana) o
     * semana del mes (mes). Todo calculado a partir de filas reales, no
     * aproximado.
     */
    public function serieTemporal(int $empresaId, string $periodo): array
    {
        [$desde, $hasta] = $this->rangoDeFechas($periodo);
        $turnoIds = (new Turno())->idsDeEmpresaEnRango($empresaId, $desde, $hasta);

        [$labels, $bucketCount, $bucketFn] = $this->definicionBuckets($periodo);
        $vacio = ['total_bruto' => 0.0, 'efvo' => 0.0, 'transf' => 0.0, 'combustible' => 0.0, 'gastos' => 0.0, 'cc_total' => 0.0, 'descuentos' => 0.0];
        $buckets = array_fill(0, $bucketCount, $vacio);

        $serviceCount = 0;
        $comprobantesCount = 0;

        if ($turnoIds !== []) {
            $placeholders = implode(',', array_fill(0, count($turnoIds), '?'));

            $stmtViajes = $this->db->prepare(
                "SELECT v.monto, v.descuento, v.hora, mp.nombre AS medio
                 FROM viajes v JOIN medios_pago mp ON mp.id = v.medio_pago_id
                 WHERE v.turno_id IN ({$placeholders})"
            );
            $stmtViajes->execute($turnoIds);
            foreach ($stmtViajes->fetchAll() as $row) {
                $idx = $bucketFn(new DateTimeImmutable($row['hora']));
                $neto = (float) $row['monto'] - (float) $row['descuento'];
                $buckets[$idx]['total_bruto'] += $neto;
                $buckets[$idx]['descuentos'] += (float) $row['descuento'];
                $buckets[$idx][$row['medio'] === 'Efectivo' ? 'efvo' : 'transf'] += $neto;
            }

            $stmtGastos = $this->db->prepare("SELECT monto, categoria, hora FROM gastos WHERE turno_id IN ({$placeholders})");
            $stmtGastos->execute($turnoIds);
            foreach ($stmtGastos->fetchAll() as $row) {
                $idx = $bucketFn(new DateTimeImmutable($row['hora']));
                $buckets[$idx]['gastos'] += (float) $row['monto'];
                if ($row['categoria'] === 'combustible') {
                    $buckets[$idx]['combustible'] += (float) $row['monto'];
                }
            }

            $stmtCc = $this->db->prepare("SELECT monto, descuento, hora FROM cuentas_corrientes WHERE turno_id IN ({$placeholders})");
            $stmtCc->execute($turnoIds);
            foreach ($stmtCc->fetchAll() as $row) {
                $idx = $bucketFn(new DateTimeImmutable($row['hora']));
                $neto = (float) $row['monto'] - (float) $row['descuento'];
                $buckets[$idx]['total_bruto'] += $neto;
                $buckets[$idx]['cc_total'] += $neto;
                $buckets[$idx]['descuentos'] += (float) $row['descuento'];
            }

            $stmtComprobantes = $this->db->prepare("SELECT COUNT(*) FROM comprobantes WHERE turno_id IN ({$placeholders})");
            $stmtComprobantes->execute($turnoIds);
            $comprobantesCount = (int) $stmtComprobantes->fetchColumn();
        }

        $stmtServices = $this->db->prepare(
            'SELECT COUNT(*) FROM services s JOIN moviles m ON m.id = s.movil_id WHERE m.empresa_id = :eid AND s.fecha >= :desde AND s.fecha < :hasta'
        );
        $stmtServices->execute(['eid' => $empresaId, 'desde' => $desde, 'hasta' => $hasta]);
        $serviceCount = (int) $stmtServices->fetchColumn();

        $comisionPct = (float) (new Empresa())->primera()['comision_chofer_pct'];
        $totalBrutoPeriodo = 0.0;
        $resultado = [];
        foreach ($labels as $i => $label) {
            $b = $buckets[$i];
            $b['comision'] = round($b['total_bruto'] * ($comisionPct / 100), 2);
            $totalBrutoPeriodo += $b['total_bruto'];
            $resultado[] = ['label' => $label, ...array_map(static fn ($v) => round($v, 2), $b)];
        }

        return [
            'periodo' => $periodo,
            'titulo_total' => match ($periodo) {
                'dia' => 'Total día',
                'semana' => 'Total semana',
                default => 'Total mes',
            },
            'buckets' => $resultado,
            'promedio' => count($resultado) > 0 ? round($totalBrutoPeriodo / count($resultado), 2) : 0.0,
            'service_count' => $serviceCount,
            'comprobantes_count' => $comprobantesCount,
        ];
    }

    /**
     * @return array{0: string[], 1: int, 2: callable(DateTimeImmutable): int}
     */
    private function definicionBuckets(string $periodo): array
    {
        if ($periodo === 'dia') {
            return [
                ['00-04', '04-08', '08-12', '12-16', '16-20', '20-24'],
                6,
                static fn (DateTimeImmutable $d): int => intdiv((int) $d->format('G'), 4),
            ];
        }
        if ($periodo === 'mes') {
            return [
                ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
                4,
                static fn (DateTimeImmutable $d): int => min(intdiv(((int) $d->format('j')) - 1, 7), 3),
            ];
        }
        return [
            ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            7,
            static fn (DateTimeImmutable $d): int => ((int) $d->format('N')) - 1,
        ];
    }
}
