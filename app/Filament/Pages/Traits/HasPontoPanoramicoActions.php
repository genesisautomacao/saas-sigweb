<?php

namespace App\Filament\Pages\Traits;

use App\Models\PontoPanoramico;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

trait HasPontoPanoramicoActions
{
    public ?int $pontoPanoramicoAtivoId = null;

    public function criarPontoPanoramicoAction(): Action
    {
        return Action::make('criarPontoPanoramico')
            ->modalHeading('Registrar Ponto 360º no Mapa')
            ->modalWidth('lg') // 👈 Aumentei de 'md' para 'lg' para caber a caixa de upload bonita
            ->modalSubmitActionLabel('Salvar Localização')
            ->form([
                TextInput::make('titulo')
                    ->label('Título do Local (Ex: Praça Matriz)')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('data_captura')
                    ->label('Data')
                    ->default(now()),

                // 👇 NOVO CAMPO DE UPLOAD NA MODAL 👇
                FileUpload::make('image_path')
                    ->label('Imagem 360º (Equirretangular)')
                    ->image()
                    ->directory('panoramas') // Salva direto no storage certinho
                    ->helperText('Faça o upload agora ou deixe em branco para simulação.')
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                // O code já é gerado pelo Model graças à nossa última correção!

                $registro = PontoPanoramico::create($data);

                Notification::make()->title('Ponto 360º Registrado!')->success()->send();

                $this->dispatch('adicionar-ponto_panoramico-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->titulo,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    /**
     * 🚗 Navegação estilo Street View: dados do panorama + SETAS para os
     * vizinhos. Chamado pelo viewer via $wire a cada passo (o clique numa seta
     * troca a foto sem fechar o modal).
     */
    public function panoramaDados(int $id): ?array
    {
        $ponto = PontoPanoramico::find($id);

        return $ponto ? $this->montarDadosPanorama($ponto) : null;
    }

    /**
     * Vizinhos navegáveis: da MESMA trajetória, só o ponto imediatamente
     * anterior/seguinte (andar pela rua); de OUTRAS trajetórias, o mais próximo
     * de cada uma num raio de 15 m (virar no cruzamento). Direção (bearing) e
     * distância calculadas no PostGIS; setas quase na mesma direção (< 20°)
     * são deduplicadas ficando a mais próxima. Máximo de 4 setas.
     */
    protected function montarDadosPanorama(PontoPanoramico $ponto): array
    {
        $candidatos = DB::select('
            SELECT p.id, p.titulo, p.trajectory,
                   ST_Distance(p.geo::geography, ref.geo::geography) AS dist,
                   degrees(ST_Azimuth(ref.geo::geometry, p.geo::geometry)) AS bearing
            FROM pontos_panoramicos p
            CROSS JOIN (SELECT geo FROM pontos_panoramicos WHERE id = ?) ref
            WHERE p.tenant_id = ?
              AND p.id <> ?
              AND p.deleted_at IS NULL
              AND p.geo IS NOT NULL
              AND ST_DWithin(p.geo::geography, ref.geo::geography, 15)
            ORDER BY dist
            LIMIT 12
        ', [$ponto->id, $ponto->tenant_id, $ponto->id]);

        // Mesma trajetória: o nome do arquivo é sequencial → o vizinho imediato
        // em cada sentido é o de menor distância com título maior/menor.
        $mesma = collect($candidatos)->filter(fn ($c) => $c->trajectory === $ponto->trajectory);
        $proximo = $mesma->filter(fn ($c) => $c->titulo > $ponto->titulo)->sortBy('dist')->first();
        $anterior = $mesma->filter(fn ($c) => $c->titulo < $ponto->titulo)->sortBy('dist')->first();

        // Cruzamentos: o ponto mais próximo de CADA outra trajetória
        $cruzamentos = collect($candidatos)
            ->filter(fn ($c) => $c->trajectory !== $ponto->trajectory)
            ->groupBy('trajectory')
            ->map(fn ($grupo) => $grupo->sortBy('dist')->first())
            ->values();

        $setas = [];

        foreach (collect([$proximo, $anterior])->merge($cruzamentos)->filter() as $c) {
            $bearing = fmod((float) $c->bearing + 360, 360);

            // Duas setas praticamente na mesma direção = fica só a primeira
            // (anterior/próximo têm prioridade sobre cruzamentos).
            foreach ($setas as $s) {
                $delta = abs($s['bearing'] - $bearing);
                if (min($delta, 360 - $delta) < 20) {
                    continue 2;
                }
            }

            $setas[] = [
                'id' => (int) $c->id,
                'bearing' => round($bearing, 1),
                'dist' => round((float) $c->dist, 1),
            ];

            if (count($setas) >= 4) {
                break;
            }
        }

        return [
            'id' => $ponto->id,
            'titulo' => $ponto->titulo,
            'url' => $ponto->imagem_url,
            'azimuth' => (float) ($ponto->azimuth ?? 0),
            'setas' => $setas,
        ];
    }

    public function opcoesPontoPanoramicoAction(): Action
    {
        return Action::make('opcoesPontoPanoramico')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Opções do Ponto 360º')
            ->modalWidth('4xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->extraModalFooterActions([
                Action::make('editar_geo_ponto')
                    ->label('Mover Ponto')
                    ->color('warning')
                    ->icon('heroicon-o-arrows-pointing-out')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-ponto_panoramico', id: $this->pontoPanoramicoAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_ponto')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        PontoPanoramico::find($this->pontoPanoramicoAtivoId)?->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-ponto_panoramico-mapa', ['id' => $this->pontoPanoramicoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ])
            // Viewer 360 (Pannellum) com navegação Street View: as setas dentro
            // do panorama trocam a foto via $wire.panoramaDados() sem fechar o modal.
            ->modalContent(function () {
                $ponto = PontoPanoramico::find($this->pontoPanoramicoAtivoId);
                if (!$ponto) return new HtmlString('Ponto não encontrado.');

                $dados = $this->montarDadosPanorama($ponto);
                $badge = null;

                // Cascata do accessor: URL externa → bucket R2 (URL assinada; null
                // se a foto ainda não subiu) → storage local. Sem imagem = demo +
                // aviso, e sem setas (não há para onde navegar na demo).
                if (! $dados['url']) {
                    $badge = $ponto->image_path
                        ? '📤 Foto ainda não enviada ao bucket'
                        : 'MODO SIMULAÇÃO';
                    $dados['url'] = 'https://pannellum.org/images/alma.jpg';
                    $dados['setas'] = [];
                }

                return view('filament.pages.panorama-viewer', [
                    'ponto' => $ponto,
                    'dados' => $dados,
                    'badge' => $badge,
                    'uniqueId' => 'pano-' . $ponto->id,
                ]);
            });
    }
}
