-- ============================================================================
-- DIPUDATA / MATCH ELECTORAL 360 — ESQUEMA SQL COMPLETO (MySQL 8.0+ / MariaDB)
-- Arquitectura de Datos Cívicos, Educación Financiera y Hub & Spoke SEO
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS blog_articulos;
DROP TABLE IF EXISTS votaciones;
DROP TABLE IF EXISTS patrimonio_ddjj_cuit;
DROP TABLE IF EXISTS legisladores_master;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. TABLA MAESTRA: legisladores_master
-- Unifica identidad parlamentaria (id_recinto), fiscal (cuit) y enciclopédica (nombre_wiki)
-- ============================================================================
CREATE TABLE legisladores_master (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE COMMENT 'Slug SEO para /diputado/{slug} o /senador/{slug}',
    camara ENUM('Diputados', 'Senadores') NOT NULL,
    provincia VARCHAR(100) NOT NULL,
    bloque VARCHAR(120) NOT NULL,
    estado ENUM('Activo', 'Inactivo', 'Mandato Cumplido') NOT NULL DEFAULT 'Activo',
    id_recinto VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador oficial HCDN / HSN (DataCP)',
    cuit VARCHAR(13) NOT NULL UNIQUE COMMENT 'Formato XX-XXXXXXXX-X (Oficina Anticorrupción)',
    nombre_wiki VARCHAR(150) NULL COMMENT 'Título exacto del artículo en es.wikipedia.org',
    biografia_extracto TEXT NULL COMMENT 'Extracto sincronizado vía MediaWiki API',
    foto_url VARCHAR(255) NULL,
    indice_lealtad DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje de alineamiento con su bloque (0-100)',
    asistencia DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje de asistencia a sesiones (0-100)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_master_camara_provincia (camara, provincia, estado),
    INDEX idx_master_bloque (bloque),
    INDEX idx_master_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TABLA PATRIMONIAL: patrimonio_ddjj
-- Relacionada al CUIT del legislador. Almacena declaración nominal, IPC y variación real
-- ============================================================================
CREATE TABLE IF NOT EXISTS patrimonio_ddjj_cuit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cuit VARCHAR(13) NOT NULL COMMENT 'FK hacia legisladores_master.cuit',
    anio YEAR NOT NULL COMMENT 'Año fiscal de la DDJJ ante Oficina Anticorrupción',
    monto_declarado DECIMAL(18,2) NOT NULL COMMENT 'Patrimonio neto nominal declarado en ARS',
    ajuste_inflacion_ipc DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'IPC interanual / coeficiente INDEC aplicado (%)',
    monto_ajustado_real DECIMAL(18,2) GENERATED ALWAYS AS (
        ROUND(monto_declarado / NULLIF((1 + (ajuste_inflacion_ipc / 100)), 0), 2)
    ) STORED COMMENT 'Valor real deflactado por inflación del período',
    variacion_real_porcentaje DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Crecimiento o caída patrimonial real neta de inflación (%)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ddjj_legislador_cuit
        FOREIGN KEY (cuit) REFERENCES legisladores_master(cuit)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_ddjj_cuit_anio (cuit, anio),
    INDEX idx_ddjj_anio (anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. TABLA DE VOTACIONES: votaciones
-- Relacionada al ID_Recinto. Clasificada en los 4 ejes del Match Electoral 360
-- ============================================================================
CREATE TABLE votaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_recinto VARCHAR(50) NOT NULL COMMENT 'FK hacia legisladores_master.id_recinto',
    ley VARCHAR(255) NOT NULL COMMENT 'Nombre o expediente de la ley votada',
    eje_tematico ENUM(
        'Salud Pública',
        'Educación Pública',
        'Industria Nacional',
        'Apertura Comercial'
    ) NOT NULL,
    voto ENUM(
        'Afirmativo',
        'Negativo',
        'Abstención',
        'Ausente'
    ) NOT NULL,
    fecha DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_votaciones_id_recinto
        FOREIGN KEY (id_recinto) REFERENCES legisladores_master(id_recinto)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_recinto_ley_fecha (id_recinto, ley, fecha),
    INDEX idx_votaciones_recinto_eje (id_recinto, eje_tematico, voto),
    INDEX idx_votaciones_fecha (fecha DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. TABLA SEO HUB & SPOKE: blog_articulos
-- Artículos de educación financiera y análisis legislativo (/blog/{categoria}/{slug})
-- ============================================================================
CREATE TABLE blog_articulos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_slug VARCHAR(80) NOT NULL COMMENT 'Ej: educacion-financiera, auditoria-legislativa',
    articulo_slug VARCHAR(160) NOT NULL UNIQUE COMMENT 'Slug del artículo en la URL',
    titulo VARCHAR(200) NOT NULL,
    meta_descripcion VARCHAR(300) NOT NULL,
    contenido_html MEDIUMTEXT NOT NULL COMMENT 'Contenido base sobre el que opera el Helper de Auto-Linking',
    publicado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_publicacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blog_cat_slug (categoria_slug, articulo_slug, publicado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
