<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Core\Validator;
use App\Models\Chofer;
use App\Models\Comprobante;
use App\Models\CuentaCorriente;
use App\Models\Empresa;
use App\Models\Gasto;
use App\Models\Movil;
use App\Models\Turno;
use App\Models\Viaje;
use App\Services\LiquidacionService;
use DateTimeImmutable;

final class TurnoController
{
    private function choferActual(): array
    {
        $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
        if ($chofer === null) {
            Response::error('No se encontró el perfil de chofer.', 404);
        }
        return $chofer;
    }

    public function iniciar(): void
    {
        Auth::requireRole('chofer');
        $chofer = $this->choferActual();

        $turnoModel = new Turno();
        if ($turnoModel->activoDeChofer((int) $chofer['id']) !== null) {
            Response::error('Ya tenés un turno activo.', 409);
        }

        $data = Request::json();
        Validator::requireFields($data, ['movil_numero']);
        $numero = trim((string) $data['movil_numero']);
        if ($numero === '') {
            Response::error('Ingresá el número de móvil.', 422);
        }

        $movil = (new Movil())->findOrCreate((int) $chofer['empresa_id'], $numero);

        $id = $turnoModel->insert([
            'chofer_id' => $chofer['id'],
            'movil_id' => $movil['id'],
            'fecha_inicio' => date('Y-m-d H:i:s'),
            'estado' => 'activo',
        ]);

        Response::json(['turno' => $turnoModel->conMovil($id)], 201);
    }

    public function activo(): void
    {
        Auth::requireRole('chofer');
        $chofer = $this->choferActual();
        $turno = (new Turno())->activoDeChofer((int) $chofer['id']);
        if ($turno === null) {
            Response::json(['turno' => null]);
        }

        Response::json($this->ticket((int) $turno['id']));
    }

    public function cerrar(string $id): void
    {
        Auth::requireRole('chofer');
        $chofer = $this->choferActual();
        $turnoModel = new Turno();
        $turno = $turnoModel->find((int) $id);
        if ($turno === null || (int) $turno['chofer_id'] !== (int) $chofer['id']) {
            Response::error('Turno no encontrado.', 404);
        }
        if ($turno['estado'] !== 'activo') {
            Response::error('Este turno ya está cerrado.', 409);
        }

        foreach (Request::files('fotos') as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $ruta = Uploader::guardarImagen($file, 'comprobantes');
            (new Comprobante())->insert(['turno_id' => $turno['id'], 'ruta_archivo' => $ruta]);
        }

        $turnoModel->cerrar((int) $turno['id']);
        Response::json($this->ticket((int) $turno['id']));
    }

    public function historial(): void
    {
        Auth::requireRole('chofer');
        $chofer = $this->choferActual();
        $turnos = (new Turno())->historialDeChofer((int) $chofer['id']);
        $empresa = (new Empresa())->primera();
        $liquidacion = new LiquidacionService(Database::connection());
        $pct = $liquidacion->comisionPctDeChofer($chofer, $empresa);

        $out = [];
        foreach ($turnos as $turno) {
            $calc = $liquidacion->calcularParaTurno((int) $turno['id'], $pct);
            $out[] = [...$turno, ...$calc];
        }

        Response::json(['turnos' => $out]);
    }

    /**
     * Serie para el gráfico "Mis comisiones" del historial: últimos 7 días,
     * últimas 6 semanas o últimos 6 meses, según $periodo.
     */
    public function comisiones(): void
    {
        Auth::requireRole('chofer');
        $chofer = $this->choferActual();
        $periodo = (string) Request::query('periodo', 'semana');
        $periodo = in_array($periodo, ['dia', 'semana', 'mes'], true) ? $periodo : 'semana';

        $empresa = (new Empresa())->primera();
        $liquidacion = new LiquidacionService(Database::connection());
        $pct = $liquidacion->comisionPctDeChofer($chofer, $empresa);
        $turnoModel = new Turno();

        $hoy = new DateTimeImmutable('today');
        $buckets = [];

        if ($periodo === 'dia') {
            for ($i = 6; $i >= 0; $i--) {
                $dia = $hoy->modify("-{$i} days");
                $ids = $turnoModel->deChoferEnRango((int) $chofer['id'], $dia->format('Y-m-d H:i:s'), $dia->modify('+1 day')->format('Y-m-d H:i:s'));
                $calc = $liquidacion->calcularParaTurnos($ids, $pct);
                $buckets[] = ['label' => $dia->format('d/m'), 'comision' => $calc['comision_chofer']];
            }
        } elseif ($periodo === 'semana') {
            $inicioActual = $hoy->modify('monday this week');
            for ($i = 5; $i >= 0; $i--) {
                $inicio = $inicioActual->modify("-{$i} weeks");
                $ids = $turnoModel->deChoferEnRango((int) $chofer['id'], $inicio->format('Y-m-d H:i:s'), $inicio->modify('+7 days')->format('Y-m-d H:i:s'));
                $calc = $liquidacion->calcularParaTurnos($ids, $pct);
                $buckets[] = ['label' => $inicio->format('d/m'), 'comision' => $calc['comision_chofer']];
            }
        } else {
            $mesesEs = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $inicioActual = $hoy->modify('first day of this month');
            for ($i = 5; $i >= 0; $i--) {
                $inicio = $inicioActual->modify("-{$i} months");
                $ids = $turnoModel->deChoferEnRango((int) $chofer['id'], $inicio->format('Y-m-d H:i:s'), $inicio->modify('+1 month')->format('Y-m-d H:i:s'));
                $calc = $liquidacion->calcularParaTurnos($ids, $pct);
                $buckets[] = ['label' => $mesesEs[(int) $inicio->format('n') - 1], 'comision' => $calc['comision_chofer']];
            }
        }

        Response::json(['periodo' => $periodo, 'buckets' => $buckets]);
    }

    public function ver(string $id): void
    {
        Auth::requireAuth();
        $turno = (new Turno())->conMovil((int) $id);
        if ($turno === null) {
            Response::error('Turno no encontrado.', 404);
        }

        if (Auth::role() === 'chofer') {
            $chofer = $this->choferActual();
            if ((int) $turno['chofer_id'] !== (int) $chofer['id']) {
                Response::error('No autorizado.', 403);
            }
        } elseif ((int) $turno['empresa_id'] !== (int) Auth::empresaId()) {
            Response::error('No autorizado.', 403);
        }

        Response::json($this->ticket((int) $id));
    }

    private function ticket(int $turnoId): array
    {
        $turno = (new Turno())->conMovil($turnoId);
        $chofer = (new Chofer())->find((int) $turno['chofer_id']);
        $empresa = (new Empresa())->primera();
        $liquidacion = new LiquidacionService(Database::connection());
        $pct = $liquidacion->comisionPctDeChofer($chofer, $empresa);
        $calc = $liquidacion->calcularParaTurno($turnoId, $pct);

        return [
            'turno' => $turno,
            'liquidacion' => $calc,
            'viajes' => (new Viaje())->allDeTurno($turnoId),
            'gastos' => (new Gasto())->allDeTurno($turnoId),
            'cuentas_corrientes' => (new CuentaCorriente())->allDeTurno($turnoId),
            'comprobantes' => (new Comprobante())->allDeTurno($turnoId),
        ];
    }
}
