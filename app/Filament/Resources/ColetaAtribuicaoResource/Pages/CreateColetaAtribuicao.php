<?php

namespace App\Filament\Resources\ColetaAtribuicaoResource\Pages;

use App\Filament\Resources\ColetaAtribuicaoResource;
use App\Filament\Resources\ColetaAtribuicaoResource\Pages\Concerns\ValidaConflitoRegiao;
use Filament\Resources\Pages\CreateRecord;

class CreateColetaAtribuicao extends CreateRecord
{
    use ValidaConflitoRegiao;

    protected static string $resource = ColetaAtribuicaoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->validarConflitoRegiao($data);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
