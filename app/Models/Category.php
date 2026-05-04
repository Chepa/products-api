<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

class Category extends Model
{
    public ?int $id = null;
    public ?int $parent_id = null;
    public string $name = '';
    public string $slug = '';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function initialize(): void
    {
        $this->setSource('categories');

        $this->belongsTo('parent_id', self::class, 'id', [
            'alias'      => 'parent',
            'reusable'   => true,
        ]);

        $this->hasMany('id', self::class, 'parent_id', [
            'alias' => 'children',
        ]);

        $this->hasMany('id', Product::class, 'category_id', [
            'alias' => 'products',
        ]);
    }
}
