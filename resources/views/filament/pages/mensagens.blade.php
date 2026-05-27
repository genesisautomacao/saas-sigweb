<x-filament-panels::page>
    <div wire:poll.5s class="grid grid-cols-1 md:grid-cols-3 gap-4 h-[calc(100vh-220px)] min-h-[500px]">

        {{-- Coluna 1: Lista de contatos --}}
        <div class="md:col-span-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <span class="text-sm font-bold text-gray-700 dark:text-gray-200">Contatos</span>
                <span class="text-[10px] text-gray-400">Atualiza a cada 30s</span>
            </div>

            <div class="flex-1 overflow-y-auto">
                @forelse($this->contatos as $c)
                    <div wire:click="selecionarContato({{ $c['id'] }})"
                         class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/50 cursor-pointer transition-colors
                                {{ $contatoSelecionadoId === $c['id']
                                    ? 'bg-primary-50 dark:bg-primary-900/30 border-l-4 border-l-primary-500'
                                    : 'hover:bg-gray-50 dark:hover:bg-gray-700/40' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center flex-shrink-0">
                                    <span class="text-primary-700 dark:text-primary-300 font-bold text-xs">
                                        {{ strtoupper(substr($c['name'], 0, 1)) }}
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $c['name'] }}</p>
                                    @if($c['ultima_msg'])
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $c['ultima_msg'] }}</p>
                                    @else
                                        <p class="text-xs text-gray-400 italic">Sem mensagens</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                @if($c['ultima_em'])
                                    <span class="text-[10px] text-gray-400">{{ $c['ultima_em'] }}</span>
                                @endif
                                @if($c['nao_lidas'] > 0)
                                    <span class="bg-red-500 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5 min-w-[18px] text-center">
                                        {{ $c['nao_lidas'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-10 text-center text-gray-400">
                        <x-heroicon-o-users class="w-10 h-10 mb-2 opacity-30" />
                        <p class="text-sm">Nenhum outro usuário neste tenant.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Colunas 2-3: Janela de chat --}}
        <div class="md:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col">
            @if($contatoSelecionadoId)
                @php
                    $contatoAtivo = collect($this->contatos)->firstWhere('id', $contatoSelecionadoId);
                @endphp

                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                        <span class="text-primary-700 dark:text-primary-300 font-bold text-xs">
                            {{ strtoupper(substr($contatoAtivo['name'] ?? '?', 0, 1)) }}
                        </span>
                    </div>
                    <span class="font-semibold text-sm text-gray-800 dark:text-gray-100">{{ $contatoAtivo['name'] ?? 'Conversa' }}</span>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-2 bg-gray-50/50 dark:bg-gray-900/30"
                     x-data x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
                     x-on:livewire-update.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                    @forelse($this->conversa as $m)
                        @php $enviada = $m->remetente_id === auth()->id(); @endphp
                        <div class="flex {{ $enviada ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[70%] px-3 py-2 rounded-2xl text-sm shadow-sm
                                {{ $enviada
                                    ? 'bg-primary-500 text-white rounded-br-sm'
                                    : 'bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-bl-sm border border-gray-200 dark:border-gray-600' }}">
                                <div class="whitespace-pre-wrap break-words">{{ $m->texto }}</div>
                                <div class="text-[10px] opacity-70 mt-1 text-right">{{ $m->created_at->format('d/m H:i') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="flex items-center justify-center h-full text-gray-400 text-sm">
                            Comece a conversa enviando uma mensagem.
                        </div>
                    @endforelse
                </div>

                <form wire:submit.prevent="enviarMensagem" class="p-3 border-t border-gray-200 dark:border-gray-700 flex gap-2 bg-white dark:bg-gray-800">
                    <input wire:model="novaMsg"
                           type="text"
                           maxlength="2000"
                           placeholder="Digite sua mensagem..."
                           class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm focus:ring-primary-500 focus:border-primary-500"
                           required>
                    <button type="submit"
                            class="px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white font-medium rounded-lg flex items-center gap-1 transition-colors">
                        <x-heroicon-o-paper-airplane class="w-4 h-4" />
                        Enviar
                    </button>
                </form>
            @else
                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                    <x-heroicon-o-chat-bubble-left-right class="w-16 h-16 mb-3 opacity-30" />
                    <p class="text-sm">Selecione um contato à esquerda para começar.</p>
                </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>
