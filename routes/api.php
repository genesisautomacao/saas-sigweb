<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ArvoreSyncController;

// 🔓 Rota Pública: O celular bate aqui para tentar entrar
Route::post('/login', [AuthController::class, 'login']);

// 🔒 Rotas Protegidas: O celular SÓ entra aqui se mandar o Token no cabeçalho
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // 🚧 É aqui dentro que vamos colocar a Rota de Sincronização de Árvores no próximo passo!
    // 🌳 Rotas do Motor Offline (WatermelonDB)
    Route::get('/sync/pull', [ArvoreSyncController::class, 'pull']);
    Route::post('/sync/push', [ArvoreSyncController::class, 'push']);
});