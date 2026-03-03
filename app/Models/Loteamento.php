<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;

class Loteamento extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'loteamentos';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'code',
        'name',
        'setor',
        'geo',
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    // --- GEOMETRIA ---
    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('loteamentos')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    // --- RELACIONAMENTOS ---
    public function quadras()
    {
        return $this->hasMany(Quadra::class);
    }
}