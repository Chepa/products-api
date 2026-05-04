<?php

declare(strict_types=1);

namespace App\Services;

use Phalcon\Db\Adapter\AdapterInterface;

class CategorySubtreeService
{
    public function __construct(
        private readonly AdapterInterface $db
    ) {
    }

    /**
     * @return int[]
     */
    public function idsBySlug(string $slug): array
    {
        $sql = <<<SQL
            WITH RECURSIVE sub AS (
                SELECT id, parent_id FROM categories WHERE slug = :slug
                UNION ALL
                SELECT c.id, c.parent_id
                FROM categories c
                INNER JOIN sub s ON c.parent_id = s.id
            )
            SELECT id FROM sub
            SQL;

        $rows = $this->db->fetchAll($sql, \PDO::FETCH_ASSOC, ['slug' => $slug]);
        $ids  = array_map(static fn (array $r) => (int) $r['id'], $rows);

        return array_values(array_unique($ids));
    }

    /**
     * @return int[]
     */
    public function idsIncludingChildren(int $rootId): array
    {
        $sql = <<<SQL
            WITH RECURSIVE sub AS (
                SELECT id, parent_id FROM categories WHERE id = :id
                UNION ALL
                SELECT c.id, c.parent_id
                FROM categories c
                INNER JOIN sub s ON c.parent_id = s.id
            )
            SELECT id FROM sub
            SQL;

        $rows = $this->db->fetchAll($sql, \PDO::FETCH_ASSOC, ['id' => $rootId]);
        $ids  = array_map(static fn (array $r) => (int) $r['id'], $rows);

        return array_values(array_unique($ids));
    }
}
