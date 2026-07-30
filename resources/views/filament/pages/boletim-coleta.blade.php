<x-filament-panels::page>
    <div class="fi-section-content p-4 mb-4 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
        Defina o que o <strong>cadastrador de rua</strong> preenche no aplicativo. Os campos abaixo são os que o
        município usa hoje — para criar campos novos ou renomeá-los, use o menu
        <strong>Customizações</strong>.
    </div>

    <form wire:submit="salvar">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" size="lg">Salvar boletim</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
