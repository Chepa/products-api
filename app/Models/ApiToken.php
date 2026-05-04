<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

class ApiToken extends Model
{
    public ?int $id = null;
    public string $token_hash = '';
    public ?string $name = null;
    public ?string $created_at = null;

    public function initialize(): void
    {
        $this->setSource('api_tokens');
    }
}
