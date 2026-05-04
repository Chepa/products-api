<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ControllerBase;
use App\Models\ApiToken;
use Phalcon\Http\Response;
use Random\RandomException;

class TokensController extends ControllerBase
{
    /**
     * @throws RandomException
     */
    public function generateAction(): Response
    {
        $body = $this->jsonBodyOrFail();
        if ($body instanceof Response) {
            return $body;
        }
        $name = isset($body['name']) ? (string)$body['name'] : null;

        $plain = bin2hex(random_bytes(32));
        $row = new ApiToken();
        $row->token_hash = hash('sha256', $plain);
        $row->name = $name;

        if (!$row->save()) {
            return $this->jsonError('Could not create token', 'server', 500);
        }

        return $this->jsonOk([
            'data' => [
                'id' => (int)$row->id,
                'token' => $plain,
                'name' => $row->name,
            ],
        ], 201);
    }

    public function indexAction(): Response
    {
        $tokens = ApiToken::find(['order' => 'id DESC']);
        $list = [];
        foreach ($tokens as $t) {
            $list[] = [
                'id' => (int)$t->id,
                'name' => $t->name,
                'created_at' => $t->created_at,
            ];
        }

        return $this->jsonOk(['data' => $list]);
    }

    public function deleteAction(): Response
    {
        $id = (int)$this->dispatcher->getParam('id');
        $row = ApiToken::findFirst($id);
        if (!$row) {
            return $this->jsonError('Not found', 'not_found', 404);
        }

        if (!$row->delete()) {
            return $this->jsonError('Could not delete token', 'server', 500);
        }

        return $this->jsonOk(['data' => ['id' => $id, 'status' => 'gone']], 410);
    }
}
