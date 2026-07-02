<?php

namespace App\Filament\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Resources\ProcessoDigitalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessoDigitals extends ListRecords
{
    protected static string $resource = ProcessoDigitalResource::class;

    protected function getHeaderActions(): array
    {
        // A prefeitura NÃO cria processos — ela julga/tramita. Quem abre processo é o cidadão
        // (painel `cidadao`). Um CreateAction aqui abriria um modal que falha ao salvar
        // (Tenant não tem a relação `processoDigitals`).
        return [];
    }
}
