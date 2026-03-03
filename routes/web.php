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
