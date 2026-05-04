<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\IntegrationTestCase;
use JsonException;
use Random\RandomException;

/** Интеграционные тесты `/api/v1/categories` и дерева категорий. */
final class CategoriesApiTest extends IntegrationTestCase
{
    /**
     * GET списка категорий без Bearer: 401.
     */
    public function testListWithoutBearerReturns401(): void
    {
        $res = $this->handle('GET', '/api/v1/categories');
        $this->assertSame(401, $res->getStatusCode());
    }

    /**
     * GET `/api/v1/categories` с Bearer: 200, непустой массив data.
     */
    public function testListReturns200(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('GET', '/api/v1/categories', [], null, $token);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertIsArray($payload['data']);
        $this->assertNotEmpty($payload['data']);
    }

    /**
     * GET `/api/v1/categories/tree`: 200, data — массив дерева.
     */
    public function testTreeReturns200(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('GET', '/api/v1/categories/tree', [], null, $token);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertIsArray($payload['data']);
    }

    /**
     * GET категории по id (ожидается сид id=1, slug electronics): 200.
     */
    public function testGetByIdReturns200(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('GET', '/api/v1/categories/1', [], null, $token);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertSame('electronics', $payload['data']['slug']);
    }

    /**
     * GET несуществующей категории: 404.
     */
    public function testGetByIdReturns404(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('GET', '/api/v1/categories/999999999', [], null, $token);
        $this->assertSame(404, $res->getStatusCode());
    }

    /**
     * POST создание категории с уникальным slug: 201.
     *
     * @throws RandomException
     * @throws JsonException
     */
    public function testCreateReturns201(): void
    {
        $token = $this->createBearerToken();
        $slug = 'phpunit-cat-' . bin2hex(random_bytes(4));
        $body = json_encode([
            'name' => 'PHPUnit category',
            'slug' => $slug,
        ], JSON_THROW_ON_ERROR);
        $res = $this->handle('POST', '/api/v1/categories', [], $body, $token);
        $this->assertSame(201, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertSame($slug, $payload['data']['slug']);
    }

    /**
     * PUT полное обновление созданной категории: 200, новое имя.
     *
     * @throws RandomException
     * @throws JsonException
     */
    public function testPutUpdateReturns200(): void
    {
        $token = $this->createBearerToken();
        $slug = 'phpunit-put-' . bin2hex(random_bytes(4));
        $create = json_encode(['name' => 'Cat PUT', 'slug' => $slug], JSON_THROW_ON_ERROR);
        $created = $this->handle('POST', '/api/v1/categories', [], $create, $token);
        $id = (int) $this->jsonResponse($created)['data']['id'];

        $update = json_encode(['name' => 'Cat PUT renamed'], JSON_THROW_ON_ERROR);
        $res = $this->handle('PUT', '/api/v1/categories/' . $id, [], $update, $token);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertSame('Cat PUT renamed', $payload['data']['name']);
    }

    /**
     * PATCH частичное обновление категории: 200.
     *
     * @throws RandomException
     * @throws JsonException
     */
    public function testPatchUpdateReturns200(): void
    {
        $token = $this->createBearerToken();
        $slug = 'phpunit-patch-' . bin2hex(random_bytes(4));
        $create = json_encode(['name' => 'Cat PATCH', 'slug' => $slug], JSON_THROW_ON_ERROR);
        $created = $this->handle('POST', '/api/v1/categories', [], $create, $token);
        $id = (int) $this->jsonResponse($created)['data']['id'];

        $patch = json_encode(['name' => 'Cat PATCH renamed'], JSON_THROW_ON_ERROR);
        $res = $this->handle('PATCH', '/api/v1/categories/' . $id, [], $patch, $token);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertSame('Cat PATCH renamed', $payload['data']['name']);
    }

    /**
     * DELETE категории с привязанными товарами (сид id=1): 409.
     */
    public function testDeleteWithProductsReturns409(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('DELETE', '/api/v1/categories/1', [], null, $token);
        $this->assertSame(409, $res->getStatusCode());
    }

    /**
     * DELETE категории без товаров и детей: 200, deleted=true.
     *
     * @throws RandomException
     * @throws JsonException
     */
    public function testDeleteEmptyCategoryReturns200(): void
    {
        $token = $this->createBearerToken();
        $slug = 'phpunit-del-' . bin2hex(random_bytes(4));
        $create = json_encode(['name' => 'Del me', 'slug' => $slug], JSON_THROW_ON_ERROR);
        $created = $this->handle('POST', '/api/v1/categories', [], $create, $token);
        $id = (int) $this->jsonResponse($created)['data']['id'];

        $del = $this->handle('DELETE', '/api/v1/categories/' . $id, [], null, $token);
        $this->assertSame(200, $del->getStatusCode());
        $payload = $this->jsonResponse($del);
        $this->assertTrue($payload['data']['deleted']);
    }

    /**
     * DELETE родительской категории при наличии дочерней: 409.
     *
     * @throws RandomException
     * @throws JsonException
     */
    public function testDeleteWhenHasChildrenReturns409(): void
    {
        $token = $this->createBearerToken();
        $slugP = 'phpunit-par-' . bin2hex(random_bytes(4));
        $createP = json_encode(['name' => 'Parent', 'slug' => $slugP], JSON_THROW_ON_ERROR);
        $parentRes = $this->handle('POST', '/api/v1/categories', [], $createP, $token);
        $this->assertSame(201, $parentRes->getStatusCode());
        $parentId = (int) $this->jsonResponse($parentRes)['data']['id'];

        $slugC = 'phpunit-ch-' . bin2hex(random_bytes(8));
        $createC = json_encode([
            'name' => 'Child',
            'slug' => $slugC,
            'parent_id' => $parentId,
        ], JSON_THROW_ON_ERROR);
        $childRes = $this->handle('POST', '/api/v1/categories', [], $createC, $token);
        $this->assertSame(201, $childRes->getStatusCode());

        $del = $this->handle('DELETE', '/api/v1/categories/' . $parentId, [], null, $token);
        $this->assertSame(409, $del->getStatusCode());
    }
}
