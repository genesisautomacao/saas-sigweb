<?php
namespace App\Filament\Resources\RuralLocalidadeResource\Pages;

use App\Filament\Resources\RuralLocalidadeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditRuralLocalidade extends EditRecord
{
    protected static string $resource = RuralLocalidadeResource::class;

    protected function getHeaderActions(): array { return [ Actions\DeleteAction::make(), ]; }

    protected function mutateFormDataBeforeFill(array $data): array {
        if ($this->record->geo_json) { $data['geo_json_input'] = json_encode($this->record->geo_json, JSON_PRETTY_PRINT); }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array {
        if (!empty($data['geo_json_input'])) {
            try { $data['geo'] = json_decode($data['geo_json_input'], true); } 
            catch (\Exception $e) { /* ignora se invalido */ }
        }
        unset($data['geo_json_input']);
        return $data;
    }

    protected function afterSave(): void {
        DB::statement("UPDATE rural_localidades SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);
    }
}