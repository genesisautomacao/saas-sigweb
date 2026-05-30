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
// Aceita GET (consultas por atributo / espacial / intervalo — payload pequeno)
// e POST (consultas por desenho com GeoJSON longo — evita 414 URI Too Long)
Route::match(['get', 'post'], '/api/mapa/advanced-query', [MapDataController::class, 'advancedSpatialQuery']);
Route::get('/api/mapa/estatisticas', [MapDataController::class, 'getEstatisticas']);

// Exportação de camada em Shapefile (.zip) — TR Tangará Intranet #30
Route::middleware(['web', 'auth'])->get('/api/mapa/export-shp', function (\Illuminate\Http\Request $request) {
    $layer    = (string) $request->query('layer');
    $tenantId = (int) $request->query('tenant_id');

    if (!$layer || !$tenantId) {
        abort(400, 'Parâmetros layer e tenant_id são obrigatórios.');
    }

    return app(\App\Services\Exports\ShapefileExportService::class)->exportStream($layer, $tenantId);
})->name('api.mapa.export-shp');

// Rota exclusiva para o Mapa do Portal do Cidadão
Route::get('/cidadao/lotes-geojson', [\App\Http\Controllers\CidadaoMapController::class, 'getLotes'])->middleware('web');

// Seletor público de prefeitura — usado quando o cidadão acessa o mapa anonimamente
// e precisa escolher de qual município quer ver os dados.
Route::get('/mapa-publico', function () {
    $tenants = \App\Models\Tenant::orderBy('name')->get(['id', 'slug', 'name']);
    return view('mapa-publico-selecionar-cidade', ['tenants' => $tenants]);
})->name('mapa.publico.selecionar');

// Atalho legado: redireciona para o seletor (ou direto para o mapa se vier com slug)
Route::get('/mapa-anonimo/{tenantSlug?}', function (?string $tenantSlug = null) {
    return $tenantSlug
        ? redirect('/cidadao/mapa-publico?t=' . urlencode($tenantSlug))
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
    if (! $tenant) abort(404);

    $userId   = auth()->id();
    $tenantId = $tenant->id;

    // Bypass total para Master e Manager (raw query — evita cache Spatie)
    $isPrivileged = \Illuminate\Support\Facades\DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $userId)
        ->where('model_has_roles.model_type', 'App\\Models\\User')
        ->where('roles.tenant_id', $tenantId)
        ->whereIn('roles.name', ['Master', 'Manager'])
        ->exists();

    if ($isPrivileged) {
        return response()->json(['bypass' => true]);
    }

    // IDs dos roles do usuário neste tenant
    $roleIds = \Illuminate\Support\Facades\DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $userId)
        ->where('model_has_roles.model_type', 'App\\Models\\User')
        ->where('roles.tenant_id', $tenantId)
        ->pluck('roles.id');

    // Todas as permissões desses roles
    $permNames = \Illuminate\Support\Facades\DB::table('role_has_permissions')
        ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
        ->whereIn('role_has_permissions.role_id', $roleIds)
        ->pluck('permissions.name')
        ->unique();

    // Camadas: null = sem configuração (backward compat — mostra tudo)
    $anyLayerPerm = \Illuminate\Support\Facades\DB::table('permissions')
        ->where('name', 'like', 'ver_camada_%')->exists();
    $layers = null;
    if ($anyLayerPerm) {
        $layers = $permNames
            ->filter(fn ($p) => str_starts_with($p, 'ver_camada_'))
            ->map(fn ($p) => str_replace('ver_camada_', '', $p))
            ->values();
    }

    // Toolbar: null = sem configuração (mostra tudo)
    $anyToolbarPerm = \Illuminate\Support\Facades\DB::table('permissions')
        ->where('name', 'like', 'toolbar_%')->exists();
    $toolbar = null;
    if ($anyToolbarPerm) {
        $toolbar = [
            'criar_artefatos' => $permNames->contains('toolbar_criar_artefatos'),
            'ferramentas'     => $permNames->contains('toolbar_ferramentas'),
            'filtros'         => $permNames->contains('toolbar_filtros'),
        ];
    }

    return response()->json(['bypass' => false, 'layers' => $layers, 'toolbar' => $toolbar]);
})->middleware(['web', 'auth'])->name('gis.map-permissions');

