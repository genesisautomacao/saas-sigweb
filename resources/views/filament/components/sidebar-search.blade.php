<div class="px-4 pb-2 mt-4" 
    {{-- Esconde a barra de busca se o usuário recolher (colapsar) a barra lateral --}}
    @if (filament()->isSidebarCollapsibleOnDesktop())
        x-bind:class="$store.sidebar.isOpen ? 'block' : 'hidden'"
    @endif
>
    <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
        <x-filament::input
            type="search"
            placeholder="Buscar no menu..."
            x-data="{
                searchTerm: '',
                filterItems() {
                    const term = this.searchTerm.toLowerCase();
                    const groups = document.querySelectorAll('.fi-sidebar-group');
                    const items = document.querySelectorAll('.fi-sidebar-item');

                    // 1. Oculta ou exibe os itens de menu individuais
                    items.forEach(item => {
                        const itemText = item.textContent.toLowerCase();
                        if (term === '' || itemText.includes(term)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    // 2. Trata os grupos e seus cabeçalhos para esconder grupos vazios
                    groups.forEach(group => {
                        const groupTitle = group.querySelector('.fi-sidebar-group-button')?.textContent.toLowerCase() || '';
                        const visibleItems = Array.from(group.querySelectorAll('.fi-sidebar-item')).filter(i => i.style.display !== 'none');
                        
                        if (term === '') {
                            group.style.display = '';
                            return;
                        }

                        if (groupTitle.includes(term)) {
                            // Se o usuário digitou o nome do grupo, exibe ele todo
                            group.style.display = '';
                            group.querySelectorAll('.fi-sidebar-item').forEach(i => i.style.display = '');
                        } else if (visibleItems.length === 0) {
                            // Se não sobrou nada visível dentro do grupo, esconde o grupo inteiro
                            group.style.display = 'none';
                        } else {
                            group.style.display = '';
                        }
                    });
                }
            }"
            x-model="searchTerm"
            x-on:input.debounce.300ms="filterItems()"
            x-on:keydown.escape="searchTerm = ''; filterItems()"
        />
    </x-filament::input.wrapper>
</div>