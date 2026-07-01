<?php

namespace App\Filament\Resources\SecaoLogradouroResource\Pages;

use App\Filament\Resources\SecaoLogradouroResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;

class ListSecoesLogradouro extends ListRecords
{
    protected static string $resource = SecaoLogradouroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('nova_secao_logradouro')
                ->label('Nova Seção')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como deseja registrar esta seção do logradouro?')
                ->modalSubmitActionLabel('Continuar')
                ->modalWidth('md')
                ->form([
                    Forms\Components\Radio::make('metodo')
                        ->hiddenLabel()
                        ->options([
                            'mapa'    => '🗺️ Desenhar a linha no Mapa Interativo',
                            'geojson' => '💻 Preencher manualmente / Importar GeoJSON',
                        ])
                        ->default('mapa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($data['metodo'] === 'mapa') {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return redirect()->to(url('/app/' . $tenant->slug . '/mapa-interativo?layer=secoes_logradouro&action=create'));
                    }
                    return redirect()->to(SecaoLogradouroResource::getUrl('create'));
                }),
        ];
    }
}
