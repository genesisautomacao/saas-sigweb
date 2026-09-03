<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use App\Traits\LogsGeometryChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Câmera de monitoramento em tempo real (docs/piuma.txt, Onda 5): ponto no
 * mapa + fonte do vídeo. O clique no ícone abre o player; nada é gravado.
 */
class MobCamera extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    public const TIPOS = [
        'embed' => 'Player embutido (iframe: FullCam, provedor…)',
        'youtube' => 'YouTube ao vivo',
        'hls' => 'Stream HLS (.m3u8)',
        'imagem' => 'Imagem atualizada (snapshot JPEG)',
    ];

    protected $table = 'mob_cameras';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'nome', 'tipo', 'url', 'url_snapshot', 'provedor',
        'azimute_visada', 'descricao', 'ativo',
        'dados_customizados', 'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array',
        'ativo' => 'boolean',
        'azimute_visada' => 'float',
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public function geometryLogLabel(): string
    {
        return 'Posição da câmera alterada';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getGeoJsonAttribute()
    {
        if (! isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table($this->table)
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();

        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        // POINT puro (sem ST_Multi)
        $this->attributes['geo'] = DB::raw("ST_GeomFromGeoJSON('".json_encode($value)."')");
    }

    /** URL pronta para o player: YouTube (watch/youtu.be/live) vira /embed/ID com autoplay mudo. */
    public function urlPlayer(): string
    {
        $url = trim((string) $this->url);
        if ($this->tipo === 'youtube') {
            $id = self::youtubeId($url);

            return $id ? "https://www.youtube.com/embed/{$id}?autoplay=1&mute=1&rel=0" : $url;
        }

        return $url;
    }

    public static function youtubeId(string $url): ?string
    {
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:embed/|live/|watch\?(?:.*&)?v=))([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
