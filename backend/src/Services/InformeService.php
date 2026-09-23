<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Genera el "informe" del dueño (pestaña Asistente) con reglas simples sobre
 * datos reales del día — no hay IA ni chat: el prototipo tampoco lo tenía
 * conectado a ninguna UI, solo mostraba 3 tarjetas estáticas.
 */
final class InformeService
{
    public function __construct(private ReporteService $reportes)
    {
    }

    public function generar(int $empresaId): array
    {
        $hoy = $this->reportes->kpis($empresaId, 'dia');

        $bien = [];
        $atencion = [];
        $consejos = [];

        if ($hoy['viajes_count'] > 0) {
            $bien[] = sprintf(
                'Hoy se cargaron %d viajes por un total de $%s.',
                $hoy['viajes_count'],
                number_format($hoy['total_bruto'], 0, ',', '.')
            );
        }

        if (!empty($hoy['ranking_choferes'])) {
            $top = $hoy['ranking_choferes'][0];
            $bien[] = sprintf(
                '%s lidera la facturación de hoy con $%s.',
                $top['nombre'],
                number_format($top['total_bruto'], 0, ',', '.')
            );
        }

        if ($hoy['descuentos_pct'] > 15) {
            $atencion[] = sprintf(
                'Los descuentos representan un %.1f%% de la facturación de hoy, es un porcentaje alto.',
                $hoy['descuentos_pct']
            );
        }

        if ($hoy['cc_pendiente'] > 0) {
            $atencion[] = sprintf(
                'Hay $%s pendientes de cobro en cuenta corriente.',
                number_format($hoy['cc_pendiente'], 0, ',', '.')
            );
        }

        if ($hoy['choferes_activos'] === 0) {
            $atencion[] = 'Ningún chofer tiene un turno activo en este momento.';
        }

        if ($hoy['gastos_combustible'] > 0 && $hoy['total_bruto'] > 0) {
            $consejos[] = sprintf('El combustible representa un %.1f%% de la facturación de hoy.', $hoy['combustible_pct']);
        }

        if ($bien === []) {
            $bien[] = 'Todavía no hay actividad registrada hoy.';
        }
        if ($atencion === []) {
            $atencion[] = 'No se detectaron alertas por ahora.';
        }
        if ($consejos === []) {
            $consejos[] = 'Cargá más datos durante el día para ver consejos personalizados acá.';
        }

        return ['bien' => $bien, 'atencion' => $atencion, 'consejos' => $consejos];
    }
}
