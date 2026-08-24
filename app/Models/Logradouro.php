<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\LogsGeometryChanges;

class Logradouro extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges;

    /** Croqui Antes/Depois na Auditoria (achado do usuário, PoC AC 2026-08-23). */
    public function geometryLogLabel(): string
    {
        return 'Geometria do logradouro alterada';
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Auditoria completa (PoC AC 2026-08-23): qualquer campo alterado entra no log.
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = ['tenant_id', 'sequential_id', 'code', 'codigo', 'name', 'dados_customizados', 'extensao_geo', 'geo'];

    /** Transiente da cascata de restore (não persiste). */
    public ?string $marcoExclusaoAnterior = null;

    protected $casts = [
        'dados_customizados' => 'array', // campos do município (item 75)
    ];
    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo']))
            return null;
        $result = DB::table('logradouros')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        // Envelopa em ST_Multi para aceitar LineStrings simples e transformar em MultiLineString
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    public function secoes()
    {
        return $this->hasMany(SecaoLogradouro::class, 'logradouro_id');
    }

    /**
     * Item 52 do edital ("Excluir Logradouro E Seções") — cascata do SOFT delete.
     *
     * O cascadeOnDelete da FK só dispara em DELETE físico; com SoftDeletes o
     * logradouro sumia e as seções ficavam ativas no mapa apontando para um pai
     * excluído. Aqui o soft delete e o restore propagam para as seções.
     * O force delete não precisa: a FK cuida.
     */
    protected static function booted(): void
    {
        static::deleted(function (Logradouro $logradouro) {
            if (! $logradouro->isForceDeleting()) {
                $logradouro->secoes()->delete();
            }
        });

        static::restoring(function (Logradouro $logradouro) {
            // Guarda o deleted_at ANTES do restore zerá-lo — é o marco da cascata.
            $logradouro->marcoExclusaoAnterior = $logradouro->getOriginal('deleted_at');
        });

        static::restored(function (Logradouro $logradouro) {
            $query = $logradouro->secoes()->onlyTrashed();

            // Restaura só as seções excluídas JUNTO com o logradouro (mesma cascata)
            // — uma seção excluída individualmente dias antes não deve ressuscitar.
            if ($logradouro->marcoExclusaoAnterior) {
                $query->where('deleted_at', '>=', \Carbon\Carbon::parse($logradouro->marcoExclusaoAnterior)->subSeconds(2));
            }

            $query->restore();
        });
    }
}