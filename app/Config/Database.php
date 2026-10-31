<?php
declare(strict_types=1);

namespace App\Config;

require_once dirname(__DIR__, 2) . '/api/config.php';

use LegisData\Config;
use PDO;
use Throwable;

/**
 * Capa de acceso a datos MVC para DipuData / Match Electoral 360
 * Reutiliza la conexión PDO grado bancario y asegura el auto-bootstrap de
 * legisladores_master, patrimonio_ddjj_cuit, votaciones y blog_articulos.
 */
class Database
{
    private static bool $mvcBootstrapped = false;

    public static function getConnection(): ?PDO
    {
        $pdo = Config::getConnection();
        if ($pdo !== null && !self::$mvcBootstrapped) {
            self::$mvcBootstrapped = true;
            self::ensureMvcTables($pdo);
        }
        return $pdo;
    }

    public static function slugify(string $text): string
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ];
        $clean = strtr(trim($text), $map);
        $clean = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $clean));
        return trim($clean, '-');
    }

    private static function ensureMvcTables(PDO $pdo): void
    {
        $hasTables = false;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS c FROM leyes_impacto_provincial");
            $row = $stmt ? $stmt->fetch() : false;
            if ($row && (int)$row['c'] >= 0) {
                $hasTables = true;
            }
        } catch (Throwable) {
            $hasTables = false;
        }

        if (!$hasTables) {
            $files = [
                dirname(__DIR__, 2) . '/database/schema_mvc_360.sql',
                dirname(__DIR__, 2) . '/database/migration_fase2.sql',
                dirname(__DIR__, 2) . '/database/migration_fase3.sql',
                dirname(__DIR__, 2) . '/database/migration_fase4.sql',
            ];
            foreach ($files as $file) {
                if (is_readable($file)) {
                    $sql = (string)file_get_contents($file);
                    foreach (explode(';', $sql) as $statement) {
                        $statement = trim($statement);
                        if ($statement !== '') {
                            try {
                                $pdo->exec($statement);
                            } catch (Throwable) {
                            }
                        }
                    }
                }
            }
        }

        self::seedInitialData($pdo);
    }

    public static function seedInitialData(PDO $pdo): void
    {
        $bios = self::bios();
        self::importCsvSeeds($pdo, $bios);
    }

    public static function bios(): array
    {
        return [
            'HCDN-1001' => 'Abogado y empresario argentino graduado en la Universidad de Belgrano. Diputado Nacional por La Rioja y Presidente de la H. Cámara de Diputados de la Nación. Impulsor de la desregulación económica, el equilibrio fiscal y la apertura comercial.',
            'HCDN-1002' => 'Político y dirigente deportivo argentino. Presidente del bloque PRO en la Cámara de Diputados de la Nación y ex Ministro de Seguridad de la Provincia de Buenos Aires.',
            'HCDN-1003' => 'Abogado y político argentino por la Provincia de Córdoba. Presidente del bloque de la Unión Cívica Radical en la Cámara de Diputados, con foco en educación universitaria y competitividad productiva.',
            'HCDN-1004' => 'Licenciado en Ciencia Política por la Universidad Nacional de Rosario. Diputado Nacional por Santa Fe y jefe del bloque Unión por la Patria, enfocado en defensa de la industria nacional y el presupuesto público.',
            'HCDN-1005' => 'Abogado y legislador de extensa trayectoria institucional. Presidente del bloque Encuentro Federal en la Cámara de Diputados y ex Auditor General de la Nación, promotor de acuerdos federales y productivos.',
            'HCDN-1006' => 'Abogada laboralista, activista de derechos humanos y dirigente del PTS en el Frente de Izquierda y de Trabajadores - Unidad. Defensora de la salud y educación pública estatal.',
            'HSN-2001'  => 'Economista, ex Ministro de Economía de la Nación y Senador Nacional por la Ciudad Autónoma de Buenos Aires (UCR). Especializado en macroeconomía, financiamiento educativo y desarrollo productivo.',
            'HSN-2002'  => 'Político argentino y Senador Nacional por la provincia de Formosa. Presidente del interbloque de Unión por la Patria en el Senado de la Nación.',
        ];
    }

    public static function getDefaultEjes(): array
    {
        return [
            1  => ['id' => 1,  'nombre_eje' => 'Salud Pública', 'descripcion' => 'Políticas sanitarias, financiamiento hospitalario, vacunas y emergencias de salud pública.'],
            2  => ['id' => 2,  'nombre_eje' => 'Educación', 'descripcion' => 'Presupuesto para universidades y escuelas, salario docente, infraestructura educativa e investigación.'],
            3  => ['id' => 3,  'nombre_eje' => 'Apertura Comercial', 'descripcion' => 'Desregulación arancelaria, comercio exterior, importaciones libres y acuerdos bilaterales.'],
            4  => ['id' => 4,  'nombre_eje' => 'Industria Nacional', 'descripcion' => 'Protección y estímulo a PyMEs industriales, empleo fabril y desarrollo de proveedores locales.'],
            5  => ['id' => 5,  'nombre_eje' => 'Previsión Social y Discapacidad', 'descripcion' => 'Fórmula de movilidad jubilatoria, cobertura de medicamentos y pensiones no contributivas por discapacidad.'],
            6  => ['id' => 6,  'nombre_eje' => 'Medio Ambiente y Derechos Humanos', 'descripcion' => 'Protección de glaciares y humedales, transición energética, soberanía ambiental y derechos humanos.'],
            7  => ['id' => 7,  'nombre_eje' => 'Seguridad y Sistema Penal', 'descripcion' => 'Régimen penal juvenil, Código Penal, lucha contra el crimen organizado y endurecimiento de penas.'],
            8  => ['id' => 8,  'nombre_eje' => 'Género y Cuidados', 'descripcion' => 'Sistema integral de cuidados, régimen de licencias laborales familiares y prevención de violencias.'],
            9  => ['id' => 9,  'nombre_eje' => 'Federalismo y Agro', 'descripcion' => 'Distribución equitativa de coparticipación, retenciones agropecuarias y desarrollo regional.'],
            10 => ['id' => 10, 'nombre_eje' => 'Transparencia Institucional', 'descripcion' => 'Ficha Limpia, declaraciones juradas públicas, acceso a la información y control del gasto político.'],
            11 => ['id' => 11, 'nombre_eje' => 'Tecnología y Trabajo', 'descripcion' => 'Regulación del empleo en plataformas digitales, economía del conocimiento, telecomunicaciones e IA.'],
        ];
    }

    private static function importCsvSeeds(PDO $pdo, array $bios): void
    {
        // 1. Sembrar ejes temáticos si no existen
        $stmtEjes = $pdo->prepare("
            INSERT INTO ejes_tematicos (id, nombre_eje, descripcion, activo)
            VALUES (:id, :nombre, :desc, 1)
            ON DUPLICATE KEY UPDATE nombre_eje = VALUES(nombre_eje), descripcion = VALUES(descripcion)
        ");
        foreach (self::getDefaultEjes() as $eje) {
            $stmtEjes->execute([
                ':id'     => $eje['id'],
                ':nombre' => $eje['nombre_eje'],
                ':desc'   => $eje['descripcion'],
            ]);
        }

        $csvVotos = dirname(__DIR__, 2) . '/storage/csv/votaciones_datacp.csv';
        if (is_readable($csvVotos) && ($handle = fopen($csvVotos, 'r')) !== false) {
            $headers = fgetcsv($handle);
            $stmtLeg = $pdo->prepare("
                INSERT INTO legisladores_master
                    (nombre, slug, camara, provincia, bloque, estado, id_recinto, cuit, nombre_wiki, biografia_extracto, indice_lealtad, asistencia)
                VALUES
                    (:nombre, :slug, :camara, :provincia, :bloque, :estado, :id_recinto, :cuit, :nombre_wiki, :bio, :indice_lealtad, :asistencia)
                ON DUPLICATE KEY UPDATE
                    nombre = VALUES(nombre),
                    bloque = VALUES(bloque),
                    indice_lealtad = VALUES(indice_lealtad),
                    asistencia = VALUES(asistencia)
            ");
            $stmtVoto = $pdo->prepare("
                INSERT INTO votaciones (id_recinto, ley, eje_id, voto, fecha)
                VALUES (:id_recinto, :ley, :eje_id, :voto, :fecha)
                ON DUPLICATE KEY UPDATE voto = VALUES(voto), eje_id = VALUES(eje_id)
            ");

            while (($row = fgetcsv($handle)) !== false) {
                if (!$headers || count($row) < count($headers)) {
                    continue;
                }
                $data = array_combine($headers, $row);
                if (!is_array($data)) {
                    continue;
                }
                $idRecinto = trim((string)$data['id_recinto']);
                $nombre = trim((string)$data['nombre']);
                $stmtLeg->execute([
                    ':nombre'         => $nombre,
                    ':slug'           => self::slugify($nombre),
                    ':camara'         => trim((string)$data['camara']),
                    ':provincia'      => trim((string)$data['provincia']),
                    ':bloque'         => trim((string)$data['bloque']),
                    ':estado'         => trim((string)$data['estado']),
                    ':id_recinto'     => $idRecinto,
                    ':cuit'           => trim((string)$data['cuit']),
                    ':nombre_wiki'    => trim((string)$data['nombre_wiki']),
                    ':bio'            => $bios[$idRecinto] ?? 'Legislador Nacional del Congreso de la Nación Argentina.',
                    ':indice_lealtad' => (float)$data['indice_lealtad'],
                    ':asistencia'     => (float)$data['asistencia'],
                ]);

                $ejeId = isset($data['eje_id']) && is_numeric($data['eje_id']) ? (int)$data['eje_id'] : 1;
                $stmtVoto->execute([
                    ':id_recinto' => $idRecinto,
                    ':ley'        => trim((string)$data['ley']),
                    ':eje_id'     => $ejeId,
                    ':voto'       => trim((string)$data['voto']),
                    ':fecha'      => trim((string)$data['fecha']),
                ]);
            }
            fclose($handle);
        }

        $csvDdjj = dirname(__DIR__, 2) . '/storage/csv/ddjj_anticorrupcion.csv';
        if (is_readable($csvDdjj) && ($handle = fopen($csvDdjj, 'r')) !== false) {
            $headers = fgetcsv($handle);
            $stmtDdjj = $pdo->prepare("
                INSERT INTO patrimonio_ddjj_cuit (cuit, anio, monto_declarado, ajuste_inflacion_ipc, variacion_real_porcentaje)
                VALUES (:cuit, :anio, :monto_declarado, :ajuste_inflacion_ipc, :variacion_real_porcentaje)
                ON DUPLICATE KEY UPDATE
                    monto_declarado = VALUES(monto_declarado),
                    ajuste_inflacion_ipc = VALUES(ajuste_inflacion_ipc),
                    variacion_real_porcentaje = VALUES(variacion_real_porcentaje)
            ");
            while (($row = fgetcsv($handle)) !== false) {
                if (!$headers || count($row) < count($headers)) {
                    continue;
                }
                $data = array_combine($headers, $row);
                if (!is_array($data)) {
                    continue;
                }
                $stmtDdjj->execute([
                    ':cuit'                      => trim((string)$data['cuit']),
                    ':anio'                      => (int)$data['anio'],
                    ':monto_declarado'           => (float)$data['monto_declarado'],
                    ':ajuste_inflacion_ipc'      => (float)$data['ajuste_inflacion_ipc'],
                    ':variacion_real_porcentaje' => (float)$data['variacion_real_porcentaje'],
                ]);
            }
            fclose($handle);
        }

        $stmtBlog = $pdo->prepare("
            INSERT INTO blog_articulos (categoria_slug, articulo_slug, titulo, meta_descripcion, contenido_html, publicado)
            VALUES (:cat, :slug, :titulo, :meta, :html, 1)
            ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), contenido_html = VALUES(contenido_html)
        ");

        $articulos = self::getDefaultBlogArticles();
        foreach ($articulos as $art) {
            $stmtBlog->execute([
                ':cat'    => $art['categoria_slug'],
                ':slug'   => $art['articulo_slug'],
                ':titulo' => $art['titulo'],
                ':meta'   => $art['meta_descripcion'],
                ':html'   => $art['contenido_html'],
            ]);
        }

        // 3. Sembrar Proyectos de Ley e Iniciativa Legislativa (Fase 3)
        $csvProyectos = dirname(__DIR__, 2) . '/storage/csv/proyectos_iniciativa.csv';
        if (is_readable($csvProyectos) && ($handle = fopen($csvProyectos, 'r')) !== false) {
            $headers = fgetcsv($handle);
            $stmtProy = $pdo->prepare("
                INSERT INTO proyectos (expediente, titulo_sumario, fecha_presentacion, eje_id, estado_tramite)
                VALUES (:expediente, :titulo, :fecha, :eje_id, :estado)
                ON DUPLICATE KEY UPDATE
                    titulo_sumario = VALUES(titulo_sumario),
                    estado_tramite = VALUES(estado_tramite),
                    eje_id = VALUES(eje_id)
            ");
            $stmtFirma = $pdo->prepare("
                INSERT INTO proyectos_firmas (proyecto_id, legislador_id, rol)
                SELECT p.id, l.id, :rol
                FROM proyectos p, legisladores_master l
                WHERE p.expediente = :expediente AND l.id_recinto = :id_recinto
                ON DUPLICATE KEY UPDATE rol = VALUES(rol)
            ");

            while (($row = fgetcsv($handle)) !== false) {
                if (!$headers || count($row) < count($headers)) {
                    continue;
                }
                $data = array_combine($headers, $row);
                if (!is_array($data)) {
                    continue;
                }
                $exp = trim((string)$data['expediente']);
                $stmtProy->execute([
                    ':expediente' => $exp,
                    ':titulo'     => trim((string)$data['titulo_sumario']),
                    ':fecha'      => trim((string)$data['fecha_presentacion']),
                    ':eje_id'     => (int)$data['eje_id'],
                    ':estado'     => trim((string)$data['estado_tramite']),
                ]);
                $stmtFirma->execute([
                    ':rol'        => trim((string)$data['rol']),
                    ':expediente' => $exp,
                    ':id_recinto' => trim((string)$data['legislador_id_recinto']),
                ]);
            }
            fclose($handle);
        }

        // 4. Sembrar Mapeo de Impacto Provincial (Fase 4 - Tensión Partido vs Provincia)
        $csvImpacto = dirname(__DIR__, 2) . '/storage/csv/leyes_impacto_provincial.csv';
        if (is_readable($csvImpacto) && ($handle = fopen($csvImpacto, 'r')) !== false) {
            $headers = fgetcsv($handle);
            $stmtImpacto = $pdo->prepare("
                INSERT INTO leyes_impacto_provincial (ley, provincia, voto_esperado_regional, nivel_criticidad, descripcion_impacto, ley_id)
                VALUES (:ley, :provincia, :voto_esperado, :criticidad, :descripcion, (SELECT id FROM votaciones WHERE ley = :ley_match LIMIT 1))
                ON DUPLICATE KEY UPDATE
                    voto_esperado_regional = VALUES(voto_esperado_regional),
                    nivel_criticidad = VALUES(nivel_criticidad),
                    descripcion_impacto = VALUES(descripcion_impacto)
            ");
            while (($row = fgetcsv($handle)) !== false) {
                if (!$headers || count($row) < count($headers)) {
                    continue;
                }
                $data = array_combine($headers, $row);
                if (!is_array($data)) {
                    continue;
                }
                $ley = trim((string)$data['ley']);
                $stmtImpacto->execute([
                    ':ley'           => $ley,
                    ':ley_match'     => $ley,
                    ':provincia'     => trim((string)$data['provincia']),
                    ':voto_esperado' => trim((string)$data['voto_esperado_regional']),
                    ':criticidad'    => (int)$data['nivel_criticidad'],
                    ':descripcion'   => trim((string)$data['descripcion_impacto']),
                ]);
            }
            fclose($handle);
        }
    }

    public static function getDefaultBlogArticles(): array
    {
        return [
            [
                'categoria_slug'   => 'educacion-financiera',
                'articulo_slug'    => 'como-auditar-patrimonio-legisladores-inflacion-ipc',
                'titulo'           => 'Cómo auditar las Declaraciones Juradas de los legisladores descontando la inflación (IPC)',
                'meta_descripcion' => 'Guía práctica de educación financiera cívica para distinguir el incremento nominal en pesos del crecimiento patrimonial real en las DDJJ ante la Oficina Anticorrupción.',
                'fecha_publicacion'=> '2026-10-30 10:00:00',
                'contenido_html'   => '<p>En contextos de alta nominalidad, analizar el patrimonio declarado por un funcionario público en pesos corrientes genera distorsiones severas. Para realizar una auditoría rigurosa, <strong>DipuData 360</strong> cruza cada declaración jurada presentada ante la Oficina Anticorrupción con el Índice de Precios al Consumidor (IPC) publicado por el INDEC.</p>'
                    . '<h2>Fórmula de Variación Patrimonial Real</h2>'
                    . '<p>Cuando auditamos perfiles parlamentarios como el de Martín Alexis Menem en la Presidencia de la Cámara o el de Cristian Ritondo en la bancada del PRO, el indicador clave no es cuánto subió el monto nominal en pesos, sino si ese incremento superó la tasa de inflación acumulada del período fiscal.</p>'
                    . '<p>De igual modo, en los debates sobre presupuesto universitario y equilibrio fiscal donde intervinieron Rodrigo de Loredo, Germán Martínez y Martín Lousteau, la trazabilidad entre el patrimonio declarado vía CUIT y el sentido del voto nominal en el recinto permite al ciudadano evaluar la coherencia material e ideológica de sus representantes.</p>'
                    . '<h2>Transparencia Activa y Similitud del Coseno</h2>'
                    . '<p>Contrastando posturas opuestas —desde la agenda de apertura comercial hasta la defensa estatal impulsada por Myriam Bregman o el federalismo legislativo de Miguel Ángel Pichetto y José Mayans—, nuestro motor matemático transforma cada votación nominal en vectores comparables con tus preferencias ciudadanas.</p>',
            ],
            [
                'categoria_slug'   => 'auditoria-legislativa',
                'articulo_slug'    => 'mapa-votaciones-congreso-apertura-comercial-vs-industria-nacional',
                'titulo'           => 'Apertura Comercial vs. Industria Nacional: así votó cada bloque en el Congreso',
                'meta_descripcion' => 'Radiografía de datos sobre las votaciones clave de desregulación económica, aranceles tecnológicos, salud pública y financiamiento universitario.',
                'fecha_publicacion'=> '2026-10-31 09:30:00',
                'contenido_html'   => '<p>El registro nominal de votaciones de DataCP revela cómo se reconfiguraron las alianzas parlamentarias en torno a cuatro ejes estructurales: <strong>Salud Pública</strong>, <strong>Educación Pública</strong>, <strong>Industria Nacional</strong> y <strong>Apertura Comercial</strong>.</p>'
                    . '<h2>Bloques y Disciplina Partidaria</h2>'
                    . '<p>Mientras que Martín Alexis Menem y Cristian Ritondo mantuvieron una alta cohesión afirmativa en las leyes de desregulación y apertura comercial, legisladores como Rodrigo de Loredo y Miguel Ángel Pichetto combinaron el apoyo a reformas económicas con votos afirmativos en financiamiento educativo y promoción autopartista.</p>'
                    . '<p>En el polo opuesto del espectro parlamentario, Germán Martínez, José Mayans y Myriam Bregman concentraron sus votos afirmativos en los ejes de Salud Pública y Educación Pública, rechazando los capítulos de desregulación arancelaria, mientras que en el Senado Martín Lousteau marcó disidencias propias frente al oficialismo.</p>',
            ],
        ];
    }
}
