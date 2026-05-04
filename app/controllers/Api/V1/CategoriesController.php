<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ControllerBase;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategorySubtreeService;
use App\Support\Transliteration;
use Phalcon\Http\Response;

class CategoriesController extends ControllerBase
{
    public function indexAction(): Response
    {
        $rows = Category::find(['order' => 'id ASC']);
        $data = array_map(fn (Category $c) => $this->serializeCategory($c, false), iterator_to_array($rows));

        return $this->jsonOk(['data' => $data]);
    }

    public function treeAction(): Response
    {
        $rows   = Category::find(['order' => 'id ASC']);
        $byRoot = [];
        foreach ($rows as $c) {
            $pid = $c->parent_id === null ? 0 : (int) $c->parent_id;
            if (!isset($byRoot[$pid])) {
                $byRoot[$pid] = [];
            }
            $byRoot[$pid][] = $c;
        }
        $tree = $this->buildTree($byRoot, 0);

        return $this->jsonOk(['data' => $tree]);
    }

    /**
     * @param array<int, Category[]> $byRoot
     * @return list<array<string, mixed>>
     */
    private function buildTree(array $byRoot, int $parentId): array
    {
        if (!isset($byRoot[$parentId])) {
            return [];
        }
        $out = [];
        foreach ($byRoot[$parentId] as $c) {
            $out[] = [
                'id'         => (int) $c->id,
                'name'       => $c->name,
                'slug'       => $c->slug,
                'parent_id'  => $c->parent_id !== null ? (int) $c->parent_id : null,
                'children'   => $this->buildTree($byRoot, (int) $c->id),
            ];
        }

        return $out;
    }

    public function getAction(): Response
    {
        $id = (int) $this->dispatcher->getParam('id');
        $c  = Category::findFirst($id);
        if (!$c) {
            return $this->jsonError('Not found', 'not_found', 404);
        }

        return $this->jsonOk(['data' => $this->serializeCategory($c, true)]);
    }

    public function createAction(): Response
    {
        $b = $this->jsonBodyOrFail();
        if ($b instanceof Response) {
            return $b;
        }
        $e = $this->validateCategory($b, true);
        if ($e !== null) {
            return $this->jsonError('Validation failed', 'validation', 422, $e);
        }

        $slug = $this->resolveSlug($b);
        if ($slug === '') {
            return $this->jsonError('Validation failed', 'validation', 422, [
                'slug' => 'Slug is required (or provide a valid name)',
            ]);
        }

        if (Category::findFirst(['slug = :s:', 'bind' => ['s' => $slug]])) {
            return $this->jsonError('Validation failed', 'validation', 422, [
                'slug' => 'Slug already exists',
            ]);
        }

        $parentId = null;
        if (isset($b['parent_id']) && $b['parent_id'] !== null && $b['parent_id'] !== '') {
            $pid = (int) $b['parent_id'];
            $p   = Category::findFirst($pid);
            if (!$p) {
                return $this->jsonError('Validation failed', 'validation', 422, [
                    'parent_id' => 'Parent category not found',
                ]);
            }
            $parentId = $pid;
        }

        $c             = new Category();
        $c->name       = trim((string) $b['name']);
        $c->slug       = $slug;
        $c->parent_id  = $parentId;

        if (!$c->save()) {
            return $this->jsonError('Could not save category', 'server', 500);
        }

        return $this->jsonOk(['data' => $this->serializeCategory($c, true)], 201);
    }

    public function updateAction(): Response
    {
        $id = (int) $this->dispatcher->getParam('id');
        $c  = Category::findFirst($id);
        if (!$c) {
            return $this->jsonError('Not found', 'not_found', 404);
        }

        $b = $this->jsonBodyOrFail();
        if ($b instanceof Response) {
            return $b;
        }
        $e = $this->validateCategory($b, false);
        if ($e !== null) {
            return $this->jsonError('Validation failed', 'validation', 422, $e);
        }

        if (isset($b['name'])) {
            $c->name = trim((string) $b['name']);
        }

        if (array_key_exists('slug', $b)) {
            $slug = $this->slugify((string) $b['slug']);
            if ($slug === '') {
                return $this->jsonError('Validation failed', 'validation', 422, ['slug' => 'Invalid slug']);
            }
            $other = Category::findFirst(['slug = :s: AND id != :id:', 'bind' => ['s' => $slug, 'id' => $id]]);
            if ($other) {
                return $this->jsonError('Validation failed', 'validation', 422, ['slug' => 'Slug already exists']);
            }
            $c->slug = $slug;
        }

        if (array_key_exists('parent_id', $b)) {
            if ($b['parent_id'] === null || $b['parent_id'] === '') {
                $c->parent_id = null;
            } else {
                $pid = (int) $b['parent_id'];
                if ($pid === $id) {
                    return $this->jsonError('Validation failed', 'validation', 422, [
                        'parent_id' => 'Category cannot be its own parent',
                    ]);
                }
                $p = Category::findFirst($pid);
                if (!$p) {
                    return $this->jsonError('Validation failed', 'validation', 422, [
                        'parent_id' => 'Parent category not found',
                    ]);
                }
                /** @var CategorySubtreeService $sub */
                $sub    = $this->di->get('categorySubtree');
                $desc   = $sub->idsIncludingChildren($id);
                if (in_array($pid, $desc, true)) {
                    return $this->jsonError('Validation failed', 'validation', 422, [
                        'parent_id' => 'Cannot set descendant as parent (cycle)',
                    ]);
                }
                $c->parent_id = $pid;
            }
        }

        if (!$c->save()) {
            return $this->jsonError('Could not save category', 'server', 500);
        }

        return $this->jsonOk(['data' => $this->serializeCategory($c, true)]);
    }

    public function deleteAction(): Response
    {
        $id = (int) $this->dispatcher->getParam('id');
        $c  = Category::findFirst($id);
        if (!$c) {
            return $this->jsonError('Not found', 'not_found', 404);
        }

        $child = Category::findFirst(['parent_id = :id:', 'bind' => ['id' => $id]]);
        if ($child) {
            return $this->jsonError('Category has subcategories', 'conflict', 409);
        }

        $prod = Product::findFirst(['category_id = :id:', 'bind' => ['id' => $id]]);
        if ($prod) {
            return $this->jsonError('Category has products', 'conflict', 409);
        }

        if (!$c->delete()) {
            return $this->jsonError('Could not delete category', 'server', 500);
        }

        return $this->jsonOk(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * @return array<string, string>|null
     */
    private function validateCategory(array $b, bool $create): ?array
    {
        $errors = [];
        if ($create) {
            if (!isset($b['name']) || trim((string) $b['name']) === '') {
                $errors['name'] = 'Name is required';
            }
        }

        return $errors === [] ? null : $errors;
    }

    /**
     * @param array<string, mixed> $b
     */
    private function resolveSlug(array $b): string
    {
        if (!empty($b['slug'])) {
            return $this->slugify((string) $b['slug']);
        }

        return $this->slugify((string) ($b['name'] ?? ''));
    }

    private function slugify(string $s): string
    {
        $s = Transliteration::toLatinAscii($s);
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9\-]+/u', '-', $s) ?? '';
        $s = trim((string) $s, '-');

        return $s;
    }

    private function serializeCategory(Category $c, bool $includeTimestamps): array
    {
        $row = [
            'id'        => (int) $c->id,
            'name'      => $c->name,
            'slug'      => $c->slug,
            'parent_id' => $c->parent_id !== null ? (int) $c->parent_id : null,
        ];
        if ($includeTimestamps) {
            $row['created_at'] = $c->created_at;
            $row['updated_at'] = $c->updated_at;
        }

        return $row;
    }
}
