<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Config\Env;
use App\Core\Auth;
use App\Core\Response;
use App\Core\Router;

$origin = Env::get('FRONTEND_ORIGIN', '');
if ($origin !== '' && $origin !== null) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Calcula la ruta relativa a donde esté ubicado este index.php, sea que la API
// viva en la raíz de un (sub)dominio propio o en una subcarpeta como /api.
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? $uri : '/';
$path = substr($uri, strlen($scriptDir));
if ($path === '' || $path === false) {
    $path = '/';
}
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) && $path !== '/auth/login') {
    Auth::requireCsrf();
}

/** @var Router $router */
$router = require __DIR__ . '/../src/routes.php';

try {
    $router->dispatch($method, $path);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    Response::error('Error interno del servidor.', 500);
}
