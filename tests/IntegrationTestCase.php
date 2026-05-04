<?php

declare(strict_types=1);

namespace App\Tests;

use App\Services\TokenGenerateRateLimiter;
use Phalcon\Di\FactoryDefault;
use Phalcon\Http\Request;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Application;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Throwable;

/**
 * Базовый интеграционный тест: общий DI, выключенный rate limit для токенов, HTTP (nginx) или in-process запрос к приложению.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected FactoryDefault $di;

    /** Если true — перед тестами проверяется доступность БД. */
    protected bool $requireDb = true;

    /**
     * Поднимает DI как в `services.php`, подменяет лимитер токенов на безлимитный; при необходимости пропускает тесты без БД.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->di = new FactoryDefault();
        $di = $this->di;
        require APP_PATH . '/config/services.php';

        $this->di->remove('tokenRateLimiter');
        $this->di->setShared(
            'tokenRateLimiter',
            fn (): TokenGenerateRateLimiter => new TokenGenerateRateLimiter(
                0,
                3600,
                BASE_PATH . '/cache/ratelimit'
            )
        );

        if ($this->requireDb) {
            try {
                $this->di->get('db')->query('SELECT 1');
            } catch (Throwable $e) {
                $this->markTestSkipped('База недоступна: ' . $e->getMessage());
            }
        }
    }

    /**
     * Сбрасывает суперглобальные GET/POST/REQUEST между тестами.
     */
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = array_merge($_COOKIE, $_GET, $_POST);
        parent::tearDown();
    }

    /**
     * Выполняет HTTP-запрос к приложению: через реальный URL при `PHPUNIT_HTTP_BASE`, иначе через `Application::handle()` в том же процессе.
     *
     * @param array<string, string|int|float|bool> $query
     */
    protected function handle(
        string $method,
        string $path,
        array $query = [],
        ?string $jsonBody = null,
        ?string $bearerToken = null,
    ): ResponseInterface {
        $base = getenv('PHPUNIT_HTTP_BASE');
        if (is_string($base) && $base !== '') {
            return $this->handleViaRealHttp(rtrim($base, '/'), $method, $path, $query, $jsonBody, $bearerToken);
        }

        return $this->handleViaApplication($method, $path, $query, $jsonBody, $bearerToken);
    }

    /**
     * Запрос через nginx/php-fpm (реальный HTTP) — корректные PUT/PATCH и окружение как у клиента.
     *
     * @param array<string, string|int|float|bool> $query
     */
    private function handleViaRealHttp(
        string $baseUrl,
        string $method,
        string $path,
        array $query,
        ?string $jsonBody,
        ?string $bearerToken,
    ): ResponseInterface {
        $url = $baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = ['Accept: application/json'];
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($bearerToken !== null && $bearerToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        if (!function_exists('curl_init')) {
            $this->markTestSkipped('Нужно расширение PHP curl для HTTP-интеграционных тестов');
        }

        return $this->executeCurlHttpRequest($url, $method, $headers, $jsonBody);
    }

    /**
     * Выполняет cURL-запрос и упаковывает ответ в объект Phalcon `Response`.
     *
     * @param list<string> $headers
     */
    private function executeCurlHttpRequest(
        string $url,
        string $method,
        array $headers,
        ?string $jsonBody,
    ): ResponseInterface {
        $ch = curl_init($url);
        if ($ch === false) {
            $this->markTestSkipped('Не удалось инициализировать cURL для ' . $url);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($jsonBody !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            }
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            $this->markTestSkipped('HTTP недоступен (' . $url . '): ' . $err);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $response = new Response((string) $body);
        $response->setStatusCode($status);

        return $response;
    }

    /**
     * Эмулирует окружение запроса (`$_SERVER`, тело) и отдаёт управление `Mvc\Application`.
     *
     * @param array<string, string|int|float|bool> $query
     */
    private function handleViaApplication(
        string $method,
        string $path,
        array $query,
        ?string $jsonBody,
        ?string $bearerToken,
    ): ResponseInterface {
        $uri = $path;
        if ($query !== []) {
            $uri .= '?' . http_build_query($query);
        }

        $_GET = $method === 'GET' ? $query : [];
        $_REQUEST = array_merge($_COOKIE, $_GET, $_POST);
        $_SERVER['QUERY_STRING'] = ($method === 'GET' && $query !== [])
            ? http_build_query($query)
            : '';

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        if ($bearerToken !== null && $bearerToken !== '') {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearerToken;
        } else {
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }

        if ($jsonBody !== null) {
            $_SERVER['CONTENT_TYPE'] = 'application/json';
            $_SERVER['CONTENT_LENGTH'] = (string) strlen($jsonBody);
        } else {
            unset($_SERVER['CONTENT_TYPE'], $_SERVER['CONTENT_LENGTH']);
        }

        $di = $this->di;
        $this->di->remove('request');
        $this->di->setShared(
            'request',
            function () use ($di): Request {
                $request = new Request();
                $request->setDI($di);

                return $request;
            }
        );

        $application = new Application($this->di);
        $this->applyRequestRawBody($jsonBody);

        return $application->handle($uri);
    }

    /**
     * Подставляет сырое тело запроса в экземпляр Request через reflection (для PUT/PATCH/POST в CLI).
     */
    private function applyRequestRawBody(?string $jsonBody): void
    {
        $request = $this->di->get('request');
        $this->assertNotNull($request);

        $this->setRequestReflectionProperty($request, 'rawBody', $jsonBody ?? '');
        foreach (['putCache', 'patchCache', 'postCache'] as $cacheKey) {
            if ($this->hasRequestReflectionProperty($request, $cacheKey)) {
                $this->setRequestReflectionProperty($request, $cacheKey, null);
            }
        }
    }

    /**
     * Проверяет наличие защищённого свойства у Request (включая родительские классы).
     */
    private function hasRequestReflectionProperty(object $request, string $name): bool
    {
        $ref = new ReflectionObject($request);
        while (true) {
            if ($ref->hasProperty($name)) {
                return true;
            }
            $parent = $ref->getParentClass();
            if ($parent === false) {
                return false;
            }
            $ref = $parent;
        }
    }

    /**
     * Записывает значение в защищённое свойство Request; если свойства нет — помечает тест как пропущенный.
     *
     * @param object $request
     * @param string $name
     * @param mixed $value
     */
    private function setRequestReflectionProperty(object $request, string $name, mixed $value): void
    {
        $ref = new ReflectionObject($request);
        while (true) {
            if ($ref->hasProperty($name)) {
                $prop = $ref->getProperty($name);
                $prop->setValue($request, $value);

                return;
            }
            $parent = $ref->getParentClass();
            if ($parent === false) {
                break;
            }
            $ref = $parent;
        }

        $this->markTestSkipped('У Request нет свойства ' . $name);
    }

    /**
     * Декодирует JSON-тело ответа; падает с понятным сообщением, если тело не JSON.
     *
     * @return array<string, mixed>
     */
    protected function jsonResponse(ResponseInterface $response): array
    {
        $content = $response->getContent();
        $data = json_decode($content, true);
        $this->assertIsArray($data, 'Ответ не JSON: ' . $content);

        return $data;
    }

    /**
     * Создаёт новый API-токен через POST `/api/v1/tokens/generate` и возвращает строку токена.
     */
    protected function createBearerToken(): string
    {
        $res = $this->handle('POST', '/api/v1/tokens/generate', [], '{}');
        $this->assertSame(201, $res->getStatusCode());
        $payload = $this->jsonResponse($res);
        $this->assertTrue($payload['success']);
        $token = $payload['data']['token'] ?? null;
        $this->assertIsString($token);

        return $token;
    }
}
