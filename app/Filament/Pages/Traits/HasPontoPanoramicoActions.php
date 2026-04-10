<?php

namespace App\Filament\Pages\Traits;

use App\Models\PontoPanoramico;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload; // 👈 NOVA IMPORTAÇÃO
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;

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
                        PontoPanoramico::where('id', $this->pontoPanoramicoAtivoId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-ponto_panoramico-mapa', ['id' => $this->pontoPanoramicoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ])
            // A MÁGICA: O Visualizador 360º embutido na própria Modal usando Pannellum via Alpine.js!
            ->modalContent(function () {
                $ponto = PontoPanoramico::find($this->pontoPanoramicoAtivoId);
                if (!$ponto) return new HtmlString('Ponto não encontrado.');

                // Lógica de Simulação: Se não tiver imagem upada, usamos uma de teste do Pannellum
                $imagemUrl = $ponto->image_path 
                    ? asset('storage/' . $ponto->image_path) 
                    : 'https://pannellum.org/images/alma.jpg'; 
                
                // Gera um ID único para evitar conflitos no DOM
                $uniqueId = 'pano-' . $ponto->id;

                $bladeView = <<<'BLADE'
                <div class="flex flex-col gap-2" 
                    x-data="{
                        initPannellum() {
                            if (typeof pannellum === 'undefined') {
                                let link = document.createElement('link');
                                link.rel = 'stylesheet';
                                link.href = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css';
                                document.head.appendChild(link);

                                let script = document.createElement('script');
                                script.src = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js';
                                script.onload = () => this.renderizarPanorama();
                                document.head.appendChild(script);
                            } else {
                                this.renderizarPanorama();
                            }
                        },
                        renderizarPanorama() {
                            // Dá um delay de 300ms para a modal terminar a animação de abrir e ter altura calculada
                            setTimeout(() => {
                                pannellum.viewer('{{ $uniqueId }}', {
                                    'type': 'equirectangular',
                                    'panorama': '{{ $imagemUrl }}',
                                    'autoLoad': true,
                                    'compass': true,
                                    'showZoomCtrl': true,
                                    'mouseZoom': true
                                });
                            }, 300);
                        }
                    }" 
                    x-init="initPannellum()">
                    
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                            <x-heroicon-o-camera class="w-6 h-6 text-blue-500" />
                            {{ $ponto->titulo }}
                        </h3>
                        @if(!$ponto->image_path)
                            <span class="px-2 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded border border-amber-200">
                                MODO SIMULAÇÃO
                            </span>
                        @endif
                    </div>
                    
                    {{-- Container protegido do Livewire para o JS poder brincar com o Canvas livremente --}}
                    <div wire:ignore>
                        <div id="{{ $uniqueId }}" class="w-full rounded-xl overflow-hidden shadow-inner border border-gray-300 dark:border-gray-700 bg-gray-200" style="height: 500px;"></div>
                    </div>
                    
                </div>
                BLADE;

                return new HtmlString(Blade::render($bladeView, [
                    'ponto' => $ponto,
                    'imagemUrl' => $imagemUrl,
                    'uniqueId' => $uniqueId
                ]));
            });
    }
}