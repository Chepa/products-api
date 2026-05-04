<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\IntegrationTestCase;

/** Публичные маршруты без авторизации (главная, редирект документации). */
final class PublicRoutesApiTest extends IntegrationTestCase
{
    protected bool $requireDb = false;

    /**
     * GET `/`: 200 и непустое HTML-тело.
     */
    public function testRootReturns200(): void
    {
        $res = $this->handle('GET', '/');
        $this->assertSame(200, $res->getStatusCode() ?? 200);
        $this->assertNotSame('', $res->getContent());
    }

    /**
     * GET `/api/docs`: редирект на статический Swagger (302).
     */
    public function testApiDocsRedirects(): void
    {
        $res = $this->handle('GET', '/api/docs');
        $this->assertSame(302, $res->getStatusCode());
    }
}
