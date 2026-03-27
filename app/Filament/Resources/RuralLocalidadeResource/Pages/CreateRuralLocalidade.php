<?php
namespace App\Filament\Resources\RuralLocalidadeResource\Pages;

use App\Filament\Resources\RuralLocalidadeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CreateRuralLocalidade extends CreateRecord
{
    protected static string $resource = RuralLocalidadeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array {
        $data['code'] = (string) Str::uuid();
        if (!empty($data['geo_json_input'])) {
            try { $data['geo'] = json_decode($data['geo_json_input'], true); } 
            catch (\Exception $e) { $data['geo'] = null; }
        }
        unset($data['geo_json_input']);
        return $data;
    }

    protected function afterCreate(): void {
        DB::statement("UPDATE rural_localidades SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);
    }
}