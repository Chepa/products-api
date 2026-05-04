<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Models\ApiToken;
use Phalcon\Di\Injectable;
use Phalcon\Events\Event;
use Phalcon\Http\Response;
use Phalcon\Mvc\Dispatcher;

class BearerAuthPlugin extends Injectable
{
    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher): bool
    {
        $uri = parse_url($this->request->getURI(), PHP_URL_PATH) ?: '/';

        if ($uri === '/' || str_starts_with($uri, '/public/')) {
            return true;
        }

        if (
            ($uri === '/swagger.html' || $uri === '/openapi.yaml')
            && $this->request->isGet()
        ) {
            return true;
        }

        if ($uri === '/api/v1/tokens/generate' && $this->request->isPost()) {
            /** @var \App\Services\TokenGenerateRateLimiter $rl */
            $rl = $this->getDI()->get('tokenRateLimiter');
            $ip = $this->request->getClientAddress() ?: 'unknown';
            $result = $rl->hit('tg:' . $ip);
            if (!$result['allowed']) {
                return $this->rateLimited($event, (int)($result['retry_after'] ?? 60));
            }

            return true;
        }

        if ($uri === '/api/docs' && $this->request->isGet()) {
            return true;
        }

        $auth = $this->request->getHeader('Authorization') ?: $this->request->getHeader('AUTHORIZATION');
        if (!$auth || !preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
            return $this->unauthorized($event, 'Missing or invalid Bearer token');
        }

        $plain = $m[1];
        $hash  = hash('sha256', $plain);

        $token = ApiToken::findFirst([
            'conditions' => 'token_hash = :h:',
            'bind'       => ['h' => $hash],
        ]);

        if (!$token) {
            return $this->unauthorized($event, 'Invalid token');
        }

        return true;
    }

    private function unauthorized(Event $event, string $message): bool
    {
        /** @var Response $response */
        $response = $this->response;
        $response->setStatusCode(401);
        $response->setContentType('application/json', 'UTF-8');
        $response->setJsonContent([
            'success' => false,
            'error'   => [
                'message' => $message,
                'type'    => 'auth',
            ],
        ]);
        $event->stop();

        return false;
    }

    private function rateLimited(Event $event, int $retryAfter): bool
    {
        /** @var Response $response */
        $response = $this->response;
        $response->setStatusCode(429);
        $response->setHeader('Retry-After', (string)max(1, $retryAfter));
        $response->setContentType('application/json', 'UTF-8');
        $response->setJsonContent([
            'success' => false,
            'error'   => [
                'message' => 'Too many token generation attempts',
                'type'    => 'rate_limit',
            ],
        ]);
        $event->stop();

        return false;
    }
}
