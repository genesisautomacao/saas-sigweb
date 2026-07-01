<?php

namespace App\Filament\Pages\Traits;

use App\Filament\Resources\SecaoLogradouroResource;
use App\Models\Logradouro;
use App\Models\SecaoLogradouro;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

trait HasSecaoLogradouroActions
{
    public ?int $secaoLogradouroAtivoId = null;

    // Auto-detecção topológica + cálculo de extensão pré-criação (preenchidos em interceptarDesenho)
    public ?int $secaoLogradouroLogradouroPreSelecionadoId = null;
    public ?float $secaoLogradouroExtensaoCalculada = null;

    public function criarSecaoLogradouroAction(): Action
    {
        return Action::make('criarSecaoLogradouro')
            ->modalHeading('Cadastrar Nova Seção de Logradouro')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar')
            ->fillForm(function (): array {
                return [
                    'logradouro_id' => $this->secaoLogradouroLogradouroPreSelecionadoId,
                ];
            })
            ->form([
                Placeholder::make('extensao_calculada')
                    ->label('Extensão calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->secaoLogradouroExtensaoCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->secaoLogradouroExtensaoCalculada, 2, ',', '.') . ' m</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a linha no mapa primeiro.</em>'
                    )),
                Select::make('logradouro_id')
                    ->label('Logradouro')
                    ->helperText('Logradouro vinculado. Pode alterar se necessário.')
                    ->options(Logradouro::where('tenant_id', $this->tenantId)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('Nome / Identificação da Seção')
                    ->maxLength(255),
                Select::make('tipo_pavimentacao')
                    ->label('Tipo de Pavimentação')
                    ->options(SecaoLogradouroResource::getTipoPavimentacaoOptions()),
            ])
            ->action(function (array $data) {
                $data['tenant_id']    = $this->tenantId;
                $data['geo']          = $this->geometriaRascunho;
                $data['code']         = (string) Str::uuid();
                $data['extensao_geo'] = $this->secaoLogradouroExtensaoCalculada;

                $registro = SecaoLogradouro::create($data);

                // Recalcula extensão direto do PostGIS pra garantir precisão
                try {
                    DB::statement('UPDATE secoes_logradouro SET extensao_geo = ST_Length(geo::geography) WHERE id = ?', [$registro->id]);
                } catch (\Exception $e) {
                    // Ignora silenciosamente se a coluna ainda não existir (deploy fora de ordem)
                }

                Notification::make()->title('Seção de Logradouro criada!')->success()->send();

                $this->dispatch('adicionar-secao_logradouro-mapa', [
                    'id'   => $registro->id,
                    'name' => $registro->name ?: ('Seção #' . $registro->sequential_id),
                    'geo'  => $this->geometriaRascunho,
                ]);
                $this->dispatch('limpar-rascunho-mapa');

                $this->secaoLogradouroLogradouroPreSelecionadoId = null;
                $this->secaoLogradouroExtensaoCalculada = null;
            });
    }

    public function opcoesSecaoLogradouroAction(): Action
    {
        return Action::make('opcoesSecaoLogradouro')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Seção #' . SecaoLogradouro::find($this->secaoLogradouroAtivoId)?->sequential_id)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = SecaoLogradouro::find($this->secaoLogradouroAtivoId);
                return [
                    'name'              => $reg?->name,
                    'tipo_pavimentacao' => $reg?->tipo_pavimentacao,
                    'logradouro_id'     => $reg?->logradouro_id,
                ];
            })
            ->form([
                Placeholder::make('extensao_atual')
                    ->label('Extensão atual')
                    ->content(function (): HtmlString {
                        $reg = SecaoLogradouro::find($this->secaoLogradouroAtivoId);
                        $valor = $reg?->extensao_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),
                Select::make('logradouro_id')
                    ->label('Logradouro')
                    ->options(Logradouro::where('tenant_id', $this->tenantId)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('Nome / Identificação da Seção')
                    ->maxLength(255),
                Select::make('tipo_pavimentacao')
                    ->label('Tipo de Pavimentação')
                    ->options(SecaoLogradouroResource::getTipoPavimentacaoOptions()),
            ])
            ->action(function (array $data) {
                $reg = SecaoLogradouro::find($this->secaoLogradouroAtivoId);
                if ($reg) {
                    $reg->update($data);
                    $this->dispatch('atualizar-label-secao_logradouro', [
                        'id'   => $reg->id,
                        'name' => $reg->name ?: ('Seção #' . $reg->sequential_id),
                    ]);
                    Notification::make()->title('Atualizado!')->success()->send();
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_secao_logradouro')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-secao_logradouro', id: $this->secaoLogradouroAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_secao_logradouro')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        SecaoLogradouro::where('id', $this->secaoLogradouroAtivoId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-secao_logradouro-mapa', ['id' => $this->secaoLogradouroAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}
