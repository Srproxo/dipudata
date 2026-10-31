-- ============================================================================
-- LEGISDATA (dipudata.com) — ESQUEMA RELACIONAL Y SEGURIDAD GRADO BANCARIO
-- Motor: MySQL 8.0+ / MariaDB 10.6+ (InnoDB, utf8mb4_unicode_ci)
-- Etapa 1: Arquitectura y Modelo de Datos
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TRIGGER IF EXISTS trg_audit_logs_no_update;
DROP TRIGGER IF EXISTS trg_audit_logs_no_delete;

DROP TABLE IF EXISTS votos;
DROP TABLE IF EXISTS leyes;
DROP TABLE IF EXISTS patrimonio_ddjj;
DROP TABLE IF EXISTS indicadores_economicos;
DROP TABLE IF EXISTS legisladores;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS audit_logs;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. TABLA CORE DE LEGISLADORES
-- ============================================================================
CREATE TABLE legisladores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_congreso VARCHAR(50) UNIQUE,
    cuit VARCHAR(15) UNIQUE,
    wiki_slug VARCHAR(150),
    slug VARCHAR(150) GENERATED ALWAYS AS (LOWER(REPLACE(wiki_slug, '_', '-'))) STORED UNIQUE,
    nombre_completo VARCHAR(150) NOT NULL,
    camara ENUM('Diputado', 'Senador', 'Diputados', 'Senadores') NOT NULL,
    provincia VARCHAR(100) NOT NULL,
    bloque_actual VARCHAR(100) NOT NULL,
    bloque VARCHAR(100) GENERATED ALWAYS AS (bloque_actual) VIRTUAL,
    estado ENUM('Activo', 'Inactivo', 'Mandato Cumplido', 'Candidato') DEFAULT 'Activo',
    biografia TEXT,
    foto_url VARCHAR(255),
    asistencia_historica DECIMAL(5, 2) NOT NULL DEFAULT 90.00 COMMENT 'Porcentaje historico de asistencia a sesiones',
    lealtad_partidaria DECIMAL(5, 2) NOT NULL DEFAULT 85.00 COMMENT 'Porcentaje de votacion alineada con su bloque',
    asistencia_pct DECIMAL(5, 2) GENERATED ALWAYS AS (asistencia_historica) VIRTUAL,
    lealtad_pct DECIMAL(5, 2) GENERATED ALWAYS AS (lealtad_partidaria) VIRTUAL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_legisladores_camara_prov_estado (camara, provincia, estado),
    INDEX idx_legisladores_bloque (bloque_actual),
    INDEX idx_legisladores_nombre (nombre_completo),
    INDEX idx_legisladores_wiki_slug (wiki_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TABLA PARA DEFLACTACIÓN AUTOMÁTICA (IPC E ÍNDICE DÓLAR MEP)
-- ============================================================================
CREATE TABLE indicadores_economicos (
    anio INT PRIMARY KEY,
    ipc_interanual DECIMAL(10, 4) NOT NULL COMMENT 'Inflacion anual acumulada (%)',
    dolar_mep_cierre DECIMAL(10, 2) NOT NULL COMMENT 'Cotizacion USD MEP al cierre del año (ARS)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. TABLA DE DECLARACIONES JURADAS (VALORES NOMINALES DE OFICINA ANTICORRUPCIÓN)
-- ============================================================================
CREATE TABLE patrimonio_ddjj (
    id INT AUTO_INCREMENT PRIMARY KEY,
    legislador_id INT NOT NULL,
    anio_fiscal INT NOT NULL,
    monto_pesos_nominal DECIMAL(18, 2) NOT NULL,
    fuente VARCHAR(100) DEFAULT 'Oficina Anticorrupción',
    CONSTRAINT fk_patrimonio_legislador
        FOREIGN KEY (legislador_id) REFERENCES legisladores(id) ON DELETE CASCADE,
    CONSTRAINT fk_patrimonio_anio
        FOREIGN KEY (anio_fiscal) REFERENCES indicadores_economicos(anio),
    UNIQUE KEY uq_legislador_anio_fiscal (legislador_id, anio_fiscal),
    INDEX idx_patrimonio_legislador_anio (legislador_id, anio_fiscal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. TABLA DE LEYES Y EXPEDIENTES (CLASIFICADAS POR EJE TEMÁTICO)
-- ============================================================================
CREATE TABLE leyes (
    id VARCHAR(50) PRIMARY KEY COMMENT 'Ej: 27610 o Expediente',
    titulo VARCHAR(255) NOT NULL,
    resumen TEXT NULL COMMENT 'Sintesis parlamentaria del expediente o ley sancionada',
    fecha_votacion DATE NOT NULL,
    eje_tematico ENUM('Salud', 'Educacion', 'MercadoLibre', 'Proteccionismo', 'Laboral') NOT NULL,
    peso_ideologico DECIMAL(3, 2) DEFAULT 1.00 COMMENT 'Multiplicador de relevancia de la ley (0.10 a 1.00)',
    INDEX idx_leyes_eje_fecha (eje_tematico, fecha_votacion),
    INDEX idx_leyes_fecha (fecha_votacion DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. TABLA DE VOTOS (EL CORAZÓN DEL PERFILAMIENTO Y MATCH ELECTORAL)
-- ============================================================================
CREATE TABLE votos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    legislador_id INT NOT NULL,
    ley_id VARCHAR(50) NOT NULL,
    sentido_voto ENUM('Afirmativo', 'Negativo', 'Abstencion', 'Ausente') NOT NULL,
    CONSTRAINT fk_votos_legislador
        FOREIGN KEY (legislador_id) REFERENCES legisladores(id) ON DELETE CASCADE,
    CONSTRAINT fk_votos_ley
        FOREIGN KEY (ley_id) REFERENCES leyes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_voto (legislador_id, ley_id),
    INDEX idx_votos_ley_sentido (ley_id, sentido_voto),
    INDEX idx_votos_legislador_sentido (legislador_id, sentido_voto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. SEGURIDAD GRADO BANCARIO: AUDIT LOG INMUTABLE (OWASP ASVS L3)
-- ============================================================================
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(60) NOT NULL COMMENT 'Ej: CRON_SYNC_WIKIPEDIA, RATE_LIMIT_EXCEEDED, INVALID_INPUT',
    actor_ip_hash CHAR(64) NOT NULL COMMENT 'SHA-256 de la IP del cliente o identificador CLI',
    endpoint VARCHAR(150) NOT NULL,
    payload_hash CHAR(64) NULL COMMENT 'SHA-256 del cuerpo de la peticion',
    severity ENUM('INFO', 'WARN', 'CRITICAL') NOT NULL DEFAULT 'INFO',
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_event_created (event_type, created_at),
    INDEX idx_audit_severity_created (severity, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TRIGGER trg_audit_logs_no_update
BEFORE UPDATE ON audit_logs
FOR EACH ROW
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Security Violation: audit_logs table is append-only and immutable (UPDATE prohibited)';

CREATE TRIGGER trg_audit_logs_no_delete
BEFORE DELETE ON audit_logs
FOR EACH ROW
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Security Violation: audit_logs table is append-only and immutable (DELETE prohibited)';

-- ============================================================================
-- 7. SEGURIDAD GRADO BANCARIO: RATE LIMITING POR VENTANA DESLIZANTE
-- ============================================================================
CREATE TABLE rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_hash CHAR(64) NOT NULL COMMENT 'SHA-256 de la IP anonimizada',
    route_group VARCHAR(50) NOT NULL COMMENT 'api_read | api_match | cron',
    window_start INT UNSIGNED NOT NULL COMMENT 'Bucket temporal en minutos (UNIX_TIMESTAMP DIV 60)',
    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rate_limit_window (ip_hash, route_group, window_start),
    INDEX idx_rate_limit_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
