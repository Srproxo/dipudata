<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class BlogModel
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function all(bool $soloPublicados = false): array
    {
        $defaults = Database::getDefaultBlogArticles();
        $articulos = $defaults;
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("SELECT * FROM blog_articulos WHERE publicado = 1 ORDER BY fecha_publicacion DESC");
                $rows = $stmt ? $stmt->fetchAll() : [];
                if (!empty($rows)) {
                    if (count($rows) >= count($defaults)) {
                        $articulos = $rows;
                    } else {
                        $indexed = [];
                        foreach ($rows as $r) {
                            $indexed[$r['articulo_slug']] = $r;
                        }
                        foreach ($defaults as $d) {
                            if (!isset($indexed[$d['articulo_slug']])) {
                                $rows[] = $d;
                            }
                        }
                        $articulos = $rows;
                    }
                }
            } catch (\Throwable) {
            }
        }
        if ($soloPublicados) {
            $now = time();
            return array_values(array_filter($articulos, fn($a) => strtotime($a['fecha_publicacion']) <= $now));
        }
        return $articulos;
    }

    public function publicados(): array
    {
        return $this->all(true);
    }

    public function programados(): array
    {
        $now = time();
        $futuros = array_filter($this->all(false), fn($a) => strtotime($a['fecha_publicacion']) > $now);
        usort($futuros, fn($a, $b) => strcmp($a['fecha_publicacion'], $b['fecha_publicacion']));
        return array_values($futuros);
    }

    public function byCategoria(string $categoria, bool $soloPublicados = false): array
    {
        return array_values(array_filter($this->all($soloPublicados), fn($a) => $a['categoria_slug'] === $categoria));
    }

    public function find(string $categoria, string $slug): ?array
    {
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM blog_articulos
                    WHERE categoria_slug = :cat AND articulo_slug = :slug AND publicado = 1 LIMIT 1
                ");
                $stmt->execute([':cat' => $categoria, ':slug' => $slug]);
                $row = $stmt->fetch();
                if ($row) {
                    return $row;
                }
            } catch (\Throwable) {
            }
        }
        foreach (Database::getDefaultBlogArticles() as $a) {
            if ($a['categoria_slug'] === $categoria && $a['articulo_slug'] === $slug) {
                return $a;
            }
        }
        return null;
    }

    public function categorias(): array
    {
        $cats = [];
        foreach ($this->all() as $a) {
            $cats[$a['categoria_slug']] = ($cats[$a['categoria_slug']] ?? 0) + 1;
        }
        return $cats;
    }
}
