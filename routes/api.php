<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ArvoreSyncController;
use App\Http\Controllers\Api\SolicitacaoManutencaoSyncController;
use App\Http\Controllers\Api\OgcController;
use App\Http\Controllers\Api\MobileMapDataController;
use App\Http\Controllers\Api\LoteSyncController;
use App\Http\Controllers\Api\LoteNearestController;
use App\Http\Controllers\Api\CadastradorLocationController;
use App\Http\Controllers\Api\ProductividadeController;

// Rota OGC Interoperability (WFS/WMS) com isolamento SaaS via Slug
Route::get('/ogc/{tenant_slug}', [OgcController::class, 'handle']);

// Rota Pública: autenticação mobile
Route::post('/login', [AuthController::class, 'login']);

// Rotas Protegidas
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Arborização (offline-first)
    Route::get('/sync/pull', [ArvoreSyncController::class, 'pull']);
    Route::post('/sync/push', [ArvoreSyncController::class, 'push']);

    // Manutenções (offline-first)
    Route::get('/sync/manutencoes/pull', [SolicitacaoManutencaoSyncController::class, 'pull']);
    Route::post('/sync/manutencoes/push', [SolicitacaoManutencaoSyncController::class, 'push']);

    // Camadas do Mapa Mobile (GeoJSON por layer, com bbox opcional)
    Route::get('/map/data', [MobileMapDataController::class, 'index']);

    // Lotes CTM (offline-first — pull com ficha completa, push com boletim de campo)
    Route::get('/sync/lotes/pull',  [LoteSyncController::class, 'pull']);
    Route::post('/sync/lotes/push', [LoteSyncController::class, 'push']);

    // Imóvel mais próximo não visitado (GPS do cadastrador)
    Route::get('/lotes/nearest', LoteNearestController::class);

    // GPS tracking dos cadastradores em campo
    Route::post('/cadastradores/location', [CadastradorLocationController::class, 'store']);
    Route::get('/cadastradores/locations', [CadastradorLocationController::class, 'index']);

    // Relatório de produtividade (por cadastrador / quadra / dia)
    Route::get('/reports/productivity', ProductividadeController::class);
});
