<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RuralPontoInteresse extends Model {
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;
    protected $table = 'rural_pontos_interesse';
    protected $fillable = ['tenant_id', 'sequential_id', 'code', 'rural_localidade_id', 'nome', 'categoria', 'observacoes', 'geo'];
    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    protected static function booted() {
        static::creating(function ($model) { if (empty($model->code)) { $model->code = (string) Str::uuid(); } });
    }

    public function getGeoJsonAttribute() {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) return null;
        $result = DB::table('rural_pontos_interesse')->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))->where('id', $this->attributes['id'])->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value) {
        $this->attributes['geo'] = $value ? DB::raw("ST_GeomFromGeoJSON('" . json_encode($value) . "')") : null;
    }

    public function localidade() { return $this->belongsTo(RuralLocalidade::class, 'rural_localidade_id'); }
}