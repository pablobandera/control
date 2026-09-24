<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Database;

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $https = self::requestIsHttps();

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $https,
            ]);
            session_start();
        }
    }

    public static function login(array $usuario): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $usuario['id'];
        $_SESSION['rol'] = $usuario['rol'];
        $_SESSION['empresa_id'] = (int) $usuario['empresa_id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function role(): ?string
    {
        return $_SESSION['rol'] ?? null;
    }

    public static function empresaId(): ?int
    {
        return isset($_SESSION['empresa_id']) ? (int) $_SESSION['empresa_id'] : null;
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            Response::error('No autenticado.', 401);
        }
        if (!self::usuarioSigueActivo()) {
            self::logout();
            Response::error('Tu cuenta fue desactivada.', 401);
        }
    }

    /**
     * Detecta HTTPS incluso detrás de un proxy/balanceador (común en hostings
     * compartidos), donde $_SERVER['HTTPS'] puede no llegar seteado aunque el
     * visitante sí esté en HTTPS. Sin esto, la cookie de sesión podría no
     * marcarse como "secure" en producción y el login fallar en el navegador.
     */
    private static function requestIsHttps(): bool
    {
        if ((($_SERVER['HTTPS'] ?? '') !== '') && ($_SERVER['HTTPS'] ?? '') !== 'off') {
            return true;
        }
        if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /**
     * Revalida contra la base que el usuario de la sesión sigue activo, para
     * que una baja del dueño corte el acceso de inmediato aunque la sesión
     * del navegador siga viva.
     */
    private static function usuarioSigueActivo(): bool
    {
        $stmt = Database::connection()->prepare('SELECT activo FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => self::userId()]);
        $row = $stmt->fetch();
        return $row !== false && (int) $row['activo'] === 1;
    }

    public static function requireRole(string $rol): void
    {
        self::requireAuth();
        if (self::role() !== $rol) {
            Response::error('No autorizado.', 403);
        }
    }

    public static function requireCsrf(): void
    {
        if (!self::check()) {
            // Sin sesion todavia no hay nada que proteger; el endpoint en si
            // va a rechazar con 401 si requiere autenticacion.
            return;
        }

        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected = $_SESSION['csrf_token'] ?? '';
        if ($expected === '' || !hash_equals($expected, (string) $header)) {
            Response::error('Token CSRF invalido o ausente.', 419);
        }
    }
}
