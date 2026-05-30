<?php

namespace App\Filament\Pages\Traits;

use App\Models\Logradouro;
use App\Models\MeioFio;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

trait HasMeioFioActions
{
    public ?int $meioFioAtivoId = null;

    // Auto-detecção topológica + cálculo de extensão pré-criação (preenchidos em interceptarDesenho)
    public ?int $meioFioLogradouroPreSelecionadoId = null;
    public ?float $meioFioExtensaoCalculada = null;

    public function criarMeioFioAction(): Action
    {
        return Action::make('criarMeioFio')
            ->modalHeading('Cadastrar Novo Meio-fio / Calçada')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar')
            ->fillForm(function (): array {
                // Pré-preenche com o logradouro detectado automaticamente em interceptarDesenho
                return [
                    'logradouro_id' => $this->meioFioLogradouroPreSelecionadoId,
                ];
            })
            ->form([
                Placeholder::make('extensao_calculada')
                    ->label('Extensão calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->meioFioExtensaoCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->meioFioExtensaoCalculada, 2, ',', '.') . ' m</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a linha no mapa primeiro.</em>'
                    )),
                Select::make('logradouro_id')
                    ->label('Logradouro vinculado')
                    ->helperText('Detectado automaticamente como o logradouro mais próximo. Pode trocar manualmente.')
                    ->options(Logradouro::where('tenant_id', $this->tenantId)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
                Select::make('material')
                    ->label('Material')
                    ->options([
                        'concreto' => 'Concreto',
                        'granito'  => 'Granito',
                        'asfalto'  => 'Asfalto',
                        'pedra'    => 'Pedra',
                        'outro'    => 'Outro',
                    ]),
                Select::make('estado_conservacao')
                    ->label('Estado de Conservação')
                    ->options([
                        'bom'     => 'Bom',
                        'regular' => 'Regular',
                        'ruim'    => 'Ruim',
                    ]),
                Textarea::make('observacoes')->label('Observações')->rows(2),
            ])
            ->action(function (array $data) {
                $data['tenant_id']    = $this->tenantId;
                $data['geo']          = $this->geometriaRascunho;
                $data['code']         = (string) Str::uuid();
                $data['extensao_geo'] = $this->meioFioExtensaoCalculada;

                $registro = MeioFio::create($data);

                // Recalcula extensão direto do PostGIS pra garantir precisão
                try {
                    DB::statement('UPDATE meio_fios SET extensao_geo = ST_Length(geo::geography) WHERE id = ?', [$registro->id]);
                } catch (\Exception $e) {
                    // Ignora silenciosamente se a coluna ainda não existir (deploy fora de ordem)
                }

                Notification::make()->title('Meio-fio criado!')->success()->send();

                $this->dispatch('adicionar-meio_fio-mapa', [
                    'id'   => $registro->id,
                    'name' => 'Meio-fio #' . $registro->sequential_id,
                    'geo'  => $this->geometriaRascunho,
                ]);
                $this->dispatch('limpar-rascunho-mapa');

                // Limpa as variáveis de pré-detecção pra próxima criação não herdar dados
                $this->meioFioLogradouroPreSelecionadoId = null;
                $this->meioFioExtensaoCalculada = null;
            });
    }

    public function opcoesMeioFioAction(): Action
    {
        return Action::make('opcoesMeioFio')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Meio-fio #' . MeioFio::find($this->meioFioAtivoId)?->sequential_id)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = MeioFio::find($this->meioFioAtivoId);
                return [
                    'material'           => $reg?->material,
                    'estado_conservacao' => $reg?->estado_conservacao,
                    'logradouro_id'      => $reg?->logradouro_id,
                    'observacoes'        => $reg?->observacoes,
                ];
            })
            ->form([
                Placeholder::make('extensao_atual')
                    ->label('Extensão atual')
                    ->content(function (): HtmlString {
                        $reg = MeioFio::find($this->meioFioAtivoId);
                        $valor = $reg?->extensao_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),
                Select::make('logradouro_id')
                    ->label('Logradouro vinculado')
                    ->options(Logradouro::where('tenant_id', $this->tenantId)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
                Select::make('material')
                    ->label('Material')
                    ->options([
                        'concreto' => 'Concreto',
                        'granito'  => 'Granito',
                        'asfalto'  => 'Asfalto',
                        'pedra'    => 'Pedra',
                        'outro'    => 'Outro',
                    ]),
                Select::make('estado_conservacao')
                    ->label('Estado de Conservação')
                    ->options([
                        'bom'     => 'Bom',
                        'regular' => 'Regular',
                        'ruim'    => 'Ruim',
                    ]),
                Textarea::make('observacoes')->label('Observações')->rows(2),
            ])
            ->action(function (array $data) {
                $reg = MeioFio::find($this->meioFioAtivoId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Atualizado!')->success()->send();
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_meio_fio')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-meio_fio', id: $this->meioFioAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_meio_fio')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        MeioFio::where('id', $this->meioFioAtivoId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-meio_fio-mapa', ['id' => $this->meioFioAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}
