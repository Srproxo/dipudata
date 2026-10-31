-- ============================================================================
-- DIPUDATA / MATCH ELECTORAL 360 — MIGRACIÓN FASE 3
-- Agenda Proactiva e Iniciativa Legislativa (Proyectos y Firmas)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabla proyectos
CREATE TABLE IF NOT EXISTS proyectos (
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

-- 2. Tabla pivote proyectos_firmas (Autor y Co-autor)
CREATE TABLE IF NOT EXISTS proyectos_firmas (
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

SET FOREIGN_KEY_CHECKS = 1;
