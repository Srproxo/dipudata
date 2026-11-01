<?php
declare(strict_types=1);

/**
 * "Cómo funciona el Congreso": textos propios, redactados a partir de la Constitución Nacional
 * (artículos citados en cada sección). Texto plano: la vista lo escapa.
 * Las cifras de bancas, bloques y provincias NO se escriben acá: salen de storage/verificado.
 */
function secciones_congreso(): array
{
    return [
        'poder-legislativo' => [
            'titulo' => 'Qué es el Congreso de la Nación',
            'bajada' => 'El Poder Legislativo nacional sanciona las leyes, controla al Poder Ejecutivo y representa a la población y a las provincias.',
            'resumen' => 'Dos cámaras, una representa a la población y otra a las provincias.',
            'partes' => [
                ['El Congreso tiene dos cámaras', [
                    'La Constitución Nacional (art. 44) dispone que el Poder Legislativo lo ejerce un Congreso compuesto por dos cámaras: la Cámara de Diputados de la Nación y el Senado de la Nación.',
                    'Los diputados representan a la población: cada provincia y la Ciudad Autónoma de Buenos Aires eligen una cantidad de bancas según sus habitantes. Los senadores representan a las provincias: cada distrito elige tres, sin importar su tamaño.',
                ]],
                ['Qué hace', [
                    'Sanciona las leyes que rigen en todo el país, aprueba el presupuesto nacional, autoriza la declaración de guerra y los tratados internacionales y presta acuerdo a ciertos nombramientos del Poder Ejecutivo.',
                    'También controla al Gobierno: puede pedir informes, citar a ministros y, en casos graves, iniciar un juicio político.',
                ]],
                ['Dos cámaras, un mismo proceso', [
                    'Para que un proyecto sea ley tiene que ser aprobado por las dos cámaras. Esa doble revisión es una de las garantías centrales del sistema. El detalle del recorrido está en la sección sobre cómo se hace una ley.',
                ]],
            ],
            'fuentes' => [['Constitución Nacional, arts. 44 a 86', 'https://www.congreso.gob.ar/constitucionNacional.php']],
        ],
        'camara-de-diputados' => [
            'titulo' => 'La Cámara de Diputados',
            'bajada' => 'Representa al pueblo de la Nación. Es la cámara donde nacen las leyes sobre impuestos y la que acusa en un juicio político.',
            'resumen' => 'Representa a la población. Se renueva por mitades cada dos años.',
            'partes' => [
                ['Cómo se integra', [
                    'Los diputados son elegidos directamente por el pueblo de cada provincia y de la Ciudad Autónoma de Buenos Aires, que funcionan como distritos electorales (art. 45). La cantidad de bancas de cada distrito depende de su población y la fija la ley.',
                    'Duran cuatro años en el cargo y la Cámara se renueva por mitades cada dos años (art. 50).',
                    'Para ser diputado hay que tener 25 años, cuatro años de ciudadanía en ejercicio y ser nativo de la provincia que lo elige o tener dos años de residencia inmediata en ella (art. 48).',
                ]],
                ['Qué le corresponde en especial', [
                    'Las leyes sobre contribuciones (impuestos) y sobre reclutamiento de tropas solo pueden tener su origen en esta cámara (art. 52).',
                    'Es también la cámara que acusa ante el Senado al Presidente, al vicepresidente, al jefe de Gabinete, a los ministros y a los miembros de la Corte Suprema (art. 53).',
                ]],
                ['Quiénes la integran hoy', []],
            ],
            'datos' => 'diputados',
            'fuentes' => [['Constitución Nacional, arts. 45 a 53', 'https://www.congreso.gob.ar/constitucionNacional.php']],
        ],
        'senado' => [
            'titulo' => 'El Senado de la Nación',
            'bajada' => 'Representa a las provincias y a la Ciudad de Buenos Aires con igual número de bancas para cada una.',
            'resumen' => 'Representa a las provincias. Tres senadores por distrito.',
            'partes' => [
                ['Cómo se integra', [
                    'Cada provincia y la Ciudad de Buenos Aires eligen tres senadores, de forma directa y conjunta. Dos bancas son para el partido más votado y una para el que le sigue (art. 54).',
                    'Duran seis años en el cargo y el Senado se renueva por tercios cada dos años, a razón de un tercio de los distritos (art. 56).',
                    'Para ser senador hay que tener 30 años, seis años de ciudadanía en ejercicio y ser nativo de la provincia que lo elige o tener dos años de residencia inmediata en ella (art. 55).',
                ]],
                ['Quién lo preside', [
                    'El vicepresidente de la Nación preside el Senado y solo vota en caso de empate (art. 57). La cámara elige además un presidente provisional que lo reemplaza cuando hace falta (art. 58).',
                ]],
                ['Qué le corresponde en especial', [
                    'Juzga en juicio político a los funcionarios acusados por Diputados (art. 59) y presta acuerdo para designar a los jueces de la Corte Suprema y a otros funcionarios que propone el Poder Ejecutivo.',
                ]],
                ['Quiénes lo integran hoy', []],
            ],
            'datos' => 'senado',
            'fuentes' => [['Constitución Nacional, arts. 54 a 59', 'https://www.congreso.gob.ar/constitucionNacional.php']],
        ],
        'como-se-hace-una-ley' => [
            'titulo' => 'Cómo se hace una ley',
            'bajada' => 'Del proyecto a la promulgación: los pasos que tiene que cumplir una norma para convertirse en ley.',
            'resumen' => 'Proyecto, comisiones, votación en ambas cámaras y promulgación.',
            'partes' => [
                ['1. Presentación del proyecto', [
                    'Las leyes pueden empezar en cualquiera de las dos cámaras, por proyectos de sus miembros o del Poder Ejecutivo, salvo las excepciones que fija la Constitución (art. 77). Los ciudadanos también pueden presentar proyectos mediante la iniciativa popular (art. 39).',
                ]],
                ['2. Estudio en comisión', [
                    'El proyecto se gira a una o más comisiones, que lo analizan, invitan a especialistas y funcionarios y, si hay acuerdo, firman un dictamen. Sin dictamen, el proyecto normalmente no llega al recinto.',
                ]],
                ['3. Debate y votación en el recinto', [
                    'Con dictamen, el proyecto se incluye en el orden del día de una sesión. Para sesionar hace falta mayoría absoluta de los miembros de la cámara (art. 64). La cámara de origen lo vota; si lo aprueba, pasa a la otra cámara, que lo revisa.',
                    'Si la cámara revisora introduce cambios, el proyecto vuelve a la de origen, que puede aceptarlos o insistir en su texto original, con las mayorías que establece el art. 81.',
                ]],
                ['4. Promulgación o veto', [
                    'Sancionado por las dos cámaras, el proyecto pasa al Poder Ejecutivo. Si lo aprueba, lo promulga y se publica en el Boletín Oficial. Si no lo devuelve en diez días hábiles, queda promulgado automáticamente (art. 80).',
                    'El Ejecutivo también puede vetarlo, total o parcialmente. En ese caso vuelve a la cámara de origen, y las dos cámaras pueden insistir con dos tercios de los votos para que se convierta en ley (art. 83).',
                ]],
            ],
            'fuentes' => [['Constitución Nacional, arts. 77 a 84', 'https://www.congreso.gob.ar/constitucionNacional.php']],
        ],
        'comisiones' => [
            'titulo' => 'Las comisiones',
            'bajada' => 'Es donde se estudian los proyectos antes de que lleguen al recinto.',
            'resumen' => 'Grupos de legisladores que analizan cada proyecto por tema.',
            'partes' => [
                ['Para qué sirven', [
                    'Las comisiones son cuerpos de asesoramiento de cada cámara, organizados por tema: educación, presupuesto, salud, justicia, relaciones exteriores y muchos otros. Allí se hace el trabajo técnico de analizar un proyecto y proponer su aprobación, su modificación o su rechazo.',
                ]],
                ['Cómo se integran', [
                    'Cada cámara forma sus comisiones con legisladores de los distintos bloques, en proporción a la cantidad de bancas de cada uno. Cada comisión tiene un presidente, vicepresidentes y secretarios.',
                ]],
                ['Tipos', [
                    'Hay comisiones permanentes, que existen en cada período, y comisiones especiales o investigadoras, que se crean para un asunto puntual. Las comisiones bicamerales están integradas por diputados y senadores.',
                ]],
                ['Las comisiones del Senado', []],
            ],
            'datos' => 'comisiones-senado',
            'fuentes' => [['Cámara de Diputados: El Congreso explicado', 'https://www.hcdn.gob.ar/congreso_explicado/index.html'], ['Senado de la Nación, datos abiertos', 'https://www.senado.gob.ar/']],
        ],
        'sesiones' => [
            'titulo' => 'Las sesiones',
            'bajada' => 'Cuándo se reúne el Congreso y qué tipos de sesión existen.',
            'resumen' => 'Ordinarias, extraordinarias, especiales e informativas.',
            'partes' => [
                ['Período ordinario', [
                    'Ambas cámaras se reúnen en sesiones ordinarias todos los años del 1° de marzo al 30 de noviembre (art. 63). El Presidente de la Nación inaugura el período ante la Asamblea Legislativa, que reúne a las dos cámaras.',
                ]],
                ['Sesiones extraordinarias', [
                    'El Poder Ejecutivo puede convocar al Congreso a sesiones extraordinarias fuera del período ordinario o prorrogar las ordinarias. En ellas se tratan únicamente los temas incluidos en la convocatoria.',
                ]],
                ['Sesiones especiales e informativas', [
                    'Los legisladores pueden pedir sesiones especiales para tratar un tema determinado. El jefe de Gabinete debe concurrir al Congreso al menos una vez por mes, alternando entre las dos cámaras, para informar sobre la marcha del Gobierno (art. 101).',
                ]],
                ['Quórum y votaciones', [
                    'Para abrir una sesión se necesita la mayoría absoluta de los miembros de la cámara (art. 64). En las votaciones nominales cada legislador vota en voz alta o por sistema electrónico y su voto queda registrado en el acta.',
                ]],
            ],
            'fuentes' => [['Constitución Nacional, arts. 63, 64 y 101', 'https://www.congreso.gob.ar/constitucionNacional.php']],
        ],
        'juicio-politico' => [
            'titulo' => 'El juicio político',
            'bajada' => 'El mecanismo para destituir al Presidente, a los ministros y a los jueces de la Corte Suprema.',
            'resumen' => 'Diputados acusa, el Senado juzga. Se necesitan dos tercios.',
            'partes' => [
                ['Quiénes pueden ser sometidos', [
                    'El Presidente, el vicepresidente, el jefe de Gabinete, los ministros y los miembros de la Corte Suprema, por mal desempeño, por delito en el ejercicio de sus funciones o por crímenes comunes (art. 53).',
                ]],
                ['La acusación', [
                    'La Cámara de Diputados decide si hay lugar a la formación de causa. Necesita el voto de dos tercios de los miembros presentes (art. 53).',
                ]],
                ['El juicio', [
                    'El Senado juzga en juicio público. Si el acusado es el Presidente, lo preside el presidente de la Corte Suprema. Nadie puede ser declarado culpable sin los votos de dos tercios de los miembros presentes (art. 59).',
                    'El fallo se limita a destituir al acusado y a inhabilitarlo para ocupar cargos públicos. Después, la justicia ordinaria puede juzgarlo por los delitos que correspondan (art. 60).',
                ]],
            ],
            'fuentes' => [['Constitución Nacional, arts. 53, 59 y 60', 'https://www.congreso.gob.ar/constitucionNacional.php']],
        ],
    ];
}
