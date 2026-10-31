-- ============================================================================
-- DIPUDATA / MATCH ELECTORAL 360 — MIGRACIÓN FASE 2
-- Normalización de Ejes Temáticos: De ENUM a Tabla Relacional Dinámica (11 Ejes)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Crear tabla ejes_tematicos
CREATE TABLE IF NOT EXISTS ejes_tematicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_eje VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ejes_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insertar los 11 ejes temáticos obligatorios
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
(11, 'Tecnología y Trabajo', 'Regulación del empleo en plataformas digitales, economía del conocimiento, telecomunicaciones e IA.', 1)
ON DUPLICATE KEY UPDATE 
    nombre_eje = VALUES(nombre_eje),
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- 3. Modificación de la tabla votaciones: sustituir ENUM por clave foránea eje_id
-- Paso 3.1: Agregar columna eje_id si no existe
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'votaciones' 
      AND COLUMN_NAME = 'eje_id'
);
SET @sql_add_col = IF(@col_exists = 0, 
    'ALTER TABLE votaciones ADD COLUMN eje_id INT UNSIGNED NULL AFTER ley', 
    'SELECT 1'
);
PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

-- Paso 3.2: Migrar datos si existe columna previa eje_tematico
SET @enum_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'votaciones' 
      AND COLUMN_NAME = 'eje_tematico'
);

SET @sql_migrate_data = IF(@enum_exists > 0, 
    'UPDATE votaciones SET eje_id = CASE 
        WHEN eje_tematico LIKE "%Salud%" THEN 1
        WHEN eje_tematico LIKE "%Educac%" THEN 2
        WHEN eje_tematico LIKE "%Apertura%" OR eje_tematico LIKE "%Mercado%" THEN 3
        WHEN eje_tematico LIKE "%Industria%" OR eje_tematico LIKE "%Proteccion%" THEN 4
        ELSE 1
     END WHERE eje_id IS NULL',
    'SELECT 1'
);
PREPARE stmt_migrate FROM @sql_migrate_data;
EXECUTE stmt_migrate;
DEALLOCATE PREPARE stmt_migrate;

-- Asignar valor por defecto para cualquier fila huérfana
UPDATE votaciones SET eje_id = 1 WHERE eje_id IS NULL;

-- Asegurar restricción NOT NULL
ALTER TABLE votaciones MODIFY COLUMN eje_id INT UNSIGNED NOT NULL;

-- Paso 3.3: Eliminar columna antigua eje_tematico si todavía existe
SET @sql_drop_enum = IF(@enum_exists > 0, 
    'ALTER TABLE votaciones DROP COLUMN eje_tematico', 
    'SELECT 1'
);
PREPARE stmt_drop FROM @sql_drop_enum;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

-- Paso 3.4: Clave foránea hacia ejes_tematicos(id)
-- Eliminar restricción previa si existe para evitar duplicados
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'votaciones' 
      AND CONSTRAINT_NAME = 'fk_votaciones_eje'
);
SET @sql_add_fk = IF(@fk_exists = 0,
    'ALTER TABLE votaciones ADD CONSTRAINT fk_votaciones_eje FOREIGN KEY (eje_id) REFERENCES ejes_tematicos(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt_fk FROM @sql_add_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- Paso 3.5: Índice compuesto de alto rendimiento para perfilamiento y match
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'votaciones' 
      AND INDEX_NAME = 'idx_votaciones_recinto_eje_id'
);
SET @sql_add_idx = IF(@idx_exists = 0,
    'ALTER TABLE votaciones ADD INDEX idx_votaciones_recinto_eje_id (id_recinto, eje_id, voto)',
    'SELECT 1'
);
PREPARE stmt_idx FROM @sql_add_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET FOREIGN_KEY_CHECKS = 1;
