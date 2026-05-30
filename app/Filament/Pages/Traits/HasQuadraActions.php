<?php

namespace App\Filament\Pages\Traits;

use App\Models\Quadra;
use App\Models\Bairro;
use App\Models\Loteamento;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

trait HasQuadraActions
{
    public ?int $quadraAtivaId = null;

    // Variáveis que receberão o auto-preenchimento topológico
    public ?int $quadraBairroPreSelecionadoId = null;
    public ?int $quadraLoteamentoPreSelecionadoId = null;
    public ?int $quadraPerimetroPreSelecionadoId = null;

    public function criarQuadraAction(): Action
    {
        return Action::make('criarQuadra')
            ->modalHeading('Cadastrar Nova Quadra')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Quadra')
            ->form([
                TextInput::make('name')
                    ->label('Identificação da Quadra (Ex: A, 10, etc)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('setor_codigo')
                    ->label('Código do Setor')
                    ->maxLength(20)
                    ->nullable(),
                Select::make('bairro_id')
                    ->label('Bairro')
                    ->options(Bairro::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->default(fn() => $this->quadraBairroPreSelecionadoId)
                    ->searchable(),
                Select::make('loteamento_id')
                    ->label('Loteamento')
                    ->options(Loteamento::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->default(fn() => $this->quadraLoteamentoPreSelecionadoId)
                    ->searchable(),
            ])
            ->action(function (array $data) {
                // 🛑 VALIDAÇÃO ANTIFRAUDE: O usuário mudou o Select manualmente?
                $polyWKT = "ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($this->geometriaRascunho) . "'), 4326)";
                $bairroId = $data['bairro_id'] ?? null;
                $loteamentoId = $data['loteamento_id'] ?? null;

                if (!$bairroId && !$loteamentoId) {
                    Notification::make()->title('Erro Obrigatório')->body('A quadra deve pertencer a pelo menos um Bairro ou Loteamento.')->danger()->send();
                    throw new \Filament\Support\Exceptions\Halt();
                }

                if ($bairroId) {
                    $valBairro = DB::selectOne("SELECT ST_Area(ST_Difference($polyWKT, (SELECT geo::geometry FROM bairros WHERE id = ?))::geography) as area_fora", [$bairroId]);
                    if ($valBairro && $valBairro->area_fora > 1.0) {
                        Notification::make()->title('Incompatibilidade Espacial')->body('A quadra desenhada possui áreas que vazam para fora do Bairro selecionado.')->danger()->send();
                        throw new \Filament\Support\Exceptions\Halt();
                    }
                }

                if ($loteamentoId) {
                    $valLoteamento = DB::selectOne("SELECT ST_Area(ST_Difference($polyWKT, (SELECT geo::geometry FROM loteamentos WHERE id = ?))::geography) as area_fora", [$loteamentoId]);
                    if ($valLoteamento && $valLoteamento->area_fora > 1.0) {
                        Notification::make()->title('Incompatibilidade Espacial')->body('A quadra desenhada possui áreas que vazam para fora do Loteamento selecionado.')->danger()->send();
                        throw new \Filament\Support\Exceptions\Halt();
                    }
                }

                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();
                // Atribui o perímetro de forma invisível para o usuário
                $data['perimetro_id'] = $this->quadraPerimetroPreSelecionadoId;

                $registro = Quadra::create($data);

                try {
                    DB::statement("UPDATE quadras SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$registro->id]);
                } catch (\Exception $e) {
                }

                Notification::make()->title('Quadra Criada!')->success()->send();

                $this->dispatch('adicionar-quadra-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->name,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesQuadraAction(): Action
    {
        return Action::make('opcoesQuadra')
            ->hiddenLabel()
            ->modalHeading(fn() => 'Editar Quadra: ' . Quadra::find($this->quadraAtivaId)?->name)
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = Quadra::find($this->quadraAtivaId);
                return [
                    'name'         => $reg?->name,
                    'setor_codigo' => $reg?->setor_codigo,
                    'bairro_id'    => $reg?->bairro_id,
                    'loteamento_id' => $reg?->loteamento_id,
                ];
            })
            ->form([
                TextInput::make('name')->label('Identificação da Quadra')->required()->maxLength(255),
                TextInput::make('setor_codigo')->label('Código do Setor')->maxLength(20)->nullable(),
                Select::make('bairro_id')
                    ->label('Bairro')
                    ->options(Bairro::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->helperText('Atualizado automaticamente ao mover a quadra no mapa.')
                    ->searchable(),
                Select::make('loteamento_id')
                    ->label('Loteamento')
                    ->options(Loteamento::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->helperText('Atualizado automaticamente ao mover a quadra no mapa.')
                    ->searchable(),
            ])
            ->action(function (array $data) {
                $reg = Quadra::find($this->quadraAtivaId);
                if ($reg) {
                    $bairroId = $data['bairro_id'] ?? null;
                    $loteamentoId = $data['loteamento_id'] ?? null;

                    if (!$bairroId && !$loteamentoId) {
                        Notification::make()->title('Erro Obrigatório')->body('A quadra deve pertencer a pelo menos um Bairro ou Loteamento.')->danger()->send();
                        throw new \Filament\Support\Exceptions\Halt();
                    }

                    // 🛑 As travas espaciais foram removidas daqui para permitir o "Override" manual pelo gestor.

                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-quadra', ['id' => $reg->id, 'name' => $data['name']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_quadra')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-quadra', id: $this->quadraAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('imprimir_planta_quadra')
                    ->label('Planta da Quadra')
                    ->color('success')
                    ->icon('heroicon-o-document-text')
                    ->action(function () {
                        // Fecha a modal e dispara a captura do mapa via JS.
                        // O JS encerra chamando $this->imprimirPlantaQuadra($id, $base64).
                        $this->dispatch('capturar-mapa-planta-quadra', id: $this->quadraAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_quadra')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        Quadra::where('id', $this->quadraAtivaId)->delete();
                        Notification::make()->title('Excluída!')->success()->send();
                        $this->dispatch('remover-quadra-mapa', ['id' => $this->quadraAtivaId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    /**
     * Recebe o base64 do canvas capturado e gera o PDF da Planta da Quadra.
     * (TR Tangará Intranet #16)
     */
    public function imprimirPlantaQuadra($quadraId, $mapImageBase64)
    {
        $quadra = Quadra::query()->find($quadraId);
        if (!$quadra) {
            Notification::make()->title('Erro')->body('Quadra não encontrada.')->danger()->send();
            return;
        }

        $service = app(\App\Services\Gis\PlantaQuadraPdfService::class);
        return $service->generatePdf($quadraId, $mapImageBase64);
    }
}
