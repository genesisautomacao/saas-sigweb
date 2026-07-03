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
use App\Http\Controllers\Api\MensagemController;
use App\Http\Controllers\Api\ChamadoController;
use App\Http\Controllers\Api\CidadaoAuthController;

// Rota OGC Interoperability (WFS/WMS) com isolamento SaaS via Slug
Route::get('/ogc/{tenant_slug}', [OgcController::class, 'handle']);

// Rota Pública: autenticação mobile (equipe/fiscal e cidadão por e-mail/senha)
Route::post('/login', [AuthController::class, 'login']);

// Rotas Públicas: cadastro/login do CIDADÃO no app de Chamados (Módulo XVI)
// 3 tipos de login exigidos pelo edital: e-mail/senha, Google e Facebook.
Route::get('/prefeituras', [CidadaoAuthController::class, 'prefeituras']);   // picker de cidade
Route::post('/cidadao/register', [CidadaoAuthController::class, 'register']); // e-mail/senha (cadastro)
Route::post('/auth/google', [CidadaoAuthController::class, 'google']);        // Google (id_token)
Route::post('/auth/facebook', [CidadaoAuthController::class, 'facebook']);    // Facebook (access_token)

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

    // Mensagens supervisor ↔ cadastrador
    Route::get('/contatos', [MensagemController::class, 'contatos']);
    Route::get('/mensagens', [MensagemController::class, 'index']);
    Route::post('/mensagens', [MensagemController::class, 'store']);
    Route::put('/mensagens/{id}/lida', [MensagemController::class, 'marcarLida']);

    // App de Chamados (Módulo XV — superfície de integração para o app do cidadão)
    Route::get('/categorias-chamado', [ChamadoController::class, 'categorias']);
    Route::get('/fluxos-chamado', [ChamadoController::class, 'fluxos']); // fluxo + boletim (G1)
    Route::get('/chamados', [ChamadoController::class, 'index']);
    Route::post('/chamados', [ChamadoController::class, 'store']);
    Route::get('/chamados/{id}/mensagens', [ChamadoController::class, 'mensagens']);
    Route::post('/chamados/{id}/mensagens', [ChamadoController::class, 'enviarMensagem']);
});
