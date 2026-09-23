<?php

declare(strict_types=1);

namespace App\Core;

final class Uploader
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Valida y guarda una imagen subida por $_FILES en backend/public/uploads/{subdir}/
     * con un nombre aleatorio, y devuelve la ruta relativa a guardar en la base de datos.
     */
    public static function guardarImagen(array $file, string $subdir): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('Error al subir el archivo.', 422);
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            Response::error('El archivo supera el tamaño máximo permitido (5MB).', 422);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            Response::error('Archivo inválido.', 422);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_MIME[$mime])) {
            Response::error('Formato de imagen no permitido. Usá JPG, PNG o WEBP.', 422);
        }

        $ext = self::ALLOWED_MIME[$mime];
        $dir = __DIR__ . '/../../public/uploads/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            Response::error('No se pudo preparar el destino del archivo.', 500);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destino = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            Response::error('No se pudo guardar el archivo.', 500);
        }

        return 'uploads/' . $subdir . '/' . $filename;
    }
}
