<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Calcula la liquidación de uno o varios turnos por agregación SQL directa
 * (SUM sobre viajes/gastos/cuentas corrientes), en vez de acumuladores que se
 * van sumando en el cliente: así el total nunca puede desincronizarse de las
 * filas reales, sea para el ticket de un turno o para el reporte de un período.
 */
final class LiquidacionService
{
    public function __construct(private PDO $db)
    {
    }

    public function comisionPctDeChofer(array $chofer, array $empresa): float
    {
        if (($chofer['comision_pct'] ?? null) !== null) {
            return (float) $chofer['comision_pct'];
        }
        return (float) ($empresa['comision_chofer_pct'] ?? 35);
    }

    /**
     * @param int[] $turnoIds
     */
    public function calcularParaTurnos(array $turnoIds, float $comisionPct): array
    {
        if ($turnoIds === []) {
            return $this->estructuraVacia($comisionPct);
        }

        $placeholders = implode(',', array_fill(0, count($turnoIds), '?'));

        $stmt = $this->db->prepare(
            "SELECT tv.nombre AS tipo, mp.nombre AS medio,
                    COALESCE(SUM(v.monto - v.descuento), 0) AS neto,
                    COALESCE(SUM(v.descuento), 0) AS descuentos,
                    COUNT(*) AS cantidad
             FROM viajes v
             JOIN tipos_viaje tv ON tv.id = v.tipo_viaje_id
             JOIN medios_pago mp ON mp.id = v.medio_pago_id
             WHERE v.turno_id IN ({$placeholders})
             GROUP BY tv.nombre, mp.nombre"
        );
        $stmt->execute($turnoIds);

        $taxiEfvo = $taxiTransf = $uberEfvo = $uberTransf = 0.0;
        $descuentoViajes = 0.0;
        $viajesCount = 0;
        foreach ($stmt->fetchAll() as $fila) {
            $neto = (float) $fila['neto'];
            $descuentoViajes += (float) $fila['descuentos'];
            $viajesCount += (int) $fila['cantidad'];
            if ($fila['tipo'] === 'Taxi' && $fila['medio'] === 'Efectivo') {
                $taxiEfvo += $neto;
            } elseif ($fila['tipo'] === 'Taxi' && $fila['medio'] === 'Transferencia') {
                $taxiTransf += $neto;
            } elseif ($fila['tipo'] === 'Uber' && $fila['medio'] === 'Efectivo') {
                $uberEfvo += $neto;
            } elseif ($fila['tipo'] === 'Uber' && $fila['medio'] === 'Transferencia') {
                $uberTransf += $neto;
            }
        }

        $stmtCc = $this->db->prepare(
            "SELECT COALESCE(SUM(monto - descuento), 0) AS neto,
                    COALESCE(SUM(descuento), 0) AS descuentos,
                    COALESCE(SUM(CASE WHEN cobrado = 0 THEN monto - descuento ELSE 0 END), 0) AS pendiente,
                    COALESCE(SUM(CASE WHEN cobrado = 1 THEN monto - descuento ELSE 0 END), 0) AS cobrado,
                    COUNT(*) AS cantidad
             FROM cuentas_corrientes WHERE turno_id IN ({$placeholders})"
        );
        $stmtCc->execute($turnoIds);
        $cc = $stmtCc->fetch();

        $stmtGastos = $this->db->prepare(
            "SELECT COALESCE(SUM(monto), 0) AS total,
                    COALESCE(SUM(CASE WHEN categoria = 'combustible' THEN monto ELSE 0 END), 0) AS combustible,
                    COUNT(*) AS cantidad
             FROM gastos WHERE turno_id IN ({$placeholders})"
        );
        $stmtGastos->execute($turnoIds);
        $gastos = $stmtGastos->fetch();

        $ccNeto = (float) $cc['neto'];
        $ccDescuento = (float) $cc['descuentos'];
        $gastosTotal = (float) $gastos['total'];
        $gastosCombustible = (float) $gastos['combustible'];

        $ingresoEfvo = $taxiEfvo + $uberEfvo;
        $ingresoTransf = $taxiTransf + $uberTransf;
        // La cuenta corriente entra en la base de la comisión aunque todavía no esté
        // cobrada: el chofer ya "vendió" ese viaje, es la misma regla que tenía el prototipo.
        $totalBruto = $ingresoEfvo + $ingresoTransf + $ccNeto;
        $comision = round($totalBruto * ($comisionPct / 100), 2);
        $aRendir = round($totalBruto - $comision - $gastosTotal, 2);

        return [
            'taxi_efvo' => round($taxiEfvo, 2),
            'taxi_transf' => round($taxiTransf, 2),
            'taxi_total' => round($taxiEfvo + $taxiTransf, 2),
            'uber_efvo' => round($uberEfvo, 2),
            'uber_transf' => round($uberTransf, 2),
            'uber_total' => round($uberEfvo + $uberTransf, 2),
            'ingreso_efvo' => round($ingresoEfvo, 2),
            'ingreso_transf' => round($ingresoTransf, 2),
            'viajes_count' => $viajesCount,
            'cc_total' => round($ccNeto, 2),
            'cc_pendiente' => round((float) $cc['pendiente'], 2),
            'cc_cobrado' => round((float) $cc['cobrado'], 2),
            'cc_count' => (int) $cc['cantidad'],
            'gastos_total' => round($gastosTotal, 2),
            'gastos_combustible' => round($gastosCombustible, 2),
            'gastos_count' => (int) $gastos['cantidad'],
            'descuentos_total' => round($descuentoViajes + $ccDescuento, 2),
            'total_bruto' => round($totalBruto, 2),
            'comision_pct' => $comisionPct,
            'comision_chofer' => $comision,
            'a_rendir' => $aRendir,
        ];
    }

    public function calcularParaTurno(int $turnoId, float $comisionPct): array
    {
        return $this->calcularParaTurnos([$turnoId], $comisionPct);
    }

    private function estructuraVacia(float $comisionPct): array
    {
        return [
            'taxi_efvo' => 0.0, 'taxi_transf' => 0.0, 'taxi_total' => 0.0,
            'uber_efvo' => 0.0, 'uber_transf' => 0.0, 'uber_total' => 0.0,
            'ingreso_efvo' => 0.0, 'ingreso_transf' => 0.0, 'viajes_count' => 0,
            'cc_total' => 0.0, 'cc_pendiente' => 0.0, 'cc_cobrado' => 0.0, 'cc_count' => 0,
            'gastos_total' => 0.0, 'gastos_combustible' => 0.0, 'gastos_count' => 0,
            'descuentos_total' => 0.0, 'total_bruto' => 0.0,
            'comision_pct' => $comisionPct, 'comision_chofer' => 0.0, 'a_rendir' => 0.0,
        ];
    }
}
