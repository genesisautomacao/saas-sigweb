<?php

namespace App\Filament\Resources\ColetaAtribuicaoResource\Pages;

use App\Filament\Resources\ColetaAtribuicaoResource;
use App\Filament\Resources\ColetaAtribuicaoResource\Pages\Concerns\ValidaConflitoRegiao;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditColetaAtribuicao extends EditRecord
{
    use ValidaConflitoRegiao;

    protected static string $resource = ColetaAtribuicaoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->validarConflitoRegiao($data, $this->record->id);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
