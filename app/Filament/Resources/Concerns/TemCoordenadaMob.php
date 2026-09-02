<?php

namespace App\Filament\Resources\Concerns;

use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Coordenada (lat, lon) com "copiar" nas telas da Mobilidade Urbana
 * (pedido 2026-09-02): ponto = a própria posição; linha/polígono = ponto
 * central SOBRE a geometria (ST_PointOnSurface). O formato "lat, lon" é o
 * MESMO que o "ir para coordenada" do mapa aceita colado — copiar aqui e
 * colar lá localiza o item em 2 cliques.
 */
trait TemCoordenadaMob
{
    /** Anexa coord_lat/coord_lon à query da tabela (guardado contra geo nula/vazia). */
    protected static function comCoordenada(Builder $query, string $tabela, string $extra = ''): Builder
    {
        return $query->selectRaw(
            "{$tabela}.*{$extra},
             CASE WHEN geo IS NULL OR ST_IsEmpty(geo) THEN NULL ELSE round(ST_Y(ST_PointOnSurface(geo::geometry))::numeric, 7) END AS coord_lat,
             CASE WHEN geo IS NULL OR ST_IsEmpty(geo) THEN NULL ELSE round(ST_X(ST_PointOnSurface(geo::geometry))::numeric, 7) END AS coord_lon"
        );
    }

    /** Coluna "Coordenada" — clique copia (copyable nativo do Filament). */
    protected static function colunaCoordenada(): TextColumn
    {
        return TextColumn::make('coordenada')
            ->label('Coordenada')
            ->state(fn (Model $record) => $record->coord_lat !== null ? $record->coord_lat.', '.$record->coord_lon : null)
            ->copyable()
            ->copyMessage('Coordenada copiada!')
            ->tooltip('Clique para copiar — cole no "ir para coordenada" do mapa')
            ->placeholder('—')
            ->toggleable();
    }

    /** Campo somente-leitura do modal de edição, com botão 📋 Copiar. */
    protected static function campoCoordenada(string $tabela): Placeholder
    {
        return Placeholder::make('coordenada_geo')
            ->label('Coordenada (lat, lon)')
            ->visible(fn (?Model $record) => $record !== null)
            ->columnSpanFull()
            ->content(function (?Model $record) use ($tabela): HtmlString {
                if (! $record) {
                    return new HtmlString('—');
                }

                $r = DB::selectOne(
                    "SELECT CASE WHEN geo IS NULL OR ST_IsEmpty(geo) THEN NULL ELSE round(ST_Y(ST_PointOnSurface(geo::geometry))::numeric, 7) END AS lat,
                            CASE WHEN geo IS NULL OR ST_IsEmpty(geo) THEN NULL ELSE round(ST_X(ST_PointOnSurface(geo::geometry))::numeric, 7) END AS lon
                     FROM {$tabela} WHERE id = ?",
                    [$record->getKey()],
                );

                if (! $r || $r->lat === null) {
                    return new HtmlString('<em style="color:#9ca3af;">Sem geometria registrada.</em>');
                }

                $coord = $r->lat.', '.$r->lon;

                return new HtmlString(
                    '<span style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;">'
                    .'<code style="font-size:13px;">'.e($coord).'</code>'
                    .'<button type="button" style="font-size:11px;padding:2px 10px;border:1px solid #d1d5db;border-radius:9999px;cursor:pointer;" '
                    .'onclick="navigator.clipboard.writeText(\''.e($coord).'\');this.innerText=\'✓ Copiado\';setTimeout(() => this.innerText=\'📋 Copiar\', 1500);">📋 Copiar</button>'
                    .'<span style="font-size:11px;color:#9ca3af;">cole no &quot;ir para coordenada&quot; do mapa</span>'
                    .'</span>'
                );
            });
    }
}
