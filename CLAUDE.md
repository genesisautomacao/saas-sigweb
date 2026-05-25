# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **Manutenção:** Sempre que uma nova feature for implementada ou um release for criado, atualize este arquivo refletindo mudanças relevantes na arquitetura, novos módulos, novos endpoints ou convenções introduzidas.

## Backlog de Pendências

O arquivo [pendencias.md](pendencias.md) é a fonte de verdade para todas as implementações futuras deste projeto. **Todo agente deve:**

1. **Consultar `pendencias.md` antes de iniciar qualquer implementação** para entender o escopo, a prioridade e os detalhes técnicos da tarefa.
2. **Ao concluir uma pendência, atualizar `pendencias.md` imediatamente:**
   - Trocar o emoji de status do item pelo ✅ (ex: `#### ~~P1 — ...~~` com riscado no título, ou inserir `**Status: ✅ Concluído**` logo abaixo do título da pendência).
   - Registrar a data de conclusão: `**Concluído em:** YYYY-MM-DD`.
   - Atualizar os contadores da tabela de conformidade no topo do arquivo se aplicável.
3. **Nunca implementar algo fora do backlog sem antes registrar a intenção em `pendencias.md`** — novas tarefas identificadas durante o desenvolvimento devem ser adicionadas ao arquivo antes de serem executadas.

## Commands

```bash
# Setup (first time)
composer run setup

# Development (runs server + queue + logs + vite concurrently)
composer run dev

# Build frontend assets
npm run build

# Run tests
composer run test
# or a single test:
php artisan test --filter TestName

# Code formatting
./vendor/bin/pint

# Run migrations
php artisan migrate

# Artisan tinker (REPL)
php artisan tinker
```

## Architecture Overview

**SIGWEB** is a multi-tenant SaaS GIS platform for Brazilian municipalities, built on Laravel 12 + Filament 3. Each municipality (tenant) gets access to a subset of modules, and all data is scoped by `tenant_id`.

### Multi-Tenancy

- **`BelongsToTenant` trait** ([app/Traits/BelongsToTenant.php](app/Traits/BelongsToTenant.php)): Applied to almost every model. Automatically adds a global Eloquent scope filtering by the current tenant, and injects `tenant_id` on creation. Uses `Filament::getTenant()` as the source of truth.
- **`SyncSpatieTenant` middleware** ([app/Http/Middleware/SyncSpatieTenant.php](app/Http/Middleware/SyncSpatieTenant.php)): Runs on every Filament request — syncs Spatie Permission's `team_id`, clears cached role/permission relations, and applies the tenant's brand color dynamically.
- **`HasTenantModule` trait** ([app/Traits/HasTenantModule.php](app/Traits/HasTenantModule.php)): Restricts Filament Resource visibility based on which modules the tenant has active (stored as a JSON array in `tenants.modules`).
- **`AppServiceProvider`** ([app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php)): Uses `Gate::before` with a raw DB query to grant full bypass to users with the `Master` or `Manager` role, avoiding Spatie's cache issues.

### Modules System

Each `Tenant` has a `modules` JSON column. Active module strings map to resource visibility and Manager role permissions:

| Module key | Covers |
|---|---|
| `administrativo` | Pessoas, Contatos, Endereços, Documentos |
| `iluminacao` | TipoPoste, Postes |
| `arborizacao` | Árvores |
| `estoque` | LocalEstoque, Marcas, Produtos, Estoques, Movimentações |
| `manutencao` | Solicitações, Ordens de Serviço |
| `cemiterio` | Cemitérios, QuadrasCemitério, Jazigos |
| `pgv` | PGV Parâmetros, SetorFiscal |
| `rural` | RuralLocalidade, RuralPropriedade, Estradas, Hidrografia, Pontes |
| `patrimonio` | TipoPatrimônio, PatrimônioPúblico |
| `social` | CadastroSocial |
| `bpmn` | BpmnFluxo, ProcessoDigital |

### GIS / Mapping

- **PostGIS** is required — spatial columns use `ST_Multi`, `ST_GeomFromGeoJSON`, `ST_AsGeoJSON`. Models with geographic data have a `geo` column with a custom setter/getter (see [app/Models/Lote.php](app/Models/Lote.php)).
- **`/api/gis-data`** ([app/Http/Controllers/Api/MapDataController.php](app/Http/Controllers/Api/MapDataController.php)): Main endpoint consumed by the map. Takes `tenant_id` and `layer` query params, returns GeoJSON FeatureCollections.
- **`/api/ogc/{tenant_slug}`** ([app/Http/Controllers/Api/OgcController.php](app/Http/Controllers/Api/OgcController.php)): WFS/WMS interoperability endpoint (OGC standard).
- **`public/js/gis/mapa-engine.js`**: The main interactive map JS engine (Leaflet-based), used by the internal Filament map page.
- **`public/js/gis/mapa-cidadao-engine.js`**: Simplified citizen-facing public map.
- **`MapaFullscreen` page** ([app/Filament/Pages/MapaFullscreen.php](app/Filament/Pages/MapaFullscreen.php)): Livewire-powered Filament page that hosts the map. Logic is split into ~20 traits in `app/Filament/Pages/Traits/Has*Actions.php`, one per GIS entity type.

### Filament Panel Structure

- **Admin panel** ([app/Filament/Admin/](app/Filament/Admin/)): Super-admin area, manages tenants. Accessible at `/admin`.
- **App panel** ([app/Filament/](app/Filament/)): Tenant-scoped area with all GIS resources. Accessible at `/app`.
- Resources follow standard Filament structure: `app/Filament/Resources/XxxResource.php` + `Pages/` subfolder.

### API (Mobile Sync)

Protected by Laravel Sanctum (`auth:sanctum`). Endpoints in [routes/api.php](routes/api.php) support offline sync for mobile field agents:
- `POST /api/login` — returns Sanctum token
- `GET /api/sync/pull` — pulls tree/maintenance records
- `POST /api/sync/push` — pushes collected field data

### Key Conventions

- All Filament forms use Portuguese field labels and entity names (Brazil-specific).
- Sequential IDs per tenant are managed by the `HasTenantSequentialId` trait.
- PDF generation uses `barryvdh/laravel-dompdf`.
- The `tenant.data` JSON column stores freeform config (logo, brand color `#rrggbb`).
