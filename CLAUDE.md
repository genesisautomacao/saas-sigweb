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

| Endpoint | Descrição |
|---|---|
| `POST /api/login` | Autenticação — retorna token + tenant (map_lat/lon/zoom) + layers |
| `GET /api/sync/pull` | Pull de árvores (arborização) |
| `POST /api/sync/push` | Push de árvores coletadas |
| `GET /api/sync/manutencoes/pull` | Pull de solicitações de manutenção |
| `POST /api/sync/manutencoes/push` | Push de manutenções |
| `GET /api/map/data?layer=lotes&bbox=...` | GeoJSON por camada (lotes, quadras, logradouros…) |
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
- The `tenant.data` JSON column stores freeform config (logo, brand color `#rrggbb`, `map_lat`, `map_lon`, `map_zoom`).
- API controllers use `$request->user()->tenants()->first()->id` for tenant isolation (no global scope in API layer).
- `cadastrador_locations` table: upsert por `user_id` — um registro por usuário, atualizado a cada ping GPS.
- Activity log (`spatie/laravel-activitylog`) instalado. Models logados: `Lote`, `Edificacao`, `Quadra`, `Logradouro`, `Pessoa`. Para logar um novo model, adicionar o trait `LogsActivity` + definir `$recordEvents` e `logOnly([...])`.

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

---

### Páginas WEB — Administração (novas)

#### Auditoria (`app/Filament/Pages/AuditoriaPage.php`)

- Rota: `/app/{tenant}/auditoria` (aparece em Administração → Auditoria)
- Lista operações registradas pelo `spatie/laravel-activitylog` filtradas pelo tenant
- Colunas: Data/Hora · Usuário · Operação (badge criado/atualizado/excluído) · Entidade · ID
- Filtros: tipo de operação (select) e período (date range)
- Ação "Ver detalhes": modal com tabela Antes/Depois dos campos alterados
- Models com log ativo: `Lote`, `Edificacao`, `Quadra`, `Logradouro`, `Pessoa`

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

**Toggle de rótulos** usa `window.loadedLayers[layerName]` (já exposto pelo engine) para cachear o style original e substituir por uma versão sem `setText`. Camadas suportadas: `lotes`, `quadras`.

**Salvar enquadramento** chama `@this.call('salvarEnquadramento', lat, lon, zoom)` no Livewire, que grava em `tenant.data['map_lat/lon/zoom']` — o mesmo campo lido pelo mobile no login. Visível apenas para roles Master/Manager.

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

## Item #1 (TR Antônio Carlos) — Integração BDG/CTM-Geo: AGUARDANDO DEFINIÇÃO

A PoC exige "acesso direto aos dados do BDG/CTM-Geo da Prefeitura via internet". O caminho concreto depende do que a Prefeitura disponibilizar. Três cenários cobertos pelo sistema atual:

1. **Arquivos (Shapefile / DWG / GeoPackage)** — Importação via PostGIS já existe no projeto (`ogr2ogr` + scripts de ingestão). Processo manual: upload + comando artisan. Esforço: imediato.
2. **WMS/WFS publicado pelo município** — Adicionar como camada base no `basemaps` do `public/js/gis/mapa-engine.js` (linha ~32), seguindo o padrão usado para Azure/Esri Sat. Não exige mudança no backend. Esforço: ~1h.
3. **Conexão direta ao banco da Prefeitura** — Configurar segunda conexão em `config/database.php` + job de sincronização periódico (`spatie/laravel-schedule-monitor` opcional). Esforço: 2-3 semanas.

**Ação:** o time comercial precisa confirmar com a Prefeitura o formato de entrega antes da implementação. **Não bloqueia a PoC** se a base de demonstração for entregue como arquivo.
