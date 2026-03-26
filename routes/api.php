<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ArvoreSyncController;
use App\Http\Controllers\Api\SolicitacaoManutencaoController;
use App\Http\Controllers\Api\SolicitacaoManutencaoSyncController;

// 🔓 Rota Pública: O celular bate aqui para tentar entrar
Route::post('/login', [AuthController::class, 'login']);

// 🔒 Rotas Protegidas: O celular SÓ entra aqui se mandar o Token no cabeçalho
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // 🌳 Rotas do Motor Offline
    Route::get('/sync/pull', [ArvoreSyncController::class, 'pull']);
    Route::post('/sync/push', [ArvoreSyncController::class, 'push']);

    // 🛠️ Rotas do Motor Offline - MANUTENÇÕES
    Route::get('/sync/manutencoes/pull', [SolicitacaoManutencaoSyncController::class, 'pull']);
    Route::post('/sync/manutencoes/push', [SolicitacaoManutencaoSyncController::class, 'push']);
});