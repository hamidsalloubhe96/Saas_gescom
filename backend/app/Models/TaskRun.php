<?php

namespace App\Models;

class TaskRun extends CentralModel
{
    public $timestamps = false;

    protected $fillable = ['name', 'status', 'message', 'duration_ms', 'ran_at'];

    protected function casts(): array
    {
        return ['ran_at' => 'datetime'];
    }
}
