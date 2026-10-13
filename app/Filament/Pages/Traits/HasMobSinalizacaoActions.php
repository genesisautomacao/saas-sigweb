<?php

namespace App\Filament\Pages\Traits;

use App\Models\MobSinalizacao;
use App\Models\MobTipoSinalizacao;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Sinalização viária no mapa (Mobilidade Urbana — docs/piuma.txt §2.2, Onda 2).
 * A placa aponta para o CATÁLOGO (decisão 6.1) — cor/ícone/nome vêm de lá.
 */
trait HasMobSinalizacaoActions
{
    public ?int $mobSinalizacaoAtivoId = null;

    protected function mobSinalizacaoOpcoesTipo(): array
    {
        return MobTipoSinalizacao::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('ativo', true)
            ->orderBy('tipo')->orderBy('ordem')
            ->get()
            ->mapWithKeys(fn ($t) => [$t->id => $t->name.' ('.MobTipoSinalizacao::TIPOS[$t->tipo].')'])
            ->all();
    }

    protected function mobSinalizacaoPayload(MobSinalizacao $s): array
    {
        $tipo = $s->tipoSinalizacao;

        return [
            'id' => $s->id,
            'layer' => 'mob_sinalizacoes',
            'name' => $tipo?->name ?? 'A Classificar',
            'sequential_id' => $s->sequential_id,
            'tipo_vh' => $tipo?->tipo ?? 'vertical',
            'cor' => $tipo?->cor ?? '#9CA3AF',
            'icone' => $tipo?->icone ? asset('storage/'.$tipo->icone) : null,
        ];
    }

    public function criarMobSinalizacaoAction(): Action
    {
        return Action::make('criarMobSinalizacao')
            ->authorize(fn () => $this->podeNoMapa('mob_sinalizacoes', 'criar')) // R83
            ->modalHeading('Cadastrar Nova Sinalização')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar')
            ->form([
                Select::make('tipo_sinalizacao_id')
                    ->label('Tipo de sinalização (catálogo)')
                    ->options(fn () => $this->mobSinalizacaoOpcoesTipo())
                    ->searchable()
                    ->required(),
                Textarea::make('observacao')->label('Observação')->rows(2)->nullable(),
            ])
            ->action(function (array $data) {
                $this->exigirNoMapa('mob_sinalizacoes', 'criar'); // R83

                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;

                $registro = MobSinalizacao::create($data);
                $registro->refresh();

                Notification::make()->title('Sinalização criada!')->success()->send();
                $this->dispatch('adicionar-mob_sinalizacoes-mapa', array_merge(
                    $this->mobSinalizacaoPayload($registro),
                    ['geo' => $this->geometriaRascunho],
                ));
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesMobSinalizacaoAction(): Action
    {
        return Action::make('opcoesMobSinalizacao')
            ->hiddenLabel()
            // R83: ver = abre a ficha; sem "editar" o formulário fica só leitura e sem Salvar
            ->authorize(fn () => $this->podeNoMapa('mob_sinalizacoes', 'mapa'))
            ->disabledForm(fn () => ! $this->podeNoMapa('mob_sinalizacoes', 'editar'))
            ->modalSubmitAction(fn ($action) => $this->podeNoMapa('mob_sinalizacoes', 'editar') ? $action : false)
            ->modalHeading(function () {
                $s = MobSinalizacao::withoutGlobalScopes()->with('tipoSinalizacao')->find($this->mobSinalizacaoAtivoId);

                return 'Sinalização #'.$s?->sequential_id.' — '.($s?->tipoSinalizacao?->name ?? 'A Classificar');
            })
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $s = MobSinalizacao::withoutGlobalScopes()->find($this->mobSinalizacaoAtivoId);

                return [
                    'tipo_sinalizacao_id' => $s?->tipo_sinalizacao_id,
                    'observacao' => $s?->observacao,
                ];
            })
            ->form([
                Placeholder::make('descricao_original')
                    ->label('Texto original da coleta de campo')
                    ->content(function (): HtmlString {
                        $s = MobSinalizacao::withoutGlobalScopes()->find($this->mobSinalizacaoAtivoId);

                        return new HtmlString(
                            filled($s?->descricao_original)
                                ? '<em style="color:#6b7280;">"'.e($s->descricao_original).'"</em>'
                                : '<em style="color:#9ca3af;">—</em>'
                        );
                    }),
                Select::make('tipo_sinalizacao_id')
                    ->label('Tipo de sinalização (catálogo)')
                    ->options(fn () => $this->mobSinalizacaoOpcoesTipo())
                    ->searchable()
                    ->required(),
                Textarea::make('observacao')->label('Observação')->rows(2)->nullable(),
            ])
            ->action(function (array $data) {
                $this->exigirNoMapa('mob_sinalizacoes', 'editar'); // R83

                $s = MobSinalizacao::withoutGlobalScopes()->find($this->mobSinalizacaoAtivoId);
                if (! $s) {
                    return;
                }
                $s->update($data);
                $s->refresh();

                Notification::make()->title('Sinalização atualizada!')->success()->send();
                $this->dispatch('atualizar-mob_sinalizacoes-mapa', $this->mobSinalizacaoPayload($s));
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_mob_sinalizacao')
                    ->label('Reposicionar')
                    ->color('warning')
                    ->icon('heroicon-o-map-pin')
                    ->visible(fn () => $this->podeNoMapa('mob_sinalizacoes', 'editar')) // R83
                    ->action(function () {
                        $this->exigirNoMapa('mob_sinalizacoes', 'editar');
                        $this->dispatch('iniciar-edicao-geometria-mob_sinalizacao', id: $this->mobSinalizacaoAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_mob_sinalizacao')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->visible(fn () => $this->podeNoMapa('mob_sinalizacoes', 'excluir')) // R83
                    ->action(function () {
                        $this->exigirNoMapa('mob_sinalizacoes', 'excluir');
                        MobSinalizacao::withoutGlobalScopes()->find($this->mobSinalizacaoAtivoId)?->delete();
                        Notification::make()->title('Sinalização excluída!')->success()->send();
                        $this->dispatch('remover-mob_sinalizacoes-mapa', ['id' => $this->mobSinalizacaoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    #[On('abrirOpcoesMobSinalizacao')]
    public function abrirOpcoesMobSinalizacao($id): void
    {
        $this->mobSinalizacaoAtivoId = (int) $id;
        $this->mountAction('opcoesMobSinalizacao');
    }

    #[On('salvarNovaGeometriaMobSinalizacao')]
    public function salvarNovaGeometriaMobSinalizacao($id, $geoJson): void
    {
        // R83: geometria só grava com permissão de EDITAR a entidade
        if (! $this->autorizarNoMapa('mob_sinalizacoes', 'editar')) {
            $this->dispatch('desfazer-edicao-geometria');

            return;
        }

        $s = MobSinalizacao::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->find($id);
        if (! $s) {
            return;
        }
        $s->update(['geo' => $geoJson]);

        Notification::make()->title('Posição da sinalização atualizada!')->success()->send();
    }
}
