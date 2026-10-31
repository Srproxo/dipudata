-- ============================================================================
-- DIPUDATA / MATCH ELECTORAL 360 — MASTER SCHEMA NORMALIZADO (MySQL 8.0+)
-- Arquitectura de Datos Cívicos, Auditoría Ética, Coherencia y Match 360
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS promesas_campana;
DROP TABLE IF EXISTS financiamiento_campana;
DROP TABLE IF EXISTS leyes_impacto_provincial;
DROP TABLE IF EXISTS despacho_costos;
DROP TABLE IF EXISTS proyectos;
DROP TABLE IF EXISTS votaciones;
DROP TABLE IF EXISTS leyes;
DROP TABLE IF EXISTS ejes_tematicos;
DROP TABLE IF EXISTS patrimonio_ddjj;
DROP TABLE IF EXISTS legisladores_master;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. TABLA MAESTRA: legisladores_master
-- Unifica identidad parlamentaria, electoral, fiscal y enciclopédica
-- ============================================================================
CREATE TABLE legisladores_master (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE COMMENT 'Slug SEO para /diputado/{slug} o /senador/{slug}',
    camara ENUM('Diputados', 'Senadores') NOT NULL,
    provincia VARCHAR(100) NOT NULL,
    bloque VARCHAR(120) NOT NULL,
    estado ENUM('Activo', 'Inactivo', 'Mandato Cumplido') NOT NULL DEFAULT 'Activo',
    id_recinto VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador recinto oficial (HCDN-1001, etc.)',
    cuit VARCHAR(13) NOT NULL UNIQUE COMMENT 'Formato XX-XXXXXXXX-X (Oficina Anticorrupción)',
    nombre_wiki VARCHAR(150) NULL COMMENT 'Nombre de artículo en es.wikipedia.org',
    biografia_extracto TEXT NULL,
    foto_url VARCHAR(255) NULL,
    asistencia DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje histórico de asistencia (0-100)',
    indice_lealtad DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje de disciplina partidaria con su bloque (0-100)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_master_camara_provincia (camara, provincia, estado),
    INDEX idx_master_bloque (bloque),
    INDEX idx_master_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TABLA: ejes_tematicos
-- 11 ejes temáticos canónicos para la matriz multidimensional
-- ============================================================================
CREATE TABLE ejes_tematicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ejes_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ejes_tematicos (id, nombre, descripcion, activo) VALUES
(1,  'Salud', 'Políticas sanitarias, financiamiento hospitalario, vacunas y emergencias de salud pública.', 1),
(2,  'Educación', 'Presupuesto para universidades y escuelas, salario docente, infraestructura educativa e investigación.', 1),
(3,  'Industria', 'Protección y estímulo a PyMEs industriales, empleo fabril y desarrollo de proveedores locales.', 1),
(4,  'Comercio', 'Desregulación arancelaria, comercio exterior, importaciones libres y acuerdos comerciales.', 1),
(5,  'Previsión Social', 'Fórmula de movilidad jubilatoria, cobertura de medicamentos y pensiones por discapacidad.', 1),
(6,  'Medio Ambiente', 'Protección de glaciares y humedales, transición energética y soberanía ambiental.', 1),
(7,  'Seguridad', 'Régimen penal juvenil, Código Penal, lucha contra el crimen organizado y endurecimiento de penas.', 1),
(8,  'Género', 'Sistema integral de cuidados, régimen de licencias laborales familiares y prevención de violencias.', 1),
(9,  'Federalismo', 'Distribución equitativa de coparticipación, retenciones agropecuarias y obras regionales.', 1),
(10, 'Transparencia', 'Ficha Limpia, declaraciones juradas públicas, acceso a la información y control del gasto político.', 1),
(11, 'Tecnología', 'Regulación laboral en plataformas digitales, economía del conocimiento e Inteligencia Artificial.', 1);

-- ============================================================================
-- 3. TABLA: leyes
-- Expedientes tratados en el Congreso normalizados por eje temático
-- ============================================================================
CREATE TABLE leyes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expediente VARCHAR(50) NOT NULL UNIQUE COMMENT 'Número oficial (ej: 0025-D-2024)',
    titulo VARCHAR(300) NOT NULL COMMENT 'Título o carátula de la ley',
    eje_id INT UNSIGNED NOT NULL COMMENT 'FK hacia ejes_tematicos(id)',
    sector_vinculado VARCHAR(100) NULL COMMENT 'Sector productivo impactado (para auditoría de conflicto de interés)',
    estado_tramite VARCHAR(100) NOT NULL DEFAULT 'Sancionada',
    fecha_tratamiento DATE NULL,
    es_ley_espejo TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Marca si forma parte del set de leyes espejo entre gobiernos',
    ley_espejo_id INT UNSIGNED NULL COMMENT 'Referencia a la ley espejo opuesta en período anterior',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_leyes_eje
        FOREIGN KEY (eje_id) REFERENCES ejes_tematicos(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_leyes_espejo
        FOREIGN KEY (ley_espejo_id) REFERENCES leyes(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_leyes_eje (eje_id),
    INDEX idx_leyes_sector (sector_vinculado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. TABLA: votaciones
-- Registro nominal por legislador y ley
-- ============================================================================
CREATE TABLE votaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legislador_id INT UNSIGNED NOT NULL COMMENT 'FK a legisladores_master(id)',
    ley_id INT UNSIGNED NOT NULL COMMENT 'FK a leyes(id)',
    voto ENUM('Afirmativo', 'Negativo', 'Abstención', 'Ausente') NOT NULL,
    fecha DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_votaciones_legislador
        FOREIGN KEY (legislador_id) REFERENCES legisladores_master(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_votaciones_ley
        FOREIGN KEY (ley_id) REFERENCES leyes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_voto_leg_ley (legislador_id, ley_id),
    INDEX idx_votaciones_leg (legislador_id, voto),
    INDEX idx_votaciones_ley (ley_id, voto),
    INDEX idx_votaciones_fecha (fecha DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. TABLA: proyectos (Autoría)
-- Registro de iniciativa proactiva parlamentaria (Autor / Co-autor)
-- ============================================================================
CREATE TABLE proyectos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ley_id INT UNSIGNED NOT NULL COMMENT 'FK a leyes(id)',
    legislador_id INT UNSIGNED NOT NULL COMMENT 'FK a legisladores_master(id)',
    rol ENUM('Autor', 'Co-autor') NOT NULL DEFAULT 'Autor',
    anio YEAR NOT NULL DEFAULT 2024,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_proyectos_ley
        FOREIGN KEY (ley_id) REFERENCES leyes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_proyectos_legislador
        FOREIGN KEY (legislador_id) REFERENCES legisladores_master(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_proyecto_ley_leg (ley_id, legislador_id),
    INDEX idx_proyectos_leg_rol (legislador_id, rol, anio),
    INDEX idx_proyectos_ley (ley_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. TABLA: patrimonio_ddjj
-- Declaraciones Juradas ante la Oficina Anticorrupción ajustadas por inflación
-- ============================================================================
CREATE TABLE patrimonio_ddjj (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cuit VARCHAR(13) NOT NULL COMMENT 'FK hacia legisladores_master(cuit)',
    anio YEAR NOT NULL,
    patrimonio_declarado DECIMAL(18,2) NOT NULL COMMENT 'Patrimonio nominal en pesos',
    ajuste_inflacion DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'IPC interanual INDEC acumulado (%)',
    variacion_real_porcentaje DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Variación neta real deflactada (%)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ddjj_cuit
        FOREIGN KEY (cuit) REFERENCES legisladores_master(cuit)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_patrimonio_cuit_anio (cuit, anio),
    INDEX idx_patrimonio_anio (anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. TABLA: leyes_impacto_provincial
-- Mapea impacto regional y sentido de voto esperado provincial
-- ============================================================================
CREATE TABLE leyes_impacto_provincial (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ley_id INT UNSIGNED NOT NULL COMMENT 'FK a leyes(id)',
    provincia VARCHAR(100) NOT NULL COMMENT 'Provincia afectada',
    voto_esperado_regional ENUM('Afirmativo', 'Negativo') NOT NULL COMMENT 'Voto que favorece a la provincia',
    nivel_criticidad INT NOT NULL DEFAULT 2 COMMENT '1=Media, 2=Alta, 3=Estructural/Vital',
    descripcion_impacto TEXT NULL COMMENT 'Fundamento técnico o territorial',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_impacto_ley
        FOREIGN KEY (ley_id) REFERENCES leyes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_impacto_ley_provincia (ley_id, provincia),
    INDEX idx_impacto_provincia (provincia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. TABLA: despacho_costos
-- Estructura de asesores, masa salarial, ratio por proyecto y nepotismo
-- ============================================================================
CREATE TABLE despacho_costos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legislador_id INT UNSIGNED NOT NULL COMMENT 'FK a legisladores_master(id)',
    anio INT NOT NULL DEFAULT 2024,
    cantidad_asesores INT NOT NULL DEFAULT 0 COMMENT 'Total de asesores en nómina',
    gasto_mensual DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo salarial mensual en ARS',
    gasto_anual DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo anual estimado (mensual * 13 con SAC)',
    alertas_nepotismo INT NOT NULL DEFAULT 0 COMMENT 'Asesores con coincidencia de apellido / grupo societario',
    proyectos_autor_anio INT NOT NULL DEFAULT 0 COMMENT 'Proyectos propios como Autor en el año legislativo',
    costo_por_proyecto DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Ratio: gasto_anual / proyectos_autor_anio',
    detalle_asesores_json JSON NULL,
    fuente VARCHAR(100) NOT NULL DEFAULT 'n8n.buceo.co',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_despacho_costos_leg
        FOREIGN KEY (legislador_id) REFERENCES legisladores_master(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_despacho_leg_anio (legislador_id, anio),
    INDEX idx_despacho_eficiencia (costo_por_proyecto),
    INDEX idx_despacho_nepotismo (alertas_nepotismo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. TABLA: financiamiento_campana
-- Fondos privados de campaña electoral para cruce de conflicto de intereses
-- ============================================================================
CREATE TABLE financiamiento_campana (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legislador_id INT UNSIGNED NOT NULL COMMENT 'FK a legisladores_master(id)',
    aportante_cuit VARCHAR(13) NOT NULL COMMENT 'CUIT de la persona física o jurídica aportante',
    aportante_nombre VARCHAR(200) NOT NULL COMMENT 'Razón social o nombre del donante',
    sector_productivo VARCHAR(100) NOT NULL COMMENT 'Sector económico (Agro, Bancario, Farmacéutico, Energía, etc.)',
    monto_ars DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto aportado formalmente a la campaña',
    anio YEAR NOT NULL DEFAULT 2023,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_financiamiento_legislador
        FOREIGN KEY (legislador_id) REFERENCES legisladores_master(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_fin_sector (sector_productivo),
    INDEX idx_fin_leg (legislador_id),
    INDEX idx_fin_aportante (aportante_cuit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. TABLA: promesas_campana
-- Compromisos públicos de campaña electoral vinculados a leyes tratadas
-- ============================================================================
CREATE TABLE promesas_campana (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legislador_id INT UNSIGNED NOT NULL COMMENT 'FK a legisladores_master(id)',
    promesa_texto TEXT NOT NULL COMMENT 'Declaración textual o compromiso público',
    ley_id_vinculada INT UNSIGNED NOT NULL COMMENT 'FK a leyes(id) donde se sometió a votación la promesa',
    estado ENUM('Cumplida', 'Incumplida') NOT NULL DEFAULT 'Cumplida',
    descripcion_verificacion TEXT NULL COMMENT 'Explicación del cruce entre promesa y voto emitido',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_promesas_legislador
        FOREIGN KEY (legislador_id) REFERENCES legisladores_master(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_promesas_ley
        FOREIGN KEY (ley_id_vinculada) REFERENCES leyes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_promesas_leg (legislador_id),
    INDEX idx_promesas_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
