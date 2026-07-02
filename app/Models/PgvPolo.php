<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PgvPolo extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'pgv_polos';

    protected $fillable = ['tenant_id', 'sequential_id', 'name', 'geo'];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'] ?? null)) {
            return null;
        }
        $r = DB::table('pgv_polos')->select(DB::raw('ST_AsGeoJSON(geo) as g'))->where('id', $this->attributes['id'])->first();
        return $r && $r->g ? json_decode($r->g) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = empty($value) ? null : DB::raw("ST_GeomFromGeoJSON('" . json_encode($value) . "')");
    }
}
