{{-- resources/views/filament/cidadao/components/visualizador-360-publico.blade.php --}}
@if($ponto)
    <div class="relative w-full rounded-xl overflow-hidden shadow-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900" 
         style="height: 65vh; min-height: 500px;"
         x-data="{
            initPannellum() {
                // Verifica se a biblioteca já existe na memória do navegador
                if (typeof pannellum === 'undefined') {
                    
                    // 1. Injeta o CSS do Pannellum no cabeçalho da página
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css';
                    document.head.appendChild(link);

                    // 2. Injeta o JS do Pannellum e aguarda o download
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js';
                    
                    // 3. Quando terminar de baixar, renderiza a imagem
                    script.onload = () => {
                        this.renderizarPanorama();
                    };
                    document.head.appendChild(script);
                    
                } else {
                    // Se já existir (usuário abriu a modal pela 2ª vez), renderiza direto
                    this.renderizarPanorama();
                }
            },
            
            renderizarPanorama() {
                // Pequeno delay para garantir que a div já está na tela
                setTimeout(() => {
                    pannellum.viewer('{{ $uniqueId }}', {
                        'type': 'equirectangular',
                        'panorama': '{{ $imagemUrl }}',
                        'autoLoad': true,
                        'compass': true,
                        'showControls': true
                    });
                }, 150);
            }
         }" 
         x-init="initPannellum()">
        
        {{-- DIV DE RENDERIZAÇÃO DO PANNELLUM (Blindada pelo Livewire e com altura garantida) --}}
        <div wire:ignore class="w-full h-full">
            <div id="{{ $uniqueId }}" class="w-full h-full bg-gray-200 dark:bg-gray-800"></div>
        </div>

        {{-- Tarja informativa limpa (Modo Leitura) --}}
        <div class="absolute bottom-0 left-0 w-full bg-black/60 backdrop-blur-sm text-white px-4 py-3 flex justify-between items-center z-10 pointer-events-none">
            <div>
                <p class="font-bold text-sm flex items-center gap-2">
                    <x-heroicon-o-camera class="w-4 h-4 text-blue-400" />
                    {{ $ponto->titulo }}
                </p>
                @if(!$ponto->image_path)
                    <span class="inline-block mt-1 px-2 py-0.5 bg-amber-500/20 text-amber-300 text-xs font-bold rounded border border-amber-500/50">
                        MODO SIMULAÇÃO
                    </span>
                @endif
            </div>
        </div>

    </div>
@else
    <div class="flex flex-col items-center justify-center h-[500px] text-gray-500">
        <x-heroicon-o-exclamation-triangle class="w-12 h-12 mb-2 text-gray-400" />
        <p>Ponto Panorâmico não encontrado.</p>
    </div>
@endif