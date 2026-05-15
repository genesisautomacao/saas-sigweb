<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MapDataController;



/* Route::get('/', function () {
    return view('welcome');
}); */

Route::redirect('/', '/app');

// Rota pública/protegida para o mapa consumir
Route::get('/api/gis-data', [MapDataController::class, 'getMapData'])->name('api.gis-data');
Route::get('/api/search-lote', [MapDataController::class, 'searchLote']);

// Adicione isto junto das suas outras rotas de mapa:
Route::get('/api/mapa/advanced-query', [MapDataController::class, 'advancedSpatialQuery']);
Route::get('/api/mapa/estatisticas', [MapDataController::class, 'getEstatisticas']);

// Rota exclusiva para o Mapa do Portal do Cidadão
Route::get('/cidadao/lotes-geojson', [\App\Http\Controllers\CidadaoMapController::class, 'getLotes'])->middleware('web');

