<?php

namespace App\Filament\Resources\SetorFiscalResource\Pages;

use App\Filament\Resources\SetorFiscalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSetorFiscal extends EditRecord
{
    protected static string $resource = SetorFiscalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->geo_json) {
            $data['geo_json_input'] = json_encode($this->record->geo_json, JSON_PRETTY_PRINT);
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['geo_json_input'])) {
            try {
                $data['geo'] = json_decode($data['geo_json_input'], true);
            } catch (\Exception $e) {
                // Ignora se for inválido
            }
        }
        unset($data['geo_json_input']);
        return $data;
    }
}