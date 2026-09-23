<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Chofer;
use App\Models\Usuario;

final class AuthController
{
    public function login(): void
    {
        $data = Request::json();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            Response::error('Usuario y contraseña son obligatorios.', 422);
        }

        $usuario = (new Usuario())->findByUsername($username);
        if ($usuario === null || (int) $usuario['activo'] !== 1 || !password_verify($password, $usuario['password_hash'])) {
            Response::error('Usuario o contraseña incorrectos.', 401);
        }

        Auth::login($usuario);
        Response::json($this->payload($usuario));
    }

    public function logout(): void
    {
        Auth::logout();
        Response::json(['ok' => true]);
    }

    public function me(): void
    {
        if (!Auth::check()) {
            Response::json(['authenticated' => false]);
        }

        $usuario = (new Usuario())->find((int) Auth::userId());
        if ($usuario === null || (int) $usuario['activo'] !== 1) {
            Auth::logout();
            Response::json(['authenticated' => false]);
        }

        Response::json(['authenticated' => true, ...$this->payload($usuario)]);
    }

    private function payload(array $usuario): array
    {
        $chofer = null;
        if ($usuario['rol'] === 'chofer') {
            $chofer = (new Chofer())->findByUsuarioId((int) $usuario['id']);
        }

        return [
            'rol' => $usuario['rol'],
            'username' => $usuario['username'],
            'empresa_id' => (int) $usuario['empresa_id'],
            'chofer' => $chofer,
            'csrf_token' => $_SESSION['csrf_token'] ?? null,
        ];
    }
}
