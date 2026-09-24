<?php

declare(strict_types=1);

use App\Controllers\AsistenteController;
use App\Controllers\AuthController;
use App\Controllers\ChoferController;
use App\Controllers\CuentaCorrienteController;
use App\Controllers\EmpresaController;
use App\Controllers\GastoController;
use App\Controllers\PerfilController;
use App\Controllers\ReporteController;
use App\Controllers\ServiceController;
use App\Controllers\TurnoController;
use App\Controllers\ViajeController;
use App\Core\Router;

$router = new Router();

$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/auth/logout', [AuthController::class, 'logout']);
$router->get('/auth/me', [AuthController::class, 'me']);

// Rutas literales antes que las parametrizadas ({id}), para que no se confundan entre sí.
$router->post('/turnos', [TurnoController::class, 'iniciar']);
$router->get('/turnos/activo', [TurnoController::class, 'activo']);
$router->get('/turnos/historial', [TurnoController::class, 'historial']);
$router->get('/turnos/comisiones', [TurnoController::class, 'comisiones']);
$router->post('/turnos/{id}/cerrar', [TurnoController::class, 'cerrar']);
$router->get('/turnos/{id}', [TurnoController::class, 'ver']);

$router->post('/viajes', [ViajeController::class, 'crear']);
$router->put('/viajes/{id}', [ViajeController::class, 'actualizar']);

$router->post('/gastos', [GastoController::class, 'crear']);
$router->put('/gastos/{id}', [GastoController::class, 'actualizar']);

$router->post('/cuentas-corrientes', [CuentaCorrienteController::class, 'crear']);
$router->put('/cuentas-corrientes/{id}', [CuentaCorrienteController::class, 'actualizar']);
$router->post('/cuentas-corrientes/{id}/cobrar', [CuentaCorrienteController::class, 'cobrar']);

$router->post('/services', [ServiceController::class, 'crear']);
$router->get('/services', [ServiceController::class, 'listar']);

$router->put('/perfil/password', [PerfilController::class, 'cambiarPassword']);
$router->post('/perfil/foto', [PerfilController::class, 'foto']);
$router->get('/perfil', [PerfilController::class, 'ver']);
$router->put('/perfil', [PerfilController::class, 'actualizar']);

$router->get('/choferes', [ChoferController::class, 'listar']);
$router->post('/choferes', [ChoferController::class, 'crear']);
$router->post('/choferes/{id}/foto', [ChoferController::class, 'foto']);
$router->put('/choferes/{id}', [ChoferController::class, 'actualizar']);
$router->delete('/choferes/{id}', [ChoferController::class, 'eliminar']);

$router->get('/reportes/export', [ReporteController::class, 'exportar']);
$router->get('/reportes/comisiones', [ReporteController::class, 'comisiones']);
$router->get('/reportes/services', [ReporteController::class, 'services']);
$router->get('/reportes/comprobantes', [ReporteController::class, 'comprobantes']);
$router->get('/reportes/serie', [ReporteController::class, 'serie']);
$router->get('/reportes', [ReporteController::class, 'kpis']);

$router->get('/asistente/informe', [AsistenteController::class, 'informe']);

$router->get('/empresa', [EmpresaController::class, 'ver']);
$router->put('/empresa', [EmpresaController::class, 'actualizar']);

return $router;
