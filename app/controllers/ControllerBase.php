<?php

declare(strict_types=1);

namespace App\Controllers;

use Phalcon\Http\Response;
use Phalcon\Mvc\Controller;

class ControllerBase extends Controller
{
    protected function jsonOk(array $payload, int $code = 200): Response
    {
        $this->response->setStatusCode($code);
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setJsonContent(array_merge(['success' => true], $payload));

        return $this->response;
    }

    protected function jsonError(
        string $message,
        string $type = 'validation',
        int $code = 400,
        ?array $details = null
    ): Response {
        $err = [
            'message' => $message,
            'type'    => $type,
        ];
        if ($details !== null) {
            $err['details'] = $details;
        }

        $this->response->setStatusCode($code);
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setJsonContent([
            'success' => false,
            'error'   => $err,
        ]);

        return $this->response;
    }

    /**
     * Empty body → []. Non-empty invalid JSON or non-object JSON → JSON error response.
     *
     * @return array<string, mixed>|Response
     */
    protected function jsonBodyOrFail(): array|Response
    {
        $raw = $this->request->getRawBody();
        if ($raw === '' || $raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->jsonError('Invalid JSON: ' . json_last_error_msg(), 'validation', 400);
        }
        if (!is_array($data)) {
            return $this->jsonError('JSON body must be a JSON object', 'validation', 400);
        }
        if (array_is_list($data) && $data !== []) {
            return $this->jsonError('JSON body must be a JSON object', 'validation', 400);
        }

        return $data;
    }
}
