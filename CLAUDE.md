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
| `estoque` | Estabelecimento, LocalEstoque, TipoEstoque, Fabricante, Marca, Fornecedor, FamíliaProduto, UnidadeMedida, Embalagem, Produto, LoteEstoque (lote/série), OperaçãoInterna, Estoque (saldo), Movimentações |
| `manutencao` | Solicitações, Ordens de Serviço |
| `cemiterio` | Cemitérios, QuadrasCemitério, Jazigos |
| `pgv` | PGV Parâmetros, SetorFiscal, LoteValorHistórico + **Avaliação em Massa** (PgvAmostra, PgvPolo, PgvCub, PgvDepreciação, FaceQuadra, PgvConfiguração) |
| `rural` | RuralLocalidade, RuralPropriedade, Estradas, Hidrografia, Pontes |
| `patrimonio` | TipoPatrimônio, PatrimônioPúblico |
| `social` | Pessoa-Social, CadastroSocial (Família), TipoRenda, TipoEntidade, Entidade, ServiçoSocial, Programa, Evento, InformaçãoSocial, Empreendimento, Painel Social |
| `bpmn` | BpmnFluxo, ProcessoDigital |

> **Processos Digitais (BPMN) — material de estudo:** [processosConceito.md](processosConceito.md) — o motor de processos é **único e configurável por fluxos BPMN**: "Aprovação de Projeto" (XIII), "Habite-se" (XIV) e "REURB" (XVIII) são **fluxos-semente do mesmo motor**, não módulos separados. Documenta a arquitetura do **fluxo híbrido** (cada `BpmnEtapa` declara `executor` [solicitante|analista] + formulário próprio; respostas por etapa em `processo_respostas`; roteamento livre via tramitação), o vínculo **User↔Pessoa** (`pessoas.user_id`), a entidade **`Setor`** (swimlanes) e o plano de execução em ondas. Ler antes de mexer no motor de processos.

### GIS / Mapping

- **PostGIS** is required — spatial columns use `ST_Multi`, `ST_GeomFromGeoJSON`, `ST_AsGeoJSON`. Models with geographic data have a `geo` column with a custom setter/getter (see [app/Models/Lote.php](app/Models/Lote.php)).
- **`/api/gis-data`** ([app/Http/Controllers/Api/MapDataController.php](app/Http/Controllers/Api/MapDataController.php)): Main endpoint consumed by the map. Takes `tenant_id` and `layer` query params, returns GeoJSON FeatureCollections.
- **`/api/ogc/{tenant_slug}`** ([app/Http/Controllers/Api/OgcController.php](app/Http/Controllers/Api/OgcController.php)): WFS/WMS interoperability endpoint (OGC standard).
- **`public/js/gis/mapa-engine.js`**: The main interactive map JS engine (**OpenLayers 8.2**, não Leaflet), used by the internal Filament map page. Camadas vetoriais em `layerConfigs` + `fetchAndDrawLayer(layer)` → `window.loadedLayers[layer]`. Heatmaps nativos via `ol.layer.Heatmap`.
- **`public/js/gis/mapa-cidadao-engine.js`**: Simplified citizen-facing public map.

#### Camada "Processos Digitais" no mapa (um toggle por fluxo + heatmap)

Camada `processos` (gate `ver_camada_processos`) que **marca os lotes que têm processo** (self-contained — não depende da camada `lotes`), colorindo cada lote com a **cor do fluxo** (`bpmn_fluxos.cor`, definida no `BpmnFluxoResource` via `ColorPicker`) e rotulando com **nome do fluxo + etapa atual + "✓ Concluído"**. Inclui processos `em_andamento` e `concluido` com `lote_id`.

- **Backend:** `case 'processos'` em [MapDataController.php](app/Http/Controllers/Api/MapDataController.php) (query `DB::table` juntando `processos_digitais`+`lotes`+`bpmn_fluxos`+`bpmn_etapas`; geometria = polígono do lote; props `bpmn_fluxo_id`, `fluxo_cor`, `fluxo_nome`, `etapa_nome`, `is_concluido`).
- **UI:** acordeon dedicado abaixo de "Cadastro Base" em [mapa-fullscreen.blade.php](resources/views/filament/pages/mapa-fullscreen.blade.php), **um checkbox por fluxo** (padrão idêntico ao Zoneamento: swatch da cor + nome, via `@entangle('processoFluxos')`). **O acordeon só aparece se o tenant tiver ≥1 fluxo ativo** (`$processoFluxos` em `MapaFullscreen::mount()`). Toggle "Mapa de Calor" adicional.
- **Engine:** `layerConfigs.processos` (estilo por fluxo, oculta fluxos fora de `processosFluxosAtivos`), handler delegado `.processo-fluxo-toggle` (espelha `.zona-toggle`), `heatmapProcessosLayer`/`syncProcessosHeatmap()` (listener `sigweb-processos-heatmap`).
- Substituiu o antigo toggle "Processos Digitais" (que recoloria lotes por etapa e dependia da camada `lotes` estar ligada — bug do "Nenhum processo em andamento").

#### Camada "Coleta de Dados" no mapa (status de coleta + heatmap)

Acordeon abaixo de "Processos Digitais" com a camada `coleta` (**self-contained** — não depende da camada `lotes`), colorindo os lotes por `status_cadastro` (coletado=`#10B981`, pendente=`#F59E0B`, inconformidade=`#EF4444`, não visitado=`#9CA3AF`). O toggle **"Status de Coleta"** foi **movido** de baixo de "Lotes" para cá (agora é a própria camada `coleta`, `.coleta-toggle`, não recolore mais a camada `lotes`). Toggle **"Mapa de Calor"** = uma `ol.layer.Heatmap` por status (cada status na sua cor). Backend: `case 'coleta'` em [MapDataController.php](app/Http/Controllers/Api/MapDataController.php) (todos os lotes + `status_cadastro`). Engine: `layerConfigs.coleta`, handler `.coleta-toggle`, `syncColetaHeatmap()` (listener `sigweb-coleta-heatmap`). Gate `data-permission-group="layer:lotes"`.
- **`MapaFullscreen` page** ([app/Filament/Pages/MapaFullscreen.php](app/Filament/Pages/MapaFullscreen.php)): Livewire-powered Filament page that hosts the map. Logic is split into ~21 traits in `app/Filament/Pages/Traits/Has*Actions.php`, one per GIS entity type.
- **`SecaoLogradouro`** ([app/Models/SecaoLogradouro.php](app/Models/SecaoLogradouro.php)): LINESTRING/MULTILINESTRING entity — sections of a street with `tipo_pavimentacao` and cached `extensao_geo`. Accessible via `LogradouroResource` RelationManager tab ("Seções") and via standalone `SecaoLogradouroResource`. Map layer `secoes_logradouro` (violet dashed, z=52, minZoom=15). **Dois gates distintos:** camada do mapa = `ver_camada_secoes_logradouro`; recurso CRUD (Policy `SecaoLogradouroPolicy`) = `gerenciar_secoes_logradouro`.

> **Gate de recurso via Policy (importante):** um Resource **sem Policy** fica visível para qualquer usuário (o `HasTenantModule::canAccess()` só filtra por módulo; sem Policy o Gate libera por padrão). Todo Resource novo que apareça no menu precisa de `app/Policies/{Model}Policy.php` + permissão no `PermissionsSeeder` + checkbox na `RoleResource` + whitelist no `EditRole::mutateFormDataBeforeFill`. Recursos gated por permissão única "gerenciar": `AreaReurbResource` (`gerenciar_areas_reurb`), `SecaoLogradouroResource` (`gerenciar_secoes_logradouro`), `ViabilidadeEmissaoResource` (view-only, `view_viabilidade_emissoes`).

### Filament Panel Structure

- **Admin panel** ([app/Filament/Admin/](app/Filament/Admin/)): Super-admin area, manages tenants. Accessible at `/admin`.
- **App panel** ([app/Filament/](app/Filament/)): Tenant-scoped area with all GIS resources. Accessible at `/app`.
- **Cidadão panel** ([app/Filament/Cidadao/](app/Filament/Cidadao/)): Public-facing citizen area (auto-registration, digital processes). Accessible at `/cidadao`.
- Resources follow standard Filament structure: `app/Filament/Resources/XxxResource.php` + `Pages/` subfolder.

#### Distinção Prefeitura × Cidadão (`users.tipo`)

Coluna `users.tipo` = `prefeitura` | `cidadao` separa a **equipe** do **cidadão** (auto-cadastro). Regras:

- **Acesso:** `User::canAccessPanel()` bloqueia o `cidadao` no painel `app` (`return !$this->isCidadao()` para o painel `app`). O painel `cidadao` fica aberto.
- **Registro (fluxo "prefeitura primeiro"):** `RegisterCidadao` grava `tipo='cidadao'` (+ cria/vincula `Pessoa`); `RegisterPrefeitura` (`/app/register`, self-cadastro de equipe sem papel) grava `tipo='prefeitura'`. Ambos usam o trait [`HasTenantFirstRegistration`](app/Filament/Concerns/HasTenantFirstRegistration.php): **passo 1** = escolher a prefeitura (só aparece se a URL não trouxer `?prefeitura={slug}`); ao continuar, redireciona para a mesma rota com `?prefeitura={slug}` e cai no **passo 2** (dados do usuário, prefeitura fixada da URL, `tenant_id` vem de `$this->tenantSelecionadoId` — não do form). A URL com slug é **compartilhável** (ex.: link no site oficial da prefeitura cai direto nos dados). Ex.: `/cidadao/register?prefeitura=prefeitura-de-santa-cecilia`.
- **Equipe (UserResource):** filtro `tipo` **default `prefeitura`** — cidadãos ficam ocultos por padrão, mas o admin pode trocar o filtro para vê-los. Coluna **Tipo** (badge) + ação **Tornar Equipe / Tornar Cidadão** (não permite rebaixar a si mesmo) corrigem classificação sem SQL.
- **Sinal confiável (backfill legado):** usuário **sem nenhum papel = cidadão** (toda a equipe tem papel). É o critério do backfill em `2026_07_02_100012_add_tipo_to_users_table.php` — pega cidadãos antigos e novos. Em produção, revise `SELECT id,name,email FROM users WHERE tipo='cidadao'` após o `migrate`.
- **Exclusão = soft delete:** `User` usa `SoftDeletes` (coluna `deleted_at`). Excluir na Equipe marca `deleted_at` em vez de apagar a linha — evita violar as FKs `RESTRICT` das tabelas de histórico que referenciam `users` (`processo_tramitacoes`, `processo_respostas`, `processo_anexos`, `pessoas`, `mensagens`…). O usuário some das listas e do login (escopo global), mas o histórico segue resolvível: relações que precisam exibir o nome de um usuário possivelmente excluído usam `->withTrashed()` (ex.: `ProcessoTramitacao::responsavel()`).

### API (Mobile Sync)

Protected by Laravel Sanctum (`auth:sanctum`). Endpoints in [routes/api.php](routes/api.php) support offline sync for mobile field agents:

| Endpoint | Descrição |
|---|---|
| `POST /api/login` | Autenticação — retorna token + tenant (map_lat/lon/zoom) + layers |
| `GET /api/sync/pull` | Pull de árvores (arborização) |
| `POST /api/sync/push` | Push de árvores coletadas |
| `GET /api/sync/manutencoes/pull` | Pull de solicitações de manutenção |
| `POST /api/sync/manutencoes/push` | Push de manutenções |
| `GET /api/map/layers` | **Catálogo de camadas configuradas no SIG WEB p/ o app (item 179)** — só as camadas base + as dos módulos ativos, filtradas por `tenant.data['mobile_layers']` (curadoria no Admin→Tenant); cada uma com `label/tipo/cor/visivel_padrao/min_zoom` |
| `GET /api/map/data?layer=lotes&bbox=...` | GeoJSON por camada (lotes, quadras, logradouros, bairros, zonas, arvores, postes) |
| `GET /api/sync/lotes/pull` | Pull completo de lotes CTM com unidades imobiliárias + edificações |
| `POST /api/sync/lotes/push` | Push do boletim de campo (status, fotos, vistoria, inconformidades) |
| `GET /api/lotes/nearest?lat&lon` | Lote não visitado mais próximo via PostGIS |
| `POST /api/cadastradores/location` | Atualiza posição GPS do cadastrador (upsert por user) |
| `GET /api/cadastradores/locations` | Lista cadastradores ativos nos últimos 10 min |
| `GET /api/reports/productivity` | Estatísticas de coleta por cadastrador e quadra |
| `GET /api/contatos` | Lista users do tenant com role atribuída (exceto o próprio). Resposta `{data: [{id, name, email, role}]}` |
| `GET /api/mensagens` | Lista mensagens do user logado (remetente OU destinatário). Opcional `?contato_id=X` |
| `POST /api/mensagens` | Envia mensagem. Body: `{destinatario_id, texto}` — valida same-tenant. Dispara push Expo se destinatário tiver `expo_push_token` |
| `PUT /api/mensagens/{id}/lida` | Marca mensagem como lida (somente o destinatário pode) |
| `GET /api/ogc/{tenant_slug}` | Interoperabilidade WFS/WMS (OGC) |

**CTM Field Collection — estrutura do Lote para mobile:**
- `status_cadastro`: `nao_visitado` | `coletado` | `pendente` | `inconformidade`
- `foto_frontal`, `foto_lateral_esq`, `foto_lateral_dir` (base64 no push → arquivo no servidor)
- `dados_vistoria` (JSON livre — boletim de campo)
- `unidades_imobiliarias[]` — inclui `dados_tributarios` (nome do proprietário, valores, classificação)
- `edificacoes[]` — tipo, tp_construcao, estado_conservacao, area_geo

**Importação tributária:**
```bash
php artisan tributario:importar --tenant=antonio-carlos --file=dados.json
```
Faz upsert em `unidades_imobiliarias.dados_tributarios` por `inscricao_imobiliaria`. Suporta `--dry-run`.

### Key Conventions

- All Filament forms use Portuguese field labels and entity names (Brazil-specific).
- Sequential IDs per tenant are managed by the `HasTenantSequentialId` trait.
- PDF generation uses `barryvdh/laravel-dompdf`.
- Export Services (`app/Services/Exports/*ExportService.php`) follow a 3-method convention: `exportToExcel()` (Spatie SimpleExcel — `addNewSheetAndMakeItCurrent()` for multi-sheet workbooks), `exportToPdf()` (DomPDF + blade view), and `exportToXml()` (raw `SimpleXMLElement`, returned via `streamDownload` with `Content-Type: application/xml`). `LoteExportService` nests `unidadesImobiliarias`/`edificacoes` (via `lote_id`) in all three formats — XML as child elements, Excel as separate sheets, PDF via `pdf/lote-detalhado-report.blade.php` (per-lote block with sub-tables). `QuadraExportService`/`LogradouroExportService`/`LoteamentoExportService`/`BairroExportService` also expose `exportToXml()`. **Os 4 formatos XLS/PDF/CSV/XML** estão completos no núcleo imobiliário (`Lote`, `Quadra`, `Bairro`, `Logradouro`, `Loteamento` — item 015) e no `Produto` (item 053) — cada um com os 4 botões no `ActionGroup` da List page. Iluminação/Arborização/Rural (`Poste`, `Arvore`, `SolicitacaoManutencao`, `OrdemServico`, e os 6 `Rural*ExportService`) ganharam **`exportToCsv()` + `exportToXml()`** (padrão helper `linha()` + `SimpleExcelWriter::create($path,'csv')` + `SimpleXMLElement`) para atender os 4 formatos XLS/PDF/CSV/XML dos itens 060/076/251 — `RuralPropriedadeExportService` inclui `codigo_incra`/`codigo_car`/`codigo_sigef` (251b). The export menu (Filament `ActionGroup` in each `List*` page) exposes "Exportar Excel" / "Exportar PDF" / "Exportar CSV" / "Exportar XML".
- The `tenant.data` JSON column stores freeform config (logo, brand color `#rrggbb`, `map_lat`, `map_lon`, `map_zoom`).
- API controllers use `$request->user()->tenants()->first()->id` for tenant isolation (no global scope in API layer).
- `cadastrador_locations` table: upsert por `user_id` — um registro por usuário, atualizado a cada ping GPS.
- Activity log (`spatie/laravel-activitylog`) instalado. Models logados: `Lote`, `Edificacao`, `Quadra`, `Logradouro`, `Bairro`, `Pessoa`. Para logar um novo model, adicionar o trait `LogsActivity` + definir `$recordEvents` e `logOnly([...])`.
- **Histórico cartográfico** (croqui Antes/Depois na Auditoria): trait [`LogsGeometryChanges`](app/Traits/LogsGeometryChanges.php) registra uma atividade dedicada (`old`/`attributes` com `geo_json`) sempre que a coluna `geo` muda. Aplicado em `Lote`, `Quadra`, `Bairro`. Como `LogsActivity`+`logOnlyDirty` não vê a coluna `geo` (raw PostGIS), este trait é o que faz a **alteração de geometria** aparecer na Auditoria. Requer accessor `geo_json` + `setGeoAttribute`; sobrescreva `geometryLogLabel()` para o rótulo. Para incluir outra entidade GIS (Logradouro, Loteamento, Zona, PerímetroUrbano…), basta adicionar o trait ao model.

---

## CTM — Módulo de Coleta em Campo (PoC Antônio Carlos/MG)

Este módulo implementa a infraestrutura de cadastro técnico multifinalitário (CTM) para coleta em campo via app Android, com supervisão em tempo real pelo painel WEB.

### Hierarquia de dados

```
Lote (pai)
├── UnidadeImobiliaria[]  (lote_id FK) — imóvel tributário, dados do proprietário
└── Edificacao[]          (lote_id FK) — física da construção
```

O pull retorna lotes com `unidades_imobiliarias` e `edificacoes` aninhados, permitindo ao cadastrador ver dados existentes sem conexão.

### Campos CTM no model `Lote`

| Campo | Tipo | Valores / Observação |
|---|---|---|
| `status_cadastro` | enum | `nao_visitado` · `coletado` · `pendente` · `inconformidade` |
| `ocupacao` | enum\|null | `baldio` · `construido` |
| `situacao_quadra` | enum\|null | `meio_quadra` · `esquina` · `encravado` |
| `foto_frontal` | string\|null | path relativo (base64 no push → arquivo no servidor) |
| `foto_lateral_esq` | string\|null | idem |
| `foto_lateral_dir` | string\|null | idem |
| `inconformidade_descricao` | text\|null | texto livre |
| `dados_vistoria` | json\|null | boletim livre — qualquer schema aceito |
| `coletado_por_id` | FK users\|null | preenchido automaticamente no push |
| `coletado_em` | timestamp\|null | preenchido automaticamente no push |

### API — Detalhe dos endpoints CTM

#### `GET /api/sync/lotes/pull`

Retorna todos os lotes do tenant com a ficha completa para uso offline. Usar 3 queries flat (lotes raw SQL + unidades + edificações agrupadas por `lote_id`) para evitar N+1.

Estrutura de resposta por lote:
```json
{
  "id": "uuid",
  "numero_lote": "0187",
  "sequential_id": 42,
  "area_geo": 534.0,
  "status_cadastro": "nao_visitado",
  "ocupacao": null,
  "situacao_quadra": null,
  "foto_frontal": null,
  "foto_lateral_esq": null,
  "foto_lateral_dir": null,
  "inconformidade_descricao": null,
  "dados_vistoria": null,
  "coletado_por_id": null,
  "coletado_em": null,
  "geo_json": { "type": "Polygon", "coordinates": [[...]] },
  "unidades_imobiliarias": [{
    "id": "uuid",
    "inscricao_imobiliaria": "01.02.009.0187.001.1",
    "codigo_imovel_tributario": "0000000084",
    "logradouro_nome": "Rua das Flores",
    "numero_imovel": "123",
    "complemento": null,
    "dados_tributarios": {
      "proprietario_name": "João da Silva",
      "tipo_construcao": "Casa",
      "descricao_classificacao": "Residencial",
      "area_geo": "534.00",
      "area_edificacao": "213.23",
      "valor_total_imposto": "654.30"
    }
  }],
  "edificacoes": [{
    "id": "uuid",
    "tipo": "Residencial",
    "tp_construcao": "Alvenaria",
    "caracteristica_construcao": null,
    "estado_conservacao": "Bom",
    "area_geo": 213.23
  }]
}
```

#### `POST /api/sync/lotes/push`

Aceita apenas os lotes alterados offline. Campos aceitos por lote no array `changes.lotes.updated`:

```json
{
  "id": "uuid-do-lote",
  "status_cadastro": "coletado",
  "ocupacao": "construido",
  "situacao_quadra": "meio_quadra",
  "inconformidade_descricao": "texto livre",
  "dados_vistoria": { "num_pavimentos": 2, "uso": "residencial" },
  "foto_frontal": "data:image/jpeg;base64,...",
  "foto_lateral_esq": "data:image/jpeg;base64,...",
  "foto_lateral_dir": "data:image/jpeg;base64,...",
  "edificacoes_updates": [
    { "id": "uuid", "tipo": "Comercial", "estado_conservacao": "Regular", "area_geo": 220.0 }
  ]
}
```

`coletado_por_id` e `coletado_em` são preenchidos automaticamente pelo servidor com o usuário autenticado e `now()`.

#### `GET /api/lotes/nearest?lat={lat}&lon={lon}`

Usa operador KNN `<->` do PostGIS para encontrar o lote mais próximo com `status_cadastro = 'nao_visitado'`. Retorna:
```json
{ "id": "uuid", "numero_lote": "0042", "sequential_id": 17, "status_cadastro": "nao_visitado", "distancia_metros": 47.3, "geo_json": {...} }
```

#### `POST /api/cadastradores/location`

Upsert por `user_id` na tabela `cadastrador_locations`. Body: `{ "lat": -27.5, "lon": -50.4 }`. Chamar a cada 60s em background.

#### `GET /api/cadastradores/locations`

Lista usuários com ping GPS nos últimos 10 minutos, com `coletados_hoje`. Consumido pelo `MonitoramentoCampoPage`.

#### `GET /api/reports/productivity?data={YYYY-MM-DD}&quadra_id={id}`

Ambos opcionais. Retorna resumo geral + `por_cadastrador[]` + `por_quadra[]`, cada um com `percentual`.

#### `GET /api/map/data?layer=lotes&bbox=minLon,minLat,maxLon,maxLat`

Layer `lotes` inclui nas properties: `status_cadastro` e `ocupacao`. Paleta recomendada para o mobile:
- `nao_visitado` → `#9CA3AF` (cinza)
- `coletado` → `#10B981` (verde)
- `pendente` → `#F59E0B` (amarelo)
- `inconformidade` → `#EF4444` (vermelho)

### Importação tributária via artisan

```bash
# Teste sem gravar:
php artisan tributario:importar --tenant=antonio-carlos --file=dados.json --dry-run

# Importação real:
php artisan tributario:importar --tenant=antonio-carlos --file=dados.json
```

O JSON pode ser array raiz `[{...}]` ou `{"imoveis": [{...}]}`. Cada item precisa do campo `inscricao_imobiliaria`. Todo o restante do objeto vai para `dados_tributarios` como JSON livre. Faz upsert — não duplica se rodar mais de uma vez.

### Colunas fiscais derivadas em `unidade_imobiliarias`

Além do JSON `dados_tributarios` (fonte bruta, usada no BIC), 13 chaves fiscais são **promovidas a colunas** para busca/edição/relatórios (`UnidadeImobiliaria::CAMPOS_FISCAIS`): `tipo_construcao`, `descricao_classificacao`, `face`, `fracao_ideal`, `area_edificacao`, `area_total_edificacao`, `valor_venal_lote`, `valor_venal_edificacao`, `valor_metro_terreno`, `valor_metro_edificacao`, `valor_imposto_territorial`, `valor_imposto_predial`, `valor_total_imposto`.

- **JSON → colunas:** hook `saved()` em [UnidadeImobiliaria.php](app/Models/UnidadeImobiliaria.php) (`sincronizarColunasFiscais()`) deriva as colunas do JSON no sync tributário e faz backfill quando a coluna está vazia. Roda junto com `propagarEnderecoParaLote()` (endereço → lote).
- **Colunas → JSON (write-through):** os modais "Editar Unidade Imobiliária" (`editarUnidadeAction`) e "Cadastrar Nova Unidade Imobiliária" (`criarUnidadeAction`) em [HasLoteActions.php](app/Filament/Pages/Traits/HasLoteActions.php) trocam o textarea do JSON por inputs individuais; ao salvar, refletem os valores de volta no `dados_tributarios` para o BIC continuar correto. Ambos têm o botão ☁️ (informar só o código tributário e sincronizar preenche todos os inputs) e um bloco "JSON bruto" recolhível (read-only).
- Endereço (`tipo_logradouro`/`logradouro`/`numero_logradouro`/`cep`), `area_geo` e `testada` **não** foram duplicados na unidade — vivem no `Lote` (evita redundância, pois um lote tem N unidades).

### Módulo de Estoque / Almoxarifado (PoC Nova Esperança — itens 053–059)

Cadastros completos sob o grupo "Estoque e Almoxarifado" (módulo `estoque`), todos com CRUD via modal (Resource + só a List page):

- **Estabelecimento** → `LocalEstoque` pertence a um estabelecimento (`estabelecimento_id`).
- **Fabricante** → `Marca` pertence a um fabricante (`fabricante_id`).
- **Fornecedor** → origem dos `LoteEstoque`.
- **UnidadeMedida** (`sigla`) → usada por `Embalagem` e `Produto`.
- **Embalagem** (`quantidade` + `unidade_medida_id`).
- **FamíliaProduto** → `Produto.familia_produto_id` (dimensão dos relatórios 058/059).
- **TipoEstoque** → dimensão de saldo/movimentação (`Estoque.tipo_estoque_id`, `EstoqueMovimentacao.tipo_estoque_origem_id`/`destino_id`).
- **OperacaoInterna** (`sentido` = entrada/saida/transferencia) → configura a movimentação (item 054); ao escolher no form da movimentação, seta o `type` automaticamente pelo `sentido`.
- **LoteEstoque** (`numero_lote`/série, produto, fornecedor, `data_fabricacao`/`validade`/`garantia`, `quantidade_inicial`) → controle por lote/série (item 055). Accessor `dias_garantia` (negativo = vencida).

`Produto` ganhou `familia_produto_id`, `unidade_medida_id`, `embalagem_id`; `Estoque` (saldo) ganhou `tipo_estoque_id` + `lote_estoque_id`; `MovimentacaoItem` ganhou `lote_estoque_id`. A lógica de saldo em `EstoqueMovimentacao` continua chaveada por `type` (mantido).

**Permissões:** os 9 cadastros auxiliares usam uma **permissão única `gerenciar_X`** por entidade (não o quarteto view/create/edit/delete) — `gerenciar_estabelecimentos`, `gerenciar_fabricantes`, `gerenciar_fornecedores`, `gerenciar_unidade_medidas`, `gerenciar_embalagens`, `gerenciar_familia_produtos`, `gerenciar_tipo_estoques`, `gerenciar_operacao_internas`, `gerenciar_lote_estoques`. Cada Policy (`app/Policies/{Model}Policy.php`) checa a mesma permissão em todas as abilities. Configuráveis na CAIXA 9b da [RoleResource.php](app/Filament/Resources/RoleResource.php) ("Estoque — Cadastros Auxiliares"). Master/Manager mantêm bypass via `Gate::before`. Produto/Marca/LocalEstoque/Estoque/Movimentação continuam com o quarteto tradicional.

**Relatórios (padrão ExportService — Excel/PDF/XML, via `ActionGroup` na List page):**
- **057 Movimentação** — `EstoqueMovimentacaoExportService`; filtros por período, produto, lote/série e tipo de estoque em `EstoqueMovimentacaoResource`.
- **058 Saldo** — `EstoqueExportService`; filtros por local, tipo de estoque, produto e família em `EstoqueResource`.
- **059 Garantia** — `LoteEstoqueExportService`; `LoteEstoqueResource` com badge de situação da garantia (vencida/vence em 30d/vigente) e filtros por produto, fornecedor, família, "garantia vencida" e "vence em 30 dias".

### Módulo de Gestão do Cadastro Social (PoC Nova Esperança — itens 091–098)

Módulo `social`, grupo "Módulo Social". Entidade central `CadastroSocial` = a **Família** (RF via `pessoa_id`, moradia via `unidade_imobiliaria_id`/`empreendimento_id`, membros via `MembroFamilia`).

- **Pessoa-Social (092/093):** campos sociais adicionados direto em `pessoas` (rg, ctps, pis, nis, certidão, telefone, estado_civil, sexo, pai/mãe/conjuge self-FK). RelationManagers em `PessoaResource`: **Rendas** (`pessoa_rendas` + `tipo_renda_id` + `compoe_renda_familiar`), **Deficiências** (`pessoa_deficiencias` + CID), **Ocorrências** (`ocorrencias_sociais`, polimórfica) — além de Endereços/Documentos já existentes.
- **Família (094/095):** `cadastros_sociais` ganhou `situacao_cadastro` (enum), `empreendimento_id`, terreno (`possui_terreno` + loteamento/quadra/lote + titularidade), `indice_vulnerabilidade`. `membro_familias.representante_familiar`. RelationManagers: **DefiniçãoSocial** (pivot `familia_informacoes` ↔ `InformacaoSocial`) e **Ocorrências** (polimórfica). Empreendimento tem `geo` POINT (moradia de benefício).
- **Cálculos (096/097):** `App\Services\Social\CadastroSocialCalculoService` via Observers (`CadastroSocialObserver`, `MembroFamiliaObserver`, `PessoaRendaObserver`, registrados com `#[ObservedBy]`). Renda familiar = soma das `pessoa_rendas` (RF + membros) com `compoe_renda_familiar=true`; per capita = total/membros. Índice de vulnerabilidade 0–7 (área de risco +2, renda per capita < ¼ SM +2, PCD +1, moradia precária +1, sem benefícios +1). Grava via `DB::table` (não re-dispara evento). Badge no `CadastroSocialResource`.
- **Painel Social (098):** [PainelSocialPage](app/Filament/Pages/PainelSocialPage.php) (`view_painel_social`) — gráfico pizza (Chart.js) da distribuição por `situacao_cadastro` + mapa Leaflet com as famílias (centróide de `unidade_imobiliaria` ou `empreendimento` via `ST_Centroid(COALESCE(u.geo, e.geo))`); **clicar numa fatia filtra os pontos no mapa** (interação client-side, cores por situação).
- **Relatórios (091):** `PessoaSocialExportService` e `CadastroSocialExportService` com Excel/PDF/CSV/XML (CSV via `SimpleExcelWriter::create(..., 'csv')`, XML via `SimpleXMLElement`) nos `ActionGroup` de `ListPessoas` e `ListCadastroSocials`.
- **Cadastros auxiliares (091):** TipoRenda, TipoEntidade, Entidade, ServiçoSocial, Programa, Evento, InformaçãoSocial, Empreendimento — CRUD modal, **permissão única `gerenciar_X`** por entidade (CAIXA 13b da `RoleResource`) + Policy por model. `view_painel_social` na CAIXA 13.

### Metadados geométricos cacheados (`area_geo` / `extensao_geo`)

Oito entidades têm coluna **read-only** populada via PostGIS, atualizada automaticamente após criação ou edição de geometria:

| Entidade | Coluna | Função PostGIS |
|---|---|---|
| `perimetros_urbanos` | `area_geo` | `ST_Area(geo::geography)` |
| `zonas` | `area_geo` | `ST_Area(geo::geography)` |
| `bairros` | `area_geo` | `ST_Area(geo::geography)` |
| `loteamentos` | `area_geo` | `ST_Area(geo::geography)` |
| `quadras` | `area_geo` | `ST_Area(geo::geography)` |
| `logradouros` | `extensao_geo` | `ST_Length(geo::geography)` |
| `secoes_logradouro` | `extensao_geo` | `ST_Length(geo::geography)` |
| `meio_fios` | `extensao_geo` | `ST_Length(geo::geography)` |

Mostradas read-only no Filament Resource (Form `disabled()->dehydrated(false)` + Table com `numeric(2, ',', '.')` e sufixo `m²`/`m`). Recalculadas nas traits `Has*Actions` após `criar*Action` e nos listeners `salvarNovaGeometria*` do `MapaFullscreen` — todos os UPDATEs estão dentro de try-catch para tolerar ambientes onde a coluna ainda não exista.

**Backfill / recálculo manual:**
```bash
# Recalcula apenas onde está NULL (idempotente, todos os tenants, todas as 8 entidades)
php artisan gis:recalcular-metadata

# Filtra por tenant ou entidade
php artisan gis:recalcular-metadata --tenant=tangara --entidade=bairros

# Sobrescreve todos os valores existentes (use após mudança de SRID, p. ex.)
php artisan gis:recalcular-metadata --force
```

A `Planta de Quadra` (`pdf.planta-quadra`) exibe a área da quadra (`$quadra->area_geo`) tanto no bloco de identificação quanto como card destacado ao lado das áreas dos lotes e das edificações.

---

### Páginas WEB — Administração (novas)

#### Auditoria (`app/Filament/Pages/AuditoriaPage.php`)

- Rota: `/app/{tenant}/auditoria` (aparece em Administração → Auditoria)
- Lista operações registradas pelo `spatie/laravel-activitylog` filtradas pelo tenant
- Colunas: Data/Hora · Usuário · Operação (badge criado/atualizado/excluído) · Entidade · ID
- Filtros: tipo de operação (select) e período (date range)
- Ação "Ver detalhes": modal com tabela Antes/Depois dos campos alterados
- Models com log ativo: `Lote`, `Edificacao`, `Quadra`, `Logradouro`, `Bairro`, `Pessoa`
- Alteração de **geometria** (croqui Antes/Depois) via trait `LogsGeometryChanges` em `Lote`, `Quadra`, `Bairro`

#### Monitoramento de Campo (`app/Filament/Pages/MonitoramentoCampoPage.php`)

- Rota: `/app/{tenant}/monitoramento-campo` (Administração → Monitoramento de Campo)
- Auto-refresh a cada 30s via `#[Polling('30s')]`
- Layout: painel esquerdo (lista de agentes ativos) + mapa Leaflet (direita)
- Cada card mostra: nome, email, imóveis coletados hoje, tempo do último ping GPS
- Clicar no card voa o mapa até o cadastrador (`flyToCadastrador(lat, lon)`)
- Mostra apenas cadastradores com GPS nos últimos 10 minutos

#### Produtividade (`app/Filament/Pages/ProdutividadePage.php`)

- Rota: `/app/{tenant}/produtividade` (Administração → Produtividade)
- Filtro de data (reativo via `wire:model.live`)
- 6 cards de resumo: Total · Coletados · Pendentes · Inconformidades · Não visitados · % Progresso
- Tabela por cadastrador: coletados no dia filtrado vs. coletados total
- Tabela por quadra (top 20): com barra de progresso inline

---

### Mapa Interativo — funções globais adicionadas (`mapa-engine.js`)

| Função | Uso |
|---|---|
| `window.irParaCoordenada(lat, lon, zoom?)` | Voa o mapa para as coordenadas (zoom padrão 18, duração 1,5s) |
| `window.toggleLayerLabels(layerName, enabled)` | Ativa/desativa rótulos de uma camada sem remover os polígonos |
| `window.getEnquadramentoAtual()` | Retorna `{ lat, lon, zoom }` do enquadramento atual do mapa |
| `window.previewAzimutes(lon, lat, pares)` | Prévia (laranja) do polígono por azimutes; retorna `{area, perimetro}` |
| `window.finalizarAzimutes(lon, lat, pares, entidadePlural)` | Constrói polígono fechado → mesa de desenho (`cad_draft`) em edição |
| `window.iniciarAzimutePickStart()` | Ativa modo "clicar no mapa" para capturar o ponto inicial dos azimutes |

**Criação por Azimutes (item 046)** — botão "Criar por Azimutes" no menu **Ferramentas**; painel Alpine com ponto inicial (digitado ou clicado), tabela azimute(°)/distância(m), seletor "Salvar como". Usa `turf.destination` para montar o anel fechado; o resultado vai para a Mesa de Desenho (`cadSource`), salvável pelo fluxo `salvarEdicaoGeometria` → `abrirModalCriacao` (via `featureCloneOriginalLayer` = plural da entidade).

**Criação por Coordenada XY de cada vértice (item 045)** — botão "Criar por Coordenada XY" no menu **Ferramentas** (ao lado dos Azimutes); painel Alpine com **um textarea** onde o usuário cola as coordenadas **uma por linha no formato `longitude latitude`** (separadas por espaço; vírgula/quebra de linha opcionais) — **mesmo formato do `parseRawCoordinates`** dos Resources (`preg_split('/[\n,]+/')`). O JS faz o parse idêntico (`split(/[\n,]+/)` → limpa cada token com `[^0-9.\-]`), preview de área/perímetro e seletor "Salvar como". Botão "Pegar do mapa" (modo `window.xyPickVertex`, dispatch `coordxy-vertice-add`) **append**a a coord clicada como uma nova linha no textarea. Engine: `window.previewCoordenadasXY(pontos)` + `window.finalizarCoordenadasXY(pontos, entidade)` reaproveitam o `azimutePreviewSource` e o mesmo fluxo Mesa de Desenho → `ativarModoEdicaoAvancado` → `salvarEdicaoGeometria` → `abrirModalCriacao` dos Azimutes. Datum WGS84 (Lon/Lat); polígono fechado automaticamente. Lado Resource (colar coordenadas / GeoJSON) já existia; este é o lado do mapa.

**Camadas WMS persistidas por categoria hierárquica (item 022)** — além do quick-add runtime "WMS Externo (OGC)" (não persiste), há um **gerenciador persistido**: entidades `CategoriaWms` (`categorias_wms`, `pai_id` self-FK → hierarquia) e `FonteWms` (`fontes_wms`: `url`, `camadas`/LAYERS, `formato`, `opacidade`, `ativo`, `categoria_wms_id`). CRUD em `CategoriaWmsResource`/`FonteWmsResource` (grupo Administração), gated pela permissão única **`gerenciar_wms`** (Policies + CAIXA 19 da `RoleResource` + whitelist `permissions_administracao` no `EditRole`). No mapa, o botão **"Camadas WMS"** no menu **Ferramentas** dispara `abrir-wms-sidebar` e abre uma **barra lateral esquerda** (`fixed inset-y-0 left-0`, mesmo estilo da ficha do lote — a gestão do WMS **não** fica mais na janela de camadas). A barra tem dois blocos: **(1) Fontes salvas** — árvore `$wmsCategorias` (montada por `MapaFullscreen::montarArvoreWms()`, pai→filho com `nivel`), cada fonte um toggle `.wms-fonte-toggle`, com link "Gerenciar" (`FonteWmsResource::getUrl`) para quem tem `gerenciar_wms`; **(2) Conectar WMS avulso** — o quick-add runtime (`#wms-url`/`#wms-layer`/`#btn-add-wms`/`#wms-layers-list`, **não persiste**). Engine: `window.criarCamadaWmsExterna({url,layer,opacity,formato,id})` (instancia `ol.source.TileWMS`) + handler delegado `.wms-fonte-toggle` (liga/desliga, registry `window._wmsFonteLayers`); o handler do `#btn-add-wms` continua o mesmo (só mudou de lugar no DOM). **Interoperabilidade (params do GetMap):** enviar `STYLES:''` (obrigatório em MapServer/terrestris e GeoServer estrito — senão HTTP 500 `missing parameters ['styles']`) e **NÃO** enviar `TILED:true` (é extensão do GeoServer/WMS-C; MapServer/terrestris recusa com `Request too large or invalid BBOX. (not a single tile)`). `VERSION:'1.1.1'` evita inversão de eixo em servidores gov BR. O exemplo do workshop OpenLayers usa `TILED:true` porque aponta para um GeoServer — para um consumidor **genérico** de WMS externo isso não é seguro.

**Nuvem de Pontos 3D / Potree (itens 244–250)** — botão "Visualizador 3D (LiDAR)" no menu **Ferramentas** abre `MapaFullscreen::abrirNuvemPontosAction()` (modal com iframe Potree). **Fallback inteligente:** se existir `public/nuvem-pontos/{tenant_slug}/index.html` carrega o dado real do município; senão exibe um ambiente Potree de demonstração. A **biblioteca do Potree** (release ~1.8) fica em `public/potree/` (`build/` + `libs/`, servida same-origin); o `index.html` de cada tenant carrega a octree via `Potree.loadPointCloud("./cloud.js", …)`.
- ⚠️ **Pegadinha crítica:** o caminho **DEVE** começar com `./` — o dispatcher do Potree testa `path.indexOf('cloud.js') > 0`, então um `"cloud.js"` nu (índice 0) é **ignorado silenciosamente** (callback nunca dispara, nenhum erro, nada carrega). Use `"./cloud.js"`.
- **Conversão** (offline): LAZ/LAS → octree via **PotreeConverter/potree.exe** (a prefeitura entrega convertido). Formato Potree 1.7 = `cloud.js` + `data/`; 2.0 = `metadata.json` + `octree.bin` + `hierarchy.bin` — o viewer lê ambos.
- **Ferramentas nativas do Potree:** medição (distância/área/**volume**), **corte/seção** (profile + clip volume), filtro de **classificação**, densidade/point-budget, tamanho de ponto, marcadores/anotações.
- **Dado real de referência:** João Pinheiro/MG (tenant `prefeitura-de-joao-pinheiro`), Potree 1.7, **~397M pontos**, **EPSG:31983** (SIRGAS 2000 / UTM 23S), atributos **RGB + classificação** — **sem `intensity`**, então o item 245 ("valor de intensidade") exige o atributo `intensity` na conversão do LAS.
- **Deploy:** `public/potree/` (~64MB) e `public/nuvem-pontos/*/data/` (GBs) são **gitignored** → subir direto no servidor (rsync/SFTP); a pasta em `public/nuvem-pontos/` **precisa ter o nome = slug do tenant**. Só `index.html` + `cloud.js` vão no git.

### Módulo XV — App de Chamados / Gestão do Aplicativo Móvel (itens 149–174)

**Sistema 3 dedicado** (grupo Filament "App de Chamados") — a **gestão WEB + backend** do app de chamados do cidadão. **NÃO** reutiliza `ProcessoDigital`/`BpmnFluxo` (Sistema 1) nem `SolicitacaoManutencao` (Sistema 2). O app nativo (Módulo XVI) é entregável separado; aqui é só o lado WEB/backend + a superfície de API.

- **Entidades:** `CategoriaChamado` (`categorias_chamado`, `pai_id` self-FK + `cor` + `icone` png/jpg + `privada` — 155–159, molde `CategoriaWms`), `FluxoChamado` (`fluxos_chamado`, `boletim` JSON = questionário de 4 tipos texto/checkbox/mapa/CPF-telefone — 149/154, molde `BpmnFluxo`), `FaseChamado` (`fases_chamado`: `cor`, `aviso_duracao`, `duracao_minutos`, `ordem`, `encerramento`, `usuarios_autorizados[]` — 150–153), `Chamado` (`chamados`, `geo` POINT + `respostas_boletim`/`fotos` JSON + `user_id` do cidadão — 160–174), `MensagemChamado` (`mensagens_chamado`, flag `publica` — 169–171), `HistoricoFaseChamado` (168/174).
- **Notificações (166/168/169):** `MensagemChamado::booted()` dispara `ExpoPushService` ao `users.expo_push_token` do cidadão quando `publica=true` (mesmo padrão do model `Mensagem`); as ações "Alterar Categoria/Fase" do `ChamadoResource` (`notificarCidadao($tipo)`) também notificam. Push é best-effort. **Chaves do `data` (alinhadas com o app):** `chamado_id` (snake, em todas — navegação) + `tipo` = `mensagem`|`fase`|`categoria`.
- **Gestão WEB:** `CategoriaChamadoResource` + `FluxoChamadoResource` (com `FasesRelationManager` + Builder do boletim) + `ChamadoResource` (filtros código/data/categoria — 160/161; View com boletim/fotos/mensagens/histórico — 164/172/173; ações inline alterar categoria/fase/mensagem/imprimir/ver-no-mapa). Permissões `gerenciar_categorias_chamado`/`gerenciar_fluxos_chamado`/`gerenciar_chamados` (Policies + CAIXA 19b da `RoleResource` + grupo `permissions_chamados` no Edit/CreateRole). **Cada Resource tem `$tenantRelationshipName` + `hasMany` no `Tenant`** (`categoriasChamado`/`fluxosChamado`/`chamados`).
- **Mapa (162/163):** camada `chamados` (POINT colorido pela `categoria.cor`) — `case 'chamados'` em `MapDataController` + `layerConfigs.chamados` no `mapa-engine.js` + toggle "Chamados (App)" no painel. "Ver no Mapa" (tabela→mapa) via `?layer=chamados&focus_lat/lon`; clique no ponto (mapa→tabela) dispara `abrirChamado` → `MapaFullscreen::abrirChamado()` redireciona ao View do chamado.
- **Impressão (174):** `ChamadoPdfService` + `pdf/chamado.blade.php` (mini-mapa via `StaticMapService`, mensagens públicas, respostas do boletim, histórico de fases).
- **API (superfície completa p/ o app do cidadão — Módulo XVI):** `ChamadoController` sob `auth:sanctum` — `GET /api/categorias-chamado` (cidadão sem papel não vê `privada` — 159/189), `GET /api/fluxos-chamado?categoria_id=` (fluxo + `boletim` p/ o app renderizar o questionário), `GET/POST /api/chamados` (o `store` aceita `fotos[]` base64 → `chamados_fotos/` no disco público, via `salvarImagemBase64`), `GET /api/chamados/{id}` (detalhe completo p/ a tela de Detalhe — lat/lon do `geo_json`, `respostas_boletim`, `fotos` como URLs absolutas, categoria/fase com `cor`; cidadão só vê o próprio, fiscal vê qualquer), `GET/POST /api/chamados/{id}/mensagens` (cidadão vê/manda só públicas).
- **Auth do cidadão no app (3 tipos de login exigidos pelo edital) — `CidadaoAuthController` (rotas PÚBLICAS):** `GET /api/prefeituras` (picker de cidade), `POST /api/cidadao/register` (e-mail/senha — espelha `RegisterCidadao`: cria `User tipo=cidadao` + `tenants()->attach` + `Pessoa` dedup por CPF), `POST /api/auth/google` (valida o `idToken` em `oauth2.googleapis.com/tokeninfo`), `POST /api/auth/facebook` (valida o `accessToken` no Graph; gera e-mail sintético se o FB não devolver). **Todos** (login/register/google/facebook) devolvem o **mesmo payload** `{token,user,tenant,layers}` via trait **`BuildsMobileAuthResponse`** (usado também pelo `AuthController::login`). `register`/`google`/`facebook` exigem `tenant_id` **ou** `prefeitura_slug`. Config opcional `services.google.client_id`/`services.facebook.*` (`.env`: `GOOGLE_CLIENT_ID`, `FACEBOOK_CLIENT_ID/SECRET`) reforça a checagem do `aud`. **Instruções completas do app em [appChamados.md](appChamados.md).**
- **Perfil no app (item 187) — `AuthController`:** `GET /api/me` devolve o perfil (agora inclui `telefone`/`cpf`/`data_nascimento` da Pessoa do tenant ativo — `data_nascimento` ← `pessoas.birth_date`, normalizado p/ `Y-m-d` via `fmtDate()` — além de `id/name/email`); `PUT /api/me` edita perfil — atualiza `User` (name/email/senha) **e** a `Pessoa` do tenant (telefone/cpf/birth_date) num só request, só nos campos enviados; troca de senha exige `current_password` correta. Usa `User::pessoaNoTenant()`.
- **Seed:** `ChamadoExemploSeeder` (`CHAMADO_SEED_TENANT=<slug> php artisan db:seed --class=ChamadoExemploSeeder`) — 4 categorias (pai/filho + 1 privada), 1 fluxo/3 fases/boletim, 4 chamados com geo + mensagens. Idempotente.

**Cores de manutenção de Poste/Árvore (itens 070/086)** — `MapDataController` expõe `status_manutencao` (`null` | `solicitacao` | `os_aberta`) nas features. No `mapa-engine.js`: sem chamado = cor da condição · solicitação aberta = magenta `#d946ef` · OS aberta = laranja `#f97316` (base + hover). No modal do mapa, OS aberta troca o botão para "Ver Ordem de Serviço".

**Toggle de rótulos** usa `window.loadedLayers[layerName]` (já exposto pelo engine) para cachear o style original e substituir por uma versão sem `setText`. Camadas suportadas: `lotes`, `quadras`.

**Salvar enquadramento** chama `@this.call('salvarEnquadramento', lat, lon, zoom)` no Livewire, que grava em `tenant.data['map_lat/lon/zoom']` — o mesmo campo lido pelo mobile no login. Visível apenas para roles Master/Manager.

**Buffer Circular no Filtro Avançado (TR Tangará Intranet #23)** — o `filtroAvancadoAction` (`MapaFullscreen.php`) no bloco "Desenho" expõe 3 formatos: `Polygon` (traço livre), `Box` (retângulo) e `BufferCircular`. No modo Buffer, o usuário informa um raio em metros (1–50.000) e, ao iniciar a consulta, o engine entra em modo "clique único" — o ponto clicado vira centro de um círculo gerado via `turf.buffer(turf.point([lon,lat]), raio, {units:'meters', steps:64})` (`steps:128` para raios >5km) e o GeoJSON resultante é enviado para `MapDataController::advancedSpatialQuery` exatamente como qualquer outro `drawn_geometry`. O toggle "TOTALMENTE dentro" continua válido (`ST_Within` vs. `ST_Intersects`).

### Numeração Predial no Mapa (PoC Nova Esperança do Sul — itens 099–109)

Fluxo em `MapaFullscreen.php` + `mapa-engine.js`, iniciado pelo botão "Numeração Predial" (menu Ferramentas):

1. **Seleção do logradouro** (item 099) → clique na linha do logradouro (`numeracao_step1`).
2. **Ponto de partida + trajeto** (item 104) → o usuário desenha uma polilinha; o **1º ponto é o marco zero** da numeração. `drawend` dispara `abrirModalNumeracao`.
3. **Modal de configuração** (`configurarNumeracaoAction`): lado par (direita/esquerda) + **números iniciais separados para PAR e ÍMPAR** (item 105).
4. **Cálculo** — SQL PostGIS: lotes a até 15 m do trajeto, distância via `ST_LineSubstring`/`ST_LineLocatePoint`, lado via produto vetorial. `numero_atual` vem de `unidade_imobiliarias.numero_imovel`. `recomputarNumeros()` aplica número inicial + distância + paridade.
5. **Prévia colorida** (item 100) — `mostrar-preview-numeracao`: **verde=par, azul=ímpar, cinza=excluído** (camada `previewNumSource`).
6. **Incluir/excluir parcela** (itens 101/102) — com a prévia ativa (`window.numeracaoPreviewAtivo`), clicar numa parcela dispara `toggleParcelaNumeracao`.
7. **Inverter** (item 103) — `inverterLadosNumeracao()` troca par↔ímpar de todas as parcelas e recalcula, sem redesenhar.
8. **Revisar** (item 107) — `revisarNumeracaoAction()`: modal com Repeater listando cada parcela (nº atual, lado, **faixa sugerida**, nº gerado editável). Override marca `manual=true` (preservado no recálculo).
9. **Salvar** (item 108) — `confirmarNumeracaoAction()`: para cada parcela, **preserva o número atual** movendo `lotes.numero_logradouro → lotes.numero_predial_antigo` e grava o **novo número** em `lotes.numero_logradouro` (`UPDATE ... SET numero_predial_antigo = numero_logradouro, numero_logradouro = <novo>`). Parcelas excluídas não recebem número.
10. **Divergências** (item 109) — `verDivergenciasNumeracao()`: pinta de vermelho (camada `divergNumSource`) as parcelas onde `numero_logradouro <> numero_predial_antigo`, rótulo `antigo → novo`, e enquadra o mapa nelas.
11. **Relatório PDF** (`pdf.relatorio-numeracao`) — inclui lado, nº atual, nº proposto e marca parcelas excluídas.

**Campos de endereço no `Lote` (herança do tributário):** `tipo_logradouro`, `logradouro`, `numero_logradouro`, `cep` são colunas em `lotes`, **herdadas do JSON `unidade_imobiliarias.dados_tributarios`** e propagadas automaticamente pelo hook `saved()` em [UnidadeImobiliaria.php](app/Models/UnidadeImobiliaria.php) (dispara quando `dados_tributarios` muda — cobre `tributario:importar`, simulação da API `IntegraPrefeituraService`, edição da unidade). Facilitam a busca no `LoteResource` (colunas `searchable`). O `numero_logradouro` é o **número predial atual** do lote; o gerador de numeração o sobrescreve guardando o valor anterior em `numero_predial_antigo`.

---

## PoC Antônio Carlos/MG — Checklist de Implementação

> Plano estratégico para atender os 30 requisitos da Prova de Conceito.
> Atualizar status ao concluir cada item.

### Frente A — API (desbloqueador do time mobile)

| # | Item | Arquivo(s) | Status |
|---|---|---|---|
| A1 | Migration: campos CTM na tabela `lotes` | `database/migrations/2026_05_25_191259_add_campo_coleta_to_lotes_table.php` | ✅ |
| A2 | LoteSyncController — pull com ficha completa + push com boletim | `app/Http/Controllers/Api/LoteSyncController.php` | ✅ |
| A2b | Command `tributario:importar` — importa JSON tributário por tenant | `app/Console/Commands/ImportarDadosTributarios.php` | ✅ |
| A3 | MobileMapDataController — layer lotes retorna `status_cadastro` | `app/Http/Controllers/Api/MobileMapDataController.php` | ✅ |
| A4 | Endpoint `GET /api/lotes/nearest` — imóvel mais próximo (PostGIS) | `app/Http/Controllers/Api/LoteNearestController.php` | ✅ |
| A5 | GPS tracking: migration `cadastrador_locations` + controller | `app/Http/Controllers/Api/CadastradorLocationController.php` | ✅ |
| A6 | Endpoint `GET /api/reports/productivity` — produtividade por quadra/cadastrador | `app/Http/Controllers/Api/ProductividadeController.php` | ✅ |

### Frente B — WEB Quick Wins

| # | Item | Arquivo(s) | Status |
|---|---|---|---|
| B1 | Activity log: `spatie/laravel-activitylog` + `AuditoriaPage` | `app/Filament/Pages/AuditoriaPage.php` | ✅ |
| B2 | Localização por coordenada (lat/lon input → fly-to no mapa) | `mapa-fullscreen.blade.php` + `mapa-engine.js` | ✅ |
| B3 | Toggle de rótulos por camada no painel de camadas (Quadras + Lotes) | `mapa-fullscreen.blade.php` + `mapa-engine.js` | ✅ |
| B4 | Botão "Salvar enquadramento padrão" — salva `map_lat/lon/zoom` no `tenant.data` via Livewire | `MapaFullscreen.php::salvarEnquadramento()` + blade | ✅ |
| B5 | `MonitoramentoCampoPage` — mapa Leaflet com cadastradores ativos (poll 30s) | `app/Filament/Pages/MonitoramentoCampoPage.php` | ✅ |
| B6 | Dashboard de produtividade WEB — cards resumo + tabelas por cadastrador/quadra | `app/Filament/Pages/ProdutividadePage.php` | ✅ |
| B7 | Mensagens (chat Supervisor ↔ Cadastrador) — `MensagensPage` poll 5s + endpoints API + push Expo | `app/Filament/Pages/MensagensPage.php` · `app/Http/Controllers/Api/MensagemController.php` · `app/Models/Mensagem.php` · `app/Services/Expo/ExpoPushService.php` | ✅ |
| B8 | Toggle "Status de Coleta" no mapa principal — recolore lotes por `status_cadastro` | `public/js/gis/mapa-engine.js::toggleLotesStatusColor` · `mapa-fullscreen.blade.php` | ✅ |
| B9 | Ficha lateral do lote no mapa — badge de status + ocupação/situação + rodapé "Coletado por" | `MapaFullscreen.php::carregarFicha` · `mapa-fullscreen.blade.php` | ✅ |
| B10 | Modal "Editar Dados" expandido — status, observação, inconformidade (`live()`), `dados_vistoria` | `HasLoteActions::editarDadosLoteAction` | ✅ |
| B11 | Modal "Fotos do Lote" — 3 colunas (frontal + 2 laterais) compartilhando storage com mobile | `HasLoteActions::gerenciarFotosLoteAction` | ✅ |

**Legenda:** ✅ Concluído · ⏳ Pendente · 🔄 Em andamento

---

### Conformidade dos 30 itens do TR de Antônio Carlos

Após confronto com `check_final.pdf`, status final:

| Item TR | Estado | Notas |
|---|---|---|
| #1 BDG/CTM-Geo da Prefeitura | 📋 Aguardando definição | Depende do formato que a Prefeitura entregar (ver seção abaixo) |
| #2 a #12, #14 | ✅ Atendido | Login, perfis, auditoria, admin, menus dinâmicos, histórico, camadas, navegação, toponímia, rótulos (com seletor de campo), tematização (toggle Status de Coleta), coordenadas (B2), mapa CTM |
| #13 Boletim PDF com fotos | ✅ Atendido | `BicPdfService::generatePdf` já entrega PDF por unidade imobiliária com foto frontal + print do mapa |
| #15 a #20, #22, #23, #24 | ✅ Atendido | API mobile (nearest, push/pull com boletim livre + 3 fotos, sync 1-clique, GPS tracking), Monitoramento, Produtividade com filtro setor, Status de Coleta no mapa, Basemaps (OSM/Esri/Azure/Ortofoto) |
| #18 Inconformidade geométrica | ✅ Atendido | Status `inconformidade` pinta o lote em vermelho via `toggleLotesStatusColor` + campo `inconformidade_descricao` para anotação textual. TR usa "marcações no mapa" como **"se possível"**, não mandatório |
| #21 Chat supervisor ↔ cadastrador | ✅ Atendido | `MensagensPage` Filament + 3 endpoints API com poll 30s |
| #25, #26, #27, #30 | ✅ Atendido (backend) | Backend expõe endpoints necessários; UI/captura são do app mobile |
| #28 Zoom original do projeto | ✅ Atendido | B4 — `salvarEnquadramento()` grava em `tenant.data.map_lat/lon/zoom` |
| #29 Compatibilidade web | ✅ Atendido | Filament 3 é compatível com Chrome/Edge/Firefox |

**Total Laravel/WEB: 29/30 atendidos.** Único item em aberto é o #1, sem bloqueio técnico — depende da Prefeitura.

---

## TR Tangará/SC — Sistema de Permissões e Itens de Cadastro por Perfil

> **Item TR Tangará Intranet #88 — "Configuração da utilização/visualização de Itens de Cadastro em determinado Perfil"**
>
> Este item tem duas leituras possíveis. Sob a **leitura ampla** (entidades cadastrais + camadas + funcionalidades), o sistema **já atende** através do stack de permissões existente, sem necessidade de novo código:
>
> 1. **Acesso por entidade (CRUD por perfil):** todo Resource Filament tem Policy auto-descoberta em `app/Policies/{Model}Policy.php` que controla `viewAny`, `view`, `create`, `update`, `delete` baseado nas permissões Spatie atribuídas ao papel. Exemplo: `LotePolicy::create()` checa `$user->can('create_lote')`. Master e Manager têm bypass total via `Gate::before` em [AppServiceProvider.php](app/Providers/AppServiceProvider.php).
>
> 2. **Acesso por camada do mapa:** permissões com prefixo `ver_camada_*` (ex: `ver_camada_lotes`, `ver_camada_perimetros`, `ver_camada_zonas`, `ver_camada_arvores`, etc.) controlam a visibilidade de cada camada GIS. Configuração centralizada na CAIXA 20 da [RoleResource.php](app/Filament/Resources/RoleResource.php), aplicada pelo mecanismo `data-permission-group="layer:X"` no painel de camadas do `mapa-fullscreen.blade.php`.
>
> 3. **Acesso por funcionalidade do mapa:** permissões com prefixo `toolbar_*` (`toolbar_criar_artefatos`, `toolbar_ferramentas`, `toolbar_filtros`) controlam botões da barra superior. Configuração via CAIXA 19 da `RoleResource`.
>
> 4. **Acesso por página administrativa:** permissões individuais (`view_auditoria`, `view_monitoramento_campo`, `view_produtividade`, `view_mensagens`) controlam acesso a Pages Filament via `canAccess()`.
>
> Ou seja: na leitura ampla, **#88 está atendido**. Sob a leitura estrita (controlar campos EAV criados dinamicamente pelo gestor — item #75), depende de refactor arquitetural fora do escopo PoC.

---

## PGV — Avaliação em Massa / Planta Genérica de Valores (Sprint C1, itens 225–243)

Motor de avaliação em massa que estende o simulador PGV simples (área × valor m²) com regressão linear, cálculo por face de quadra e simulação de IPTU. Módulo `pgv`, grupo Filament **"Gestão Tributária (PGV)"**.

> **Material de estudo:** [pgvConceito.md](pgvConceito.md) — documento conceitual do módulo (a ideia central, os 3 estágios do cálculo, glossário item-a-item do menu "Gestão Tributária (PGV)" com o papel de cada cadastro na conta, quadro-resumo, onde os resultados aparecem ligados ao lote e as lacunas conhecidas). Ler antes de mexer no motor PGV.

### Entidades (todas `BelongsToTenant` + `HasTenantSequentialId` + `SoftDeletes`, exceto `PgvConfiguracao`)

| Model | Tabela | Geo | Papel |
|---|---|---|---|
| `PgvAmostra` | `pgv_amostras` | POINT | Amostra de mercado (valor m² observado, idade, conservação, tipologia, áreas, `espuria` bool, `lote_id` opcional) |
| `PgvPolo` | `pgv_polos` | POINT | Pólo valorizante — referência de distância para a regressão |
| `PgvCub` | `pgv_cubs` | — | Tabela CUB por tipologia/estrutura/padrão (coeficiente + valor m²) |
| `PgvDepreciacao` | `pgv_depreciacoes` | — | Faixas de depreciação por estado de conservação × faixa de idade |
| `FaceQuadra` | `face_quadras` | MULTILINESTRING | Face de quadra (linear), desenhada no mapa; guarda saída do cálculo (`valor_m2_calculado`, `distancia_polo`, `pgv_polo_id`, `setor_fiscal_id`) |
| `PgvConfiguracao` | `pgv_configuracoes` | — | 1 registro/tenant — `fatores` JSON (homogeneização), `lote_paradigma_id`, `percentual_valor_venal`, `limite_aumento_iptu` |

### Services (`app/Services/Pgv/`)

- **`PgvRegressaoService`** — `calcular($tenantId)`: distância de cada amostra não-espúria ao pólo mais próximo (`ST_Distance(::geography)`), regressão por mínimos quadrados `valor_m2 = a + b·distancia`, retorna `pontos` + `equacao{a,b,r2,n}`. `toggleEspuria($tenantId,$amostraId)` marca espúria e recalcula (itens 230–232).
- **`PgvFaceCalculoService`** — `recalcularTodas($tenantId)`: para cada face, distância ao pólo mais próximo (KNN `<->`), setor via `ST_LineInterpolatePoint` no ponto médio, aplica `a + b·dist × fatorGlobal` → grava `valor_m2_calculado` (itens 233–234).
- **`PgvSimulacaoIptuService`** — `simular($tenantId, $params)`: valor venal por lote (valor da face mais próxima com fallback para parâmetro do setor + edificação), IPTU simulado = valorVenal × %venal × alíquota, com limitador vs. `dados_tributarios.valor_total_imposto` (IPTU atual). Retorna `linhas` + `totais` (itens 237–243).
- **`PgvFaceExportService`** — Excel/PDF/XML das faces (código, quadra, logradouro, zona, extensão, valor) — item 236.

### Integração no mapa (`MapaFullscreen`)

Trait **`HasPgvMotorActions`** (Livewire listeners `#[On]`): `rodarRegressaoPgv`, `toggleEspuriaPgv`, `calcularFacesPgv` (dispatch `pgv-mostrar-faces`), `criarAmostraPgvAction`/`criarPoloPgvAction` (clique único no mapa), `criarFacePgvAction` (desenho LineString), `simularIptuAction` (modal → service → dispatch `pgv-simulacao-resultado`).

No menu **Ferramentas** (fora da janela de camadas): **Motor da PGV** (painel flutuante Alpine com Chart.js scatter + linha de tendência + R², botões rodar/calcular faces/simular), **Nova Amostra**, **Novo Pólo**, **Nova Face**. `mapa-engine.js`: camada `pgvFacesLayer` colore faces por `valor_m2_calculado` (gradiente), `window.pgvIniciarClique(tipo)` e `window.pgvIniciarDesenhoFace()`.

### Permissões

`gerenciar_pgv_amostras`, `gerenciar_pgv_polos`, `gerenciar_pgv_cubs`, `gerenciar_pgv_depreciacoes`, `gerenciar_face_quadras` (permissão única "gerenciar" por entidade, Policy auto-descoberta). Configuráveis na CAIXA 18b "PGV — Avaliação em Massa" da `RoleResource`; grupo `permissions_pgv_avaliacao` em Create/EditRole. Master/Manager têm bypass via `Gate::before`.

### Dados de demonstração (PoC)

`PgvExemploSeeder` popula um cenário completo ancorado em geometria real do tenant (pólo no centro da malha de lotes, 10 amostras em lotes reais com valor/m² decaindo pela distância + 1 outlier no meio da faixa para demonstrar "remover espúria", tabela CUB, depreciação, 12 faces nas bordas de 3 quadras reais, config) e roda os motores para as faces já aparecerem coloridas. Idempotente (pula tenant que já tem amostras).

```bash
# Alvo por slug (recomendado):
PGV_SEED_TENANT=prefeitura-de-santa-cecilia php artisan db:seed --class=PgvExemploSeeder
# Sem slug: todos os tenants com módulo 'pgv' + lotes com geometria
php artisan db:seed --class=PgvExemploSeeder
```

---

## Item #1 (TR Antônio Carlos) — Integração BDG/CTM-Geo: AGUARDANDO DEFINIÇÃO

A PoC exige "acesso direto aos dados do BDG/CTM-Geo da Prefeitura via internet". O caminho concreto depende do que a Prefeitura disponibilizar. Três cenários cobertos pelo sistema atual:

1. **Arquivos (Shapefile / DWG / GeoPackage)** — Importação via PostGIS já existe no projeto (`ogr2ogr` + scripts de ingestão). Processo manual: upload + comando artisan. Esforço: imediato.
2. **WMS/WFS publicado pelo município** — Adicionar como camada base no `basemaps` do `public/js/gis/mapa-engine.js` (linha ~32), seguindo o padrão usado para Azure/Esri Sat. Não exige mudança no backend. Esforço: ~1h.
3. **Conexão direta ao banco da Prefeitura** — Configurar segunda conexão em `config/database.php` + job de sincronização periódico (`spatie/laravel-schedule-monitor` opcional). Esforço: 2-3 semanas.

**Ação:** o time comercial precisa confirmar com a Prefeitura o formato de entrega antes da implementação. **Não bloqueia a PoC** se a base de demonstração for entregue como arquivo.

---

## PoC Bom Princípio/RS — Conformidade e Backlog (Auditoria 2026-06-13)

> Auditoria contra os requisitos §§ 19.2.1.2, 19.2.1.3, 19.3.1, 19.4.1 e 19.5 do edital.
> Plano detalhado em `C:\Users\jesse\.claude\plans\preciso-que-fa-a-uma-fancy-naur.md`.

### Contexto importante

- **`IntegraPrefeituraService`** (`app/Services/ApiTools/IntegraPrefeituraService.php`) — integração com o sistema tributário genérico da prefeitura (já existente e operacional, independente do GOVBR).
- **GOVBR** — sistema de Arrecadação específico usado por Bom Princípio/RS. É um sistema externo distinto, sem integração atual no SIGWEB. Requer nova implementação + credenciais fornecidas pela prefeitura.

### Resultado Global

| Status | Qtd | % |
|---|---|---|
| ✅ Atendido | 24/37 | 65% |
| ⚠️ Parcial | 10/37 | 27% |
| ❌ Não atendido | 3/37 | 8% |

> Com parciais contando como ✅: **34/37 = 92%**. Único bloqueador técnico real: GOVBR (depende de credenciais + API da prefeitura).

### Itens ✅ já demonstráveis (não precisam de código)

- § 19.2.1.3a/b/c — CTM app (foto fachada, trena via `dados_vistoria`, `MonitoramentoCampoPage`, `LoteNearestController`)
- § 19.4.1c/d/e/f/h/i/j/k/l — Georreferenciamento, unificação/subdivisão, WMS externo, busca multicampo, perfis, impressão quadra/lote, cores de status, viabilidade
- § 19.5.1a/b/c/d/e/f/g/k/n — Todos os módulos principais (imobiliário, viabilidade, iluminação, arborização, patrimônio, social, numeração predial, processo digital, CTM)

### Backlog de Implementação (P1–P8)

| ID | Requisito | O que fazer | Arquivos chave | Esforço |
|---|---|---|---|---|
| **P1** | GOVBR (19.3.1) ⚠️ CRÍTICO | Criar nova integração com a API de Arrecadação do GOVBR (sistema distinto do `IntegraPrefeituraService`). **Alternativa PoC:** importar dump JSON/CSV exportado do GOVBR via `tributario:importar`, demonstrando que os dados tributários chegam ao SIGWEB. Requer credenciais + documentação da API GOVBR fornecidas pela prefeitura | Novo `GovbrService.php`, `.env` com `GOVBR_API_URL` + `GOVBR_API_TOKEN` | 8–16h (bloqueado por API GOVBR) |
| **P2** | Comparativo áreas (19.2.1.2a) | Campo `area_cadastrada` na tabela `lotes`; popular via `tributario:importar`; coluna `Δ área (%)` com badge vermelho no `LoteResource`; filtro "divergência > X%" | `LoteResource.php`, `Lote.php`, migration, `ImportarDadosTributarios.php` | 4–6h |
| **P3** | Geo em chamados mobile (19.5.1i/m) | Coluna `geo` POINT em `solicitacoes_manutencao`; receber lat/lon no push; camada `solicitacoes` no mapa | `SolicitacaoManutencaoSyncController.php`, `MapDataController.php`, migration | 4–6h |
| **P4** | Coloração REURB no mapa (19.5.1l) | Campo `etapa_reurb` no `Lote`; toggle "Colorir REURB" análogo a `toggleLotesStatusColor()`; payload na camada lotes | `mapa-engine.js`, `mapa-fullscreen.blade.php`, `MapDataController.php`, `LoteResource.php`, migration | 5–8h |
| **P5** | Falecido no Cemitério (19.5.1h) | Pivot `jazigo_falecidos` (`jazigo_id`, `pessoa_id FK pessoas`, `data_obito`, `data_sepultamento`, `numero_certidao_obito`); `FalecidosRelationManager` em `JazigoResource` | `JazigoResource.php`, novo `FalecidosRelationManager.php`, migration | 4–6h |
| **P6** | Aprovação de Projeto BPMN (19.5.1j) | Seeder com `BpmnFluxo` padrão "Aprovação de Projeto de Construção/Reforma" (protocolo → análise → vistoria → aprovação → alvará) | `BpmnFluxoAprovacaoProjetoSeeder.php`, `ProcessoDigitalResource.php` | 3–4h |
| **P7** | Base cartográfica (19.4.1a) | Criar tenant `bom-principio`, importar Shapefiles via `ogr2ogr`, executar `gis:recalcular-metadata --tenant=bom-principio` | Scripts SQL/bash de importação — não é código do sistema | 8–24h |
| **P8** | Capacitação (19.5.2e) | Manual de uso SIGWEB focado em CTM/imobiliário — entregável documental | N/A | 8–16h |

### Itens ⚠️ parciais relevantes para demonstração

- **§ 19.2.1.2a** — `area_geo` existe via PostGIS; falta comparativo automatizado com área tributária (→ P2)
- **§ 19.4.1g** — `ST_MakeValid()` automático; falta relatório de QA topológica (sobreposição/gaps)
- **§ 19.5.1h** — `JazigoResource` existe; falta `FalecidosRelationManager` (→ P5)
- **§ 19.5.1i/m** — sync de chamados existe; falta ponto GPS de abertura (→ P3)
- **§ 19.5.1j** — motor BPMN pronto; falta fluxo pré-configurado de aprovação de projeto (→ P6)
- **§ 19.5.1l** — BPMN suporta REURB; falta coloração de lotes por etapa no mapa (→ P4)

### Nota GOVBR — Estratégia para PoC

GOVBR é o sistema de Arrecadação específico de Bom Princípio/RS. Para a demonstração sem API disponível:
1. Solicitar dump JSON/CSV exportado do GOVBR pela própria prefeitura
2. Executar `php artisan tributario:importar --tenant=bom-principio --file=govbr-dump.json`
3. Mostrar que os dados tributários (proprietário, valor, inscrição) estão integrados ao SIGWEB
4. Argumentar contratualmente que a arquitetura está pronta para integração em tempo real assim que as credenciais e documentação da API GOVBR forem fornecidas
