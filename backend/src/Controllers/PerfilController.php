<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Models\Chofer;
use App\Models\Usuario;

final class PerfilController
{
    private function choferIdActual(): int
    {
        Auth::requireRole('chofer');
        $chofer = (new Chofer())->findByUsuarioId((int) Auth::userId());
        if ($chofer === null) {
            Response::error('Perfil no encontrado.', 404);
        }
        return (int) $chofer['id'];
    }

    public function ver(): void
    {
        $id = $this->choferIdActual();
        Response::json(['chofer' => (new Chofer())->findConUsuario($id)]);
    }

    public function actualizar(): void
    {
        $id = $this->choferIdActual();
        $data = Request::json();

        $update = [];
        foreach (['telefono', 'vehiculo'] as $campo) {
            if (isset($data[$campo])) {
                $update[$campo] = trim((string) $data[$campo]);
            }
        }
        if (array_key_exists('licencia_vencimiento', $data)) {
            $update['licencia_vencimiento'] = $data['licencia_vencimiento'] !== '' ? $data['licencia_vencimiento'] : null;
        }

        (new Chofer())->update($id, $update);
        Response::json(['chofer' => (new Chofer())->findConUsuario($id)]);
    }

    public function foto(): void
    {
        $id = $this->choferIdActual();
        $file = Request::file('foto');
        if ($file === null) {
            Response::error('Falta el archivo de la foto.', 422);
        }

        $ruta = Uploader::guardarImagen($file, 'perfiles');
        (new Chofer())->update($id, ['foto_path' => $ruta]);
        Response::json(['chofer' => (new Chofer())->findConUsuario($id)]);
    }

    public function cambiarPassword(): void
    {
        Auth::requireRole('chofer');
        $data = Request::json();
        if (empty($data['actual']) || empty($data['nueva'])) {
            Response::error('Ingresá la contraseña actual y la nueva.', 422);
        }
        if (strlen((string) $data['nueva']) < 6) {
            Response::error('La nueva contraseña debe tener al menos 6 caracteres.', 422);
        }

        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->find((int) Auth::userId());
        if (!password_verify((string) $data['actual'], $usuario['password_hash'])) {
            Response::error('La contraseña actual no es correcta.', 401);
        }

        $usuarioModel->update((int) $usuario['id'], [
            'password_hash' => password_hash((string) $data['nueva'], PASSWORD_DEFAULT),
        ]);
        Response::json(['ok' => true]);
    }
}
