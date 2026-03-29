{{-- BARRA FLUTUANTE DE EDIÇÃO ESPACIAL (Alpine.js) --}}
        <div x-data="{ editandoId: null, modoLocal: 'mover' }" 
            x-show="editandoId !== null"
            @iniciar-edicao.window="editandoId = $event.detail.id; modoLocal = 'mover';" 
            @encerrar-edicao.window="editandoId = null"
            style="display: none; position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 9999;"
            class="bg-white dark:bg-gray-800 shadow-2xl rounded-full border-2 border-emerald-500 px-4 py-2 flex items-center gap-3 animate-bounce-short">
            
            <span class="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2 pr-2 hidden sm:flex">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                Editando...
            </span>

            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 hidden sm:block"></div>

            {{-- TOGGLE MOVER / REDIMENSIONAR --}}
            <div class="flex items-center bg-gray-100 dark:bg-gray-900 rounded-full p-1 border border-gray-200 dark:border-gray-700">
                <button 
                    @click="modoLocal = 'mover'; window.alternarFerramentaEdicao('mover');"
                    :class="modoLocal === 'mover' ? 'bg-white dark:bg-gray-700 shadow text-emerald-600 dark:text-emerald-400' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-3 py-1.5 rounded-full text-xs font-bold flex items-center gap-1 transition-all">
                    <x-heroicon-o-arrows-pointing-out class="w-4 h-4" /> Arrastar
                </button>
                <button 
                    @click="modoLocal = 'redimensionar'; window.alternarFerramentaEdicao('redimensionar');"
                    :class="modoLocal === 'redimensionar' ? 'bg-white dark:bg-gray-700 shadow text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-3 py-1.5 rounded-full text-xs font-bold flex items-center gap-1 transition-all">
                    <x-heroicon-o-squares-plus class="w-4 h-4" /> Redimensionar
                </button>
            </div>

            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>

            <button onclick="salvarEdicaoGeometria()"
                class="text-sm font-black text-emerald-600 hover:text-emerald-700 uppercase flex items-center gap-1">
                <x-heroicon-o-check class="w-5 h-5" />
            </button>

            <button onclick="cancelarEdicaoGeometria()"
                class="text-sm font-black text-red-600 hover:text-red-700 uppercase flex items-center gap-1">
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
        </div>