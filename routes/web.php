<?php

use App\Http\Controllers\Api\MapDataController;
use Illuminate\Support\Facades\Route;

/* Route::get('/', function () {
    return view('welcome');
}); */

Route::redirect('/', '/app');

// Rota pública/protegida para o mapa consumir
Route::get('/api/gis-data', [MapDataController::class, 'getMapData'])
    ->middleware(\App\Http\Middleware\ComprimirJsonMapa::class)
    ->name('api.gis-data');
Route::get('/api/search-lote', [MapDataController::class, 'searchLote']);

// Adicione isto junto das suas outras rotas de mapa:
// Aceita GET (consultas por atributo / espacial / intervalo — payload pequeno)
// e POST (consultas por desenho com GeoJSON longo — evita 414 URI Too Long)
Route::match(['get', 'post'], '/api/mapa/advanced-query', [MapDataController::class, 'advancedSpatialQuery'])
    ->middleware(\App\Http\Middleware\ComprimirJsonMapa::class);
Route::get('/api/mapa/estatisticas', [MapDataController::class, 'getEstatisticas']);

// Exportação de camada em Shapefile (.zip) — TR Tangará Intranet #30.
// POST = com recorte por polígono desenhado no mapa (2026-08-06); GET = camada inteira.
Route::middleware(['web', 'auth'])->match(['get', 'post'], '/api/mapa/export-shp', function (\Illuminate\Http\Request $request) {
    $layer = (string) $request->input('layer');
    $tenantId = (int) $request->input('tenant_id');
    $drawnGeometry = $request->input('drawn_geometry'); // GeoJSON do polígono de recorte (opcional)

    if (! $layer || ! $tenantId) {
        abort(400, 'Parâmetros layer e tenant_id são obrigatórios.');
    }

    // R83: equipe da prefeitura + ferramenta "exportar_shp" + permissão de ver a camada
    $tenant = \App\Models\Tenant::find($tenantId);
    abort_unless($tenant, 404);
    $user = $request->user();
    abort_unless(\App\Support\MapaPermissoes::vincularEquipe($user, $tenantId), 403, 'Só a equipe da prefeitura exporta camadas.');
    abort_unless(\App\Support\MapaPermissoes::podeFerramenta('exportar_shp', $user, $tenant), 403, 'Seu papel não tem a exportação SHP liberada.');
    abort_unless(\App\Support\MapaPermissoes::podeVerCamada($layer, $user, $tenant), 403, 'Sem permissão para esta camada.');

    return app(\App\Services\Exports\ShapefileExportService::class)
        ->exportStream($layer, $tenantId, is_string($drawnGeometry) ? $drawnGeometry : null);
})->name('api.mapa.export-shp');

// Rota exclusiva para o Mapa do Portal do Cidadão
Route::get('/cidadao/lotes-geojson', [\App\Http\Controllers\CidadaoMapController::class, 'getLotes'])->middleware('web');

// Anotação em PDF de anexos de Processo Digital (item 222 / B15)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/processo-anexo/{anexo}/anotar', [\App\Http\Controllers\ProcessoAnexoController::class, 'anotar'])->name('processo-anexo.anotar');
    Route::post('/processo-anexo/{anexo}/anotar', [\App\Http\Controllers\ProcessoAnexoController::class, 'salvar'])->name('processo-anexo.salvar');
    Route::get('/processo-anexo/{anexo}/arquivo', [\App\Http\Controllers\ProcessoAnexoController::class, 'arquivo'])->name('processo-anexo.arquivo');

    // PD-1 — download do requerimento em PDF (gera a partir do template do fluxo)
    Route::get('/processo/{processo}/requerimento', [\App\Http\Controllers\ProcessoRequerimentoController::class, 'gerar'])->name('processo.requerimento.gerar');

    // R67-4 — quadras (GeoJSON) para o mapa de atribuição de região, com quem já ocupa cada uma
    Route::get('/coleta/quadras-geojson', \App\Http\Controllers\ColetaQuadrasController::class)->name('coleta.quadras-geojson');
});

// INF-2 — link estável de um anexo de processo (lista de documentos, PDF do processo):
// confere quem pede e redireciona para o arquivo (bucket privado ou VPS). Fora do grupo
// 'auth' pelo mesmo motivo da rota abaixo (sem rota 'login' = 500); 403 manual.
Route::get('/anexos/{anexo}', [\App\Http\Controllers\ProcessoAnexoController::class, 'abrir'])
    ->middleware('web')->name('processo.anexo.abrir');

// R80-1 — foto do chamado pelo mapa quando ela ficou no disco local privado (sem o
// bucket "midia" configurado). Fora do grupo 'auth' de propósito: o middleware redireciona
// para uma rota 'login' que não existe (500). Autorização manual: equipe da prefeitura.
Route::get('/chamados-mapa/{chamadoMapa}/foto', function (\App\Models\ChamadoMapa $chamadoMapa) {
    $user = auth()->user();
    abort_unless($user && ! $user->isCidadao() && $user->tenants()->whereKey($chamadoMapa->tenant_id)->exists(), 403);
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($chamadoMapa->tenant_id);
    abort_unless($user->temPermissao('gerenciar_chamados_mapa'), 403);
    abort_unless($chamadoMapa->foto && \Illuminate\Support\Facades\Storage::disk('local')->exists($chamadoMapa->foto), 404);

    return \Illuminate\Support\Facades\Storage::disk('local')->response($chamadoMapa->foto);
})->name('chamados-mapa.foto');

// R84-4 — download de arquivo gerado pela fila (PDF detalhado, exportações). Link estável
// do sininho e da lista "Arquivos Gerados": confere quem pede a cada clique e assina um
// link novo do bucket. Fora do grupo 'auth' pelo mesmo motivo da rota acima.
Route::get('/arquivos-gerados/{arquivo}/baixar', function (\App\Models\ArquivoGerado $arquivo) {
    $user = auth()->user();
    if (! $user) {
        return redirect($arquivo->painel === 'admin' ? '/admin/login' : '/app/login');
    }
    abort_unless($arquivo->visivelPara($user), 403);
    abort_unless($arquivo->pronto(), 410, 'Arquivo expirado ou ainda não está pronto.');

    return \App\Services\ArquivosGerados\ArquivosGeradosService::respostaDownload($arquivo);
})->middleware('web')->name('arquivos-gerados.baixar');

// PT-1 — Portal de links do município: landing pública que a prefeitura usa como
// destino no site oficial (login cidadão, cadastro, mapa anônimo, vídeo tutorial).
Route::get('/portal/{tenant_slug}', [\App\Http\Controllers\PortalMunicipioController::class, 'show'])->name('portal.municipio');

// Seletor público de prefeitura — usado quando o cidadão acessa o mapa anonimamente
// e precisa escolher de qual município quer ver os dados.
Route::get('/mapa-publico', function () {
    $tenants = \App\Models\Tenant::orderBy('name')->get(['id', 'slug', 'name']);

    return view('mapa-publico-selecionar-cidade', ['tenants' => $tenants]);
})->name('mapa.publico.selecionar');

// Atalho legado: redireciona para o seletor (ou direto para o mapa se vier com slug)
Route::get('/mapa-anonimo/{tenantSlug?}', function (?string $tenantSlug = null) {
    return $tenantSlug
        ? redirect('/cidadao/mapa-publico?t='.urlencode($tenantSlug))
        : redirect('/mapa-publico');
})->name('mapa.anonimo');

// Validação pública de PDFs de Viabilidade/Parcelamento/Unificação (TR Tangará #21 / #14)
Route::get('/v/{protocolo}', [\App\Http\Controllers\ValidarViabilidadeController::class, 'show'])->name('viabilidade.validar');

// Permissões de camadas e toolbar do mapa (sessão web — sem token Sanctum)
Route::get('/gis/{tenantSlug}/map-permissions', function (\Illuminate\Http\Request $request, string $tenantSlug) {
    if (! auth()->check()) {
        return response()->json(['bypass' => false, 'layers' => null, 'toolbar' => null]);
    }

    $tenant = \App\Models\Tenant::where('slug', $tenantSlug)->first();
    if (! $tenant) {
        abort(404);
    }

    // MÓDULOS da prefeitura valem para TODO MUNDO, Manager incluído (decisão D2 de
    // docs/Modulos_Permissoes.txt): camadas de módulo inativo saem antes do bypass.
    $modulos = \App\Support\Modulos::ativos($tenant);
    $camadasIndisponiveis = \App\Support\Modulos::camadasIndisponiveis($tenant);

    // R83 (2026-10-12): fonte única = config/mapa.php via MapaPermissoes. Fora do
    // Filament o team id do Spatie não está setado — vincularEquipe() resolve isso e
    // devolve false para quem não é equipe da prefeitura (cidadão / sem vínculo).
    $user = auth()->user();
    $vazio = ['bypass' => false, 'layers' => [], 'permissoes' => [], 'artefatos' => [], 'ferramentas' => [],
        'modulos' => $modulos, 'camadas_indisponiveis' => $camadasIndisponiveis];
    if (! \App\Support\MapaPermissoes::vincularEquipe($user, (int) $tenant->id)) {
        return response()->json($vazio);
    }

    $resumo = \App\Support\MapaPermissoes::resumo($user, $tenant);

    return response()->json([
        'bypass' => $resumo['bypass'],
        // `layers` = camadas (chave com "_" e com "-") que o papel vê; null = bypass (engine não esconde nada)
        'layers' => $resumo['bypass'] ? null : array_keys(array_filter($resumo['camadas'])),
        'permissoes' => $resumo['entidades'],   // entidade => {mapa, criar, editar, excluir}
        'artefatos' => $resumo['artefatos'],     // tipo do enableDrawing => pode criar
        'ferramentas' => $resumo['ferramentas'], // id => pode usar
        'modulos' => $modulos,
        'camadas_indisponiveis' => $camadasIndisponiveis,
    ]);
})->middleware(['web', 'auth'])->name('gis.map-permissions');
