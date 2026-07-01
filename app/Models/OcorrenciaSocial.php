<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OcorrenciaSocial extends Model
{
    use BelongsToTenant;

    protected $table = 'ocorrencias_sociais';

    protected $fillable = [
        'tenant_id', 'ocorrenciavel_type', 'ocorrenciavel_id',
        'data', 'tipo', 'descricao', 'user_id',
    ];

    protected $casts = [
        'data' => 'date',
    ];

    public function ocorrenciavel()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
