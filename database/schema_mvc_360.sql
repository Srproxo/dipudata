-- ============================================================================
-- DIPUDATA / MATCH ELECTORAL 360 — ESQUEMA SQL COMPLETO (MySQL 8.0+ / MariaDB)
-- Arquitectura de Datos Cívicos, Educación Financiera y Hub & Spoke SEO (Fase 2)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS blog_articulos;
DROP TABLE IF EXISTS despacho_costos;
DROP TABLE IF EXISTS leyes_impacto_provincial;
DROP TABLE IF EXISTS proyectos_firmas;
DROP TABLE IF EXISTS proyectos;
DROP TABLE IF EXISTS votaciones;
DROP TABLE IF EXISTS ejes_tematicos;
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
-- 2. TABLA PATRIMONIAL: patrimonio_ddjj_cuit
-- Relacionada al CUIT del legislador. Almacena declaración nominal, IPC y variación real
-- ============================================================================
CREATE TABLE patrimonio_ddjj_cuit (
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
-- 3. TABLA DINÁMICA DE EJES TEMÁTICOS: ejes_tematicos
-- Normalización relacional de la matriz temática (11 dimensiones)
-- ============================================================================
CREATE TABLE ejes_tematicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_eje VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ejes_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ejes_tematicos (id, nombre_eje, descripcion, activo) VALUES
(1,  'Salud Pública', 'Políticas sanitarias, financiamiento hospitalario, vacunas y emergencias de salud pública.', 1),
(2,  'Educación', 'Presupuesto para universidades y escuelas, salario docente, infraestructura educativa e investigación.', 1),
(3,  'Apertura Comercial', 'Desregulación arancelaria, comercio exterior, importaciones libres y acuerdos bilaterales.', 1),
(4,  'Industria Nacional', 'Protección y estímulo a PyMEs industriales, empleo fabril y desarrollo de proveedores locales.', 1),
(5,  'Previsión Social y Discapacidad', 'Fórmula de movilidad jubilatoria, cobertura de medicamentos y pensiones no contributivas por discapacidad.', 1),
(6,  'Medio Ambiente y Derechos Humanos', 'Protección de glaciares y humedales, transición energética, soberanía ambiental y derechos humanos.', 1),
(7,  'Seguridad y Sistema Penal', 'Régimen penal juvenil, Código Penal, lucha contra el crimen organizado y endurecimiento de penas.', 1),
(8,  'Género y Cuidados', 'Sistema integral de cuidados, régimen de licencias laborales familiares y prevención de violencias.', 1),
(9,  'Federalismo y Agro', 'Distribución equitativa de coparticipación, retenciones agropecuarias y desarrollo regional.', 1),
(10, 'Transparencia Institucional', 'Ficha Limpia, declaraciones juradas públicas, acceso a la información y control del gasto político.', 1),
(11, 'Tecnología y Trabajo', 'Regulación del empleo en plataformas digitales, economía del conocimiento, telecomunicaciones e IA.', 1);

-- ============================================================================
-- 4. TABLA DE VOTACIONES: votaciones
-- Clave foránea eje_id referenciando a ejes_tematicos(id)
-- ============================================================================
CREATE TABLE votaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_recinto VARCHAR(50) NOT NULL COMMENT 'FK hacia legisladores_master.id_recinto',
    ley VARCHAR(255) NOT NULL COMMENT 'Nombre o expediente de la ley votada',
    eje_id INT UNSIGNED NOT NULL COMMENT 'FK hacia ejes_tematicos.id',
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
    CONSTRAINT fk_votaciones_eje
        FOREIGN KEY (eje_id) REFERENCES ejes_tematicos(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_recinto_ley_fecha (id_recinto, ley, fecha),
    INDEX idx_votaciones_recinto_eje_id (id_recinto, eje_id, voto),
    INDEX idx_votaciones_fecha (fecha DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. TABLA DE PROYECTOS: proyectos (Iniciativa Legislativa Proactiva - Fase 3)
-- ============================================================================
CREATE TABLE proyectos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expediente VARCHAR(50) NOT NULL UNIQUE COMMENT 'Número oficial de expediente (ej: 0142-D-2024)',
    titulo_sumario VARCHAR(300) NOT NULL COMMENT 'Síntesis legislativa del proyecto',
    fecha_presentacion DATE NOT NULL,
    eje_id INT UNSIGNED NOT NULL COMMENT 'FK hacia ejes_tematicos.id',
    estado_tramite VARCHAR(100) NOT NULL DEFAULT 'En Comisión' COMMENT 'En Comisión, Con Dictamen, Sancionado, Media Sanción, etc.',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_proyectos_eje
        FOREIGN KEY (eje_id) REFERENCES ejes_tematicos(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_proyectos_eje (eje_id),
    INDEX idx_proyectos_fecha (fecha_presentacion DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. TABLA PIVOTE DE AUTORÍAS: proyectos_firmas
-- ============================================================================
CREATE TABLE proyectos_firmas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT UNSIGNED NOT NULL,
    legislador_id INT UNSIGNED NOT NULL,
    rol ENUM('Autor', 'Co-autor') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_firmas_proyecto
        FOREIGN KEY (proyecto_id) REFERENCES proyectos(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_firmas_legislador
        FOREIGN KEY (legislador_id) REFERENCES legisladores_master(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_proyecto_legislador (proyecto_id, legislador_id),
    INDEX idx_firmas_legislador_rol (legislador_id, rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. TABLA SEO HUB & SPOKE: blog_articulos
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

-- ============================================================================
-- 8. TABLA DE IMPACTO REGIONAL: leyes_impacto_provincial (Fase 4 - Tensión Provincial)
-- Cruza leyes con el sentido de voto esperado regionalmente y criticidad
-- ============================================================================
CREATE TABLE leyes_impacto_provincial (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ley_id BIGINT UNSIGNED NULL COMMENT 'Foreign Key a votaciones.id',
    ley VARCHAR(255) NOT NULL COMMENT 'Nombre o carátula de la ley evaluada',
    provincia VARCHAR(100) NOT NULL COMMENT 'Provincia afectada',
    voto_esperado_regional ENUM('Afirmativo', 'Negativo') NOT NULL COMMENT 'Sentido del voto que favorece a la provincia',
    nivel_criticidad INT NOT NULL DEFAULT 2 COMMENT 'Criticidad de 1 a 3 (1=Baja, 2=Media, 3=Crítica/Vital)',
    descripcion_impacto TEXT NULL COMMENT 'Fundamento de impacto provincial',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_impacto_ley_votaciones
        FOREIGN KEY (ley_id) REFERENCES votaciones(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_impacto_provincia (provincia),
    INDEX idx_impacto_ley (ley)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. TABLA DE COSTOS DE DESPACHO Y EFICIENCIA: despacho_costos (Fase 6 - n8n)
-- Registra asesores, masa salarial, ratio por proyecto y alertas de nepotismo
-- ============================================================================
CREATE TABLE despacho_costos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legislador_id INT UNSIGNED NOT NULL COMMENT 'FK a legisladores_master(id)',
    anio INT NOT NULL DEFAULT 2024 COMMENT 'Año legislativo auditado',
    total_asesores INT NOT NULL DEFAULT 0,
    costo_mensual_ars DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    costo_anual_ars DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    alertas_nepotismo INT NOT NULL DEFAULT 0 COMMENT 'Cantidad de asesores con coincidencia de apellido o vínculos societarios',
    proyectos_autor_anio INT NOT NULL DEFAULT 0,
    costo_por_proyecto_ars DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    detalle_asesores_json JSON NULL,
    fuente VARCHAR(100) NOT NULL DEFAULT 'n8n.buceo.co',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_despacho_legislador
        FOREIGN KEY (legislador_id) REFERENCES legisladores_master(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_despacho_legislador_anio (legislador_id, anio),
    INDEX idx_despacho_eficiencia (costo_por_proyecto_ars),
    INDEX idx_despacho_nepotismo (alertas_nepotismo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
