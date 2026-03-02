<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class LeadStatus extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'color',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    // --- MÁGICA DA ORDENAÇÃO AUTOMÁTICA ---
    protected static function booted(): void
    {
        static::creating(function ($status) {
            // Se for um status novo, pega o maior número de ordem desta Tenant e soma 1
            if (empty($status->order)) {
                $maxOrder = static::where('tenant_id', $status->tenant_id)->max('order');
                $status->order = $maxOrder + 1;
            }
        });
    }

    // Relacionamento OBRIGATÓRIO para o isolamento do Filament
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}