<?php

namespace App\Models;

use Database\Factories\DielineTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DielineTemplate extends Model
{
    /** @use HasFactory<DielineTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'standard',
        'service_class',
        'active',
        'defaults',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'defaults' => 'array',
        ];
    }
}
