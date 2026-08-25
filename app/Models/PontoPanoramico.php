<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PontoPanoramico extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'pontos_panoramicos';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'code',
        'titulo',
        'image_path',
        'image_url_simulacao',
        'data_captura',
        'azimuth',
        'altitude',
        'trajectory',
        'geo',
    ];

    /**
     * URL da imagem 360 para o viewer, em cascata:
     * 1. URL externa de simulação (campo legado);
     * 2. Bucket de mídias (importação em massa — caminho contém "/panoramicas/"):
     *    URL ASSINADA temporária do disk "midia" (Cloudflare R2, bucket privado);
     * 3. Storage local (upload manual pelo modal do mapa).
     * NÃO está em $appends de propósito: assinar URL de 55 mil pontos no payload
     * do mapa seria desperdício — o accessor é chamado só ao abrir o viewer.
     */
    public function getImagemUrlAttribute(): ?string
    {
        if ($this->image_url_simulacao) {
            return $this->image_url_simulacao;
        }

        if (! $this->image_path) {
            return null;
        }

        if (str_contains($this->image_path, '/panoramicas/')) {
            try {
                if (config('filesystems.disks.midia.key')) {
                    // URL assinada MEMOIZADA (~5h; a assinatura vale 6h): URL
                    // ESTÁVEL deixa o navegador reaproveitar o cache HTTP
                    // (ETag/304) — reabrir a mesma foto não baixa os ~6,7MB de
                    // novo. Egress no R2 é grátis; isto é velocidade, não custo.
                    // Foto ainda não enviada → closure devolve null (que o cache
                    // NÃO guarda) → volta a checar a cada abertura até subir.
                    return \Illuminate\Support\Facades\Cache::remember(
                        "pano-url-{$this->id}",
                        now()->addHours(5),
                        function () {
                            $disk = \Illuminate\Support\Facades\Storage::disk('midia');

                            if (! $disk->exists($this->image_path)) {
                                return null;
                            }

                            return $disk->temporaryUrl($this->image_path, now()->addHours(6));
                        }
                    );
                }
            } catch (\Throwable $e) {
                // Sem credenciais R2 neste ambiente — cai no storage local abaixo.
            }

            return null; // caminho de bucket sem R2 configurado: não há arquivo local
        }

        return asset('storage/'.$this->image_path);
    }

        protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->code)) {
                $model->code = (string) Str::uuid();
            }
        });
    }

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    /**
     * Extrai a geometria do PostGIS para o front-end
     */
    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('pontos_panoramicos')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();
            
        return $result ? json_decode($result->geo_json) : null;
    }

    /**
     * Salva a geometria recebida do mapa no PostGIS
     */
    public function setGeoAttribute($value)
    {
        if ($value) {
            $this->attributes['geo'] = DB::raw("ST_GeomFromGeoJSON('" . json_encode($value) . "')");
        } else {
            $this->attributes['geo'] = null;
        }
    }
}