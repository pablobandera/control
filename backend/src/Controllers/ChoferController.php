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
use App\Models\Empresa;
use App\Models\Turno;
use App\Models\Usuario;
use App\Services\LiquidacionService;

final class ChoferController
{
    public function listar(): void
    {
        Auth::requireRole('dueno');
        $empresaId = (int) Auth::empresaId();

        $choferes = (new Chofer())->allDeEmpresa($empresaId);
        $turnosActivos = (new Turno())->activosDeEmpresa($empresaId);
        $empresa = (new Empresa())->primera();
        $liquidacion = new LiquidacionService(Database::connection());
        $turnoModel = new Turno();

        $hoyDesde = date('Y-m-d 00:00:00');
        $hoyHasta = date('Y-m-d 00:00:00', strtotime('+1 day'));

        $out = [];
        foreach ($choferes as $chofer) {
            $turnoActivo = $turnosActivos[(int) $chofer['id']] ?? null;
            $turnoIdsHoy = $turnoModel->deChoferEnRango((int) $chofer['id'], $hoyDesde, $hoyHasta);
            $pct = $liquidacion->comisionPctDeChofer($chofer, $empresa);
            $calc = $liquidacion->calcularParaTurnos($turnoIdsHoy, $pct);

            $out[] = [
                'chofer' => $chofer,
                'en_servicio' => $turnoActivo !== null,
                'movil_actual' => $turnoActivo['movil_numero'] ?? null,
                'turno_activo_id' => $turnoActivo['id'] ?? null,
                'liquidacion_hoy' => $calc,
            ];
        }

        Response::json(['choferes' => $out]);
    }

    public function crear(): void
    {
        Auth::requireRole('dueno');
        $data = Request::json();
        Validator::requireFields($data, ['nombre', 'telefono', 'username', 'password']);

        $username = trim((string) $data['username']);
        $password = (string) $data['password'];
        if (strlen($password) < 6) {
            Response::error('La contraseña debe tener al menos 6 caracteres.', 422);
        }

        $usuarioModel = new Usuario();
        if ($usuarioModel->existsUsername($username)) {
            Response::error('Ese nombre de usuario ya está en uso.', 409);
        }

        $empresaId = (int) Auth::empresaId();
        $usuarioId = $usuarioModel->insert([
            'empresa_id' => $empresaId,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'rol' => 'chofer',
        ]);

        $choferId = (new Chofer())->insert([
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'nombre' => trim((string) $data['nombre']),
            'telefono' => trim((string) $data['telefono']),
            'comision_pct' => isset($data['comision_pct']) && $data['comision_pct'] !== ''
                ? (float) $data['comision_pct']
                : null,
            'vehiculo' => isset($data['vehiculo']) && $data['vehiculo'] !== '' ? trim((string) $data['vehiculo']) : null,
        ]);

        Response::json(['chofer' => (new Chofer())->findConUsuario($choferId)], 201);
    }

    public function actualizar(string $id): void
    {
        Auth::requireRole('dueno');
        $choferModel = new Chofer();
        $chofer = $choferModel->find((int) $id);
        if ($chofer === null || (int) $chofer['empresa_id'] !== (int) Auth::empresaId()) {
            Response::error('Chofer no encontrado.', 404);
        }

        $data = Request::json();
        $update = [];
        foreach (['nombre', 'telefono', 'vehiculo'] as $campo) {
            if (isset($data[$campo])) {
                $update[$campo] = trim((string) $data[$campo]);
            }
        }
        if (array_key_exists('comision_pct', $data)) {
            $update['comision_pct'] = ($data['comision_pct'] === '' || $data['comision_pct'] === null)
                ? null
                : (float) $data['comision_pct'];
        }
        if (array_key_exists('licencia_vencimiento', $data)) {
            $update['licencia_vencimiento'] = $data['licencia_vencimiento'] !== '' ? $data['licencia_vencimiento'] : null;
        }
        $choferModel->update((int) $id, $update);

        if (!empty($data['password'])) {
            if (strlen((string) $data['password']) < 6) {
                Response::error('La contraseña debe tener al menos 6 caracteres.', 422);
            }
            (new Usuario())->update((int) $chofer['usuario_id'], [
                'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            ]);
        }

        Response::json(['chofer' => $choferModel->findConUsuario((int) $id)]);
    }

    public function eliminar(string $id): void
    {
        Auth::requireRole('dueno');
        $chofer = (new Chofer())->find((int) $id);
        if ($chofer === null || (int) $chofer['empresa_id'] !== (int) Auth::empresaId()) {
            Response::error('Chofer no encontrado.', 404);
        }

        // Baja lógica: preserva el historial de turnos/viajes y bloquea el login.
        (new Chofer())->update((int) $id, ['activo' => 0]);
        (new Usuario())->update((int) $chofer['usuario_id'], ['activo' => 0]);
        Response::json(['ok' => true]);
    }

    public function foto(string $id): void
    {
        Auth::requireRole('dueno');
        $choferModel = new Chofer();
        $chofer = $choferModel->find((int) $id);
        if ($chofer === null || (int) $chofer['empresa_id'] !== (int) Auth::empresaId()) {
            Response::error('Chofer no encontrado.', 404);
        }

        $file = Request::file('foto');
        if ($file === null) {
            Response::error('Falta el archivo de la foto.', 422);
        }

        $ruta = Uploader::guardarImagen($file, 'perfiles');
        $choferModel->update((int) $id, ['foto_path' => $ruta]);
        Response::json(['chofer' => $choferModel->findConUsuario((int) $id)]);
    }
}
