<?php

declare(strict_types=1);

/**
 * Instalador de una sola vez: crea las tablas (a partir de database/schema.sql)
 * y el usuario administrador (dueño) inicial: usuario / 123456.
 *
 * Cómo usarlo: configurá backend/.env con los datos de tu base de datos y
 * después visitá esta URL una vez desde el navegador (o corré
 * `php backend/public/install.php` por consola). Cuando termine, BORRÁ este
 * archivo del servidor por seguridad.
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Config\Database;

header('Content-Type: text/plain; charset=utf-8');

$pdo = Database::connection();

$tablaUsuarios = $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch();
$adminYaExiste = false;
if ($tablaUsuarios !== false) {
    $check = $pdo->query("SELECT COUNT(*) AS c FROM usuarios WHERE rol = 'dueno'")->fetch();
    $adminYaExiste = ((int) $check['c']) > 0;
}

if ($adminYaExiste) {
    http_response_code(409);
    echo "La base de datos ya fue inicializada (ya existe un usuario dueño).\n";
    echo "Por seguridad, borrá este archivo (install.php) de tu servidor.\n";
    exit;
}

$schemaPath = __DIR__ . '/../../database/schema.sql';
if (!is_file($schemaPath)) {
    http_response_code(500);
    echo "No se encontró database/schema.sql. Subilo junto con la carpeta backend/.\n";
    exit;
}

$sql = file_get_contents($schemaPath);
$statements = array_filter(array_map('trim', explode(';', $sql)), static fn (string $s): bool => $s !== '');

// Nota: las sentencias CREATE TABLE hacen COMMIT implícito en MySQL/MariaDB,
// así que envolver esto en una transacción no tendría efecto real; cada
// sentencia se ejecuta y se reporta individualmente si falla.
try {
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $empresaId = (int) $pdo->query('SELECT id FROM empresas ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($empresaId === 0) {
        $pdo->exec("INSERT INTO empresas (id, nombre) VALUES (1, 'Mi Flota')");
        $empresaId = 1;
    }

    $usernameAdmin = 'usuario';
    $passwordAdmin = '123456';
    $hash = password_hash($passwordAdmin, PASSWORD_DEFAULT);

    $insert = $pdo->prepare(
        "INSERT INTO usuarios (empresa_id, username, password_hash, rol, activo) VALUES (:empresa_id, :username, :hash, 'dueno', 1)"
    );
    $insert->execute([
        'empresa_id' => $empresaId,
        'username' => $usernameAdmin,
        'hash' => $hash,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Error durante la instalación: ' . $e->getMessage() . "\n";
    exit;
}

echo "Listo. Base de datos inicializada correctamente.\n\n";
echo "Usuario administrador (dueño): usuario / 123456\n\n";
echo "IMPORTANTE:\n";
echo "1. Entrá a la app y cambiá esa contraseña desde el perfil.\n";
echo "2. Borrá este archivo (install.php) de tu servidor por seguridad.\n";
