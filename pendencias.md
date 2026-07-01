# Backlog de Pendências — SIGWEB
> Fonte de verdade para todas as implementações futuras.
> Ao concluir um item: marcar `[x]`, adicionar `**Concluído em:** YYYY-MM-DD` e atualizar os contadores abaixo.

**Contexto:** Checklist gerado a partir da análise de conformidade da PoC de Nova Esperança do Sul/RS (260 itens).
**Meta:** ≥ 90% dos itens cobertos (≥ 234/260). Meta atingida ao final do Sprint C1.

---

## Contadores de Conformidade PoC Nova Esperança do Sul

**Progresso real (atualizado 2026-07-01): ~191/260 ≈ 73%**
- ✅ **Sprint A:** A1–A11 concluídos (18 itens PoC) — pendentes: A12, A13, A14
- 🔄 **Sprint B:** B5 e B6 concluídos (18 itens PoC) — demais pendentes

| Marco | Itens cobertos | % acumulado | Status |
|-------|---------------|-------------|--------|
| Baseline | ~155/260 | ~60% | — |
| + Sprint A (A1–A11) | ~173/260 | ~67% | ✅ parcial (falta A12–A14) |
| + Sprint B (B5, B6) | **~191/260** | **~73%** | 🔄 em andamento |
| Meta Sprint B completo | ~212/260 | ~82% | ⏳ |
| Meta Sprint C1 (PGV) | ~234/260 | **~90%** | ⏳ |
| Projeção final (C2–C5) | ~251/260 | ~97% | ⏳ |

> **Itens concluídos por sprint:**
> Sprint A ✅ = 015, 017, 027, 030, 032, 034, 037, 038, 047, 067, 072, 074, 083, 088, 090, 119, 162, 163.
> Sprint B ✅ = 053, 054, 055, 056, 057, 058, 059 (B5) · 099–109 (B6).

---

## Sprint A — Quick Wins (Semanas 1–2)
> Itens próximos de pronto. Impacto rápido na conformidade.

---

#### ~~A1 — Exportação XML para entidades Imobiliárias~~
**Item PoC:** 015
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

Adicionado export XML (`exportToXml`) + botão "Exportar XML" no menu de exportação das seguintes entidades:
- `LoteExportService` — XML aninhado: cada `<lote>` contém `<unidades_imobiliarias>` e `<edificacoes>` (via `lote_id`)
- `QuadraExportService`
- `LogradouroExportService`
- `LoteamentoExportService`
- `BairroExportService`

Como melhoria adicional (solicitada junto), os exports **PDF** e **Excel** existentes de `LoteExportService` também passaram a contemplar Unidades Imobiliárias e Edificações de cada lote:
- PDF: nova view `resources/views/pdf/lote-detalhado-report.blade.php` — bloco por lote com sub-tabelas de Unidades Imobiliárias e Edificações.
- Excel: arquivo `.xlsx` passou a ter 3 abas — "Lotes", "Unidades Imobiliárias" e "Edificações" (via `SimpleExcelWriter::addNewSheetAndMakeItCurrent`).

---

#### ~~A2 — Tabela de Testadas do Lote~~
**Item PoC:** 017
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

- Migration: `lote_testadas` (`id`, `tenant_id`, `lote_id`, `logradouro_id` nullable FK, `secao_logradouro_id` nullable FK, `tipo` enum [principal|secundaria], `comprimento` decimal 10,2, `geo` MULTILINESTRING 4326, softDeletes)
- Model `LoteTestada` com `BelongsToTenant`, geo getter/setter (ST_Multi/ST_AsGeoJSON), relações `lote()`, `logradouro()`, `secaoLogradouro()`
- `TestadasRelationManager` em `LoteResource` com: tabela (tipo badge colorido, logradouro, seção, comprimento), form com select de logradouro + select reativo de seção filtrada pelo logradouro; hooks de criação/edição/exclusão sincronizam `lotes.main_facade_length` quando tipo=principal
- `testadas()` `hasMany` adicionado ao model `Lote`
- **Integração mapa:** trait `HasTestadaActions` em `MapaFullscreen` — `toggleTestadasLote()`, `criarTestadaAction()`, `opcoesTestadaAction()`; camada OL temporária `testadasAtivasLayer` (principal=verde #16a34a, secundária=cinza); botões "Ver Testadas" e "+" na ficha lateral do lote; desenho de linha ativa `enableDrawing('testada')`; ao criar testada principal sincroniza `main_facade_length` no lote

---

#### ~~A3 — Botão "Ver no Mapa" em Solicitações e Ordens de Serviço~~
**Itens PoC:** 067, 072, 083, 088, 162, 163
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

- Botão "Ver no Mapa" (ícone `map-pin`, cor verde) adicionado no `ActionGroup` de cada linha em `SolicitacaoManutencaoResource` e `OrdemServicoResource`
- Visível apenas quando `$record->asset !== null`; detecta automaticamente se é Poste (`postes`) ou Árvore (`arvores`) via `class_basename($record->asset_type)`
- Abre em nova aba: `/app/{tenant}/mapa-interativo?layer=postes&id={asset_id}` (ou `arvores`)
- `mapa-engine.js` — startup handler: ao carregar a página com `?layer=X&id=Y`, ativa o checkbox da camada, faz polling até a feature aparecer no `loadedLayers`, voa até as coordenadas (zoom 19, 1s) e despacha `abrirOpcoesPoste`/`abrirOpcoesArvore` para abrir o modal de opções automaticamente

---

#### ~~A4 — Mini-mapa na Impressão da OS~~
**Itens PoC:** 074, 090
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

- `StaticMapService` (`app/Services/Gis/StaticMapService.php`) — costura grade 3×3 de tiles OSM via PHP GD, recorta para 400×300, adiciona marcador vermelho no centro, retorna base64 PNG. Tolerante a falha: retorna null se GD não disponível ou se os tiles não carregarem.
- Action `imprimir` em `OrdemServicoResource` — extrai lat/lon do asset via `ST_X`/`ST_Y` (PostGIS), chama `StaticMapService::generate($lat, $lon, 17)` e passa `$mapImageBase64` para a view.
- Template `pdf/ordem-servico-pdf-template.blade.php` — nova seção "Localização do Artefato" com imagem 400×300 e legenda OSM; omitida se `$mapImageBase64` for null (asset sem geo).
- Funciona para Iluminação (poste, item 074) e Arborização (árvore, item 090).

---

#### ~~A5 — Documentos vinculados ao Patrimônio Público~~
**Item PoC:** 030
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

- `documentos()` morphMany adicionado ao model `PatrimonioPublico`
- `PatrimonioPublicoResource\RelationManagers\DocumentosRelationManager` criado (cópia do padrão de PessoaResource: upload, download, openable)
- `getRelations()` adicionado em `PatrimonioPublicoResource` — aba "Anexos e Documentos" aparece automaticamente na página de edição

---

#### ~~A6 — Documentos vinculados ao Falecido (Jazigo)~~
**Item PoC:** 119
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

- `documentos()` morphMany adicionado ao model `JazigoFalecido`
- Seção colapsável "Documentos Anexados" adicionada ao form do `FalecidosRelationManager` via `Repeater` com `->relationship('documentos')` — upload com openable/downloadable, sem item mínimo (`defaultItems(0)`)

---

#### ~~A7 — Notificação de Irregularidade de Edificação com PDF imprimível~~
**Item PoC:** 027
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

- `NotificacaoIrregularidadeService` (`app/Services/Irregularidade/`) — busca proprietário via `unidadesImobiliarias->proprietario` ou `dados_tributarios['proprietario_name']`, gera PDF via DomPDF
- View `resources/views/pdf/notificacao-irregularidade.blade.php` — documento A4 com cabeçalho municipal, dados do imóvel, proprietário, texto formal, `inconformidade_descricao`, prazo de 30 dias e área de assinatura
- Ação "Notificação" (vermelho) adicionada em `LoteResource` tabela — visível apenas quando `inconformidade_descricao` não vazio
- `notificacaoIrregularidadeAction()` + `imprimirNotificacaoIrregularidade()` adicionados ao trait `HasLoteActions` — visíveis apenas quando `loteStatusCadastro === 'inconformidade'`

---

#### ~~A8 — Reimprimir Consultas de Viabilidade~~
**Item PoC:** 047
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

- `reimprimirPdf(ViabilidadeEmissao $emissao)` adicionado ao `ViabilidadePdfService` — usa `dados_snapshot` + protocolo/hash originais sem criar nova emissão; seleciona view correta via `match($tipo)`
- `ViabilidadeEmissaoResource` criado (`pgv` module, grupo "Consultas de Viabilidade") — lista emissões com colunas: protocolo copiável, tipo (badge), resultado (badge), lote, emissor, data
- Filtros: tipo e período; ações por linha: "Reimprimir PDF" + "Ver URL de Validação" (nova aba)
- `ListViabilidadeEmissoes` page sem botão "Criar" (emissões só surgem via mapa)

---

#### ~~A9 — Ferramenta Espelhar (Mirror) no CAD Avançado~~
**Item PoC:** 032
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

- Botão "Espelhar" (ícone SVG de setas opostas + linha central) adicionado na barra CAD avançada (`mapa-fullscreen.blade.php`, item 7)
- Fluxo 3 passos em `mapa-engine.js`:
  - **Passo 1** (`cad_espelhar_step1`): clique na geometria → destaca na mesa de rascunho (cadSource)
  - **Passo 2** (`cad_espelhar_step2`): clique no 1º ponto do eixo de reflexão → armazena em `window.cadEspelharAxis1`
  - **Passo 3** (`cad_espelhar_step3`): clique no 2º ponto → aplica reflexão matemática (`_reflectPt` por projeção escalar), reverte orientação dos anéis (CCW/CW), deposita resultado no `cadSource`, abre modo de edição avançado via `ativarModoEdicaoAvancado`
- Algoritmo: reflexão por projeção do ponto na reta axial — funciona para Point, LineString, Polygon, MultiPolygon
- Integrado ao `setFerramentaCAD('espelhar')` no mesmo padrão dos demais CAD tools

---

#### ~~A10 — Buffer com opção Quadrada (sem arredondamento)~~
**Item PoC:** 034
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

- Toggle `◯/□` adicionado ao campinho do Buffer na barra CAD (`mapa-fullscreen.blade.php`): botão com `id="cad-buffer-tipo"` e `data-tipo="circ"|"quad"`, clique alterna o ícone e o atributo
- Em `mapa-engine.js`, o interceptador `cad_buffer` lê `document.getElementById('cad-buffer-tipo')?.dataset?.tipo` e usa `steps: 4` (Quadrado) ou `steps: 64` (Circular) na chamada `turf.buffer()`

---

#### ~~A11 — Camada de Seções de Logradouro~~
**Itens PoC:** 037, 038
**Status:** ✅ Concluído
**Concluído em:** 2026-06-30

- Migration `2026_06_30_172253_create_secoes_logradouro_table.php`: tabela `secoes_logradouro` com `id`, `tenant_id`, `sequential_id`, `code`, `name`, `tipo_pavimentacao`, `extensao_geo`, `logradouro_id` FK (cascadeOnDelete), `geo` MULTILINESTRING PostGIS + GIST index + partial unique index
- Model `App\Models\SecaoLogradouro` com traits `BelongsToTenant`, `HasTenantSequentialId`, `SoftDeletes`; acessores `geo_json`/`setGeoAttribute` (ST_Multi); relação `logradouro()` belongsTo
- `SecaoLogradouroResource` + Pages (`ListSecoesLogradouro`, `CreateSecaoLogradouro`, `EditSecaoLogradouro`) com CRUD completo, `ver_no_mapa` action, `parseRawCoordinates` para importar GeoJSON/lista de coordenadas, botão "Nova Seção" com modal radio mapa/geojson
- Trait `HasSecaoLogradouroActions` com `criarSecaoLogradouroAction()` (KNN auto-detecção do logradouro mais próximo + cálculo extensão) e `opcoesSecaoLogradouroAction()` (edit modal com footer actions "Geometria" e "Excluir")
- `MapaFullscreen.php`: import + `use HasSecaoLogradouroActions`; branch `elseif ($entityType === 'secao_logradouro')` em `interceptarDesenho`; listeners `#[On('abrirOpcoesSecaoLogradouro')]` e `#[On('salvarNovaGeometriaSecaoLogradouro')]`
- `MapDataController`: case `secoes_logradouro` + `'secoes_logradouro'` na whitelist `allowedTables`; property `tipo_pavimentacao` adicionada ao `$buildFeatureCollection` closure
- `mapa-engine.js`: 9 pontos de integração — `layerConfigs.secoes_logradouro` (roxo, z=52, minZoom=15, label placement=line), hover style âmbar-roxo com tooltip, `hoverableLayers`, `clickPriority` + switch case `abrirOpcoesSecaoLogradouro`, `geometryType LineString` includes, event listeners `adicionar/atualizar-label/remover-secao_logradouro-mapa`, `configsEdicao`, `mapSingular`, `salvarNovaGeometriaSecaoLogradouro` dispatch, `drawableEntities`
- `mapa-fullscreen.blade.php`: checkbox `data-layer="secoes_logradouro"` no painel de camadas + botão `enableDrawing('secao_logradouro')` no menu de desenho
- `SecoesRelationManager` em `LogradouroResource` (aba "Seções de Logradouro" na edição do logradouro); relação `secoes()` adicionada ao model `Logradouro`
- `RecalcularGeoMetadata.php`: `secoes_logradouro` e `meio_fios` adicionados ao mapa `ENTIDADES` (extensao_geo via ST_Length)
- Permissão `ver_camada_secoes_logradouro` adicionada ao `PermissionsSeeder`, `RoleResource` (CAIXA 20) e `EditRole` (array_intersect)

---

#### A12 — Toggle "Ativar Fluxo" no Editor BPMN
**Item PoC:** 123
**Status:** ⏳ Pendente

- Adicionar toggle `ativo` inline na listagem do `BpmnFluxoResource` (ToggleColumn ou ação rápida)
- Lógica: fluxo inativo não aparece na seleção ao criar novo `ProcessoDigital`
- Se já existe o campo `ativo` no model, apenas expor na UI

---

#### A13 — Cores Customizadas de Poste/Árvore por Status de OS
**Itens PoC:** 070, 086
**Status:** ⏳ Pendente

- Verificar no `MapDataController` (ou endpoint de GeoJSON de postes/árvores) se já retorna `status_os` ou `tem_os_aberta` nas properties
- Se não, incluir via LEFT JOIN com `ordens_servico` (status aberta/em andamento)
- No `mapa-engine.js`, aplicar paleta de cores por status:
  - Sem OS: cor padrão
  - Com solicitação aberta: amarelo/laranja
  - Com OS em andamento: vermelho/vermelho vivo
  - OS concluída: verde

---

#### A14 — Criação de Geometria por Azimutes
**Item PoC:** 046
**Status:** ⏳ Pendente

- Adicionar modo "Criar por Azimutes" na barra CAD do `mapa-engine.js`
- Interface no painel lateral:
  1. Ponto inicial: coordenadas (input) ou clique no mapa
  2. Tabela de pares: Azimute (graus) + Distância (metros) — adicionar/remover linhas
- Construir geometria iterativamente com Turf.js (`turf.destination`)
- Prévia da geometria em tempo real no mapa à medida que o usuário preenche

---

**Total Sprint A: ~21 itens PoC cobertos**

---

## Sprint B — Médio Esforço (Semanas 3–6)
> Funcionalidades com escopo controlado mas que requerem mais desenvolvimento.

---

#### B1 — Campos de Pessoa-Social Faltantes
**Itens PoC:** 092, 093
**Status:** ⏳ Pendente

Campos a adicionar na tabela `pessoas` (ou tabela complementar `pessoa_social_dados`):
- `rg`, `ctps`, `pis` (strings)
- `nis` (mover de `CadastroSocial` ou duplicar)
- `certidao_nascimento` (string)
- `telefone` (string)
- `estado_civil` (enum: solteiro, casado, divorciado, viuvo, uniao_estavel)
- `sexo` (enum: masculino, feminino, outro)
- `pai_id` (FK nullable → pessoas)
- `mae_id` (FK nullable → pessoas)
- `conjuge_id` (FK nullable → pessoas)

RelationManagers adicionais em `CadastroSocialResource`:
- **Rendas**: `pessoa_id`, `valor`, `tipo`, `compoe_renda_familiar` (bool) — afeta cálculo de renda bruta
- **Ocorrências Sociais**: data, tipo, descrição

---

#### B2 — Entidade Família Completa
**Itens PoC:** 094, 095
**Status:** ⏳ Pendente

- Verificar campos existentes em `CadastroSocial` e completar:
  - `situacao_cadastro` (enum: cadastrado, beneficiado, aprovado, sorteado, nao_localizado, apresentou_documentos)
  - `empreendimento_id` (FK)
  - `titularidade` (enum: proprio, alugado, cedido, ocupacao) para o terreno
  - Coluna `geo` POINT em `CadastroSocial` ou relação com `Lote`/`UnidadeImobiliaria` para localização do terreno

---

#### B3 — Entidades Sociais Faltantes
**Item PoC:** 091
**Status:** ⏳ Pendente

Migration + Model + Resource para:
- `Entidade` (nome, tipo, cnpj, telefone, endereço)
- `TipoEntidade`
- `ServicoSocial` (nome, entidade_id, descricao)
- `Programa` (nome, descricao, data_inicio, data_fim)
- `Evento` (nome, data, local, programa_id)
- `InformacaoSocial` (tipo, valor — para registrar informações livres na família)

Relacionar com `CadastroSocial` onde aplicável via RelationManagers.

---

#### B4 — Índice de Vulnerabilidade + Gráfico Família
**Itens PoC:** 096, 098
**Status:** ⏳ Pendente

**Índice de vulnerabilidade (096):**
- Algoritmo em `CadastroSocial` (calculado no `save()`):
  - +2 pts: em área de risco
  - +2 pts: renda per capita < 1/4 salário mínimo
  - +1 pt: possui membro com deficiência
  - +1 pt: situação de moradia precária (ocupação irregular, situação de rua)
  - +1 pt: sem benefícios sociais
  - Campo `indice_vulnerabilidade` (int 0–7) salvo automaticamente
- Exibir badge colorido na listagem do `CadastroSocialResource`

**Gráfico pizza + mapa (098):**
- Widget Filament Chart (Chart.js) na page ou dashboard:
  - Fatias: Alta Vulnerabilidade / Média / Baixa / Sem Dados
  - Clicar em fatia → filtrar tabela e destacar famílias no mapa pelo `unidade_imobiliaria_id`

---

#### ~~B5 — Estoque Completo: Entidades Faltantes~~
**Itens PoC:** 053, 054, 055, 056, 057, 058, 059
**Status: ✅ Concluído**
**Concluído em:** 2026-07-01

Implementado o **PoC 053 completo** — 9 entidades novas + integração no saldo/movimentação + 3 relatórios.

**Novas entidades (053):** `Estabelecimento`, `Fabricante`, `Fornecedor`, `UnidadeMedida`, `Embalagem`, `FamiliaProduto`, `TipoEstoque`, `OperacaoInterna` (substitui o enum de tipo por operação configurável — item 054), `LoteEstoque` (lote/série com garantia — item 055).

**Wiring:** `Marca.fabricante_id`, `LocalEstoque.estabelecimento_id`, `Produto.{familia_produto_id,unidade_medida_id,embalagem_id}`, `Estoque.{tipo_estoque_id,lote_estoque_id}`, `EstoqueMovimentacao.{operacao_interna_id,tipo_estoque_origem_id,tipo_estoque_destino_id}`, `MovimentacaoItem.lote_estoque_id`.

**Relatórios (Excel/PDF/XML):**
- [x] 057 Movimentação — filtros por período, produto, lote e tipo de estoque
- [x] 058 Saldo — filtros por local, tipo de estoque, produto e família (`EstoqueExportService`)
- [x] 059 Garantia — badge de vencimento + filtros vencida / vence em 30 dias (`LoteEstoqueExportService`)

**Arquivos:** 15 migrations (`2026_07_01_1500xx`) · 9 models novos + 6 editados · 9 Resources novos (+ List pages) + 5 Resources editados · `EstoqueExportService`, `LoteEstoqueExportService` novos + `EstoqueMovimentacaoExportService` (XML adicionado).

**Permissões:** 1 permissão única `gerenciar_X` por cadastro auxiliar (CAIXA 9b da `RoleResource`) + Policy por model.

##### Glossário das entidades de estoque (ordem hierárquica = ordem do menu "Estoque e Almoxarifado")

| # | Entidade | O que é |
|---|---|---|
| 1 | **Estabelecimento** | Unidade da prefeitura dona dos locais (ex.: Secretaria de Obras). |
| 2 | **Local de Estoque** | Onde o material fica guardado (Almoxarifado Central, Caminhão 01) — pertence a um Estabelecimento. |
| 3 | **Tipo de Estoque** | A situação do saldo num local (Disponível, Danificado, Em Trânsito) — separa saldos do mesmo produto. |
| 4 | **Fabricante** | Quem **produz** o item (ex.: Philips). |
| 5 | **Marca** | A marca comercial do produto — pertence a um Fabricante. |
| 6 | **Fornecedor** | Quem **vende/entrega** o item à prefeitura. |
| 7 | **Família de Produto** | Categoria que agrupa produtos (Iluminação, Elétrica...). |
| 8 | **Unidade de Medida** | Como o item é medido (UN, M, CX). |
| 9 | **Embalagem** | Acondicionamento + quantidade (ex.: "Caixa com 12"). |
| 10 | **Produto** | O item em si (lâmpada, reator), com marca/família/unidade. |
| 11 | **Lote / Série** | Um lote específico de um produto, com validade e **garantia** (rastreabilidade). |
| 12 | **Operação Interna** | O "tipo de movimento" configurável (entrada/saída/transferência), ex.: "Nota de Entrada por Compra". |
| 13 | **Estoque (Saldo)** | A quantidade atual de um produto em um local + tipo + lote. |
| 14 | **Movimentação** | Cada entrada/saída/transferência que altera o saldo. |

> **Fluxo:** um **Produto** (de uma **Marca**/**Fabricante**, numa **Família**) chega de um **Fornecedor** como um **Lote**, é registrado por uma **Operação Interna** de entrada, guardado num **Local** (dentro de um **Estabelecimento**) sob um **Tipo de Estoque**, virando **Saldo** — e cada **Movimentação** altera esse saldo.

---

#### ~~B6 — Numeração Predial: Melhorias e Customização PoC~~
**Itens PoC:** 099–109
**Status: ✅ Concluído**
**Concluído em:** 2026-07-01

A ferramenta de numeração predial foi ampliada:
- [x] Pares/ímpares em cores diferentes no mapa (verde=par, azul=ímpar, cinza=excluído) — item 100
- [x] Botão "Inverter" lados pares/ímpares sem redesenhar o trajeto — item 103
- [x] Números iniciais separados para lado PAR e lado ÍMPAR — item 105
- [x] Ponto de partida = 1º ponto do trajeto desenhado no mapa — item 104
- [x] "Revisar" — modal lista as parcelas com nº editável, lado e faixa sugerida — item 107
- [x] Clique numa parcela na prévia inclui/exclui do processo — itens 101/102
- [x] "Salvar" move `numero_logradouro → numero_predial_antigo` e grava o novo nº em `numero_logradouro` — item 108
- [x] "Divergências" pinta de vermelho as parcelas com `numero_logradouro ≠ numero_predial_antigo` — item 109
- [x] Campos de endereço no `Lote` (`tipo_logradouro`, `logradouro`, `numero_logradouro`, `cep`) herdados do `dados_tributarios` e pesquisáveis no `LoteResource`

**Arquivos:** `app/Filament/Pages/MapaFullscreen.php` (métodos `recomputarNumeros`, `inverterLadosNumeracao`, `toggleParcelaNumeracao`, `revisarNumeracaoAction`, `verDivergenciasNumeracao`, `confirmarNumeracaoAction` reescrito) · `app/Models/UnidadeImobiliaria.php` (hook `saved()` propaga endereço → lote) · `app/Models/Lote.php` · `app/Filament/Resources/LoteResource.php` (form + colunas de busca) · `public/js/gis/mapa-engine.js` (preview colorido + interceptador de clique + camada de divergências) · `resources/views/filament/pages/mapa-fullscreen.blade.php` (pílula com legenda e botões) · `resources/views/pdf/relatorio-numeracao.blade.php` · migrations `add_numero_predial_calculado_to_lotes_table` + `restructure_numeracao_predial_on_lotes_table`.

---

#### B7 — Categorias do App de Chamados (pai/filho, cor, ícone, privada)
**Itens PoC:** 155, 156, 157, 158, 159
**Status:** ⏳ Pendente

- Migration: tabela `categorias_chamado` (`id`, `tenant_id`, `nome`, `cor` hex, `icone` path, `pai_id` FK self-referencing, `privada` bool)
- `CategoriaChamadoResource` com árvore pai/filho no Filament
- Adicionar `categoria_chamado_id` FK em `SolicitacaoManutencao`
- Categorias privadas: visíveis apenas para usuários com role `fiscal` ou superior
- Endpoints API: `GET /api/categorias-chamado` (respeitando privacidade)

---

#### B8 — Notificações de Mudança de Categoria e Fase
**Itens PoC:** 166, 168
**Status:** ⏳ Pendente

- Criar Events: `CategoriaAlterada`, `FaseAlterada` em `SolicitacaoManutencao`
- Listener: dispara `ExpoPushService` para o cidadão (se tiver `expo_push_token`)
- Mensagem push:
  - Categoria: "Sua solicitação #{id} teve a categoria alterada para {nova_categoria}"
  - Fase: "Sua solicitação #{id} avançou para a fase: {nova_fase}"

---

#### B9 — Mensagens Públicas ao Cidadão por Solicitação
**Itens PoC:** 169, 170, 171
**Status:** ⏳ Pendente

- Adicionar `solicitacao_manutencao_id` (FK nullable) e `publica` (bool) ao model `Mensagem`
- Endpoints API:
  - `GET /api/solicitacoes/{id}/mensagens` — lista mensagens da solicitação (público: apenas públicas; interno: todas)
  - `POST /api/solicitacoes/{id}/mensagens` — envia mensagem (body: `{texto, publica}`)
- Enviar Expo push quando `publica = true` (mesmo após solicitação finalizada — item 171)
- Na `MensagensPage` do Filament: exibir mensagens agrupadas por solicitação

---

#### B10 — Impressão da Solicitação com Mapa, Mensagens, Questionário e Histórico
**Item PoC:** 174
**Status:** ⏳ Pendente

Criar `SolicitacaoManutencaoPdfService` com template blade contendo:
- Mini-mapa com localização do asset (mesma lógica do A4)
- Dados básicos da solicitação
- Questionário/boletim respondido (se houver)
- Mensagens públicas
- Histórico de fases (tramitações com data/responsável)

---

#### B11 — Mapa REURB por Etapa + Dashboard de Progresso
**Itens PoC:** 223, 224
**Status:** ⏳ Pendente

**Mapa por etapa (223):**
- Adicionar `processo_digital_id` e `etapa_atual` ao `AreaReurb` (atualizado na tramitação)
- Toggle "Colorir REURB por Etapa" no mapa (análogo ao `toggleLotesStatusColor`)
- Paleta por etapa configurável

**Dashboard (224):**
- Page Filament `ReurbProgressoPage` com:
  - Cards: total de áreas, por etapa, % concluídas
  - Gráfico de barras por etapa
  - Auto-refresh a cada 60s

---

#### B12 — Tela de Cadastro para Usuário da Prefeitura (sem necessitar autorização de gerente)
**Item PoC:** 011
**Status:** ⏳ Pendente

- Criar rota pública ou semi-pública `/app/{tenant}/cadastro-usuario`
- Formulário: nome, email, senha (sem seleção de permissões)
- Usuário criado com role padrão sem permissões (aguarda promoção pelo Manager)
- Manager vê lista de usuários pendentes de ativação no painel de usuários

---

#### B13 — Histórico Cartográfico (antes/depois) na Auditoria
**Item PoC:** 044
**Status:** ⏳ Pendente

- No `LogsActivity` do `Lote`, incluir o campo `geo` no log de alterações (salvar GeoJSON antigo em `properties.old.geo`)
- Na `AuditoriaPage`, quando a operação for `updated` e envolver o campo `geo`:
  - Exibir dois mini-mapas lado a lado (Antes / Depois) com a geometria renderizada
  - Usar OpenLayers leve embutido no modal de detalhes

---

#### B14 — Seleção de Imóvel no Mapa dentro do Formulário de Processo
**Itens PoC:** 130, 221
**Status:** ⏳ Pendente

- Componente Filament/Livewire reutilizável: mapa clicável dentro do formulário de processo
- Ao clicar em lote: preenche automaticamente campos:
  - `lote_id`, `numero_cadastro_imobiliario`, `inscricao_imobiliaria`, `localizacao_texto`
- Usar nos processos: Aprovação de Projeto (130), REURB Digital (221) e Habite-se (item análogo)

---

#### B15 — Anotação em PDF de Documentos do Processo
**Item PoC:** 222
**Status:** ⏳ Pendente

- Integrar PDF.js + Fabric.js (ou similar) na view de anexos do processo
- Usuário pode adicionar anotações (texto, setas, destaques) sobre o PDF
- Ao salvar: criar novo registro em `ProcessoAnexo` com `versao = N+1`, sem sobrescrever o original
- Exibir histórico de versões do documento

---

#### B16 — Enforcement: Correções Somente em Fases Reprovadas
**Itens PoC:** 129, 220
**Status:** ⏳ Pendente

- No `ProcessoDigital`, bloquear edição dos campos do formulário quando `status != 'reprovado'`
- Exibir mensagem clara: "Edição disponível apenas após parecer reprovado nesta fase"
- Aplicar tanto na view do analista quanto na view do solicitante/cidadão

---

#### B17 — Seeders de Fluxos BPMN Pré-configurados
**Itens PoC:** 138–148 (Habite-se), 205 (REURB)
**Status:** ⏳ Pendente

Criar seeders com fluxos pré-configurados:

1. **Habite-se Online:** protocolo → análise técnica → vistoria → aprovação → emissão do habite-se
2. **Atestado de Conclusão de Obra:** protocolo → análise → emissão
3. **Aprovação de Projeto de Construção/Reforma:** protocolo → análise técnica → parecer → aprovação → alvará

Cada seeder cria o `BpmnFluxo` com etapas, formulários e perfis autorizados.

---

#### B18 — Swimlanes por Setor/Departamento no BPMN
**Item PoC:** 206
**Status:** ⏳ Pendente

- Adicionar campo `setor_departamento` nas etapas (`BpmnEtapa`)
- Exibir agrupamento visual por setor no editor BPMN (swimlanes/pools)
- Filtrar no gerenciamento de processos por setor responsável

---

**Total Sprint B: ~39 itens PoC cobertos**

---

## Sprint C1 — PGV Completo (Semanas 7–9)
> Módulo crítico: 19 itens (7% da conformidade). Atingir os 90% ao concluir este sprint.

---

#### C1-F1 — PGV: Amostras, Polos Valorizantes, CUB e Depreciação
**Itens PoC:** 225, 226, 227, 228, 229
**Status:** ⏳ Pendente

**Migration:**
- `pgv_amostras`: `id`, `tenant_id`, `lote_id` FK nullable, `geo` POINT, `valor_m2`, `idade_aparente`, `estado_conservacao`, `tipologia`, `estrutura`, `padrao_construcao`, `area_terreno`, `area_edificacao`, `observacao`, `espuria` bool
- `pgv_polos_valorizantes`: `id`, `tenant_id`, `nome`, `geo` POINT
- `pgv_cub`: `id`, `tenant_id`, `tipologia`, `estrutura`, `padrao`, `coeficiente`, `valor_m2`, `mes_referencia`
- `pgv_depreciacao`: `id`, `tenant_id`, `estado_conservacao`, `idade_de`, `idade_ate`, `coeficiente`

**Resources:**
- `PgvAmostraResource` com botão "Selecionar no Mapa" (clicar no lote para registrar amostra)
- `PgvPoloResource` com geo no mapa
- `PgvCubResource` com grid de valores por tipologia/estrutura/padrão
- `PgvDepreciacaoResource`

---

#### C1-F2 — PGV: Cálculo, Regressão Linear e Homogeneização
**Itens PoC:** 230, 231, 232, 233, 234
**Status:** ⏳ Pendente

**`PgvCalculoService`:**
- Fórmula de homogeneização: configurável via JSON (fatores multiplicadores por atributo)
- Regressão linear simples (valor vs. distância ao polo): PHP puro ou via query PostGIS
- Gráfico de regressão linear com linha de tendência (Chart.js no Filament)
- Ação "Remover Espúria" na listagem de amostras (marca `espuria = true` e recalcula)
- Cálculo de distância face de quadra → polo via `ST_Distance` PostGIS
- Cálculo e armazenamento do valor estimado por face de quadra em `pgv_valores_faces` (nova tabela)

---

#### C1-F3 — PGV: Visualização no Mapa, Relatório e Simulação IPTU
**Itens PoC:** 235, 236, 237, 238, 239, 240, 241, 242, 243
**Status:** ⏳ Pendente

**Mapa (235):**
- Layer `pgv_faces_quadra` no mapa com coloração por valor calculado (gradiente de cor)

**Relatório (236):**
- PDF/XLS com: código da seção, logradouro, valor calculado por face

**Simulação IPTU (237–243):**
- Formulário de simulação:
  - Alíquotas por tipo de imóvel (238)
  - Percentual do valor venal (239)
  - Limitador de aumento % em relação ao último lançamento (240)
- Resultado: tabela comparativa IPTU atual vs. simulado (241, 242)
- Fórmula parametrizável em tempo de execução — salvar como JSON em configuração do tenant (243)

---

**Total Sprint C1: +19 itens PoC cobertos → META 90% ATINGIDA**

---

## Sprint C2 — Nuvem de Pontos 3D (Semanas 10–11)
> Condicional: depende da Prefeitura fornecer dados LAZ/LAS do levantamento aerofotogramétrico.

---

#### C2-1 — Integração Potree para Visualização de Nuvem de Pontos
**Itens PoC:** 244, 245, 246, 247, 248, 249, 250
**Status:** ⏳ Pendente (aguardando dados LAZ/LAS da Prefeitura)

- Integrar **Potree** (viewer WebGL open-source para nuvem de pontos)
- Page Filament: `NuvemPontosPage` com viewer embutido (iframe ou componente Livewire)
- Ferramentas Potree disponíveis nativamente:
  - Zoom/rotação/movimentação (247)
  - Medição de distâncias, área, volumes e cortes em seções (248)
  - Ajuste de cores, intensidade, filtro de classificação (249)
  - Densificação, ângulo de visão, qualidade e tamanho mínimo de pontos (250)
- Coordenadas 3D e valor de intensidade visíveis no hover (245)
- Integrado ao sistema WEB como mais uma página do `/app` (246)

**⚠️ Ação necessária:** Solicitar formalmente à Prefeitura os dados LAZ/LAS ou E57 do recobrimento aerofotogramétrico para poder configurar o Potree.

---

**Total Sprint C2: +7 itens PoC cobertos**

---

## Sprint C3 — Processos Digitais e REURB (Semanas 12–13)

---

#### C3-1 — Melhorias na Lógica de Processos (BPMN)
**Itens PoC:** 129, 220 (melhorar), 130, 221 (se não feito no B16/B14)
**Status:** ⏳ Pendente

Revisar a lógica de tramitação de processos e garantir que todos os fluxos (Aprovação de Projeto, Habite-se, REURB) funcionem corretamente com os seeders criados no B17.

---

#### C3-2 — Melhorias no App de Chamados (Módulo XV)
**Itens PoC:** 153, 162, 163, 165
**Status:** ⏳ Pendente

- **153** — Ordenação manual de etapas no fluxo: drag-and-drop de ordem das etapas
- **162, 163** — Bidirectional: selecionar solicitação na tabela → voar no mapa; selecionar no mapa → destacar na tabela
- **165** — Alterar categoria de uma solicitação existente (ação inline)

---

## Sprint C4 — App de Recadastramento (Semana 13)

---

#### C4-1 — Melhorias no Backend do App de Recadastramento
**Itens PoC:** 193, 196, 199
**Status:** ⏳ Pendente

- **193** — Filtro por loteamento no pull: `GET /api/sync/lotes/pull?loteamento_id=X`
- **196** — Endpoint de configuração de camadas para o app: `GET /api/layers/config` (retorna quais layers estão habilitadas e com quais estilos)
- **199** — Endpoint de export ZIP: `POST /api/sync/lotes/export-zip` — gera arquivo compactado com todos os dados coletados (boletins, fotos) para backup

---

## Sprint C5 — App Cidadão e Integrações (Semana 14)

---

#### C5-1 — Login Social no App Cidadão
**Item PoC:** 178
**Status:** ⏳ Pendente

- Instalar `laravel/socialite`
- Configurar providers: Facebook e Google (Gmail)
- Endpoints: `GET /api/auth/facebook`, `GET /api/auth/google`, callbacks OAuth
- Retornar token Sanctum após autenticação social bem-sucedida

---

#### C5-2 — Endpoint de Atualização de Perfil do Cidadão
**Item PoC:** 187
**Status:** ⏳ Pendente

- `PUT /api/perfil` — aceita: nome, data_nascimento, email, celular, senha (com confirmação)
- Validações de senha forte e email único

---

#### C5-3 — Geocoding Reverso para Busca de Endereço
**Item PoC:** 184
**Status:** ⏳ Pendente

- Endpoint: `GET /api/geocoding/reverse?lat={lat}&lon={lon}` — usa Nominatim (OSM, gratuito) ou Google Maps Geocoding API
- Retorna: logradouro, número, bairro, cidade, CEP
- Cacheado por coordenada (Redis ou file cache, TTL 24h)

---

## Pendências Pontuais (qualquer sprint, por oportunidade)

---

#### P1 — Verificar Criação de Geometria por Coordenada XY
**Item PoC:** 045
**Status:** ⏳ Verificar

O sistema já tem criação de artefatos por coordenadas geográficas. Verificar se atende o requisito de "coordenadas XY" (pode ser UTM SIRGAS 2000 conforme o TR). Documentar ou ajustar se necessário.

---

#### P2 — Verificar e Documentar Street View / Fotos 360
**Item PoC:** 003, 028
**Status:** ✅ Já implementado (confirmado pelo cliente)

Confirmar que a funcionalidade está devidamente acessível e documentar para demonstração na PoC.

---

#### P3 — WMS por Categoria Hierarquizada
**Item PoC:** 022
**Status:** ⏳ Pendente

A função WMS existe mas é genérica. Melhorar para permitir:
- Cadastro de fontes WMS com categoria hierárquica (ex: "Cartografia" > "Ortofoto" > "2025")
- Ativar/desativar por categoria no painel de camadas

---

#### P4 — Itens de Produto para Poste e Tipos de Defeito
**Item PoC:** 060
**Status:** ⏳ Verificar

Verificar se existem entidades separadas para "Itens de Produto para Poste" (reator, lâmpada, luminária com identificação do lote de estoque) e "Tipos de Defeito" como tabela de referência. Criar se não existirem.

---

#### P5 — Proprietário do Jazigo
**Item PoC:** 115
**Status:** ⏳ Verificar

Confirmar que o CRUD de Proprietário do Jazigo está completo (via tabela Pessoas). Garantir que a associação com o Jazigo está corretamente exposta no `JazigoResource`.

---

#### P6 — Código de Verificação nas Consultas de Viabilidade
**Item PoC:** 052
**Status:** ✅ Já implementado (confirmado pelo cliente)

Protocolo e hash de segurança já são gerados na tabela de emissões. Garantir que o PDF da viabilidade exibe o hash de forma visível para consulta posterior.

---

#### P7 — Exportação XML das Entidades de Arborização, Iluminação e Rural
**Item PoC:** 076, 060, 251 (extensão do A1)
**Status:** ⏳ Pendente

Junto com A1, incluir XML nos Export Services de Árvore, Poste e entidades rurais.

---

## Itens a NÃO desenvolver agora (custo × benefício baixo para PoC)

| Item PoC | Motivo |
|---------|--------|
| 175 (App Android/iOS nativo) | App mobile não é responsabilidade deste repositório |
| 183 (Editar/recortar/rotacionar foto no app) | Feature do app mobile |
| 188 (Compartilhar o aplicativo) | Feature mobile |
| 198 (Cache offline no app) | Lógica do app, não do backend |
| 204 (Modo offline completo) | Lógica do app, não do backend |

---

## Log de Conclusões

| Item | Concluído em | Responsável |
|------|-------------|-------------|
| A1 — Exportação XML (Lote/Quadra/Logradouro/Loteamento/Bairro) + nesting Unidades/Edificações em PDF e Excel do Lote | 2026-06-30 | Claude |
| A11 — Seções de Logradouro: migration, model, Resource, HasSecaoLogradouroActions, MapaFullscreen, MapDataController, mapa-engine.js (9 touchpoints), blade UI, SecoesRelationManager, gis:recalcular-metadata, permissões | 2026-06-30 | Claude |
