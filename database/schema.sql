-- AppJuan (control) - esquema de base de datos
-- Gestion de flota de taxis/remises: dueno + choferes, turnos, viajes, gastos, cuenta corriente, service, comprobantes.
--
-- Como importar:
--   phpMyAdmin: crea una base de datos vacia (utf8mb4_unicode_ci) y usa "Importar" con este archivo.
--   Consola:    mysql -u tu_usuario -p tu_base_de_datos < schema.sql
--
-- Este script NO crea usuarios de login (eso lo hace backend/public/install.php,
-- que genera el hash de la contrasena con PHP para garantizar compatibilidad).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Una fila por flota. Hoy se usa una sola (id=1), pero deja la puerta
-- abierta a soportar mas de una flota en el futuro sin romper nada.
CREATE TABLE IF NOT EXISTS empresas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(150) NOT NULL DEFAULT 'Mi Flota',
  cuit VARCHAR(20) NULL,
  ciudad VARCHAR(100) NULL,
  telefono VARCHAR(30) NULL,
  comision_chofer_pct DECIMAL(5,2) NOT NULL DEFAULT 35.00,
  moneda CHAR(3) NOT NULL DEFAULT 'ARS',
  plan VARCHAR(50) NOT NULL DEFAULT 'Standard',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id INT UNSIGNED NOT NULL,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('dueno','chofer') NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_username (username),
  KEY idx_usuarios_empresa (empresa_id),
  CONSTRAINT fk_usuarios_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS choferes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  empresa_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  telefono VARCHAR(30) NOT NULL,
  foto_path VARCHAR(255) NULL,
  comision_pct DECIMAL(5,2) NULL COMMENT 'Override de la comision de la empresa, NULL = usa la de empresas.comision_chofer_pct',
  vehiculo VARCHAR(120) NULL,
  licencia_vencimiento DATE NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_choferes_usuario (usuario_id),
  KEY idx_choferes_empresa (empresa_id),
  CONSTRAINT fk_choferes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_choferes_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS moviles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id INT UNSIGNED NOT NULL,
  numero VARCHAR(20) NOT NULL,
  modelo VARCHAR(120) NULL,
  patente VARCHAR(20) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_moviles_empresa_numero (empresa_id, numero),
  CONSTRAINT fk_moviles_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogos (en vez de ENUM) para poder agregar tipos/medios nuevos sin tocar el esquema.
CREATE TABLE IF NOT EXISTS tipos_viaje (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(30) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tipos_viaje_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medios_pago (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(30) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_medios_pago_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS turnos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  chofer_id INT UNSIGNED NOT NULL,
  movil_id INT UNSIGNED NOT NULL,
  fecha_inicio DATETIME NOT NULL,
  fecha_fin DATETIME NULL,
  estado ENUM('activo','cerrado') NOT NULL DEFAULT 'activo',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_turnos_chofer_estado (chofer_id, estado),
  KEY idx_turnos_movil (movil_id),
  KEY idx_turnos_fecha_inicio (fecha_inicio),
  CONSTRAINT fk_turnos_chofer FOREIGN KEY (chofer_id) REFERENCES choferes (id) ON DELETE CASCADE,
  CONSTRAINT fk_turnos_movil FOREIGN KEY (movil_id) REFERENCES moviles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viajes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  turno_id INT UNSIGNED NOT NULL,
  tipo_viaje_id INT UNSIGNED NOT NULL,
  medio_pago_id INT UNSIGNED NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  descuento DECIMAL(10,2) NOT NULL DEFAULT 0,
  comentario TEXT NULL,
  hora DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_viajes_turno (turno_id),
  CONSTRAINT fk_viajes_turno FOREIGN KEY (turno_id) REFERENCES turnos (id) ON DELETE CASCADE,
  CONSTRAINT fk_viajes_tipo FOREIGN KEY (tipo_viaje_id) REFERENCES tipos_viaje (id),
  CONSTRAINT fk_viajes_medio FOREIGN KEY (medio_pago_id) REFERENCES medios_pago (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gastos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  turno_id INT UNSIGNED NOT NULL,
  concepto VARCHAR(150) NOT NULL,
  categoria ENUM('combustible','otro') NOT NULL DEFAULT 'otro',
  monto DECIMAL(10,2) NOT NULL,
  hora DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gastos_turno (turno_id),
  CONSTRAINT fk_gastos_turno FOREIGN KEY (turno_id) REFERENCES turnos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cuentas_corrientes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  turno_id INT UNSIGNED NOT NULL,
  cliente_nombre VARCHAR(150) NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  descuento DECIMAL(10,2) NOT NULL DEFAULT 0,
  hora DATETIME NOT NULL,
  cobrado TINYINT(1) NOT NULL DEFAULT 0,
  fecha_cobro DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cc_turno_cobrado (turno_id, cobrado),
  CONSTRAINT fk_cc_turno FOREIGN KEY (turno_id) REFERENCES turnos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  movil_id INT UNSIGNED NOT NULL,
  chofer_id INT UNSIGNED NOT NULL,
  turno_id INT UNSIGNED NULL,
  descripcion TEXT NOT NULL,
  fecha DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_services_movil (movil_id),
  KEY idx_services_chofer (chofer_id),
  CONSTRAINT fk_services_movil FOREIGN KEY (movil_id) REFERENCES moviles (id) ON DELETE CASCADE,
  CONSTRAINT fk_services_chofer FOREIGN KEY (chofer_id) REFERENCES choferes (id) ON DELETE CASCADE,
  CONSTRAINT fk_services_turno FOREIGN KEY (turno_id) REFERENCES turnos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comprobantes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  turno_id INT UNSIGNED NOT NULL,
  ruta_archivo VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comprobantes_turno (turno_id),
  CONSTRAINT fk_comprobantes_turno FOREIGN KEY (turno_id) REFERENCES turnos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos base (no sensibles): la flota por defecto y los catalogos.
-- El usuario administrador (dueno) lo crea backend/public/install.php, no este script,
-- porque el hash de la contrasena debe generarse con la funcion password_hash() de PHP.
INSERT IGNORE INTO empresas (id, nombre) VALUES (1, 'Mi Flota');
INSERT IGNORE INTO tipos_viaje (id, nombre) VALUES (1, 'Taxi'), (2, 'Uber');
INSERT IGNORE INTO medios_pago (id, nombre) VALUES (1, 'Efectivo'), (2, 'Transferencia');

SET FOREIGN_KEY_CHECKS = 1;
