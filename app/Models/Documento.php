<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documento extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'name',
        'path',
        'type',
        'metadata',
        'documentable_id',
        'documentable_type',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Obtém o modelo pai (polimórfico).
     * Pode ser uma Pessoa, um Lote, uma Edificação, etc.
     */
    public function documentable()
    {
        return $this->morphTo();
    }
}