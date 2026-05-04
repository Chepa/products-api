<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\IntegrationTestCase;
use JsonException;

/** Интеграционные тесты CRUD `/api/v1/products`. */
final class ProductsApiTest extends IntegrationTestCase
{
    /**
     * GET /api/v1/products без Authorization: 401, ошибка типа auth.
     */
    public function testListWithoutBearerReturns401(): void
    {
        $res = $this->handle('GET', '/api/v1/products');
        $payload = $this->jsonResponse($res);
        $this->assertFalse($payload['success']);
        $this->assertSame('auth', $payload['error']['type']);
    }

    /**
     * GET /api/v1/products с валидным Bearer: 200, структура success, data, pagination, aggregates.
     */
    public function testListWithBearerReturns200AndShape(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('GET', '/api/v1/products', [], null, $token);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('pagination', $payload);
        $this->assertArrayHasKey('aggregates', $payload);
    }

    /**
     * Фильтр по несуществующему slug категории: пустой список и total = 0.
     */
    public function testListWithCategoryFilterEmptySlug(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle(
            'GET',
            '/api/v1/products',
            ['category' => 'no-such-category-slug-xyz'],
            null,
            $token
        );
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertSame([], $payload['data']);
        $this->assertSame(0, $payload['pagination']['total']);
    }

    /**
     * GET /api/v1/products/1 с Bearer: 200 и ожидаемый id в data.
     */
    public function testGetByIdReturns200(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('GET', '/api/v1/products/1', [], null, $token);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertSame(1, $payload['data']['id']);
    }

    /**
     * GET несуществующего товара: 404.
     */
    public function testGetByIdReturns404(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('GET', '/api/v1/products/999999999', [], null, $token);
        $this->assertSame(404, $res->getStatusCode());
    }

    /**
     * POST с пустым телом `{}`: валидация не проходит, 422.
     */
    public function testCreateValidation422(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('POST', '/api/v1/products', [], '{}', $token);
        $this->assertSame(422, $res->getStatusCode());
    }

    /**
     * POST создание товара: 201, имя и slug категории в ответе.
     *
     * @throws JsonException
     */
    public function testCreateReturns201(): void
    {
        $token = $this->createBearerToken();
        $body = json_encode([
            'name' => 'PHPUnit product',
            'price' => 12.5,
            'quantity' => 2,
            'category_slug' => 'electronics',
            'in_stock' => 1,
        ], JSON_THROW_ON_ERROR);
        $res = $this->handle('POST', '/api/v1/products', [], $body, $token);
        $this->assertSame(201, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertSame('PHPUnit product', $payload['data']['name']);
        $this->assertSame('electronics', $payload['data']['category']['slug']);
    }

    /**
     * PUT полного обновления товара после создания: 200 и новое имя.
     *
     * @throws JsonException
     */
    public function testPutUpdateReturns200(): void
    {
        $token = $this->createBearerToken();
        $createBody = json_encode([
            'name' => 'Tmp PUT',
            'price' => 1,
            'quantity' => 1,
            'category_slug' => 'electronics',
        ], JSON_THROW_ON_ERROR);
        $created = $this->handle('POST', '/api/v1/products', [], $createBody, $token);
        $id = (int) $this->jsonResponse($created)['data']['id'];

        $updateBody = json_encode([
            'name' => 'Tmp PUT updated',
            'price' => 2,
            'quantity' => 3,
        ], JSON_THROW_ON_ERROR);
        $res = $this->handle('PUT', '/api/v1/products/' . $id, [], $updateBody, $token);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertSame('Tmp PUT updated', $payload['data']['name']);
    }

    /**
     * PATCH частичного обновления: 200 и изменённое имя.
     *
     * @throws JsonException
     */
    public function testPatchUpdateReturns200(): void
    {
        $token = $this->createBearerToken();
        $createBody = json_encode([
            'name' => 'Tmp PATCH',
            'price' => 1,
            'quantity' => 1,
            'category_slug' => 'electronics',
        ], JSON_THROW_ON_ERROR);
        $created = $this->handle('POST', '/api/v1/products', [], $createBody, $token);
        $id = (int) $this->jsonResponse($created)['data']['id'];

        $patchBody = json_encode(['name' => 'Tmp PATCH renamed'], JSON_THROW_ON_ERROR);
        $res = $this->handle('PATCH', '/api/v1/products/' . $id, [], $patchBody, $token);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertSame('Tmp PATCH renamed', $payload['data']['name']);
    }

    /**
     * DELETE товара: 200 и признак deleted в data.
     *
     * @throws JsonException
     */
    public function testDeleteReturns200(): void
    {
        $token = $this->createBearerToken();
        $createBody = json_encode([
            'name' => 'To delete',
            'price' => 1,
            'quantity' => 0,
            'category_slug' => 'electronics',
            'in_stock' => 0,
        ], JSON_THROW_ON_ERROR);
        $created = $this->handle('POST', '/api/v1/products', [], $createBody, $token);
        $id = (int) $this->jsonResponse($created)['data']['id'];

        $del = $this->handle('DELETE', '/api/v1/products/' . $id, [], null, $token);
        $this->assertSame(200, $del->getStatusCode());
        $payload = $this->jsonResponse($del);
        $this->assertTrue($payload['data']['deleted']);
    }
}
