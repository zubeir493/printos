<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostingServiceType extends Model
{
    protected $fillable = [
        'key',
        'name',
        'calculator_class',
        'schema_class',
        'active',
        'defaults',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'active' => 'boolean',
            'defaults' => 'array',
        ];
    }
}
