<?php

namespace App\Filament\Admin\Resources\ArquivoGeradoAdminResource\Pages;

use App\Filament\Admin\Resources\ArquivoGeradoAdminResource;
use Filament\Resources\Pages\ListRecords;

class ListArquivosGeradosAdmin extends ListRecords
{
    protected static string $resource = ArquivoGeradoAdminResource::class;

    protected ?string $subheading = 'Exportações geradas em segundo plano pelo painel do SaaS. Cada arquivo fica disponível por 7 dias.';
}
