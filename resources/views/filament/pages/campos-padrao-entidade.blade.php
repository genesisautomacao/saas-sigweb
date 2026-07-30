<x-filament-panels::page>
    <form wire:submit="salvar">
        {{ $this->form }}

        <div class="mt-6 flex items-center justify-between">
            <x-filament::button
                tag="a"
                color="gray"
                icon="heroicon-o-arrow-left"
                :href="\App\Filament\Pages\CamposPadraoPage::getUrl()"
            >
                Voltar às entidades
            </x-filament::button>

            <x-filament::button type="submit" size="lg">Salvar</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
