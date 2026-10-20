<?php

namespace App\Filament\Pages\Traits;

use App\Models\PgvParametro;
use App\Models\SetorPgv;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

trait HasSetorPgvActions
{
    // Pré-cálculo de área exibido no modal de criação (preenchido em interceptarDesenho)
    public ?float $setorPgvAreaCalculada = null;

    public function criarSetorPgv(): Action
    {
        return Action::make('criarSetorPgv')
            ->authorize(fn () => $this->podeNoMapa('setores_pgv', 'criar')) // R83
            ->modalHeading('Novo Setor da PGV')
            ->modalDescription('Preencha os dados da Zona de Valor que você acabou de desenhar.')
            ->modalSubmitActionLabel('Salvar Setor')
            ->modalWidth('2xl')
            ->form([
                Placeholder::make('area_calculada')
                    ->label('Área calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->setorPgvAreaCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->setorPgvAreaCalculada, 2, ',', '.') . ' m²</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a área no mapa primeiro.</em>'
                    )),

                TextInput::make('codigo')
                    ->label('Código do Setor')
                    ->helperText('Código do cadastro municipal (item 47 do edital).')
                    ->maxLength(50),

                TextInput::make('nome')
                    ->label('Nome do Setor da PGV')
                    ->required()
                    ->maxLength(255),

                Select::make('pgv_parametro_id')
                    ->label('Regra de Valor (Parâmetro Base)')
                    ->options(fn () => PgvParametro::where('tenant_id', $this->tenantId)->pluck('nome_padrao', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),

                // Item 75 — campos customizados do município (setor da PGV)
                ...\App\Services\Coleta\CampoCustomizadoService::componentes('setor_pgv'),

            ])
            ->action(function (array $data) {
                $this->exigirNoMapa('setores_pgv', 'criar'); // R83

                if (!$this->geometriaRascunho) {
                    Notification::make()->title('Erro de Geometria')->danger()->send();
                    return;
                }

                $setor = SetorPgv::create([
                    'tenant_id' => $this->tenantId,
                    // LINHA 'code' => (string) Str::uuid(), FOI REMOVIDA DAQUI!
                    'nome' => $data['nome'],
                    'pgv_parametro_id' => $data['pgv_parametro_id'],
                    'geo' => $this->geometriaRascunho,
                    'area_geo' => $this->setorPgvAreaCalculada,
                ]);

                // Atualiza a área oficial calculada pelo PostGIS
                DB::statement("UPDATE setores_pgv SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$setor->id]);

                Notification::make()->title('Setor da PGV Criado!')->success()->send();

                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');

                // Manda o JS desenhar o polígono laranja instantaneamente
                $this->dispatch('adicionar-setor_pgv-mapa', [
                    'id' => $setor->id,
                    'name' => $setor->nome,
                    'geo' => $setor->geo_json,
                ]);

                $this->setorPgvAreaCalculada = null;
            });
    }

    public function opcoesSetorPgv(): Action
    {
        return Action::make('opcoesSetorPgv')
            // R83: ver = abre a ficha; sem "editar" o formulário fica só leitura e sem Salvar
            ->authorize(fn () => $this->podeNoMapa('setores_pgv', 'mapa'))
            ->disabledForm(fn () => ! $this->podeNoMapa('setores_pgv', 'editar'))
            ->modalSubmitAction(fn ($action) => $this->podeNoMapa('setores_pgv', 'editar') ? $action : false)
            ->modalHeading(fn () => 'Setor da PGV: ' . (SetorPgv::find($this->setorPgvAtivoId)?->nome ?? ''))
            ->modalSubmitActionLabel('Salvar Alterações')
            ->color('primary')
            ->form([
                Placeholder::make('area_atual')
                    ->label('Área atual')
                    ->content(function (): HtmlString {
                        $reg = SetorPgv::find($this->setorPgvAtivoId);
                        $valor = $reg?->area_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m²</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),

                TextInput::make('codigo')
                    ->label('Código do Setor')
                    ->maxLength(50)
                    ->default(fn () => SetorPgv::find($this->setorPgvAtivoId)?->codigo),

                TextInput::make('nome')
                    ->label('Nome do Setor')
                    ->required()
                    ->default(fn () => SetorPgv::find($this->setorPgvAtivoId)?->nome),

                Select::make('pgv_parametro_id')
                    ->label('Regra de Valor Base')
                    ->options(fn () => PgvParametro::where('tenant_id', $this->tenantId)->pluck('nome_padrao', 'id'))
                    ->required()
                    ->default(fn () => SetorPgv::find($this->setorPgvAtivoId)?->pgv_parametro_id),

                // Item 75 — campos customizados do município (setor da PGV)
                ...\App\Services\Coleta\CampoCustomizadoService::componentes('setor_pgv'),
            ])
            ->fillForm(function (): array {
                $reg = SetorPgv::find($this->setorPgvAtivoId);

                return [
                    'codigo' => $reg?->codigo,
                    'nome' => $reg?->nome,
                    'pgv_parametro_id' => $reg?->pgv_parametro_id,
                    'dados_customizados' => $reg?->dados_customizados ?? [],
                ];
            })
            ->action(function (array $data) {
                $this->exigirNoMapa('setores_pgv', 'editar'); // R83

                $setor = SetorPgv::find($this->setorPgvAtivoId);
                if ($setor) {
                    // codigo e dados_customizados entram no update (o codigo era
                    // exibido no form mas NUNCA salvo — bug herdado da Onda 1)
                    $setor->update([
                        'nome' => $data['nome'],
                        'codigo' => $data['codigo'] ?? null,
                        'pgv_parametro_id' => $data['pgv_parametro_id'],
                        'dados_customizados' => $data['dados_customizados'] ?? [],
                    ]);
                    Notification::make()->title('Setor Atualizado!')->success()->send();
                    $this->dispatch('atualizar-label-setor_pgv', [
                        'id' => $setor->id,
                        'name' => $setor->nome,
                    ]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Remodelar Polígono')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->visible(fn () => $this->podeNoMapa('setores_pgv', 'editar')) // R83
                    ->action(function () {
                        $this->exigirNoMapa('setores_pgv', 'editar');
                        // Dispara o evento para o OpenLayers ativar as "bolinhas" de edição
                        $this->dispatch('iniciar-edicao-geometria-setor_pgv', ['id' => $this->setorPgvAtivoId]);
                        $this->dispatch('fechar-modal-filament'); // <-- CORREÇÃO AQUI
                    }),

                Action::make('excluir')
                    ->label('Excluir Setor')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-trash')
                    ->visible(fn () => $this->podeNoMapa('setores_pgv', 'excluir')) // R83
                    ->action(function () {
                        $this->exigirNoMapa('setores_pgv', 'excluir');
                        $setor = SetorPgv::find($this->setorPgvAtivoId);
                        if ($setor) {
                            $setor->delete();
                            Notification::make()->title('Setor Excluído!')->success()->send();
                            $this->dispatch('remover-setor_pgv-mapa', ['id' => $this->setorPgvAtivoId]);
                            $this->dispatch('fechar-modal-filament'); // <-- CORREÇÃO AQUI
                        }
                    }),
            ]);
    }
}
