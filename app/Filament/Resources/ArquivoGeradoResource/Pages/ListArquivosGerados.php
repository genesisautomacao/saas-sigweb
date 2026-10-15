<?php

namespace App\Filament\Resources\ArquivoGeradoResource\Pages;

use App\Filament\Resources\ArquivoGeradoResource;
use Filament\Resources\Pages\ListRecords;

class ListArquivosGerados extends ListRecords
{
    protected static string $resource = ArquivoGeradoResource::class;

    protected ?string $subheading = 'Relatórios e exportações gerados em segundo plano. Cada arquivo fica disponível por 7 dias.';
}
