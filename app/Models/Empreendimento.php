<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Empreendimento extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'empreendimentos';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'descricao', 'endereco', 'num_unidades', 'geo',
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'] ?? null)) {
            return null;
        }
        $result = DB::table('empreendimentos')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])->first();
        return $result && $result->geo_json ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['geo'] = null;
            return;
        }
        $this->attributes['geo'] = DB::raw("ST_GeomFromGeoJSON('" . json_encode($value) . "')");
    }

    public function cadastros()
    {
        return $this->hasMany(CadastroSocial::class);
    }
}
