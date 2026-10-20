<?php

namespace App\Filament\Pages\Traits;

use App\Filament\Resources\SetorCadastralResource;
use App\Models\SetorCadastral;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * B9 (2026-10-19) — Setor Cadastral da inscrição imobiliária no mapa (camada
 * `setores_cadastrais`, artefato `setor_cadastral`). Setores não se sobrepõem:
 * desde o B10 (2026-10-20) o desenho que invade o vizinho é RECORTADO na divisa
 * (SetorCadastral::recortarPelosVizinhos) em vez de recusado — a trava de 5 m²
 * barrava quem contorna a divisa à mão. O setor da PGV é o HasSetorPgvActions.
 */
trait HasSetorCadastralActions
{
    public ?int $setorCadastralAtivoId = null;

    // Pré-cálculo de área exibido no modal de criação (preenchido em interceptarDesenho)
    public ?float $setorCadastralAreaCalculada = null;

    /** Chamado pelo interceptarDesenho do MapaFullscreen (o rascunho já está em $geometriaRascunho). */
    protected function interceptarDesenhoSetorCadastral(): void
    {
        $recorte = SetorCadastral::recortarPelosVizinhos((array) $this->geometriaRascunho, (int) $this->tenantId);

        if ($recorte['erro']) {
            Notification::make()->title('Desenho recusado')->body($recorte['erro'])->danger()->send();
            $this->dispatch('limpar-rascunho-mapa');

            return;
        }

        // O setor nasce com a geometria já recortada na divisa dos vizinhos
        $this->geometriaRascunho = $recorte['geo'];
        $this->setorCadastralAreaCalculada = $recorte['area'];

        if ($aviso = SetorCadastral::avisoRecorte($recorte)) {
            Notification::make()->title('Divisa ajustada')->body($aviso)->info()->send();
        }

        $this->mountAction('criarSetorCadastral');
    }

    private function setorCadastralArea(?float $valor, string $vazio): HtmlString
    {
        return new HtmlString($valor !== null
            ? '<strong style="font-size:14px;color:#0369a1;">'.number_format($valor, 2, ',', '.').' m²</strong>'
            : '<em style="color:#9ca3af;">'.$vazio.'</em>');
    }

    public function criarSetorCadastralAction(): Action
    {
        return Action::make('criarSetorCadastral')
            ->authorize(fn () => $this->podeNoMapa('setores_cadastrais', 'criar')) // R83
            ->modalHeading('Novo Setor Cadastral')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Setor')
            ->form([
                Placeholder::make('area_calculada')
                    ->label('Área calculada')
                    ->content(fn () => $this->setorCadastralArea($this->setorCadastralAreaCalculada, 'Sem geometria — desenhe a área no mapa primeiro.')),

                SetorCadastralResource::campoNumero(),

                Toggle::make('vincular_bairros')
                    ->label('Vincular os bairros que ficam dentro do setor')
                    ->helperText('Só bairros ainda sem setor; o critério é o ponto interno do bairro.')
                    ->default(true),

                // Item 75 — campos customizados do município (setor cadastral)
                ...\App\Services\Coleta\CampoCustomizadoService::componentes('setor_cadastral'),
            ])
            ->action(function (array $data) {
                $this->exigirNoMapa('setores_cadastrais', 'criar'); // R83

                if (! $this->geometriaRascunho) {
                    Notification::make()->title('Erro de Geometria')->danger()->send();

                    return;
                }

                $setor = SetorCadastral::create([
                    'tenant_id' => $this->tenantId,
                    'name' => $data['name'],
                    'dados_customizados' => $data['dados_customizados'] ?? [],
                    'geo' => $this->geometriaRascunho,
                    'area_geo' => $this->setorCadastralAreaCalculada,
                ]);

                try {
                    DB::statement('UPDATE setores_cadastrais SET area_geo = ST_Area(geo::geography) WHERE id = ?', [$setor->id]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Falha ao calcular area_geo do setor cadastral '.$setor->id.': '.$e->getMessage());
                }

                $vinculados = ($data['vincular_bairros'] ?? false) ? $setor->vincularBairrosPelaGeometria() : 0;

                Notification::make()
                    ->title('Setor Cadastral criado!')
                    ->body($vinculados ? "{$vinculados} bairro(s) vinculado(s) ao setor." : null)
                    ->success()
                    ->send();

                $this->dispatch('adicionar-setor_cadastral-mapa', [
                    'id' => $setor->id,
                    'name' => $setor->name,
                    'geo' => $this->geometriaRascunho,
                ]);
                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
                $this->setorCadastralAreaCalculada = null;
            });
    }

    public function opcoesSetorCadastralAction(): Action
    {
        return Action::make('opcoesSetorCadastral')
            ->hiddenLabel()
            // R83: ver = abre a ficha; sem "editar" o formulário fica só leitura e sem Salvar
            ->authorize(fn () => $this->podeNoMapa('setores_cadastrais', 'mapa'))
            ->disabledForm(fn () => ! $this->podeNoMapa('setores_cadastrais', 'editar'))
            ->modalSubmitAction(fn ($action) => $this->podeNoMapa('setores_cadastrais', 'editar') ? $action : false)
            ->modalHeading(fn () => 'Setor Cadastral '.(SetorCadastral::find($this->setorCadastralAtivoId)?->name ?? ''))
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->record(fn () => SetorCadastral::find($this->setorCadastralAtivoId)) // a regra unique ignora o próprio setor
            ->fillForm(function (): array {
                $reg = SetorCadastral::find($this->setorCadastralAtivoId);

                return [
                    'name' => $reg?->name,
                    'dados_customizados' => $reg?->dados_customizados ?? [],
                ];
            })
            ->form([
                Placeholder::make('area_atual')
                    ->label('Área atual')
                    ->content(fn () => $this->setorCadastralArea(
                        ($v = SetorCadastral::find($this->setorCadastralAtivoId)?->area_geo) !== null ? (float) $v : null,
                        'Sem geometria registrada.'
                    )),

                SetorCadastralResource::campoNumero(),

                Placeholder::make('bairros_do_setor')
                    ->label('Bairros do setor')
                    ->content(fn () => SetorCadastral::find($this->setorCadastralAtivoId)?->bairros()->orderBy('name')->pluck('name')->join(', ')
                        ?: 'Nenhum bairro vinculado.'),

                // Item 75 — campos customizados do município (setor cadastral)
                ...\App\Services\Coleta\CampoCustomizadoService::componentes('setor_cadastral'),
            ])
            ->action(function (array $data) {
                $this->exigirNoMapa('setores_cadastrais', 'editar'); // R83

                $reg = SetorCadastral::find($this->setorCadastralAtivoId);
                if ($reg) {
                    $reg->update([
                        'name' => $data['name'],
                        'dados_customizados' => $data['dados_customizados'] ?? [],
                    ]);
                    Notification::make()->title('Setor atualizado!')->success()->send();
                    $this->dispatch('atualizar-label-setor_cadastral', ['id' => $reg->id, 'name' => $reg->name]);
                }
            })
            ->extraModalFooterActions([
                Action::make('vincular_bairros_setor')
                    ->label('Vincular bairros')
                    ->color('gray')
                    ->icon('heroicon-o-link')
                    ->visible(fn () => $this->podeNoMapa('setores_cadastrais', 'editar'))
                    ->requiresConfirmation()
                    ->modalDescription('Os bairros sem setor cujo ponto interno fica dentro deste setor passam a apontar para ele.')
                    ->action(function () {
                        $this->exigirNoMapa('setores_cadastrais', 'editar');
                        $n = SetorCadastral::find($this->setorCadastralAtivoId)?->vincularBairrosPelaGeometria() ?? 0;
                        Notification::make()
                            ->title($n ? "{$n} bairro(s) vinculado(s)" : 'Nenhum bairro novo para vincular')
                            ->success()
                            ->send();
                    }),
                Action::make('editar_geo_setor_cadastral')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->visible(fn () => $this->podeNoMapa('setores_cadastrais', 'editar')) // R83
                    ->action(function () {
                        $this->exigirNoMapa('setores_cadastrais', 'editar');
                        $this->dispatch('iniciar-edicao-geometria-setor_cadastral', id: $this->setorCadastralAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_setor_cadastral')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalDescription('O setor vai para a lixeira. Os bairros dele continuam apontando para ele até que sejam ligados a outro setor.')
                    ->visible(fn () => $this->podeNoMapa('setores_cadastrais', 'excluir')) // R83
                    ->action(function () {
                        $this->exigirNoMapa('setores_cadastrais', 'excluir');
                        SetorCadastral::find($this->setorCadastralAtivoId)?->delete();
                        Notification::make()->title('Setor excluído!')->success()->send();
                        $this->dispatch('remover-setor_cadastral-mapa', ['id' => $this->setorCadastralAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    #[On('abrirOpcoesSetorCadastral')]
    public function abrirOpcoesSetorCadastral($id): void
    {
        $this->setorCadastralAtivoId = (int) $id;
        $this->mountAction('opcoesSetorCadastral');
    }

    #[On('salvarNovaGeometriaSetorCadastral')]
    public function salvarNovaGeometriaSetorCadastral($id, $geoJson): void
    {
        // R83: geometria só grava com permissão de EDITAR a entidade
        if (! $this->autorizarNoMapa('setores_cadastrais', 'editar')) {
            $this->dispatch('desfazer-edicao-geometria');

            return;
        }

        $setor = SetorCadastral::query()->find($id);
        if (! $setor) {
            return;
        }

        $recorte = SetorCadastral::recortarPelosVizinhos((array) $geoJson, (int) $this->tenantId, $setor->id);
        if ($recorte['erro']) {
            Notification::make()->title('Edição cancelada')->body($recorte['erro'].' A edição foi revertida.')->danger()->send();
            $this->dispatch('desfazer-edicao-geometria');

            return;
        }

        $setor->update(['geo' => $recorte['geo']]);
        DB::statement('UPDATE setores_cadastrais SET area_geo = ST_Area(geo::geography) WHERE id = ?', [$setor->id]);

        $aviso = SetorCadastral::avisoRecorte($recorte);
        Notification::make()->title('Limites do setor atualizados!')->body($aviso)->success()->send();
        if ($aviso) {
            // o mapa ainda mostra o desenho sem recorte — busca de novo a camada
            $this->dispatch('recarregarCamada', layer: 'setores_cadastrais');
        }
    }
}
