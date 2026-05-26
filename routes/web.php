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

