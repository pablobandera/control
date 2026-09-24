<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chofer;

/**
 * Responde preguntas del dueño en lenguaje simple contra datos REALES de la
 * empresa (no es un LLM: son reglas por palabras clave, igual de espíritu al
 * "asistente" del prototipo original, pero sin Math.random() de por medio).
 */
final class ChatService
{
    private const METRICAS = [
        'combustible' => ['combustible', 'nafta', 'gasolina'],
        'descuentos' => ['descuento', 'descuentos'],
        'gastos' => ['gasto', 'gastos'],
        'cuenta_corriente' => ['cuenta corriente', 'cta cte', 'cta. cte', 'pendiente', 'deuda', 'deudores'],
        'comision' => ['comision', 'comisiones'],
        'viajes' => ['viaje', 'viajes'],
        'facturacion' => ['facturacion', 'recaudacion', 'total', 'bruto'],
    ];

    public function __construct(private ReporteService $reportes)
    {
    }

    public function responder(string $pregunta, int $empresaId): string
    {
        $normalizada = $this->normalizar($pregunta);

        $periodo = 'dia';
        if (str_contains($normalizada, 'semana')) {
            $periodo = 'semana';
        } elseif (str_contains($normalizada, 'mes')) {
            $periodo = 'mes';
        }

        $kpis = $this->reportes->kpis($empresaId, $periodo);
        $tituloPeriodo = $periodo === 'dia' ? 'hoy' : ($periodo === 'semana' ? 'esta semana' : 'este mes');

        $metrica = $this->detectarMetrica($normalizada);
        $chofer = $this->detectarChofer($normalizada, $empresaId);

        if ($chofer !== null) {
            return $this->respuestaSobreChofer($chofer, $metrica, $kpis, $tituloPeriodo);
        }

        if ($this->esPreguntaRanking($normalizada)) {
            return $this->respuestaRanking($metrica, $normalizada, $kpis, $tituloPeriodo);
        }

        if ($metrica !== null) {
            return $this->respuestaMetricaFlota($metrica, $kpis, $tituloPeriodo);
        }

        return sprintf(
            'Facturación %s: %s (%d viajes). Comisiones: %s. A rendir: %s. Choferes activos: %d de %d. ¿Querés que profundice en algún chofer, combustible, descuentos o cuenta corriente?',
            $tituloPeriodo,
            $this->money($kpis['total_bruto']),
            $kpis['viajes_count'],
            $this->money($kpis['comision_chofer']),
            $this->money($kpis['a_rendir']),
            $kpis['choferes_activos'],
            $kpis['choferes_total']
        );
    }

    private function respuestaSobreChofer(array $chofer, ?string $metrica, array $kpis, string $tituloPeriodo): string
    {
        $fila = null;
        foreach ($kpis['ranking_choferes'] as $r) {
            if ((int) $r['chofer_id'] === (int) $chofer['id']) {
                $fila = $r;
                break;
            }
        }
        if ($fila === null) {
            return "{$chofer['nombre']} no tiene actividad cargada {$tituloPeriodo}.";
        }

        return match ($metrica) {
            'comision' => sprintf('%s se llevó %s de comisión %s (%d viajes).', $chofer['nombre'], $this->money($fila['comision_chofer']), $tituloPeriodo, $fila['viajes_count']),
            'gastos' => sprintf('%s gastó %s %s.', $chofer['nombre'], $this->money($fila['gastos_total']), $tituloPeriodo),
            'cuenta_corriente' => sprintf('%s tiene %s pendientes de cobro en cuenta corriente %s.', $chofer['nombre'], $this->money($fila['cc_pendiente']), $tituloPeriodo),
            'viajes' => sprintf('%s cargó %d viajes %s, por un total de %s.', $chofer['nombre'], $fila['viajes_count'], $tituloPeriodo, $this->money($fila['total_bruto'])),
            default => sprintf(
                '%s facturó %s %s, se lleva %s de comisión y le queda %s por rendir.',
                $chofer['nombre'],
                $this->money($fila['total_bruto']),
                $tituloPeriodo,
                $this->money($fila['comision_chofer']),
                $this->money($fila['a_rendir'])
            ),
        };
    }

    private function respuestaRanking(?string $metrica, string $normalizada, array $kpis, string $tituloPeriodo): string
    {
        $campo = match ($metrica) {
            'gastos' => 'gastos_total',
            'comision' => 'comision_chofer',
            'cuenta_corriente' => 'cc_pendiente',
            'viajes' => 'viajes_count',
            default => 'total_bruto',
        };

        $ranking = $kpis['ranking_choferes'];
        if ($ranking === []) {
            return "Todavía no hay datos de choferes {$tituloPeriodo}.";
        }

        $quiereMenos = str_contains($normalizada, 'menos') || str_contains($normalizada, 'menor');
        usort($ranking, static fn (array $a, array $b): int => $quiereMenos ? $a[$campo] <=> $b[$campo] : $b[$campo] <=> $a[$campo]);
        $top = $ranking[0];
        $valor = $campo === 'viajes_count' ? (string) $top['viajes_count'] : $this->money((float) $top[$campo]);

        return sprintf('%s es quien %s %s %s, con %s.', $top['nombre'], $this->etiquetaMetrica($campo), $quiereMenos ? 'menos' : 'más', $tituloPeriodo, $valor);
    }

    private function respuestaMetricaFlota(string $metrica, array $kpis, string $tituloPeriodo): string
    {
        return match ($metrica) {
            'combustible' => sprintf('El combustible representa %s %s (%.1f%% de la facturación).', $this->money($kpis['gastos_combustible']), $tituloPeriodo, $kpis['combustible_pct']),
            'descuentos' => sprintf('Los descuentos suman %s %s (%.1f%% de la facturación).', $this->money($kpis['descuentos_total']), $tituloPeriodo, $kpis['descuentos_pct']),
            'gastos' => sprintf('Los gastos de la flota suman %s %s.', $this->money($kpis['gastos_total']), $tituloPeriodo),
            'cuenta_corriente' => sprintf('Hay %s pendientes de cobro en cuenta corriente %s (de %s cargado en total).', $this->money($kpis['cc_pendiente']), $tituloPeriodo, $this->money($kpis['cc_total'])),
            'comision' => sprintf('Las comisiones de los choferes suman %s %s.', $this->money($kpis['comision_chofer']), $tituloPeriodo),
            'viajes' => sprintf('Se cargaron %d viajes %s.', $kpis['viajes_count'], $tituloPeriodo),
            default => sprintf('La facturación total %s es %s.', $tituloPeriodo, $this->money($kpis['total_bruto'])),
        };
    }

    private function detectarMetrica(string $normalizada): ?string
    {
        foreach (self::METRICAS as $clave => $palabras) {
            foreach ($palabras as $palabra) {
                if (str_contains($normalizada, $palabra)) {
                    return $clave;
                }
            }
        }
        return null;
    }

    private function detectarChofer(string $normalizada, int $empresaId): ?array
    {
        foreach ((new Chofer())->allDeEmpresa($empresaId) as $chofer) {
            foreach (preg_split('/[\s,]+/', $this->normalizar($chofer['nombre'])) as $parte) {
                if (strlen($parte) >= 3 && str_contains($normalizada, $parte)) {
                    return $chofer;
                }
            }
        }
        return null;
    }

    private function esPreguntaRanking(string $normalizada): bool
    {
        $tieneQuien = str_contains($normalizada, 'quien') || str_contains($normalizada, 'que chofer') || str_contains($normalizada, 'cual chofer');
        $tieneComparativo = str_contains($normalizada, 'mas') || str_contains($normalizada, 'menos') || str_contains($normalizada, 'mayor') || str_contains($normalizada, 'menor');
        return $tieneQuien && $tieneComparativo;
    }

    private function etiquetaMetrica(string $campo): string
    {
        return match ($campo) {
            'gastos_total' => 'gastó',
            'comision_chofer' => 'se llevó de comisión',
            'cc_pendiente' => 'tiene pendiente en cuenta corriente',
            'viajes_count' => 'viajes cargó',
            default => 'facturó',
        };
    }

    private function normalizar(string $texto): string
    {
        // strtolower (no mb_strtolower) a propósito: no depender de la extensión
        // mbstring, que no siempre está habilitada. Solo afecta A-Z ASCII, así
        // que el strtr de acentos de abajo sigue funcionando igual.
        $texto = strtolower($texto);
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
        ]);
    }

    private function money(float $n): string
    {
        return '$' . number_format(round($n), 0, ',', '.');
    }
}
