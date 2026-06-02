<?php

namespace App\Filament\Pages\Traits;

use App\Models\PgvParametro;
use App\Models\SetorFiscal;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

trait HasSetorFiscalActions
{
    // Pré-cálculo de área exibido no modal de criação (preenchido em interceptarDesenho)
    public ?float $setorFiscalAreaCalculada = null;

    public function criarSetorFiscal(): Action
    {
        return Action::make('criarSetorFiscal')
            ->modalHeading('Novo Setor Fiscal (PGV)')
            ->modalDescription('Preencha os dados da Zona de Valor que você acabou de desenhar.')
            ->modalSubmitActionLabel('Salvar Setor')
            ->modalWidth('2xl')
            ->form([
                Placeholder::make('area_calculada')
                    ->label('Área calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->setorFiscalAreaCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->setorFiscalAreaCalculada, 2, ',', '.') . ' m²</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a área no mapa primeiro.</em>'
                    )),

                TextInput::make('nome')
                    ->label('Nome do Setor Fiscal')
                    ->required()
                    ->maxLength(255),

                Select::make('pgv_parametro_id')
                    ->label('Regra de Valor (Parâmetro Base)')
                    ->options(fn () => PgvParametro::where('tenant_id', $this->tenantId)->pluck('nome_padrao', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),

                Textarea::make('descricao')
                    ->label('Descrição')
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                if (!$this->geometriaRascunho) {
                    Notification::make()->title('Erro de Geometria')->danger()->send();
                    return;
                }

                $setor = SetorFiscal::create([
                    'tenant_id' => $this->tenantId,
                    // LINHA 'code' => (string) Str::uuid(), FOI REMOVIDA DAQUI!
                    'nome' => $data['nome'],
                    'pgv_parametro_id' => $data['pgv_parametro_id'],
                    'descricao' => $data['descricao'],
                    'geo' => $this->geometriaRascunho,
                    'area_geo' => $this->setorFiscalAreaCalculada,
                ]);

                // Atualiza a área oficial calculada pelo PostGIS
                DB::statement("UPDATE setores_fiscais SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$setor->id]);

                Notification::make()->title('Setor Fiscal Criado!')->success()->send();

                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');

                // Manda o JS desenhar o polígono laranja instantaneamente
                $this->dispatch('adicionar-setor_fiscal-mapa', [
                    'id' => $setor->id,
                    'name' => $setor->nome,
                    'geo' => $setor->geo_json,
                ]);

                $this->setorFiscalAreaCalculada = null;
            });
    }

    public function opcoesSetorFiscal(): Action
    {
        return Action::make('opcoesSetorFiscal')
            ->modalHeading(fn () => 'Setor Fiscal: ' . (SetorFiscal::find($this->setorFiscalAtivoId)?->nome ?? ''))
            ->modalSubmitActionLabel('Salvar Alterações')
            ->color('primary')
            ->form([
                Placeholder::make('area_atual')
                    ->label('Área atual')
                    ->content(function (): HtmlString {
                        $reg = SetorFiscal::find($this->setorFiscalAtivoId);
                        $valor = $reg?->area_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m²</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),

                TextInput::make('nome')
                    ->label('Nome do Setor')
                    ->required()
                    ->default(fn () => SetorFiscal::find($this->setorFiscalAtivoId)?->nome),

                Select::make('pgv_parametro_id')
                    ->label('Regra de Valor Base')
                    ->options(fn () => PgvParametro::where('tenant_id', $this->tenantId)->pluck('nome_padrao', 'id'))
                    ->required()
                    ->default(fn () => SetorFiscal::find($this->setorFiscalAtivoId)?->pgv_parametro_id),
            ])
            ->action(function (array $data) {
                $setor = SetorFiscal::find($this->setorFiscalAtivoId);
                if ($setor) {
                    $setor->update([
                        'nome' => $data['nome'],
                        'pgv_parametro_id' => $data['pgv_parametro_id'],
                    ]);
                    Notification::make()->title('Setor Atualizado!')->success()->send();
                    $this->dispatch('atualizar-label-setor_fiscal', [
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
                    ->action(function () {
                        // Dispara o evento para o OpenLayers ativar as "bolinhas" de edição
                        $this->dispatch('iniciar-edicao-geometria-setor_fiscal', ['id' => $this->setorFiscalAtivoId]);
                        $this->dispatch('fechar-modal-filament'); // <-- CORREÇÃO AQUI
                    }),

                Action::make('excluir')
                    ->label('Excluir Setor')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-trash')
                    ->action(function () {
                        $setor = SetorFiscal::find($this->setorFiscalAtivoId);
                        if ($setor) {
                            $setor->delete();
                            Notification::make()->title('Setor Excluído!')->success()->send();
                            $this->dispatch('remover-setor_fiscal-mapa', ['id' => $this->setorFiscalAtivoId]);
                            $this->dispatch('fechar-modal-filament'); // <-- CORREÇÃO AQUI
                        }
                    }),
            ]);
    }
}
