<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\IntegrationTestCase;
use JsonException;

/** Интеграционные тесты эндпоинтов `/api/v1/tokens/*`. */
final class TokensApiTest extends IntegrationTestCase
{
    /**
     * POST `/api/v1/tokens/generate` с именем: 201, токен и имя в data.
     *
     * @throws JsonException
     */
    public function testGenerateReturnsToken(): void
    {
        $body = json_encode(['name' => 'phpunit'], JSON_THROW_ON_ERROR);
        $res = $this->handle('POST', '/api/v1/tokens/generate', [], $body);
        $this->assertSame(201, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('token', $payload['data']);
        $this->assertNotEmpty($payload['data']['token']);
        $this->assertSame('phpunit', $payload['data']['name']);
    }

    /**
     * GET списка токенов без Bearer: 401, тип ошибки auth.
     */
    public function testIndexWithoutBearerReturns401(): void
    {
        $res = $this->handle('GET', '/api/v1/tokens');
        $payload = $this->jsonResponse($res);
        $this->assertFalse($payload['success']);
        $this->assertSame('auth', $payload['error']['type']);
    }

    /**
     * GET `/api/v1/tokens` с Bearer: 200, data — массив.
     */
    public function testIndexWithBearerReturns200(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('GET', '/api/v1/tokens', [], null, $token);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('data', $payload);
        $this->assertIsArray($payload['data']);
    }

    /**
     * DELETE существующего токена по id: 410 (gone) при успехе.
     */
    public function testDeleteReturns410(): void
    {
        $token = $this->createBearerToken();
        $create = $this->handle('POST', '/api/v1/tokens/generate', [], '{}');
        $row = $this->jsonResponse($create);
        $id = (int) ($row['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $id);

        $del = $this->handle('DELETE', '/api/v1/tokens/' . $id, [], null, $token);
        $this->assertSame(410, $del->getStatusCode());
        $payload = $this->jsonResponse($del);
        $this->assertTrue($payload['success']);
    }

    /**
     * DELETE несуществующего id: 404.
     */
    public function testDeleteUnknownReturns404(): void
    {
        $token = $this->createBearerToken();
        $res = $this->handle('DELETE', '/api/v1/tokens/999999999', [], null, $token);
        $this->assertSame(404, $res->getStatusCode());
    }
}
