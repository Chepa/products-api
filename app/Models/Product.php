<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

class Product extends Model
{
    public ?int $id = null;
    public int $category_id = 0;
    public string $name = '';
    public ?string $content = null;
    public string $price = '0.00';
    public int $quantity = 0;
    public int $in_stock = 1;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function initialize(): void
    {
        $this->setSource('products');

        $this->belongsTo('category_id', Category::class, 'id', [
            'alias'    => 'category',
            'reusable' => true,
        ]);
    }

    public function beforeSave(): void
    {
        if ($this->quantity < 0) {
            $this->quantity = 0;
        }
    }
}
