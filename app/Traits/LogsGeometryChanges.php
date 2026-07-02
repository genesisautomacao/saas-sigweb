<?php

namespace App\Traits;

/**
 * Registra no activity log uma atividade dedicada com o croqui ANTES/DEPOIS
 * sempre que a geometria (`geo`) de um model muda — base do histórico
 * cartográfico da Auditoria (B13 / item 044).
 *
 * Requisitos do model: coluna `geo`, accessor `geo_json` (ST_AsGeoJSON) e
 * `setGeoAttribute`. Opcionalmente sobrescreva `geometryLogLabel()`.
 *
 * O snapshot é lido pelo próprio accessor `geo_json`: no evento `updating` o
 * banco ainda contém a geometria antiga; no `updated`, já contém a nova — assim
 * a precisão do "antes" e do "depois" é idêntica e a comparação é confiável.
 */
trait LogsGeometryChanges
{
    /** Snapshot do GeoJSON antigo, capturado no `updating`. */
    public ?array $geoAntigoSnapshot = null;

    public static function bootLogsGeometryChanges(): void
    {
        static::updating(function ($model) {
            $g = $model->geo_json; // banco ainda tem a geometria ANTIGA
            $model->geoAntigoSnapshot = $g ? json_decode(json_encode($g), true) : null;
        });

        static::updated(function ($model) {
            $antigo = $model->geoAntigoSnapshot;
            $g = $model->geo_json; // banco já tem a geometria NOVA
            $novo = $g ? json_decode(json_encode($g), true) : null;

            if (json_encode($antigo) === json_encode($novo)) {
                return; // geometria inalterada
            }

            activity()
                ->performedOn($model)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old'        => ['geo_json' => $antigo],
                    'attributes' => ['geo_json' => $novo],
                ])
                ->event('updated')
                ->log($model->geometryLogLabel());
        });
    }

    /** Rótulo da atividade. Sobrescreva no model para um texto específico. */
    public function geometryLogLabel(): string
    {
        return 'Geometria alterada';
    }
}
