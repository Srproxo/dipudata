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

    public function all(): array
    {
        if ($this->pdo) {
            return $this->pdo->query("SELECT * FROM blog_articulos WHERE publicado = 1 ORDER BY fecha_publicacion DESC")->fetchAll();
        }
        return Database::getDefaultBlogArticles();
    }

    public function byCategoria(string $categoria): array
    {
        return array_values(array_filter($this->all(), fn($a) => $a['categoria_slug'] === $categoria));
    }

    public function find(string $categoria, string $slug): ?array
    {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM blog_articulos
                WHERE categoria_slug = :cat AND articulo_slug = :slug AND publicado = 1 LIMIT 1
            ");
            $stmt->execute([':cat' => $categoria, ':slug' => $slug]);
            return $stmt->fetch() ?: null;
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
