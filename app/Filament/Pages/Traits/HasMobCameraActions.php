<?php

namespace App\Filament\Pages\Traits;

use App\Filament\Resources\MobCameraResource;
use App\Models\MobCamera;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Câmeras de monitoramento em tempo real no mapa (docs/piuma.txt, Onda 5).
 * Clique no ícone → modal com o PLAYER em cima (modalContent) e os dados da
 * câmera numa seção recolhida embaixo (o mesmo modal edita, reposiciona, exclui).
 */
trait HasMobCameraActions
{
    public ?int $mobCameraAtivoId = null;

    protected function mobCameraPayload(MobCamera $c): array
    {
        return [
            'id' => $c->id,
            'layer' => 'mob_cameras',
            'name' => $c->nome ?: 'Câmera #'.$c->sequential_id,
            'sequential_id' => $c->sequential_id,
            'tipo' => $c->tipo,
            'provedor' => $c->provedor,
            'azimute_visada' => $c->azimute_visada,
            'ativo' => (bool) $c->ativo,
        ];
    }

    protected function mobCameraFormulario(): array
    {
        return [
            Grid::make(3)->schema([
                TextInput::make('nome')->label('Nome / local')->required()->maxLength(255)->columnSpan(2),
                Toggle::make('ativo')->label('Ativa')->default(true)->inline(false),
                Select::make('tipo')
                    ->label('Tipo de fonte')
                    ->options(MobCamera::TIPOS)
                    ->default('embed')
                    ->required()
                    ->live(),
                TextInput::make('provedor')->label('Provedor / operadora')->maxLength(100)->nullable(),
                TextInput::make('azimute_visada')
                    ->label('Azimute da visada (graus)')
                    ->numeric()->minValue(0)->maxValue(360)->nullable()
                    ->helperText('Para onde a câmera aponta (0 = norte)'),
                TextInput::make('url')
                    ->label('URL do vídeo')
                    ->required()->maxLength(2000)
                    ->helperText(fn (Get $get) => MobCameraResource::ajudaUrl($get('tipo')))
                    ->columnSpanFull(),
                TextInput::make('url_snapshot')->label('URL de miniatura (opcional)')->maxLength(2000)->nullable()->columnSpanFull(),
                Textarea::make('descricao')->label('Descrição')->rows(2)->nullable()->columnSpanFull(),
            ]),
        ];
    }

    public function criarMobCameraAction(): Action
    {
        return Action::make('criarMobCamera')
            ->modalHeading('Cadastrar Câmera de Monitoramento')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar')
            ->form($this->mobCameraFormulario())
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;

                $registro = MobCamera::create($data);
                $registro->refresh();

                Notification::make()->title('Câmera cadastrada!')->success()->send();
                $this->dispatch('adicionar-mob_cameras-mapa', array_merge(
                    $this->mobCameraPayload($registro),
                    ['geo' => $this->geometriaRascunho],
                ));
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesMobCameraAction(): Action
    {
        return Action::make('opcoesMobCamera')
            ->hiddenLabel()
            ->modalHeading(function () {
                $c = MobCamera::withoutGlobalScopes()->find($this->mobCameraAtivoId);

                return "\u{1F3A5} ".($c?->nome ?: 'Câmera #'.$c?->sequential_id);
            })
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->modalCancelActionLabel('Fechar')
            // Player em cima (modalContent renderiza ANTES do form)
            ->modalContent(function () {
                $c = MobCamera::withoutGlobalScopes()->find($this->mobCameraAtivoId);
                if (! $c) {
                    return new HtmlString('Câmera não encontrada.');
                }

                return view('filament.pages.partials.mob-camera-player', ['camera' => $c]);
            })
            ->fillForm(function (): array {
                $c = MobCamera::withoutGlobalScopes()->find($this->mobCameraAtivoId);

                return [
                    'nome' => $c?->nome,
                    'ativo' => $c?->ativo ?? true,
                    'tipo' => $c?->tipo ?? 'embed',
                    'provedor' => $c?->provedor,
                    'azimute_visada' => $c?->azimute_visada,
                    'url' => $c?->url,
                    'url_snapshot' => $c?->url_snapshot,
                    'descricao' => $c?->descricao,
                ];
            })
            ->form([
                Section::make('Dados da câmera')
                    ->description('Fonte do vídeo, provedor e visada. Só visual: o SIGWEB não grava imagens.')
                    ->collapsed()
                    ->schema($this->mobCameraFormulario()),
            ])
            ->action(function (array $data) {
                $c = MobCamera::withoutGlobalScopes()->find($this->mobCameraAtivoId);
                if (! $c) {
                    return;
                }
                $c->update($data);
                $c->refresh();

                Notification::make()->title('Câmera atualizada!')->success()->send();
                $this->dispatch('atualizar-mob_cameras-mapa', $this->mobCameraPayload($c));
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_mob_camera')
                    ->label('Reposicionar')
                    ->color('warning')
                    ->icon('heroicon-o-map-pin')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-mob_camera', id: $this->mobCameraAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_mob_camera')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        MobCamera::withoutGlobalScopes()->find($this->mobCameraAtivoId)?->delete();
                        Notification::make()->title('Câmera excluída!')->success()->send();
                        $this->dispatch('remover-mob_cameras-mapa', ['id' => $this->mobCameraAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    #[On('abrirOpcoesMobCamera')]
    public function abrirOpcoesMobCamera($id): void
    {
        $this->mobCameraAtivoId = (int) $id;
        $this->mountAction('opcoesMobCamera');
    }

    #[On('salvarNovaGeometriaMobCamera')]
    public function salvarNovaGeometriaMobCamera($id, $geoJson): void
    {
        $c = MobCamera::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->find($id);
        if (! $c) {
            return;
        }
        $c->update(['geo' => $geoJson]);

        Notification::make()->title('Posição da câmera atualizada!')->success()->send();
    }
}
