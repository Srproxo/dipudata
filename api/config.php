<?php
declare(strict_types=1);

/**
 * LEGISDATA (dipudata.com) — Configuración de Base de Datos PDO, Auto-Bootstrap y Fallback Resiliente
 * Etapa 2: Backend PHP 8.x & Motores Algorítmicos
 */

namespace LegisData;

use PDO;
use PDOException;
use Throwable;

class Config
{
    private static ?PDO $pdo = null;
    private static bool $connectionAttempted = false;
    private static ?array $memoryStore = null;

    /**
     * Carga variables desde archivo .env si existe en la raíz del proyecto
     */
    public static function loadEnv(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $envPath = dirname(__DIR__) . '/.env';
        if (is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                        continue;
                    }
                    [$key, $val] = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val, " \t\n\r\0\x0B\"'");
                    if ($key !== '' && getenv($key) === false) {
                        putenv("{$key}={$val}");
                        $_ENV[$key] = $val;
                    }
                }
            }
        }
    }

    public static function env(string $key, string $default = ''): string
    {
        self::loadEnv();
        $val = $_ENV[$key] ?? getenv($key);
        return ($val !== false && $val !== null && $val !== '') ? (string)$val : $default;
    }

    /**
     * Obtiene conexión PDO grado bancario con sentencias preparadas nativas
     * Si MySQL no está disponible en entorno local, retorna null para activar el motor en memoria idéntico al seed.
     */
    public static function getConnection(): ?PDO
    {
        if (self::$connectionAttempted) {
            return self::$pdo;
        }
        self::$connectionAttempted = true;

        if (!extension_loaded('pdo_mysql')) {
            return null;
        }

        $localCreds = [];
        foreach ([__DIR__ . '/db_env.php', __DIR__ . '/credentials.local.php'] as $localCredsFile) {
            if (is_readable($localCredsFile)) {
                $loadedCreds = include $localCredsFile;
                if (is_array($loadedCreds)) {
                    $localCreds = $loadedCreds;
                    break;
                }
            }
        }

        $host = self::env('DB_HOST', (string)($localCreds['DB_HOST'] ?? 'localhost'));
        $db   = self::env('DB_NAME', (string)($localCreds['DB_NAME'] ?? 'legisdata'));
        $user = self::env('DB_USER', (string)($localCreds['DB_USER'] ?? 'root'));
        $pass = self::env('DB_PASS', (string)($localCreds['DB_PASS'] ?? ''));
        $port = self::env('DB_PORT', (string)($localCreds['DB_PORT'] ?? '3306'));

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 3,
            ]);

            self::autoBootstrapIfNeeded($pdo);
            self::$pdo = $pdo;
            return self::$pdo;
        } catch (PDOException) {
            self::$pdo = null;
            return null;
        }
    }

    /**
     * Auto-Bootstrap Seguro: si las tablas no existen o están vacías en MySQL,
     * ejecuta automáticamente database/schema.sql y database/seed.sql una única vez.
     */
    public static function autoBootstrapIfNeeded(PDO $pdo): void
    {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS total FROM legisladores");
            $row = $stmt ? $stmt->fetch() : false;
            if ($row && (int)$row['total'] > 0) {
                return;
            }
        } catch (Throwable) {
            // La tabla legisladores aún no existe; procedemos con el bootstrap
        }

        $baseDir = dirname(__DIR__) . '/database';
        $schemaFile = $baseDir . '/schema.sql';
        $seedFile   = $baseDir . '/seed.sql';

        if (is_readable($schemaFile)) {
            self::executeSqlFile($pdo, $schemaFile);
        }
        if (is_readable($seedFile)) {
            self::executeSqlFile($pdo, $seedFile);
        }
    }

    /**
     * Ejecuta un archivo SQL separando sentencias de manera segura
     */
    private static function executeSqlFile(PDO $pdo, string $filePath): void
    {
        $sql = file_get_contents($filePath);
        if ($sql === false || trim($sql) === '') {
            return;
        }

        // Eliminar comentarios de línea SQL (-- ...)
        $lines = explode("\n", $sql);
        $cleanLines = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '--')) {
                continue;
            }
            $cleanLines[] = $line;
        }
        $cleanSql = implode("\n", $cleanLines);

        $statements = explode(";\n", $cleanSql);
        foreach ($statements as $statement) {
            $stmt = trim($statement);
            if ($stmt === '') {
                continue;
            }
            if (str_ends_with($stmt, ';')) {
                $stmt = substr($stmt, 0, -1);
            }
            if (trim($stmt) !== '') {
                $pdo->exec($stmt);
            }
        }
    }

    /**
     * Retorna el dataset semilla completo en memoria (idéntico a database/seed.sql)
     * para garantizar resiliencia del 100% en entornos locales sin demonio MySQL activo.
     */
    public static function getMemoryStore(): array
    {
        if (self::$memoryStore !== null) {
            return self::$memoryStore;
        }

        $indicadores = [
            2019 => ['anio' => 2019, 'ipc_interanual' => 53.80,  'dolar_mep_cierre' => 73.10],
            2020 => ['anio' => 2020, 'ipc_interanual' => 36.10,  'dolar_mep_cierre' => 140.30],
            2021 => ['anio' => 2021, 'ipc_interanual' => 50.90,  'dolar_mep_cierre' => 197.80],
            2022 => ['anio' => 2022, 'ipc_interanual' => 94.80,  'dolar_mep_cierre' => 328.50],
            2023 => ['anio' => 2023, 'ipc_interanual' => 211.40, 'dolar_mep_cierre' => 995.00],
            2024 => ['anio' => 2024, 'ipc_interanual' => 117.80, 'dolar_mep_cierre' => 1165.00],
            2025 => ['anio' => 2025, 'ipc_interanual' => 32.40,  'dolar_mep_cierre' => 1280.00],
        ];

        $rawLegisladores = [
            [1, 'HCDN-1001', '20-21458912-3', 'Martin_Menem', 'Martín Alexis Menem', 'Diputados', 'La Rioja', 'La Libertad Avanza', 'Activo', 'Martín Alexis Menem (La Rioja, 19 de abril de 1975) es un abogado, empresario y político argentino. Se desempeña como Presidente de la Honorable Cámara de Diputados de la Nación desde el 10 de diciembre de 2023. Es además Vicepresidente de La Libertad Avanza a nivel nacional (junto a Karina Milei) y presidente de LLA La Rioja. Egresado en Derecho de la Universidad de Belgrano, fundó la empresa de nutrición deportiva Gentech. Hijo del histórico senador Eduardo Menem y sobrino del expresidente Carlos Menem, ejerció como diputado provincial de La Rioja (2021-2023).', 'https://upload.wikimedia.org/wikipedia/commons/3/35/MenemArmenia_%28cropped%29.jpg', 98.40, 99.10],
            [2, 'HCDN-1002', '20-14785236-9', 'Jose_Luis_Espert', 'José Luis Espert', 'Diputados', 'Buenos Aires', 'La Libertad Avanza', 'Activo', 'Doctor en Economía (UCEMA), economista y docente universitario. Presidente de la Comisión de Presupuesto y Hacienda de la Cámara de Diputados. Especializado en comercio internacional, reforma tributaria y apertura de mercados.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3c/Jos%C3%A9_Luis_Espert_2021.jpg/400px-Jos%C3%A9_Luis_Espert_2021.jpg', 95.20, 96.50],
            [3, 'HCDN-1003', '20-23654789-4', 'Gabriel_Bornoroni', 'Gabriel Bornoroni', 'Diputados', 'Córdoba', 'La Libertad Avanza', 'Activo', 'Abogado y empresario estacionero cordobés. Presidente del bloque de La Libertad Avanza en la Cámara de Diputados de la Nación. Coordinador parlamentario de los acuerdos interbloques para la sanción de la Ley de Bases.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png', 96.80, 98.70],
            [4, 'HSN-2001', '20-28963147-8', 'Ezequiel_Atauche', 'Ezequiel Atauche', 'Senadores', 'Jujuy', 'La Libertad Avanza', 'Activo', 'Empresario e ingeniero industrial jujeño. Senador Nacional por la provincia de Jujuy y Presidente del bloque de La Libertad Avanza y de la Comisión de Presupuesto y Hacienda en el Honorable Senado de la Nación.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png', 97.10, 98.90],
            [5, 'HCDN-1004', '20-18236541-7', 'Cristian_Ritondo', 'Cristian Adrián Ritondo', 'Diputados', 'Buenos Aires', 'PRO', 'Activo', 'Licenciado en Ciencia Política y dirigente del PRO. Presidente del bloque PRO en la Cámara de Diputados de la Nación. Fue Ministro de Seguridad de la Provincia de Buenos Aires (2015-2019) y Vicepresidente primero de la Legislatura porteña.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/25/Cristian_Ritondo_2019.jpg/400px-Cristian_Ritondo_2019.jpg', 92.60, 94.30],
            [6, 'HCDN-1005', '27-26147852-4', 'Silvia_Lospennato', 'Silvia Gabriela Lospennato', 'Diputados', 'Buenos Aires', 'PRO', 'Activo', 'Licenciada en Ciencia Política (UBA) con posgrado en Economía Ambiental. Diputada Nacional por la Provincia de Buenos Aires e integrante de las comisiones de Presupuesto, Legislación General y Asuntos Constitucionales.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9e/Silvia_Lospennato_2018.jpg/400px-Silvia_Lospennato_2018.jpg', 96.50, 91.80],
            [7, 'HCDN-1006', '27-23589632-1', 'Maria_Eugenia_Vidal', 'María Eugenia Vidal', 'Diputados', 'Ciudad Autónoma de Buenos Aires', 'PRO', 'Activo', 'Licenciada en Ciencia Política por la Universidad Católica Argentina. Diputada Nacional por la Ciudad Autónoma de Buenos Aires. Se desempeñó como Gobernadora de la Provincia de Buenos Aires entre 2015 y 2019.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7c/Mar%C3%ADa_Eugenia_Vidal_en_2019.jpg/400px-Mar%C3%ADa_Eugenia_Vidal_en_2019.jpg', 91.40, 93.20],
            [8, 'HSN-2002', '27-24123698-5', 'Guadalupe_Tagliaferri', 'Guadalupe Tagliaferri', 'Senadores', 'Ciudad Autónoma de Buenos Aires', 'PRO', 'Activo', 'Licenciada en Ciencia Política (UBA). Senadora Nacional por la Ciudad Autónoma de Buenos Aires y presidenta de la Comisión de Acuerdos del Senado. Ex Ministra de Desarrollo Humano y Hábitat porteña.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4e/Guadalupe_Tagliaferri.jpg/400px-Guadalupe_Tagliaferri.jpg', 95.80, 86.40],
            [9, 'HCDN-1007', '20-27852147-3', 'Rodrigo_de_Loredo', 'Rodrigo de Loredo', 'Diputados', 'Córdoba', 'UCR', 'Activo', 'Abogado graduado en la Universidad Nacional de Córdoba con maestría en Derecho Empresario. Presidente del bloque de la Unión Cívica Radical en la Cámara de Diputados de la Nación.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/59/Rodrigo_de_Loredo.jpg/400px-Rodrigo_de_Loredo.jpg', 93.80, 89.50],
            [10, 'HCDN-1008', '27-22369852-9', 'Karina_Banfi', 'Karina Banfi', 'Diputados', 'Buenos Aires', 'UCR', 'Activo', 'Abogada egresada de la UBA y especialista en políticas de transparencia, acceso a la información pública y derechos humanos. Vicepresidenta del bloque de la Unión Cívica Radical en Diputados.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/18/Karina_Banfi.jpg/400px-Karina_Banfi.jpg', 97.40, 91.20],
            [11, 'HSN-2003', '20-21789456-2', 'Martin_Lousteau', 'Martín Lousteau', 'Senadores', 'Ciudad Autónoma de Buenos Aires', 'UCR', 'Activo', 'Licenciado en Economía (UdeSA) y Magister en Ciencias Económicas por la London School of Economics. Senador Nacional por CABA y Presidente del Comité Nacional de la Unión Cívica Radical.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/07/Mart%C3%ADn_Lousteau_2020.jpg/400px-Mart%C3%ADn_Lousteau_2020.jpg', 94.10, 82.60],
            [12, 'HCDN-1009', '20-24563214-5', 'German_Martinez', 'Germán Pedro Martínez', 'Diputados', 'Santa Fe', 'Unión por la Patria', 'Activo', 'Licenciado en Ciencia Política por la Universidad Nacional de Rosario. Diputado Nacional por la provincia de Santa Fe y Presidente del bloque Unión por la Patria en la Cámara de Diputados de la Nación.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c8/Germ%C3%A1n_Mart%C3%ADnez_Diputado.jpg/400px-Germ%C3%A1n_Mart%C3%ADnez_Diputado.jpg', 97.90, 98.40],
            [13, 'HCDN-1010', '20-25147896-1', 'Leandro_Santoro', 'Leandro Santoro', 'Diputados', 'Ciudad Autónoma de Buenos Aires', 'Unión por la Patria', 'Activo', 'Politólogo graduado en la Universidad de Buenos Aires y docente universitario. Diputado Nacional por la Ciudad Autónoma de Buenos Aires e integrante de las comisiones de Presupuesto y Relaciones Exteriores.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/Leandro_Santoro_2021.jpg/400px-Leandro_Santoro_2021.jpg', 94.60, 95.80],
            [14, 'HCDN-1011', '27-23145698-7', 'Victoria_Tolosa_Paz', 'Victoria Tolosa Paz', 'Diputados', 'Buenos Aires', 'Unión por la Patria', 'Activo', 'Contadora Pública Nacional egresada de la Universidad Católica de La Plata. Diputada Nacional por la Provincia de Buenos Aires y ex Ministra de Desarrollo Social de la Nación (2022-2023).', 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/87/Victoria_Tolosa_Paz_2021.jpg/400px-Victoria_Tolosa_Paz_2021.jpg', 93.20, 96.90],
            [15, 'HSN-2004', '20-12456987-0', 'Jose_Mayans', 'José Miguel Ángel Mayans', 'Senadores', 'Formosa', 'Unión por la Patria', 'Activo', 'Senador Nacional por la provincia de Formosa desde 2001 y Presidente del interbloque de Unión por la Patria en el Honorable Senado de la Nación. Referente parlamentario del peronismo federal.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/62/Jos%C3%A9_Mayans_2018.jpg/400px-Jos%C3%A9_Mayans_2018.jpg', 96.20, 97.50],
            [16, 'HSN-2005', '27-21852963-3', 'Juliana_Di_Tullio', 'Juliana Di Tullio', 'Senadores', 'Buenos Aires', 'Unión por la Patria', 'Activo', 'Psicóloga social y dirigente política bonaerense. Senadora Nacional por la Provincia de Buenos Aires y presidenta del bloque Unidad Ciudadana / Unión por la Patria en la Cámara Alta.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b2/Juliana_Di_Tullio_2021.jpg/400px-Juliana_Di_Tullio_2021.jpg', 95.40, 98.80],
            [17, 'HCDN-1012', '20-08456321-6', 'Miguel_Angel_Pichetto', 'Miguel Ángel Pichetto', 'Diputados', 'Buenos Aires', 'Encuentro Federal', 'Activo', 'Abogado graduado en la Universidad Nacional de La Plata. Presidente del bloque Encuentro Federal en la Cámara de Diputados. Fue Senador Nacional durante tres mandatos e integrante de la Auditoría General de la Nación.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/44/Miguel_%C3%81ngel_Pichetto_2019.jpg/400px-Miguel_%C3%81ngel_Pichetto_2019.jpg', 94.90, 88.20],
            [18, 'HCDN-1013', '20-29654123-8', 'Juan_Manuel_Lopez', 'Juan Manuel López', 'Diputados', 'Buenos Aires', 'Coalición Cívica', 'Activo', 'Abogado constitucionalista egresado de la Universidad de Buenos Aires. Presidente del bloque de la Coalición Cívica ARI en la Cámara de Diputados de la Nación, especializado en institucionalidad y control republicano.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d3/Juan_Manuel_L%C3%B3pez_diputado.jpg/400px-Juan_Manuel_L%C3%B3pez_diputado.jpg', 98.10, 94.70],
            [19, 'HCDN-1014', '27-22741852-6', 'Myriam_Bregman', 'Myriam Teresa Bregman', 'Diputados', 'Ciudad Autónoma de Buenos Aires', 'Frente de Izquierda', 'Mandato Cumplido', 'Abogada laboralista y de derechos humanos egresada de la UBA. Referente parlamentaria del Frente de Izquierda y de Trabajadores - Unidad (PTS) por la Ciudad Autónoma de Buenos Aires.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9f/Myriam_Bregman_2021.jpg/400px-Myriam_Bregman_2021.jpg', 93.50, 99.40],
            [20, 'HSN-2006', '20-16985214-2', 'Carlos_Arce', 'Carlos Omar Arce', 'Senadores', 'Misiones', 'Innovación Federal', 'Activo', 'Médico tocoginecólogo y político misionero. Senador Nacional por la provincia de Misiones en representación del Frente Renovador de la Concordia (Innovación Federal) y ex Vicegobernador provincial.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/User_icon_2.svg/400px-User_icon_2.svg.png', 94.30, 87.90],
        ];

        $legisladores = [];
        foreach ($rawLegisladores as $r) {
            $id = (int)$r[0];
            $wikiSlug = (string)$r[3];
            $legisladores[$id] = [
                'id'                   => $id,
                'id_congreso'          => $r[1],
                'cuit'                 => $r[2],
                'wiki_slug'            => $wikiSlug,
                'slug'                 => ($id === 1) ? 'martin-alexis-menem' : strtolower(str_replace('_', '-', $wikiSlug)),
                'nombre_completo'      => $r[4],
                'camara'               => $r[5],
                'provincia'            => $r[6],
                'bloque_actual'        => $r[7],
                'bloque'               => $r[7],
                'estado'               => $r[8],
                'biografia'            => $r[9],
                'foto_url'             => $r[10],
                'asistencia_historica' => (float)$r[11],
                'lealtad_partidaria'   => (float)$r[12],
                'asistencia_pct'       => (float)$r[11],
                'lealtad_pct'          => (float)$r[12],
                'updated_at'           => '2025-05-15 12:00:00',
            ];
        }

        $patrimonioSeries = [
            1  => [2020 => 48500000.00, 2021 => 72400000.00, 2022 => 134800000.00, 2023 => 445000000.00, 2024 => 568000000.00, 2025 => 652000000.00],
            2  => [2020 => 39200000.00, 2021 => 58900000.00, 2022 => 104500000.00, 2023 => 338000000.00, 2024 => 412000000.00, 2025 => 436000000.00],
            3  => [2020 => 54000000.00, 2021 => 83100000.00, 2022 => 152000000.00, 2023 => 498000000.00, 2024 => 625000000.00, 2025 => 681000000.00],
            4  => [2020 => 61500000.00, 2021 => 94200000.00, 2022 => 178000000.00, 2023 => 585000000.00, 2024 => 732000000.00, 2025 => 810000000.00],
            5  => [2020 => 92000000.00, 2021 => 148000000.00, 2022 => 289000000.00, 2023 => 985000000.00, 2024 => 1210000000.00, 2025 => 1328000000.00],
            6  => [2020 => 12800000.00, 2021 => 18100000.00, 2022 => 30900000.00, 2023 => 94500000.00, 2024 => 112000000.00, 2025 => 121800000.00],
            7  => [2020 => 24500000.00, 2021 => 35800000.00, 2022 => 61200000.00, 2023 => 192000000.00, 2024 => 228000000.00, 2025 => 249000000.00],
            8  => [2020 => 15400000.00, 2021 => 21200000.00, 2022 => 34800000.00, 2023 => 104000000.00, 2024 => 123000000.00, 2025 => 136200000.00],
            9  => [2020 => 28900000.00, 2021 => 42500000.00, 2022 => 76400000.00, 2023 => 241000000.00, 2024 => 289000000.00, 2025 => 314500000.00],
            10 => [2020 => 11200000.00, 2021 => 15900000.00, 2022 => 26500000.00, 2023 => 81200000.00, 2024 => 94800000.00, 2025 => 103700000.00],
            11 => [2020 => 44600000.00, 2021 => 66100000.00, 2022 => 114000000.00, 2023 => 358000000.00, 2024 => 431000000.00, 2025 => 472000000.00],
            12 => [2020 => 13500000.00, 2021 => 20400000.00, 2022 => 36900000.00, 2023 => 121000000.00, 2024 => 146000000.00, 2025 => 158000000.00],
            13 => [2020 => 14800000.00, 2021 => 21400000.00, 2022 => 36200000.00, 2023 => 111000000.00, 2024 => 131500000.00, 2025 => 143500000.00],
            14 => [2020 => 36400000.00, 2021 => 59800000.00, 2022 => 118500000.00, 2023 => 412000000.00, 2024 => 502000000.00, 2025 => 545000000.00],
            15 => [2020 => 27500000.00, 2021 => 46200000.00, 2022 => 94800000.00, 2023 => 336000000.00, 2024 => 410000000.00, 2025 => 447000000.00],
            16 => [2020 => 18900000.00, 2021 => 29500000.00, 2022 => 54200000.00, 2023 => 179000000.00, 2024 => 214000000.00, 2025 => 231000000.00],
            17 => [2020 => 33800000.00, 2021 => 50400000.00, 2022 => 91200000.00, 2023 => 289000000.00, 2024 => 344000000.00, 2025 => 374000000.00],
            18 => [2020 => 10600000.00, 2021 => 14800000.00, 2022 => 24500000.00, 2023 => 73800000.00, 2024 => 86200000.00, 2025 => 95600000.00],
            19 => [2020 => 6800000.00,  2021 => 9100000.00,  2022 => 14600000.00,  2023 => 42900000.00,  2024 => 50100000.00,  2025 => 56100000.00],
            20 => [2020 => 21500000.00, 2021 => 31200000.00, 2022 => 55100000.00, 2023 => 174000000.00, 2024 => 206000000.00, 2025 => 223800000.00],
        ];

        $leyes = [
            '27742' => [
                'id' => '27742',
                'titulo' => 'Ley de Bases y Puntos de Partida para la Libertad de los Argentinos',
                'resumen' => 'Establece la emergencia pública administrativa y económica, la reforma del Estado, privatizaciones de empresas públicas y el Régimen de Incentivo para Grandes Inversiones (RIGI).',
                'fecha_votacion' => '2024-06-28',
                'eje_tematico' => 'MercadoLibre',
                'peso_ideologico' => 1.00,
            ],
            'DNU-70-2023' => [
                'id' => 'DNU-70-2023',
                'titulo' => 'DNU 70/2023 — Bases para la Reconstrucción de la Economía Argentina',
                'resumen' => 'Desregulación integral del comercio interior y exterior, derogación de la Ley de Alquileres, Ley de Góndolas y Ley de Abastecimiento, y liberación de precios y contratos en moneda extranjera.',
                'fecha_votacion' => '2024-03-14',
                'eje_tematico' => 'MercadoLibre',
                'peso_ideologico' => 0.95,
            ],
            '27743' => [
                'id' => '27743',
                'titulo' => 'Medidas Fiscales Paliativas y Relevantes (Paquete Fiscal)',
                'resumen' => 'Régimen de Regularización de Activos (blanqueo de capitales), reducción progresiva del Impuesto sobre los Bienes Personales, moratoria fiscal y restitución de la cuarta categoría de Ganancias.',
                'fecha_votacion' => '2024-06-28',
                'eje_tematico' => 'MercadoLibre',
                'peso_ideologico' => 0.85,
            ],
            '27642' => [
                'id' => '27642',
                'titulo' => 'Ley de Promoción de la Alimentación Saludable (Etiquetado Frontal)',
                'resumen' => 'Obliga a incorporar sellos de advertencia octogonales negros en alimentos y bebidas envasadas con exceso de azúcares, sodio, grasas saturadas y calorías, regulando su publicidad y comercialización.',
                'fecha_votacion' => '2021-10-26',
                'eje_tematico' => 'Proteccionismo',
                'peso_ideologico' => 0.75,
            ],
            '27551' => [
                'id' => '27551',
                'titulo' => 'Ley de Alquileres — Reforma del Régimen de Locaciones Urbanas',
                'resumen' => 'Extiende el plazo mínimo legal de los contratos de locación habitacional a tres años y establece un índice oficial único de actualización anual combinando IPC y RIPTE.',
                'fecha_votacion' => '2020-06-11',
                'eje_tematico' => 'Proteccionismo',
                'peso_ideologico' => 0.85,
            ],
            '27605' => [
                'id' => '27605',
                'titulo' => 'Aporte Solidario y Extraordinario para Morigerar los Efectos de la Pandemia',
                'resumen' => 'Crea un tributo extraordinario sobre los patrimonios de personas humanas superiores a los 200 millones de pesos con destino a equipamiento sanitario, subsidios a PyMEs y urbanización de barrios populares.',
                'fecha_votacion' => '2020-12-04',
                'eje_tematico' => 'Proteccionismo',
                'peso_ideologico' => 0.95,
            ],
            '27610' => [
                'id' => '27610',
                'titulo' => 'Ley de Acceso a la Interrupción Voluntaria del Embarazo (IVE)',
                'resumen' => 'Regula el acceso a la interrupción voluntaria del embarazo y a la atención posaborto en todo el sistema de salud público, privado y de obras sociales hasta la semana catorce de gestación.',
                'fecha_votacion' => '2020-12-30',
                'eje_tematico' => 'Salud',
                'peso_ideologico' => 0.90,
            ],
            '27678' => [
                'id' => '27678',
                'titulo' => 'Ley Nacional de Cuidados Paliativos Integrales',
                'resumen' => 'Asegura el acceso de los pacientes a las prestaciones integrales sobre cuidados paliativos en el ámbito público, privado y de la seguridad social, incluyendo el acompañamiento familiar.',
                'fecha_votacion' => '2022-07-05',
                'eje_tematico' => 'Salud',
                'peso_ideologico' => 0.80,
            ],
            'EXP-1420-D-2025' => [
                'id' => 'EXP-1420-D-2025',
                'titulo' => 'Emergencia Sanitaria Pediátrica y Financiamiento del Hospital Garrahan',
                'resumen' => 'Declara la emergencia sanitaria de la salud pediátrica y de las residencias médicas nacionales por dos años, garantizando recomposición presupuestaria y salarial de carácter intangible.',
                'fecha_votacion' => '2025-05-14',
                'eje_tematico' => 'Salud',
                'peso_ideologico' => 0.95,
            ],
            'EXP-2810-D-2024' => [
                'id' => 'EXP-2810-D-2024',
                'titulo' => 'Ley de Financiamiento de las Universidades Nacionales',
                'resumen' => 'Establece la actualización bimestral automática por inflación (IPC INDEC) de los gastos de funcionamiento de las universidades públicas nacionales y la recomposición salarial docente y no docente.',
                'fecha_votacion' => '2024-09-12',
                'eje_tematico' => 'Educacion',
                'peso_ideologico' => 1.00,
            ],
            '27614' => [
                'id' => '27614',
                'titulo' => 'Ley de Financiamiento del Sistema Nacional de Ciencia, Tecnología e Innovación',
                'resumen' => 'Establece un cronograma de incremento progresivo y sostenido de la inversión pública nacional en ciencia y tecnología hasta alcanzar el 1% del Producto Bruto Interno (PBI) en 2032.',
                'fecha_votacion' => '2021-02-24',
                'eje_tematico' => 'Educacion',
                'peso_ideologico' => 0.85,
            ],
            'EXP-0915-D-2024' => [
                'id' => 'EXP-0915-D-2024',
                'titulo' => 'Declaración de la Educación Obligatoria como Servicio Estratégico Esencial',
                'resumen' => 'Garantiza el cumplimiento de los 180 días mínimos de clase estableciendo guardias educativas mínimas del 30% al 50% del personal escolar durante medidas de fuerza.',
                'fecha_votacion' => '2024-08-15',
                'eje_tematico' => 'Educacion',
                'peso_ideologico' => 0.80,
            ],
            '27555' => [
                'id' => '27555',
                'titulo' => 'Régimen Legal del Contrato de Teletrabajo',
                'resumen' => 'Regula los presupuestos legales mínimos para la modalidad de teletrabajo, consagrando el derecho a la desconexión digital, la reversibilidad contractual y la provisión de equipamiento por el empleador.',
                'fecha_votacion' => '2020-07-30',
                'eje_tematico' => 'Laboral',
                'peso_ideologico' => 0.85,
            ],
            '27718' => [
                'id' => '27718',
                'titulo' => 'Exención del Impuesto a las Ganancias para Guardias Médicas y Horas Extras',
                'resumen' => 'Exime del pago del Impuesto a las Ganancias a las remuneraciones percibidas en concepto de guardias obligatorias —activas o pasivas— y horas extras por profesionales y técnicos de la salud.',
                'fecha_votacion' => '2023-05-04',
                'eje_tematico' => 'Laboral',
                'peso_ideologico' => 0.75,
            ],
            'EXP-3105-D-2024' => [
                'id' => 'EXP-3105-D-2024',
                'titulo' => 'Régimen de Tutela Sindical y Recálculo de Indemnizaciones por Despido',
                'resumen' => 'Proyecto de fortalecimiento de las indemnizaciones agravadas por trabajo no registrado y actualización de créditos laborales por IPC más tasa pura del 6% anual.',
                'fecha_votacion' => '2024-04-30',
                'eje_tematico' => 'Laboral',
                'peso_ideologico' => 0.95,
            ],
        ];

        $ordenLeyes = [
            '27742', 'DNU-70-2023', '27743',
            '27642', '27551', '27605',
            '27610', '27678', 'EXP-1420-D-2025',
            'EXP-2810-D-2024', '27614', 'EXP-0915-D-2024',
            '27555', '27718', 'EXP-3105-D-2024',
        ];

        $matrizVotos = [
            1  => ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
            2  => ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
            3  => ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Negativo'],
            4  => ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Negativo', 'Negativo', 'Negativo', 'Abstencion', 'Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
            5  => ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Negativo', 'Afirmativo', 'Abstencion', 'Negativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
            6  => ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
            7  => ['Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Ausente', 'Negativo', 'Negativo', 'Afirmativo', 'Abstencion', 'Negativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Negativo'],
            8  => ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
            9  => ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
            10 => ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion'],
            11 => ['Abstencion', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion'],
            12 => ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
            13 => ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
            14 => ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
            15 => ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
            16 => ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
            17 => ['Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo', 'Abstencion', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion', 'Afirmativo', 'Negativo'],
            18 => ['Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Negativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo'],
            19 => ['Negativo', 'Negativo', 'Negativo', 'Afirmativo', 'Abstencion', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo'],
            20 => ['Afirmativo', 'Abstencion', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Negativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Afirmativo', 'Abstencion'],
        ];

        $votos = [];
        foreach ($matrizVotos as $legId => $sentidos) {
            $votos[$legId] = [];
            foreach ($ordenLeyes as $idx => $leyId) {
                $votos[$legId][$leyId] = $sentidos[$idx];
            }
        }

        self::$memoryStore = [
            'indicadores'  => $indicadores,
            'legisladores' => $legisladores,
            'patrimonio'   => $patrimonioSeries,
            'leyes'        => $leyes,
            'votos'        => $votos,
        ];

        return self::$memoryStore;
    }
}
