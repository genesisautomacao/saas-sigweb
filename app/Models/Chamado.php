<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Chamado extends Model
{
    use BelongsToTenant, HasTenantSequentialId, SoftDeletes;

    protected $table = 'chamados';

    protected $fillable = [
        'tenant_id', 'sequential_id', 'protocolo',
        'categoria_chamado_id', 'fluxo_chamado_id', 'fase_atual_id', 'user_id',
        'solicitante_nome', 'solicitante_telefone', 'solicitante_email',
        'descricao', 'observacoes', 'anotacoes', 'respostas_boletim', 'fotos', 'status', 'geo',
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    protected $casts = [
        'respostas_boletim' => 'array',
        'fotos' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Chamado $c) {
            if (empty($c->protocolo)) {
                $c->protocolo = strtoupper(Str::random(8));
            }
        });
    }

    public function getGeoJsonAttribute()
    {
        if (! isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('chamados')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();

        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = $value
            ? DB::raw("ST_GeomFromGeoJSON('".json_encode($value)."')")
            : null;
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaChamado::class, 'categoria_chamado_id');
    }

    public function fluxo()
    {
        return $this->belongsTo(FluxoChamado::class, 'fluxo_chamado_id');
    }

    public function faseAtual()
    {
        return $this->belongsTo(FaseChamado::class, 'fase_atual_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mensagens()
    {
        return $this->hasMany(MensagemChamado::class, 'chamado_id');
    }

    public function historicoFases()
    {
        return $this->hasMany(HistoricoFaseChamado::class, 'chamado_id');
    }
}
