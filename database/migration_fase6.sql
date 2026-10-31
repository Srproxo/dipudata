-- ============================================================================
-- DIPUDATA / FASE 6 — MIGRACIÓN: EFICIENCIA PARLAMENTARIA Y NEPOTISMO (VÍA n8n)
-- Tabla despacho_costos: costos de asesores, ratio por proyecto y alertas de nepotismo
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS despacho_costos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legislador_id INT UNSIGNED NOT NULL COMMENT 'FK a legisladores_master(id)',
    anio INT NOT NULL DEFAULT 2024 COMMENT 'Año legislativo auditado',
    total_asesores INT NOT NULL DEFAULT 0 COMMENT 'Total de asesores en planta transitoria y permanente',
    costo_mensual_ars DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Gasto mensual en masa salarial de asesores (ARS)',
    costo_anual_ars DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Gasto anual proyectado con aguinaldo (ARS)',
    alertas_nepotismo INT NOT NULL DEFAULT 0 COMMENT 'Cantidad de asesores con coincidencia de apellido o vínculos societarios',
    proyectos_autor_anio INT NOT NULL DEFAULT 0 COMMENT 'Cantidad de proyectos propios presentados en el año legislativo',
    costo_por_proyecto_ars DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Campo calculado: costo anual / proyectos propios',
    detalle_asesores_json JSON NULL COMMENT 'Metadatos procesados por n8n (nombres, categorías, vínculos)',
    fuente VARCHAR(100) NOT NULL DEFAULT 'n8n.buceo.co' COMMENT 'Sistema sincronizador de origen',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_despacho_legislador
        FOREIGN KEY (legislador_id) REFERENCES legisladores_master(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_despacho_legislador_anio (legislador_id, anio),
    INDEX idx_despacho_eficiencia (costo_por_proyecto_ars),
    INDEX idx_despacho_nepotismo (alertas_nepotismo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sembrar costos base iniciales para los 8 legisladores auditados
INSERT INTO despacho_costos (legislador_id, anio, total_asesores, costo_mensual_ars, costo_anual_ars, alertas_nepotismo, proyectos_autor_anio, costo_por_proyecto_ars) VALUES
(1, 2024, 14, 21500000.00, 279500000.00, 2, 5, 55900000.00),
(2, 2024, 18, 27800000.00, 361400000.00, 1, 5, 72280000.00),
(3, 2024, 11, 16500000.00, 214500000.00, 0, 5, 42900000.00),
(4, 2024, 15, 23200000.00, 301600000.00, 0, 5, 60320000.00),
(5, 2024, 12, 19000000.00, 247000000.00, 0, 5, 49400000.00),
(6, 2024,  6,  8900000.00, 115700000.00, 0, 5, 23140000.00),
(7, 2024, 22, 35600000.00, 462800000.00, 0, 5, 92560000.00),
(8, 2024, 24, 38900000.00, 505700000.00, 1, 5, 101140000.00)
ON DUPLICATE KEY UPDATE
    total_asesores = VALUES(total_asesores),
    costo_mensual_ars = VALUES(costo_mensual_ars),
    costo_anual_ars = VALUES(costo_anual_ars),
    alertas_nepotismo = VALUES(alertas_nepotismo),
    proyectos_autor_anio = VALUES(proyectos_autor_anio),
    costo_por_proyecto_ars = VALUES(costo_por_proyecto_ars);
