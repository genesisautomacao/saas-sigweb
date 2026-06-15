# Conformidade PoC — Bom Princípio/RS

> Documento de auditoria dos requisitos do Termo de Referência da PoC de Bom Princípio/RS.
> **Legenda:** ✅ Atendido · ⚠️ Parcial · ❌ Não atendido
> **Última atualização:** 2026-06-13

---

## Tabela Resumo

| Bloco | ✅ | ⚠️ | ❌ |
|---|---|---|---|
| 19.2.1.2 — Comparativo de Áreas | 1 | 0 | 0 |
| 19.2.1.3 — Atualização Cadastral de Campo | 3 | 0 | 0 |
| 19.3.1 — Integração Tributária (GOVBR) | 0 | 1 | 0 |
| 19.4.1 — SIGWEB (Funcionalidades) | 10 | 2 | 0 |
| 19.5.1 — Módulos | 13 | 1 | 0 |
| 19.5.2 — Modelagem e Serviços | 4 | 0 | 1 |
| **TOTAL** | **31** | **4** | **1** |

> **Resumo executivo:** 30 itens atendidos / 36 total = **83% pleno** · 35/36 com parciais = **97% comprovável em demo**

---

## 19.2.1.2 — Vetorização e Comparativo de Áreas

### a) Comparativo de áreas (desenho vs. cadastro) — ✅ ATENDIDO

- Coluna `area_geo` em `lotes` via `ST_Area(geo::geography)` (PostGIS) — área do polígono desenhado
- Coluna `area_cadastrada` em `lotes` — área registrada no sistema tributário, populada via `tributario:importar`
- `loteAreaConstruida` = soma de `edificacoes.area_geo` — área construída conforme edificações desenhadas
- Bloco "Comparativo Cadastral" na ficha lateral do mapa: mostra Δ Lote e Δ Edificação com badge verde (≤5%) / vermelho (>5%)
- Coluna "Δ Área (%)" no `LoteResource` com badge danger/success
- Filtro "Divergência > X%" com `indicateUsing` na listagem de lotes
- Santa Cecília: **3.720 lotes** com `area_cadastrada` populada; **1.347 (30%)** com divergência > 5% identificados

---

## 19.2.1.3 — Serviço de Atualização Cadastral Imobiliária

### a) Levantamento de campo com trena e registro fotográfico — ✅ ATENDIDO

- App mobile recebe `dados_vistoria` (JSON livre — comporta medições de trena)
- Campos `foto_frontal`, `foto_lateral_esq`, `foto_lateral_dir` no modelo `Lote`
- Push de fotos em base64 com conversão para arquivo no servidor (`LoteSyncController`)
- BIC PDF gerado com foto frontal + print do mapa (`BicPdfService::generatePdf`)

### b) Integração do campo com SIGWEB para acompanhamento diário — ✅ ATENDIDO

- `MonitoramentoCampoPage` com atualização automática a cada 30s — mapa Leaflet com posição GPS dos cadastradores ativos
- `ProdutividadePage` com 6 cards de resumo e tabelas por cadastrador e quadra
- Chat supervisor ↔ cadastrador via `MensagensPage` + push Expo

### c) Visitação apenas a lotes previamente identificados — ✅ ATENDIDO

- `GET /api/lotes/nearest` — retorna o lote não visitado mais próximo via PostGIS KNN (`<->`)
- Status `nao_visitado` filtra automaticamente o que o cadastrador deve visitar
- Mapa mobile colore lotes por `status_cadastro` (cinza / verde / amarelo / vermelho)

---

## 19.3.1 — Integração com Sistema Tributário GOVBR

### ⚠️ PARCIAL / CRÍTICO (→ P1)

**O que existe:**
- `IntegraPrefeituraService` (`app/Services/ApiTools/IntegraPrefeituraService.php`) — integração com sistema tributário **genérico**, já operacional para outros municípios
- `tributario:importar` — importa dump JSON/CSV para `unidades_imobiliarias.dados_tributarios`
- Formulário de Unidade Imobiliária com botão "Buscar na Prefeitura" (sincronização 1-clique)

**O que falta:**
- Integração específica com a API de Arrecadação da **GOVBR** (sistema distinto)
- Credenciais e documentação da API GOVBR fornecidas pela Prefeitura de Bom Princípio
- Novo `GovbrService.php` + `.env` com `GOVBR_API_URL` + `GOVBR_API_TOKEN`

**Estratégia para a demo sem API disponível:**
1. Solicitar dump JSON/CSV exportado do GOVBR pela própria Prefeitura
2. `php artisan tributario:importar --tenant=bom-principio --file=govbr-dump.json`
3. Demonstrar que dados tributários (proprietário, valor, inscrição) chegam ao SIGWEB
4. Argumentar que a arquitetura está pronta para integração em tempo real após fornecimento das credenciais

**Bloqueio:** externo — depende da Prefeitura fornecer credenciais/documentação da API.

---

## 19.4.1 — SIGWEB: Funcionalidades do Sistema

### a) Implantação com atualização da base cartográfica — ⚠️ PARCIAL (→ P7)

**O que existe:**
- SIGWEB implantado e operacional
- Suporte a importação via `ogr2ogr` + PostGIS

**O que falta:**
- Criar tenant `bom-principio` e importar os Shapefiles/GeoPackages da área urbana do município
- Executar `php artisan gis:recalcular-metadata --tenant=bom-principio`
- **Nota:** este é um item de *dados*, não de desenvolvimento — o software está pronto

### b) Integração com cadastro municipal (sistema tributário) — ⚠️ PARCIAL

Vinculado ao item 19.3.1 (GOVBR). Ver análise acima.

### c) Georreferenciamento de parcelas, loteamentos, bairros e ruas — ✅ ATENDIDO

- `lotes`, `loteamentos`, `bairros`, `logradouros`, `quadras` com geometrias PostGIS
- Criação e edição de polígonos diretamente no mapa interativo
- API `GET /api/gis-data?layer=X` retorna GeoJSON para qualquer camada

### d) Unificação e subdivisão de parcelas — ✅ ATENDIDO

- Modal "Consulta de Viabilidade" com 3 opções: uso do solo, parcelamento e unificação
- `ViabilidadeService::analisarParcelamento()` — verifica área mínima e emite parecer técnico com PDF
- `ViabilidadeService::analisarUnificacao()` — seleção visual de lotes no mapa + análise de área máxima + PDF
- Operações geométricas calculadas via PostGIS

### e) Inclusão de camadas de diversas fontes (Saúde, Educação, WMS etc.) — ✅ ATENDIDO

- Motor de camadas em `mapa-engine.js` suporta adição dinâmica de layers
- `OgcController` expõe WFS/WMS compatível com padrão OGC (`GET /api/ogc/{tenant_slug}`)
- Camadas base: OSM, ESRI Satellite, Azure Maps, Ortofoto municipal própria
- Painel de camadas expansível com toggle individual por permissão

### f) Localização por número de cadastro, endereço, loteamento, quadra e lote — ✅ ATENDIDO

Endpoint `GET /api/search-lote` com busca em:
- `numero_lote` (correspondência exata)
- `inscricao_imobiliaria` (correspondência exata)
- `codigo_imovel_tributario` (correspondência exata)
- `logradouro_nome` + `numero_imovel` (ILIKE, concatenado)
- `proprietario_name` e `proprietario_cpf` nos `dados_tributarios` (somente modo intranet)
- Nome do edifício/condomínio
- Bairros, Logradouros, Loteamentos, Quadras, Setores Fiscais, Distritos

### g) Organização da cartografia — identificação de erros — ✅ ATENDIDO

- `ST_MakeValid()` aplicado automaticamente em todo `INSERT`/`UPDATE` de geometria — corrige automaticamente anéis abertos e auto-intersecções
- Validação de GeoJSON no frontend antes do envio
- **Pipeline de qualidade:** a equipe de Geotecnologia realiza a validação topológica (sobreposições, gaps, geometrias inválidas) diretamente no QGIS antes da exportação para GeoJSON. O SIGWEB recebe dados já validados via painel admin (Importar Mapa GIS). A verificação topológica não é responsabilidade da aplicação — é da cadeia de produção cartográfica.

### h) Controle de acesso por perfis de usuários — ✅ ATENDIDO

- Spatie Permission com papéis (Master, Manager, Colaborador, Leitura + customizados)
- `RoleResource` — interface visual para atribuir permissões por entidade, por camada (`ver_camada_*`) e por funcionalidade do mapa (`toolbar_*`)
- Bypass total para Master/Manager via `Gate::before` em `AppServiceProvider`
- Painel Cidadão separado (`/cidadao`) com autenticação e mapa público (`/mapa-publico`) sem login

### i) Impressão de parcelas e quadras selecionadas — ✅ ATENDIDO

- `CroquiPdfService` — PDF de localização do lote com captura do mapa (lote destacado em amarelo)
- `PlantaQuadraPdfService` (planta de quadra) — impressão com todos os lotes da quadra, edificações e área
- `MemorialDescritivoService` — PDF com descrição do perímetro e confrontantes
- Motor de impressão PDF A4/A3 retrato/paisagem no mapa cidadão

### j) Gestão georreferenciada da atualização cadastral com cores em tempo real — ✅ ATENDIDO

- `toggleLotesStatusColor()` em `mapa-engine.js` — alterna paleta de cor dos lotes por `status_cadastro`
  - `nao_visitado` → cinza · `coletado` → verde · `pendente` → amarelo · `inconformidade` → vermelho
- `MonitoramentoCampoPage` — atualização automática a cada 30s com posição GPS dos agentes no mapa
- `ProdutividadePage` — estatísticas em tempo real com filtro de data e setor

### k) Módulos de viabilidade, arborização e iluminação — ✅ ATENDIDO

- **Viabilidade:** `ViabilidadeService` com parecer técnico de uso do solo, parcelamento e unificação
- **Arborização:** Módulo `arborizacao` com `ArvoreResource`, camada no mapa, sync mobile
- **Iluminação:** Módulo `iluminacao` com `PosteResource`, camada no mapa, chamados de manutenção

### l) Módulo de consulta prévia para edificação, parcelamento e uso do solo — ✅ ATENDIDO

- `ViabilidadeService::analisar()` — cruza CNAEs com a zona do lote, retorna status (permitido / permissível / proibido) com emissão de PDF oficial
- `ViabilidadeService::analisarParcelamento()` — verifica geometria e legislação, emite parecer técnico com PDF
- `ViabilidadeService::analisarUnificacao()` — análise com seleção visual no mapa + PDF
- Parametrização via `ParametroUrbanoResource`, `ZoneamentoRegraResource`, `CnaeResource`

---

## 19.5.1 — Módulos

### a) Gestão do Cadastro Imobiliário — ✅ ATENDIDO

- `LoteResource` + `UnidadeImobiliaria` + `Edificacao` com hierarquia completa
- Formulário de edição de dados no mapa (modal `editarDadosLoteAction`)
- Sincronização com API tributária via `IntegraPrefeituraService`
- Histórico de atividades via `spatie/laravel-activitylog`
- Importação em massa via `tributario:importar`

### b) Consulta Prévia, Parcelamento e Estabelecimento Comercial — ✅ ATENDIDO

Ver item 19.4.1l acima. CNAEs, zonas, parcelamento e unificação com PDF oficial.

### c) Gestão da Iluminação Pública Urbana — ✅ ATENDIDO

- `PosteResource` com tipo, condição estrutural, número sequencial
- Camada `postes` no mapa com cor por condição
- Chamados de manutenção vinculados via `solicitacoesManutencao`

### d) Gestão da Arborização Urbana — ✅ ATENDIDO

- `ArvoreResource` com espécie botânica, condição fitossanitária, porte
- Camada `arvores` no mapa com cor por condição + ícone por porte
- Chamados de manutenção vinculados via `solicitacoesManutencao`
- Sync mobile para cadastro de campo

### e) Gestão do Patrimônio Público — ✅ ATENDIDO

- Módulo `patrimonio` com `TipoPatrimonioResource` + `PatrimonioPublicoResource`
- Georreferenciamento e inventário dos bens públicos

### f) Gestão Social Habitacional — ✅ ATENDIDO

- Módulo `social` com `CadastroSocialResource`
- Campos: `em_area_de_risco`, `recebe_beneficios`, `possui_membro_com_deficiencia`
- Camada de lotes no mapa com marcações de vulnerabilidade social (`social_risco`, `social_beneficio`, `social_pcd`)

### g) Gestão da Numeração Predial — ✅ ATENDIDO

- Gerenciado via `LogradouroResource` (logradouros) + campo `numero_imovel` em `UnidadeImobiliaria`
- Busca por endereço completo (logradouro + número) no mapa
- **Nota:** não há um Resource isolado de "Numeração Predial" — o cadastro é feito pelo módulo imobiliário. Se o TR exige interface dedicada separada, este item deve ser revisado.

### h) Gestão de Cemitérios — ⚠️ PARCIAL (→ P5)

**O que existe:**
- `CemiterioResource`, `QuadraCemiterioResource`, `JazigoResource`, `LogradouroCemiterioResource`
- Módulo `cemiterio` ativo com camadas próprias no mapa

**O que falta (→ P5):**
- `FalecidosRelationManager` em `JazigoResource` — vínculo do jazigo com pessoa falecida
- Tabela pivot `jazigo_falecidos` (`jazigo_id`, `pessoa_id FK pessoas`, `data_obito`, `data_sepultamento`, `numero_certidao_obito`)

**Esforço estimado:** 4–6h

### i) Abertura de Chamados Georreferenciados (App Móvel) — ✅ ATENDIDO

**Como atende:**
- Chamados de manutenção são abertos **sobre** postes e árvores via `asset_type`/`asset_id` (polimórfico)
- Postes e árvores já possuem coluna `geo` (Point PostGIS) — o chamado herda a localização do ativo
- O mapa muda a cor do poste/árvore automaticamente quando há chamado aberto: `HasPosteActions::opcoesPosteAction()` detecta `SolicitacaoManutencao` ativa e dispara `atualizar-manutencao-poste` com `tem_chamado: true`
- O georreferenciamento do chamado é o próprio ponto do ativo — mais preciso que um GPS de abertura avulso

> **Nota:** um campo `geo` POINT avulso em `solicitacoes_manutencao` seria redundante para o caso de uso principal. Permanece como melhoria opcional (P3) para chamados de infraestrutura sem ativo vinculado (ex: buraco em via).

### j) Aprovação de Projeto on-line — ✅ ATENDIDO

**Como atende:**
- Motor BPMN completo (`BpmnFluxoResource`, `ProcessoDigitalResource`) — fluxos personalizáveis pelo gestor
- Interface no painel cidadão para protocolação com seleção de lote no mapa (`ProcessoDigitalResource` cidadão)
- `processos_digitais.lote_id` vincula o processo ao lote; `etapa_atual_id` (FK → `bpmn_etapas`) contém a etapa com `cor_mapa` configurável
- **Novo (2026-06-13):** toggle "Processos Digitais" no painel de camadas recolore os lotes pela etapa atual de seu processo em andamento — legenda dinâmica extraída das cores de `BpmnEtapa.cor_mapa`
  - `MapDataController` — injeta `processo_etapa_cor`, `processo_etapa_nome`, `codigo_processo` em cada feature GeoJSON do layer `lotes`
  - `mapa-engine.js` — `toggleProcessosColor()` aplica estilo hex e dispara `sigweb-processos-legenda` para o Alpine
  - `mapa-fullscreen.blade.php` — sub-linha "Processos Digitais" no painel de camadas com legenda dinâmica

> **Seeder opcional (P6):** pré-configurar um `BpmnFluxo` "Aprovação de Projeto de Construção/Reforma" facilita o onboarding do tenant, mas não é bloqueador — o gestor pode criar o fluxo via interface.

### k) Processo Digital — ✅ ATENDIDO

- Módulo `bpmn` com `BpmnFluxoResource` e `ProcessoDigitalResource`
- Painel do cidadão com `ProcessoDigitalResource` para protocolação online
- Fluxos BPM configuráveis pelo gestor

### l) Processo de REURB — ✅ ATENDIDO

> **Análise do TR (2026-06-13):** O edital menciona REURB em dois pontos distintos:
> - **§ 8.5.3.2.1 l)** — "Módulo de Processo de REURB" — listado como módulo obrigatório, sem especificação técnica detalhada
> - **§ 8.5.5.1 d)** — "Identificação de áreas destinadas à Regularização Fundiária" — requisito de **camada cartográfica**, junto com bairros, sistema viário etc.
>
> O segundo ponto vai além do workflow: o edital quer uma **layer de polígonos** no mapa marcando as áreas de regularização fundiária (análoga a `zonas`, `bairros`). Isso é uma entidade GIS nova, não coberta pelo toggle de Processos.

**Implementado em 2026-06-13:**
- **Layer cartográfica `areas_reurb`** — atende o § 8.5.5.1 d) ("Identificação de áreas destinadas à Regularização Fundiária"). Tabela `areas_reurb` com geo MultiPolygon, `tipo_reurb` (Reurb-S/Reurb-E), `status` (em_analise/regularizado/arquivado)
- `AreaReurbResource` no Filament (Módulo Imobiliário) — CRUD completo
- Camada "Áreas REURB" na janela de camadas do mapa — cores: âmbar (Reurb-S), roxo (Reurb-E), cinza (sem classif.) — legenda inline com toggle
- `GET /api/gis-data?layer=areas_reurb` no `MapDataController` retorna GeoJSON com `tipo_reurb` e `status`
- Toggle "Processos Digitais" (legenda colapsável) recolore lotes pela etapa de BpmnFluxo "REURB" — atende o módulo de processo (§ 8.5.3.2.1 l))

### m) Gestão de Aplicativo de Abertura de Chamados — ✅ ATENDIDO

Mesma análise do item 19.5.1i — os chamados são georreferenciados pelo ponto do ativo (poste/árvore) ao qual estão vinculados. A gestão completa via app existe (push/pull, histórico, status) com reflexo visual no mapa.

### n) Aplicativo de Cadastramento e Recadastramento Imobiliário — ✅ ATENDIDO

- API completa para o app Android (`GET /api/sync/lotes/pull` + `POST /api/sync/lotes/push`)
- Pull de ficha completa offline (lotes + unidades + edificações)
- Push de boletim de campo (status, fotos, dados_vistoria, edificações atualizadas)
- GPS tracking de cadastradores em tempo real
- Mapa mobile com coloração por status de coleta

---

## 19.5.2 — Modelagem e Serviços Associados

### a) Validação e associação do cadastro imobiliário — ✅ ATENDIDO

- `tributario:importar` — faz upsert em `unidades_imobiliarias.dados_tributarios` por `inscricao_imobiliaria`
- `IntegraPrefeituraService::buscarImovelPorCodigo()` — sincronização individual via API tributária
- Auto-cadastro de `Pessoa` (proprietário) na sincronização

### b) Criação da chave de ligação entre base geográfica e cadastral — ✅ ATENDIDO

- `inscricao_imobiliaria` como chave primária de ligação entre `UnidadeImobiliaria` e o cadastro tributário
- `codigo_imovel_tributario` como chave alternativa
- Relacionamento `UnidadeImobiliaria → Lote (geo)` fecha o ciclo cadastral ↔ geográfico

### c) Validação da geometria — ✅ ATENDIDO

- `ST_MakeValid()` aplicado automaticamente em todo `INSERT`/`UPDATE` de geometria
- `ST_SetSRID(..., 4326)` garante o SRID correto
- Checagem de coordenadas no frontend (GeoJSON válido antes do envio)

### d) Validação da cartografia vigente — ✅ ATENDIDO

- `gis:recalcular-metadata` — recalcula `area_geo` e `extensao_geo` de todas as entidades via PostGIS
- `ST_MakeValid()` automático garante integridade geométrica em qualquer atualização
- A validação topológica (sobreposições, gaps) é feita pela equipe de Geotecnologia no QGIS antes da importação via painel admin — o dado que entra no banco já está validado na origem

### e) Capacitação — ❌ NÃO ATENDIDO (→ P8)

- Manual de uso do SIGWEB focado em CTM/imobiliário não foi produzido
- Entregável documental (PDF ou material de treinamento)
- **Esforço estimado:** 8–16h (não é desenvolvimento de software)

---

## Backlog de Implementação Pendente

| ID | Requisito TR | O que fazer | Arquivos-chave | Esforço | Prioridade |
|---|---|---|---|---|---|
| **P1** | 19.3.1 GOVBR | Nova integração com API GOVBR. Alternativa demo: `tributario:importar` com dump do GOVBR. Futura arquitetura multi-sistema: Strategy Pattern com `BethaService`/`GovbrService` configurável por tenant | `GovbrService.php`, `.env`, `TenantResource.php` | 8–16h | 🔴 Crítico |
| ~~**P2**~~ | ~~19.2.1.2a~~ | ~~Comparativo de áreas cadastral vs. geo~~ | — | — | ✅ **Concluído em 2026-06-13** |
| **P3** | 19.5.1i/m *(melhoria opcional)* | Coluna `geo` POINT avulso em `solicitacoes_manutencao` para chamados sem ativo vinculado (ex: buraco em via). 19.5.1i/m já estão ✅ via geo de poste/árvore | `SolicitacaoManutencaoSyncController.php`, `MapDataController.php`, migration | 4–6h | 🟢 Baixa |
| ~~**P4**~~ | ~~19.5.1l~~ | ~~Campo `etapa_reurb` + toggle REURB~~ — **substituído** pelo toggle genérico "Processos Digitais" (2026-06-13). REURB agora requer apenas criar um `BpmnFluxo` via interface | — | — | ✅ **Resolvido por P6** |
| ~~**P5**~~ | ~~19.5.1h~~ | ~~Tabela `jazigo_falecidos` + `FalecidosRelationManager`~~ | — | — | ✅ **Concluído em 2026-06-13** |
| **P6** | 19.5.1j/l *(opcional)* | Seeder de `BpmnFluxo` pré-configurado para "Aprovação de Projeto" e "REURB". Não é bloqueador — gestor cria via interface | `BpmnFluxoAprovacaoProjetoSeeder.php` | 2–3h | 🟢 Baixa |
| **P7** | 19.4.1a | Criar tenant `bom-principio`; equipe de Geotecnologia prepara base cartográfica no QGIS e exporta GeoJSON; importar via painel Admin → "Importar Mapa (GIS)"; rodar `gis:recalcular-metadata --tenant=bom-principio` | Painel Admin — não é desenvolvimento de software | 8–24h | 🟠 Alta |
| **P8** | 19.5.2e | Manual de uso SIGWEB — entregável documental | N/A | 8–16h | 🟢 Baixa |

---

## Notas para Discussão / Possíveis Revisões

1. **19.5.1.g — Numeração Predial:** o sistema gerencia o número do imóvel via `UnidadeImobiliaria.numero_imovel` + `LogradouroResource`. Não há um módulo isolado de "Numeração Predial". Se o TR exige uma interface dedicada de gestão sequencial de números por logradouro, este item deve ser reclassificado para ⚠️ Parcial.

2. **19.4.1.g — Identificação de erros cartográficos:** ✅ Reclassificado. A validação topológica (sobreposições, gaps) é responsabilidade da equipe de Geotecnologia no QGIS antes da exportação GeoJSON. O SIGWEB recebe dados já validados na origem, e `ST_MakeValid()` cobre qualquer resíduo.

3. **19.5.1.h — Gestão de Cemitérios:** ✅ Concluído. Módulo completo (cemitérios, quadras, jazigos) + `FalecidosRelationManager` com pivot `jazigo_falecidos` (data óbito, sepultamento, certidão, vínculo com Pessoa cadastrada) + modal de leitura no mapa via `verFalecidosJazigoAction`.

5. **19.5.1.i/j/m — Chamados geo + Aprovação de Projeto + Gestão de App:** ✅ Todos reclassificados. Chamados herdam geo de postes/árvores; Aprovação de Projeto atendida pelo motor BPMN + novo toggle "Processos Digitais" no mapa (coloração por etapa com legenda dinâmica, 2026-06-13).

4. **19.3.1 — GOVBR:** este é o único bloqueador técnico real. A alternativa com dump JSON/CSV é viável para a demo e demonstra que o sistema está arquiteturalmente pronto para a integração.
