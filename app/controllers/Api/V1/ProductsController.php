<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ControllerBase;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategorySubtreeService;
use Phalcon\Http\Response;

class ProductsController extends ControllerBase
{
    public function indexAction(): Response
    {
        $page = max(1, (int)$this->request->get('page', 'int', 1));
        $perPage = min(100, max(1, (int)$this->request->get('per_page', 'int', 15)));
        $category = $this->request->get('category', 'string', '');
        $inStock = $this->request->get('in_stock', 'string', '');

        /** @var CategorySubtreeService $sub */
        $sub = $this->di->get('categorySubtree');

        $categoryIds = null;
        if ($category !== '') {
            $categoryIds = $sub->idsBySlug($category);
            if ($categoryIds === []) {
                return $this->jsonOk([
                    'data' => [],
                    'pagination' => [
                        'total' => 0,
                        'count' => 0,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => 0,
                    ],
                    'aggregates' => [
                        'in_stock_count' => 0,
                        'in_stock_total_value' => '0.00',
                    ],
                ]);
            }
        }

        $listConditions = [];
        $listBind = [];
        if ($categoryIds !== null) {
            $listConditions[] = 'category_id IN ({cids:array})';
            $listBind['cids'] = $categoryIds;
        }
        if ($inStock !== '') {
            $v = (int)$inStock;
            if ($v === 0 || $v === 1) {
                $listConditions[] = 'in_stock = :ins:';
                $listBind['ins'] = $v;
            }
        }

        $conditions = count($listConditions) ? implode(' AND ', $listConditions) : '';
        $countParams = [];
        if ($conditions !== '') {
            $countParams['conditions'] = $conditions;
            $countParams['bind'] = $listBind;
        }
        $total = $countParams === [] ? Product::count() : Product::count($countParams);

        $findParams = ['order' => 'id ASC'];
        if ($conditions !== '') {
            $findParams['conditions'] = $conditions;
            $findParams['bind'] = $listBind;
        }
        $findParams['limit'] = $perPage;
        $findParams['offset'] = ($page - 1) * $perPage;
        $items = Product::find($findParams);

        $categoriesById = $this->categoriesByIdForProducts($items);

        $data = [];
        foreach ($items as $p) {
            $data[] = $this->serializeProduct($p, $categoriesById);
        }

        $totalPages = (int)ceil($total / $perPage);

        $agg = $this->computeAggregates($category, $categoryIds);

        return $this->jsonOk([
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'count' => count($data),
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $totalPages,
            ],
            'aggregates' => $agg,
        ]);
    }

    /**
     * @param int[]|null $categoryIds
     * @return array{in_stock_count: int, in_stock_total_value: string}
     */
    private function computeAggregates(
        string $category,
        ?array $categoryIds
    ): array {
        $db = $this->db;

        if ($category !== '' && ($categoryIds === null || $categoryIds === [])) {
            return ['in_stock_count' => 0, 'in_stock_total_value' => '0.00'];
        }

        $where = ['in_stock = 1'];
        $bind = [];

        if ($categoryIds !== null && $categoryIds !== []) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $where[] = 'category_id IN (' . $placeholders . ')';
            foreach ($categoryIds as $id) {
                $bind[] = (int)$id;
            }
        }

        $sql = 'SELECT COUNT(*) AS cnt, COALESCE(SUM(price * quantity), 0) AS total_value FROM products WHERE '
            . implode(' AND ', $where);

        $row = $db->fetchOne($sql, \PDO::FETCH_ASSOC, $bind);

        $rawTotal = $row && isset($row['total_value']) ? (string)$row['total_value'] : '0.00';
        $totalValue = number_format((float)$rawTotal, 2, '.', '');

        return [
            'in_stock_count' => (int)($row['cnt'] ?? 0),
            'in_stock_total_value' => $totalValue,
        ];
    }

    public function getAction(): Response
    {
        $id = (int)$this->dispatcher->getParam('id');
        $p = Product::findFirst($id);
        if (!$p) {
            return $this->jsonError('Not found', 'not_found', 404);
        }

        return $this->jsonOk(['data' => $this->serializeProduct($p)]);
    }

    public function createAction(): Response
    {
        $b = $this->jsonBodyOrFail();
        if ($b instanceof Response) {
            return $b;
        }
        $e = $this->validateProductInput($b, true);
        if ($e !== null) {
            return $this->jsonError('Validation failed', 'validation', 422, $e);
        }

        $cat = Category::findFirst(['slug = :s:', 'bind' => ['s' => (string)$b['category_slug']]]);
        if (!$cat) {
            return $this->jsonError('Validation failed', 'validation', 422, [
                'category_slug' => 'Unknown category',
            ]);
        }

        $p = new Product();
        $p->category_id = (int)$cat->id;
        $p->name = trim((string)$b['name']);
        $p->content = isset($b['content']) ? (string)$b['content'] : null;
        $p->price = $this->normalizePrice($b['price']);
        $p->quantity = (int)$b['quantity'];
        $p->in_stock = isset($b['in_stock']) ? ((int)$b['in_stock'] ? 1 : 0) : ($p->quantity > 0 ? 1 : 0);

        if (!$p->save()) {
            return $this->jsonError('Could not save product', 'server', 500);
        }

        return $this->jsonOk(['data' => $this->serializeProduct($p)], 201);
    }

    public function updateAction(): Response
    {
        $id = (int)$this->dispatcher->getParam('id');
        $p = Product::findFirst($id);
        if (!$p) {
            return $this->jsonError('Not found', 'not_found', 404);
        }

        $b = $this->jsonBodyOrFail();
        if ($b instanceof Response) {
            return $b;
        }
        $e = $this->validateProductInput($b, false);
        if ($e !== null) {
            return $this->jsonError('Validation failed', 'validation', 422, $e);
        }

        if (isset($b['name'])) {
            $p->name = trim((string)$b['name']);
        }
        if (array_key_exists('content', $b)) {
            $p->content = $b['content'] === null ? null : (string)$b['content'];
        }
        if (isset($b['price'])) {
            $p->price = $this->normalizePrice($b['price']);
        }
        if (isset($b['quantity'])) {
            $p->quantity = (int)$b['quantity'];
        }
        if (isset($b['in_stock'])) {
            $p->in_stock = (int)$b['in_stock'] ? 1 : 0;
        }
        if (isset($b['category_slug'])) {
            $cat = Category::findFirst(['slug = :s:', 'bind' => ['s' => (string)$b['category_slug']]]);
            if (!$cat) {
                return $this->jsonError('Validation failed', 'validation', 422, [
                    'category_slug' => 'Unknown category',
                ]);
            }
            $p->category_id = (int)$cat->id;
        }

        if (!$p->save()) {
            return $this->jsonError('Could not save product', 'server', 500);
        }

        return $this->jsonOk(['data' => $this->serializeProduct($p)]);
    }

    public function deleteAction(): Response
    {
        $id = (int)$this->dispatcher->getParam('id');
        $p = Product::findFirst($id);
        if (!$p) {
            return $this->jsonError('Not found', 'not_found', 404);
        }
        if (!$p->delete()) {
            return $this->jsonError('Could not delete product', 'server', 500);
        }

        return $this->jsonOk(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * @return array<string, string>|null
     */
    private function validateProductInput(array $b, bool $create): ?array
    {
        $errors = [];
        if ($create || isset($b['name'])) {
            if (!isset($b['name']) || trim((string)$b['name']) === '') {
                $errors['name'] = 'Name is required';
            }
        }
        if ($create || isset($b['price'])) {
            if (!isset($b['price'])) {
                $errors['price'] = 'Price is required';
            } elseif (!is_numeric($b['price'])) {
                $errors['price'] = 'Price must be numeric';
            }
        }
        if ($create || isset($b['quantity'])) {
            if (!isset($b['quantity']) || !is_numeric($b['quantity'])) {
                $errors['quantity'] = 'Quantity is required and must be numeric';
            }
        }
        if ($create) {
            if (empty($b['category_slug'])) {
                $errors['category_slug'] = 'Category is required';
            }
        }

        return $errors === [] ? null : $errors;
    }

    private function normalizePrice(mixed $price): string
    {
        return number_format((float)$price, 2, '.', '');
    }

    /**
     * @param iterable<Product> $products
     * @return array<int, Category>
     */
    private function categoriesByIdForProducts(iterable $products): array
    {
        $ids = [];
        foreach ($products as $p) {
            if ($p->category_id) {
                $ids[(int)$p->category_id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        /** @var Category[] $rows */
        $rows = Category::find([
            'conditions' => 'id IN ({ids:array})',
            'bind'       => ['ids' => array_keys($ids)],
        ]);

        $map = [];
        foreach ($rows as $c) {
            $map[(int)$c->id] = $c;
        }

        return $map;
    }

    /**
     * @param array<int, Category>|null $categoriesById When set, categories are resolved from this map only (no per-row queries).
     */
    private function serializeProduct(Product $p, ?array $categoriesById = null): array
    {
        if ($categoriesById !== null) {
            $c = $p->category_id ? ($categoriesById[(int)$p->category_id] ?? null) : null;
        } else {
            $c = $p->category_id ? Category::findFirst((int)$p->category_id) : null;
        }

        $categoryPayload = null;
        if ($c instanceof Category) {
            $categoryPayload = [
                'id' => (int)$c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'parent_id' => $c->parent_id !== null ? (int)$c->parent_id : null,
            ];
        }

        return [
            'id' => (int)$p->id,
            'name' => $p->name,
            'content' => $p->content,
            'price' => (string)$p->price,
            'category' => $categoryPayload,
            'in_stock' => (bool)$p->in_stock,
            'quantity' => (int)$p->quantity,
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
        ];
    }
}
