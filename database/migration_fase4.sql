-- ============================================================================
-- DIPUDATA / FASE 4 — MIGRACIÓN: ÍNDICE DE DEFENSA PROVINCIAL Y MAPEO REGIONAL
-- Tensión Partido vs. Provincia (Auditoría de Disciplina Partidaria)
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS leyes_impacto_provincial (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ley_id BIGINT UNSIGNED NULL COMMENT 'Foreign Key opcional a votaciones(id)',
    ley VARCHAR(255) NOT NULL COMMENT 'Nombre o carátula oficial de la ley evaluada',
    provincia VARCHAR(100) NOT NULL COMMENT 'Provincia afectada (ej. Córdoba, La Rioja, Buenos Aires, Santa Fe)',
    voto_esperado_regional ENUM('Afirmativo', 'Negativo') NOT NULL COMMENT 'Sentido del voto que favorece a los intereses de la provincia',
    nivel_criticidad INT NOT NULL DEFAULT 2 COMMENT 'Criticidad de 1 a 3 (1=Baja, 2=Media, 3=Crítica/Vital)',
    descripcion_impacto TEXT NULL COMMENT 'Fundamento de por qué este voto favorece o perjudica a la provincia',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_impacto_ley_votaciones
        FOREIGN KEY (ley_id) REFERENCES votaciones(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_impacto_provincia (provincia),
    INDEX idx_impacto_ley (ley)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sembrar casos testigo de impacto provincial (ej. Córdoba, La Rioja, Buenos Aires, Santa Fe, CABA, Formosa)
INSERT INTO leyes_impacto_provincial (ley, provincia, voto_esperado_regional, nivel_criticidad, descripcion_impacto) VALUES
-- CÓRDOBA
('Ley de Manejo del Fuego y Protección Serrana', 'Córdoba', 'Afirmativo', 3, 'Protege las sierras y cuencas hídricas cordobesas prohibiendo la especulación inmobiliaria y el cambio de uso del suelo en zonas arrasadas por incendios forestales.'),
('Financiamiento de Universidades Nacionales', 'Córdoba', 'Afirmativo', 3, 'Garantiza el sostenimiento y salarios de la Universidad Nacional de Córdoba (UNC), motor educativo y científico vital para la provincia.'),
('Baja de Retenciones al Agro y Fondos Provinciales', 'Córdoba', 'Afirmativo', 3, 'El sector agroindustrial cordobés aporta divisas determinantes; la quita o coparticipación de retenciones impacta de forma directa en el empleo y la producción del interior.'),

-- LA RIOJA
('Emergencia Pediátrica y Hospitales Nacionales', 'La Rioja', 'Afirmativo', 3, 'Asegura derivaciones, insumos y financiamiento coparticipable para la atención de alta complejidad médica de niños y familias riojanas.'),
('Financiamiento de Universidades Nacionales', 'La Rioja', 'Afirmativo', 3, 'Sostiene el funcionamiento de la Universidad Nacional de La Rioja (UNLaR) y sus sedes del interior provincial (Chilecito, Chamical).'),
('Baja de Retenciones al Agro y Fondos Provinciales', 'La Rioja', 'Afirmativo', 2, 'Defiende los recursos coparticipables y fondos compensadores para el desarrollo de economías regionales (olivo, vid, nuez).'),

-- BUENOS AIRES
('Régimen de Promoción de la Industria Autopartista', 'Buenos Aires', 'Afirmativo', 3, 'La provincia alberga más del 65% del entramado de autopartes y terminales automotrices; fomenta miles de empleos calificados en el cordón bonaerense.'),
('Financiamiento de Universidades Nacionales', 'Buenos Aires', 'Afirmativo', 3, 'Financia más de 20 universidades nacionales en el territorio bonaerense (UNLP, UNLaM, UNSAM, etc.) donde cursan más de 500.000 estudiantes.'),
('Movilidad Jubilatoria y Pensiones por Discapacidad', 'Buenos Aires', 'Afirmativo', 2, 'Buenos Aires concentra el 38% del padrón total de jubilados del país; la actualización por IPC protege el poder de compra y consumo en el conurbano e interior.'),

-- SANTA FE
('Ley Antimafias y Régimen Penal Juvenil', 'Santa Fe', 'Afirmativo', 3, 'Herramientas penales y despliegue federal urgente reclamados por las autoridades provinciales para combatir el narcotráfico y balaceras en el Gran Rosario.'),
('Baja de Retenciones al Agro y Fondos Provinciales', 'Santa Fe', 'Afirmativo', 3, 'El complejo agroexportador del polo de Rosario y la cuenca lechera santafesina requieren reducción de retenciones para reinversión regional.'),

-- CIUDAD AUTÓNOMA DE BUENOS AIRES
('Financiamiento de Universidades Nacionales', 'Ciudad Autónoma de Buenos Aires', 'Afirmativo', 3, 'Garantiza el funcionamiento de las facultades de la Universidad de Buenos Aires (UBA), centros de investigación de CONICET y hospitales universitarios.'),
('Emergencia Pediátrica y Hospitales Nacionales', 'Ciudad Autónoma de Buenos Aires', 'Afirmativo', 3, 'El Hospital Garrahan y el Hospital de Niños Ricardo Gutiérrez tienen sede en CABA y atienden la más alta complejidad pediátrica del país.'),

-- FORMOSA
('Baja de Retenciones al Agro y Fondos Provinciales', 'Formosa', 'Afirmativo', 3, 'Defensa irrestricta de la coparticipación federal de impuestos y compensaciones automáticas que financian la administración pública provincial.'),
('Financiamiento de Universidades Nacionales', 'Formosa', 'Afirmativo', 2, 'Presupuesto esencial para la Universidad Nacional de Formosa (UNaF) y arraigo de profesionales en la provincia.')
ON DUPLICATE KEY UPDATE 
    voto_esperado_regional = VALUES(voto_esperado_regional),
    nivel_criticidad = VALUES(nivel_criticidad),
    descripcion_impacto = VALUES(descripcion_impacto);
