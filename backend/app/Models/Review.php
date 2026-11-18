<?php

namespace App\Models;

class Review extends CentralModel
{
    protected $fillable = ['tenant_id', 'author_name', 'company', 'rating', 'body', 'status', 'published_at', 'handled_by'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'published_at' => 'datetime'];
    }
}
