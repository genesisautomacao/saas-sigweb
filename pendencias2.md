# Backlog de Pendências 2 — SIGWEB (Balanço Corrigido + Plano para 91%)

> **Criado em:** 2026-07-03
> **Origem:** Checagem avançada cruzando o **PDF da PoC de Nova Esperança do Sul/RS (Anexo III, 260 itens)** × [pendencias.md](pendencias.md) × código-fonte real.
> **Motivo de existir (separado do `pendencias.md`):** o arquivo original declara ~85% contabilizando parte do **App de Chamados do Cidadão** e sobreposições de infraestrutura BPMN/Manutenção como "cobertas". Sob a orientação de negócio confirmada — **o App de Chamados NÃO tem backend nem frontend** — esses itens (Módulos XV + XVI) voltam para ❌, e o número real cai. Este arquivo registra o **balanço honesto** e o **plano de ação para 91%**.

---

## 1. Resumo executivo (percentuais reais)

> **Atualizado em 2026-07-03 após o Módulo XV (App de Chamados — gestão WEB)** (ver §10). Marcos: checagem inicial ✅ 199 (76,5%) → Lote 1 ✅ 210 → **Módulo XV ✅ 236**.

| Situação | Itens | % |
|---|---|---|
| ✅ **Atendido** | **236** | **90,8%** |
| ⚠️ **Parcial** | **7** | **2,7%** |
| ❌ **Não atendido** | **17** | **6,5%** |

- **Meta >90% da PoC: ATINGIDA** (236/260 = 90,8%). Leitura generosa (parciais = atendidos): **243/260 = 93,5%**.
- **Maior lacuna restante:** o **app nativo** de chamados (Módulo XVI = 15 itens) — entregável contratual de 60 dias. O Módulo XV (gestão WEB + backend + API) está **concluído**.
- **Contexto importante:** a **API do App de Coletas dos Fiscais de Rua** (Recadastramento — Módulo XVII) já existia; o Módulo XV adicionou a **API do App de Chamados** (`/api/chamados`, `/api/categorias-chamado`, `/api/chamados/{id}/mensagens`).

---

## 2. Balanço por módulo (Anexo III)

| Módulo | Itens | ✅ | ⚠️ | ❌ |
|---|---|---|---|---|
| I — Características gerais (001–009) | 9 | 9 | — | — |
| II — Controle de acesso (010–014) | 5 | 5 | — | — |
| III — Imobiliário (015–030) | 16 | 16 | — | — |
| IV — Edição Cartográfica (031–046) | 16 | 16 | — | — |
| V — Consulta de Viabilidade (047–052) | 6 | 6 | — | — |
| VI — Estoque p/ Iluminação (053–059) | 7 | 7 | — | — |
| VII — Iluminação Pública (060–075) | 16 | 15 | 1 | — |
| VIII — Arborização (076–090) | 15 | 15 | — | — |
| IX — Cadastro Social (091–098) | 8 | 8 | — | — |
| X — Numeração Predial (099–109) | 11 | 11 | — | — |
| XI — Cemitérios (110–119) | 10 | 10 | — | — |
| XII — Processo Digital (120–126) | 7 | 6 | 1 | — |
| XIII — Aprovação de Projeto (127–137) | 11 | 11 | — | — |
| XIV — Habite-se online (138–148) | 11 | 11 | — | — |
| **XV — Gestão App Móvel / Chamados (149–174)** | **26** | **26** | — | — |
| **XVI — App móvel de Chamados (175–189)** | **15** | — | — | **15** |
| XVII — App Recadastramento Imobiliário (190–204) | 15 | 10 | 3 | 2 |
| XVIII — REURB Digital (205–222) | 18 | 17 | 1 | — |
| XIX — Progresso do trabalho (223–224) | 2 | 2 | — | — |
| XX — Planta Genérica de Valores (225–243) | 19 | 19 | — | — |
| XXI — Nuvem de Pontos 3D (244–250) | 7 | 6 | 1 | — |
| XXII — Cadastro Rural (251–260) | 10 | 10 | — | — |
| **TOTAL** | **260** | **236** | **7** | **17** |

---

## 3. ❌ Não atendido (17 itens)

### 3.1 App de Chamados — Módulo XVI (15 itens) — *o app nativo (60 dias)*
> O **Módulo XV (149–174, gestão WEB + backend + API)** foi **CONCLUÍDO** (✅, ver §10). Resta o **app nativo** (Módulo XVI), entregável contratual de 60 dias.

| Itens | O que é |
|---|---|
| **175–189** (XVI) | App mobile Android/iOS de abertura de chamados: login (inclusive social 178), criação de solicitação, marcador no mapa, fotos, geocoding reverso de endereço, perfil do cidadão, compartilhar app, categorias de fiscais. **Backend/API já pronto no Módulo XV** — falta a UI nativa. |

### 3.2 App de Recadastramento — offline (2 itens: 198, 204)
> Cache/sincronização offline: lógica **interna do app mobile**, fora do escopo do backend.

> **Nota:** a **Nuvem de Pontos 3D (244–250)** saiu de ❌ para ✅ no Lote 1 — o visualizador Potree já existe (`abrirNuvemPontosAction`) com drop-in `public/nuvem-pontos/{slug}/index.html`; ferramentas nativas do Potree, dado real depende da prefeitura. Ver §9.

---

## 4. ⚠️ Parcial (7 itens)

> 5 parciais fechados no Lote 1 (022, 045, 076, 251, e 075 que já existia) — ver §9. Restam:

| Item | Requisito | Estado atual | Falta |
|---|---|---|---|
| **060** | Iluminação: além de XLS/PDF/CSV/XML (✅), entidades "Itens de Produto p/ Poste" (c/ lote de estoque) e "Tipos de Defeito" | Relatórios nos 4 formatos ✅; poste guarda luminária em colunas soltas; `tipo_servico` é string livre | Entidade **`TipoDefeito`** (CRUD) + entidade **"Itens do Poste"** vinculada ao `LoteEstoque` *(fora do escopo por decisão)* |
| **245** | Nuvem 3D: coordenadas **+ intensidade** | Coords 3D ✅ (Potree); dado de João Pinheiro tem RGB+classificação, **sem `intensity`** | Pedir a conversão do LAS **com o atributo `intensity`** |
| **120** | Processo Digital via **editor BPMN** (desenhar/incorporar objetos) | Motor configurável por etapas + formulários funcional | **Canvas visual drag-drop** de desenho BPMN |
| **205** | REURB via **editor BPMN configurável** | Idem 120 (mesmo motor) | Idem — sem canvas visual |
| **193** | App recad.: listar lotes **por loteamento** | Pull completo existe | `GET /api/sync/lotes/pull?loteamento_id=X` |
| **196** | App recad.: **habilitar/desabilitar camadas** | Camadas retornadas no login | `GET /api/layers/config` com estilos |
| **199** | App recad.: gerar **ZIP de backup** | Push/pull de boletins existe | `POST /api/sync/lotes/export-zip` |

---

## 5. ✅ Atendido — módulos praticamente 100%

I (Gerais), II (Acesso), **III (Imobiliário)**, **IV (Edição Cartográfica)**, V (Viabilidade), VI (Estoque), VII (Iluminação, exc. 060), **VIII (Arborização)**, IX (Social), X (Numeração Predial), XI (Cemitérios), XIII (Aprovação de Projeto), XIV (Habite-se), XVII (Recadastramento — backend, exc. 193/196/199), XVIII (REURB, exc. 205), XIX (Progresso), **XX (PGV — 19/19)**, **XXI (Nuvem 3D — Potree, dado real pendente)**, **XXII (Rural)**.

---

## 6. 🎯 Plano de Ação para 91% (≥ 237/260)

**Meta:** sair de **199 (76,5%)** para **≥ 237 (91%)** → **+38 itens**.
**Estratégia:** só itens executáveis **neste repositório** (não depende de dados LAZ/LAS nem de app nativo publicado). O alvo da PoC WEB para o App de Chamados é **backend + gestão WEB no painel prefeitura** (o app mobile em si é entregável separado).

### Projeção acumulada

| Marco | +Itens | Total | % |
|---|---|---|---|
| Baseline (checagem original) | — | 199 | 76,5% |
| ✅ **Lote 1 executado (2026-07-03)** — 022, 045, 076, 251, 075, Nuvem 3D | +12 | **211** | **81,2%** |
| + Fase 2 (App Chamados WEB, XV) | +26 | 237 | 91,2% ✅ |
| + Fase 3 (endpoints cidadão, XVI backend) | +3 | **240** | **92,3%** |

> **A meta de 91% agora depende essencialmente da Fase 2 (App de Chamados).** O Lote 1 já colocou o sistema em 81,2%.
> Fora da meta (documentado): itens exclusivos do app nativo (175, 183, 188, 198, 204) e a camada de UI nativa do app de chamados. A Nuvem 3D (244–250) foi atendida via Potree (dado real depende da prefeitura).

---

### FASE 1 — Fechar os 11 parciais · ~40–55h · Semanas 1–2

| # | Item(s) | Tarefa | Arquivos-chave | Esforço |
|---|---|---|---|---|
| F1.1 | 060, 076, 251 | Adicionar `exportToXml()` (padrão `SimpleXMLElement`) + botão "Exportar XML" nos serviços de Poste, Árvore e Rurais (`RuralPontoInteresse`, `RuralEstrada`, `RuralHidrografia`, `RuralPonte`, `RuralLocalidade`, `RuralPropriedade`) | `app/Services/Exports/PosteExportService.php`, `ArvoreExportService.php`, `Rural*ExportService.php` | 6h |
| F1.2 | 060 (compl.) | Confirmar/criar entidades "Itens de Produto p/ Poste" (com lote de estoque) e "Tipos de Defeito" como referência | `PosteResource`, models de itens/defeito | 4h |
| F1.3 | 075 | Baixa de estoque automática na OS: migration `ordem_servico_id` em `estoque_movimentacoes` + config de `operacao_interna_saida_id`; ao abrir/finalizar OS, gerar `EstoqueMovimentacao` de saída e decrementar saldo | `OrdemServicoResource`, `EstoqueMovimentacao.php`, migration | 8h |
| F1.4 | 022 | WMS hierárquico: tabela `fontes_wms` (nome, url, layer, `categoria_id` self-FK, ordem) + `CategoriaWmsResource`/`FonteWmsResource`; painel de camadas agrupa por categoria | migration, novos Resources, `mapa-fullscreen.blade.php`, `mapa-engine.js` | 8h |
| F1.5 | 045 | Painel "Criar por Coordenada XY": tabela de vértices (Lon/Lat **ou** E/N UTM SIRGAS 2000) → polígono/linha na Mesa de Desenho (reusar plumbing dos Azimutes A14) | `mapa-fullscreen.blade.php`, `mapa-engine.js`, `MapaFullscreen.php` | 6h |
| F1.6 | 193, 196, 199 | Endpoints do app de recad.: `pull?loteamento_id=X`; `GET /api/layers/config` (layers + estilos); `POST /api/sync/lotes/export-zip` (ZipArchive com boletins/fotos) | `LoteSyncController.php`, `routes/api.php`, novo `LayerConfigController` | 7h |
| F1.7 | 120, 205 | Editor BPMN visual: integrar **bpmn-js** — render do fluxo (etapas/setores como nós/lanes) + edição básica mapeada para `BpmnFluxo`/`BpmnEtapa`/`Setor`. *(Item mais caro; se o cronograma apertar, trocar por +2 endpoints XVI da Fase 3, mantendo a meta.)* | `BpmnFluxoResource`, nova página/campo bpmn-js, assets | 16–20h |

**Saída da Fase 1:** 210/260 (80,8%).

---

### FASE 2 — App de Chamados do Cidadão: Backend + Gestão WEB (Módulo XV, 26 itens) · ~60–90h · Semanas 3–6

> **Pré-requisito:** 1 sessão de **design da entidade `Chamado`** (fluxo, fases, categorias, mensagens, boletim, geolocalização de abertura pelo cidadão). Não misturar com `SolicitacaoManutencao` (Sistema 2) nem com `ProcessoDigital` (Sistema 1).

| # | Item(s) | Tarefa | Entregável |
|---|---|---|---|
| F2.1 | 155–159 | **Categorias**: tabela `categorias_chamado` (`pai_id` self, `cor` hex, `icone` png/jpg, `privada` bool) + `CategoriaChamadoResource` (árvore pai/filho) | Migration, model, Resource, Policy, permissão |
| F2.2 | 149–153 | **Fluxo/Fases do chamado**: `fluxos_chamado` + `fases_chamado` (cor, `aviso_duracao`, `duracao_minutos`, `ordem`, `encerramento` bool) + reordenação | Migrations, models, Resources |
| F2.3 | 154, 172 | **Boletim/questionário** por fluxo + armazenar respostas + exibir na gestão | `boletins_chamado`, `respostas_chamado`, UI |
| F2.4 | (núcleo) | **Entidade `Chamado`**: `tenant_id`, `protocolo`, `categoria_chamado_id`, `fase_atual_id`, `pessoa_id`/`solicitante`, `geo` POINT, `descricao`, `observacoes` | Migration, model `Chamado`, `BelongsToTenant` |
| F2.5 | 160, 161, 164 | **Gestão WEB** `ChamadoResource`: filtros (código, data criação, atualização, observações, anotações), filtro por categoria, tela de detalhes | Resource + filtros + view |
| F2.6 | 162, 163 | **Tabela ↔ mapa**: selecionar chamado na lista → voar no mapa; selecionar no mapa → destacar na lista (camada `chamados` no `MapDataController`) | `MapDataController` case `chamados`, `mapa-engine.js`, blade |
| F2.7 | 165–168 | **Alterar categoria/fase** + Events `CategoriaChamadoAlterada`/`FaseChamadoAlterada` + notificação (Expo push reusando `ExpoPushService`) | Actions, Events, Listeners |
| F2.8 | 169–171 | **Mensagens públicas/privadas** por chamado (`mensagens_chamado` com flag `publica`); pública dispara push mesmo após finalizado | Migration, model, UI, API |
| F2.9 | 173 | **Fotos** do chamado (upload/galeria) | `fotos_chamado`, storage compartilhado |
| F2.10 | 174 | **Impressão PDF** do chamado: `ChamadoPdfService` com mini-mapa (`StaticMapService` do A4), dados, boletim, mensagens públicas, histórico de fases | Service + blade PDF |
| F2.11 | (API) | Endpoints REST para o futuro app: `GET /api/categorias-chamado` (respeita `privada`), `GET/POST /api/chamados`, `GET/POST /api/chamados/{id}/mensagens` | `routes/api.php`, controllers |

**Saída da Fase 2:** 236/260 (90,8%).

---

### FASE 3 — Endpoints do Cidadão (backend de itens do Módulo XVI) · ~20h · Semanas 6–7

> Itens do app de chamados com **entregável de backend** claro no repositório (a UI nativa é do time mobile).

| # | Item | Tarefa | Arquivos-chave | Esforço |
|---|---|---|---|---|
| F3.1 | 178 | **Login social**: `laravel/socialite` (Google/Gmail + Facebook), callbacks OAuth, retorno de token Sanctum | `routes/api.php`, `SocialAuthController`, `.env` | 8h |
| F3.2 | 184 | **Geocoding reverso**: `GET /api/geocoding/reverse?lat&lon` via Nominatim (OSM), cacheado 24h | novo `GeocodingController` | 6h |
| F3.3 | 187 | **Atualização de perfil do cidadão**: `PUT /api/perfil` (nome, data_nascimento, email, celular, senha) com validação | `PerfilController`, `routes/api.php` | 5h |

**Saída da Fase 3:** **239/260 (91,9%) ✅ meta superada.**

---

## 7. Itens explicitamente FORA da meta de 91%

| Item(s) | Motivo |
|---|---|
| 175 (app nativo Android/iOS) | Publicação do app é entregável separado deste repo |
| 183 (editar/recortar/rotacionar foto no app) | Feature exclusiva do app mobile |
| 188 (compartilhar o app) | Feature exclusiva do app mobile |
| 198, 204 (cache/modo offline) | Lógica interna do app mobile, não do backend |
| Camada de UI nativa do App de Chamados | O repo entrega backend + gestão WEB + API; a UI nativa é do time mobile |

---

## 8. Ordem de execução recomendada

1. **Fase 1** primeiro (ganho rápido, baixo risco, +4,3 pontos) — priorizar F1.1→F1.6; deixar F1.7 (bpmn-js) por último.
2. **Fase 2** (a maior alavanca, +10 pontos) — começar pela **sessão de design** do `Chamado`, depois F2.1→F2.11 em ordem.
3. **Fase 3** em paralelo/ao final da Fase 2 (fecha a meta com folga).

**Esforço total estimado:** ~120–165h (≈ 6–7 semanas de 1 dev). Ao final: **≥ 91% de conformidade** com a PoC de Nova Esperança do Sul.

---

## 9. Changelog — Lote 1 de Quick Wins (executado em 2026-07-03)

Saiu de **199 (76,5%)** para **211 (81,2%)** — +12 itens. Detalhe do que foi feito:

| Item(s) | Antes | Depois | O que foi feito |
|---|---|---|---|
| **060, 076, 251a** | ⚠️ | 076/251 ✅ · 060 ⚠️ | `exportToCsv()` + `exportToXml()` (helper `linha()` + `SimpleXMLElement`) em `Poste`, `Arvore`, `SolicitacaoManutencao`, `OrdemServico` e os 6 `Rural*ExportService`; botões "Exportar CSV/XML" nas 10 List pages. **060 segue ⚠️** (por decisão: sem entidades `TipoDefeito`/`Itens do Poste`). |
| **251b** | ⚠️ | ✅ | `codigo_incra`/`codigo_car`/`codigo_sigef` (já no model) expostos nas colunas do `RuralPropriedadeResource` + Excel/PDF/CSV/XML. |
| **045** | ⚠️ | ✅ | Painel **"Criar por Coordenada XY"** no menu Ferramentas (blade Alpine + engine `previewCoordenadasXY`/`finalizarCoordenadasXY`/`xyPickVertex`), espelhando os Azimutes → Mesa de Desenho. |
| **022** | ⚠️ | ✅ | **Gerenciador WMS persistido + categorias hierárquicas**: tabelas `categorias_wms` (`pai_id` self-FK) + `fontes_wms`; models/Policies (`gerenciar_wms`); `CategoriaWmsResource`/`FonteWmsResource`; acordeon **"Camadas WMS"** no painel de camadas (agrupado por categoria, toggle `.wms-fonte-toggle`); engine `criarCamadaWmsExterna()`. |
| **075** | ⚠️ | ✅ | **Já existia** — `OrdemServico::booted()` baixa `Estoque` + cria `EstoqueMovimentacao` saída ao concluir a OS. Apenas verificado/documentado. |
| **244–250** | ❌ (7) | ✅ (7) | **Já existia** — visualizador Potree (`abrirNuvemPontosAction`) com drop-in `public/nuvem-pontos/{slug}/index.html`. Ferramentas nativas do Potree; dado LAZ/LAS real depende da prefeitura. Reclassificado. |

**Arquivos principais:** 10 `*ExportService.php` + 10 List pages · `RuralPropriedadeResource.php` · `mapa-fullscreen.blade.php` + `mapa-engine.js` (045 e 022) · `MapaFullscreen.php` (`montarArvoreWms`, `$wmsCategorias`) · migration `2026_07_03_120000_create_wms_tables.php` · models `CategoriaWms`/`FonteWms` + Policies · `CategoriaWmsResource`/`FonteWmsResource` + pages · `PermissionsSeeder`/`RoleResource`/`EditRole` (`gerenciar_wms`) · `CLAUDE.md`.

**Validação:** `php -l` (todos) · `node --check mapa-engine.js` · `php artisan view:cache` (blade OK) · `migrate` + `db:seed PermissionsSeeder` + `permission:cache-reset` rodados · `./vendor/bin/pint` · demo WMS (Cartografia → Bases Externas → OSM/terrestris) criado no tenant `prefeitura-de-santa-cecilia`.

---

## 10. Changelog — Módulo XV: App de Chamados (Gestão WEB) · executado em 2026-07-03

Saiu de **210 (80,8%)** para **236 (90,8%)** — **+26 itens (149–174)**. Meta >90% da PoC **atingida**. É a gestão WEB + backend do app de chamados (o app nativo, Módulo XVI, fica para a janela contratual de 60 dias).

| Bloco | Itens | Entregue |
|---|---|---|
| Fluxos + Fases | 149–153 | `FluxoChamadoResource` + `FasesRelationManager` (cor, aviso de duração, duração em min, ordem, fase de encerramento, usuários autorizados) |
| Boletim / Questionário | 154 | `Builder` de 4 tipos (texto, checkbox, mapa, CPF/telefone) no fluxo |
| Categorias | 155–159 | `CategoriaChamadoResource` (pai/filho, cor, ícone png/jpg, **privada** só fiscais) |
| Gestão das solicitações | 160–168 | `ChamadoResource`: filtros (código/data/observações/anotações + categoria), View, **alterar categoria/fase → push ao cidadão**, histórico de fases |
| Mensagens | 169–171 | `MensagemChamado` pública (push via `ExpoPushService`) / privada (interna, oculta ao cidadão), inclusive após encerrado |
| Boletim + Fotos | 172, 173 | respostas do boletim + `FileUpload` de fotos na View |
| Mapa (tabela↔mapa) | 162, 163 | camada `chamados` (`MapDataController` + `mapa-engine.js` + toggle) · "Ver no Mapa" · clique no ponto → View do chamado |
| Impressão | 174 | `ChamadoPdfService` + `pdf/chamado.blade.php` (mini-mapa `StaticMapService` + mensagens + boletim + histórico) |

**Arquitetura:** sistema `Chamado` **dedicado** (não mistura com ProcessoDigital/Manutenção). 6 entidades (`CategoriaChamado`, `FluxoChamado`, `FaseChamado`, `Chamado`, `MensagemChamado`, `HistoricoFaseChamado`) + `hasMany` no `Tenant` + `$tenantRelationshipName` em cada Resource. Permissões `gerenciar_categorias_chamado`/`gerenciar_fluxos_chamado`/`gerenciar_chamados`. **API** (`ChamadoController` sob `auth:sanctum`): `/api/categorias-chamado` (respeita `privada`), `/api/chamados`, `/api/chamados/{id}/mensagens`. **Seed:** `ChamadoExemploSeeder`.

**Validação:** `php -l` (todos) · `node --check mapa-engine.js` · `php artisan view:cache` · `migrate` + `db:seed` (Permissions + ChamadoExemplo) · `route:list` (API + Resources) · endpoint `layer=chamados` → 4 features · render do PDF (881KB) · `./vendor/bin/pint` (pass). Teste funcional da cadeia categoria→fluxo→fase→chamado→mensagem OK.

**Restam para 100%:** Módulo XVI (app nativo, 60 dias) + os 7 parciais (060, 120, 205, 193, 196, 199, 245).
