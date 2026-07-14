# PoC Tangará/SC — Análise de Conformidade e Plano de Ação

> **Fonte:** `poc_tangara_sc.md` (124 itens obrigatórios: 6 Características Técnicas + 89 Intranet + 29 Internet/Público).
> **Critério de aprovação:** demonstração plena de **≥ 85%** dos itens (≥ 106 de 124). Itens não demonstrados (dentro dos 85%) devem ser implementados em até 60 dias pós-contrato.
> **Meta deste plano:** atingir **≥ 95%** (118 itens), com caminho mapeado até **100%**.
> **Análise realizada em:** 2026-07-08, por varredura direta do código-fonte (evidências arquivo:linha em cada item).

---

## 1. Tabela Resumo — Visão Geral

### 1.1 Resultado global

| Status | Qtd | % |
|---|---:|---:|
| ✅ Atendido (demonstrável hoje) | **95** | **76,6%** |
| ⚠️ Parcial (funciona, mas com lacuna frente ao texto do edital) | **19** | **15,3%** |
| ❌ Não atendido | **10** | **8,1%** |
| **Total** | **124** | 100% |

> **Leitura do risco:** contando somente os plenos (76,6%), estamos **abaixo dos 85%**. Contando plenos + parciais (114 = 91,9%), passamos — mas parciais podem ser contestados pela Comissão na demonstração ao vivo. O plano abaixo converte parciais e lacunas em ordem de esforço: a **Etapa 1 sozinha já leva a ~91% plenos** e a **Etapa 2 a ~98%**.

### 1.2 Resultado por categoria

| Categoria | Itens | ✅ | ⚠️ | ❌ | % pleno |
|---|---:|---:|---:|---:|---:|
| 1. Características Técnicas | 6 | 5 | 1 | 0 | 83% |
| 2.1 Consulta de Dados (intranet) | 21 | 15 | 5 | 1 | 71% |
| 2.2 Análise Espacial | 2 | 2 | 0 | 0 | 100% |
| 2.3 Mapas de Calor | 1 | 0 | 1 | 0 | 0% |
| 2.4 Impressão / Exportação | 7 | 7 | 0 | 0 | 100% |
| 2.5 Tematização | 6 | 3 | 1 | 2 | 50% |
| 2.6 Estatísticas | 4 | 3 | 1 | 0 | 75% |
| 2.7 Edição Cartográfica | 20 | 14 | 5 | 1 | 70% |
| 2.8 Edição de Atributos | 15 | 12 | 1 | 2 | 80% |
| 2.9 Navegação | 6 | 6 | 0 | 0 | 100% |
| 2.10 Manutenção de Usuários | 7 | 7 | 0 | 0 | 100% |
| 3. Internet / Público | 29 | 21 | 4 | 4 | 72% |
| **Total** | **124** | **95** | **19** | **10** | **76,6%** |

### 1.3 Decisões registradas (2026-07-08) e Plano de Ação Futura

| Decisão | Resolução |
|---|---|
| **D1 — Integração Betha Sistemas** | ✅ **PoC com dump JSON** via `tributario:importar` (ponto de extensão `IntegraPrefeituraService`). A API do Betha, em regra, só é liberada após aprovação da PoC + assinatura do contrato → integração em tempo real registrada como **F1 (plano futuro)** em [pendenciasTangara.md](pendenciasTangara.md). |
| **D2 — EAV / Itens de cadastro customizáveis (2.8-75/76)** | ✅ **Movido para pós-PoC** como **F2 (plano futuro)** — coberto pela janela de 60 dias pós-contrato prevista no edital. O escopo pré-PoC passa a mirar **122/124 (98,4%)**, bem acima da meta de 95%. |

> **Plano de ação futura (pós-contrato):** F1 — `BethaService` em tempo real (Strategy com `IntegraPrefeituraService`/`GovbrService`); F2 — EAV completo (ex-Etapa 3.2). Detalhes e status em [pendenciasTangara.md](pendenciasTangara.md).

---

## 2. Detalhamento por Item

Legenda das evidências: caminhos relativos à raiz do projeto.

### 2.1 Características Técnicas (6 itens)

| # | Item | Status | Evidência / Observação |
|---|---|---|---|
| 1 | Navegadores (IE, Edge, Firefox, Chrome) | ✅ | Filament 3 + OpenLayers, compatível com todos os navegadores modernos. **Nota de defesa:** o IE foi descontinuado pela Microsoft em 06/2022 — argumentar que nenhum sistema atual o suporta; Edge é o sucessor oficial. |
| 2 | Sem plugins/applets/ActiveX | ✅ | Stack 100% web (Livewire/Alpine/OpenLayers). Nenhuma dependência de plugin. |
| 3 | OGC — WMS **e** WFS | ⚠️ | `OgcController.php` implementa **WFS** (GetCapabilities + GetFeature com BBOX, camadas lotes/quadras/logradouros/bairros). **WMS servidor não existe** (o sistema só *consome* WMS externo). → Etapa 3 |
| 4 | Fontes externas (OSM, Bing etc.) | ✅ | `mapa-engine.js:68-109`: OSM, Esri World Imagery, Azure Maps (road+sat), ortofoto do tenant. O edital diz "como... entre outros" — atendido sem Bing. |
| 5 | Fornecer SGBD sem custos | ✅ | PostgreSQL + PostGIS (software livre). |
| 6 | Navegador / modalidade SaaS | ✅ | SaaS multi-tenant nativo (`AppPanelProvider` + tenancy por slug). |

### 2.2 Intranet — 2.1 Consulta de Dados (21 itens)

| # | Item | Status | Evidência / Observação |
|---|---|---|---|
| 1 | Expressões de consulta cruzando 2+ camadas | ⚠️ | `MapDataController::advancedSpatialQuery` cruza camada alvo × camada de referência (`ST_Intersects`/`ST_Within`), mas **não combina condição de atributo + cruzamento espacial na mesma consulta** (ex.: "lotes na zona X **com área > Y**"). → Etapa 1.8 |
| 2 | Delimitar área por Distrito/Setor/Bairro/Logradouro/Quadra | ⚠️ | Delimitação por bairro/setor/perímetro existe (filtro + estatísticas). Faltam **Logradouro e Quadra** como delimitadores. → Etapa 1.9 |
| 3 | Localizar imóvel por Endereço | ✅ | `MapDataController::searchLote:561-569` (logradouro + número). |
| 4 | Localizar por Inscrição Imobiliária | ✅ | `searchLote:559`. |
| 5 | Localizar Edifício por nome | ✅ | `searchLote:571,617` (`dados_tributarios->>'nome_edificio'`). |
| 6 | Localizar Loteamento/Quadra/Lote | ✅ | `searchLote:689,712,558`. |
| 7 | Localizar Quadra por número | ✅ | `searchLote:712-751`. |
| 8 | Localizar Distrito por nome | ✅ | `searchLote:777` (via Perímetros/Distritos). |
| 9 | Localizar Setor por nome | ✅ | `searchLote:754` (Setor Fiscal). |
| 10 | Localizar Bairro por nome | ✅ | `searchLote:661`. |
| 11 | Imóveis de Contribuinte por nome/CPF/CNPJ | ✅ | `searchLote:574-576` (nome + CPF no modo logado). CNPJ usa o mesmo campo `proprietario_cpf` — garantir dado de demo com CNPJ. |
| 12 | Visualização de dados de Pessoas/Contribuintes | ✅ | `PessoaResource` completo + proprietário na ficha do lote. |
| 13 | Dados do imóvel com imagem frontal e planta | ✅ | Ficha lateral do lote + fotos (`gerenciarFotosLoteAction`) + BIC PDF com foto e print do mapa. |
| 14 | Histórico de alterações cartográficas e de atributos do imóvel | ⚠️ | `AuditoriaPage` + `LogsGeometryChanges` existem, mas **não há acesso ao histórico a partir da ficha do lote no mapa**. → Etapa 1.1 |
| 15 | Memorial Descritivo (confrontantes + coordenadas) | ⚠️ | `MemorialDescritivoService`: vértices UTM SIRGAS 2000, azimutes, lotes confrontantes. **Falta o nome do contribuinte confrontante.** → Etapa 1.2 |
| 16 | Planta de Quadra completa | ✅ | `PlantaQuadraPdfService` + `pdf/planta-quadra.blade.php`: lotes, áreas em texto, testadas, unidades, edificações com tipo e área. |
| 17 | Dados dos logradouros com **imagens das Seções** | ❌ | `SecaoLogradouro` não tem coluna de foto (migration `2026_06_30_172253`). → Etapa 1.7 |
| 18 | Dados de Zoneamento | ✅ | `ZonaResource` + `ZoneamentoRegraResource` (usos) + `ParametroUrbano`. |
| 19 | Viabilidade Parcelamento/Desmembramento | ✅ | `ViabilidadeService::analisarParcelamento` (+ unificação), disparado do mapa intranet. |
| 20 | Viabilidade para Funcionamento | ✅ | `ViabilidadeService::analisar` (CNAE × zona: permitido/permissível/proibido). |
| 21 | Viabilidade com imagem, croqui, metragens, parâmetros, usos, código | ⚠️ | PDF tem imagem do mapa, zona, usos e **código de autenticação + verificação pública `/v/{protocolo}`**. Faltam **metragens/áreas no template de funcionamento** e `mapImage` na reimpressão. → Etapa 1.5 |

### 2.3 Intranet — 2.2 Análise Espacial (2 itens)

| # | Item | Status | Evidência |
|---|---|---|---|
| 22 | Cálculo de medidas lineares e áreas | ✅ | `mapa-engine.js:4580-4820` (medição linha/área, `ol.sphere`). |
| 23 | Buffer/entorno definido pelo usuário | ✅ | `filtroAvancadoAction` modo BufferCircular (`mapa-engine.js:6672-6710`, raio 1–50.000 m). |

### 2.4 Intranet — 2.3 Mapa de Calor (1 item)

| # | Item | Status | Evidência / Observação |
|---|---|---|---|
| 24 | Heatmap para **qualquer camada** com item de cadastro | ⚠️ | Heatmaps existem para Social (3), Processos (por fluxo) e Coleta (por status). **Não é genérico** — árvores, postes, chamados etc. não têm. → Etapa 1.11 |

### 2.5 Intranet — 2.4 Impressão / Exportação (7 itens)

| # | Item | Status | Evidência |
|---|---|---|---|
| 25–29 | Impressão A4, A3, A2, A1, A0 (R/P) | ✅ (5 itens) | `mapa-engine.js:7222-7309` — "Motor de Impressão" A0–A4, retrato/paisagem, jsPDF 120/150 DPI. |
| 30 | Exportar camada para SHP | ✅ | `ShapefileExportService` (`ogr2ogr`), botão por camada no painel (`routes/web.php:33`). Validar `ogr2ogr` instalado no servidor de demo. |
| 31 | Relatórios com exportação PDF e XLS/CSV | ✅ | Padrão `*ExportService` (Excel/PDF/CSV/XML) em 37 List pages. |

### 2.6 Intranet — 2.5 Tematização (6 itens)

| # | Item | Status | Evidência / Observação |
|---|---|---|---|
| 32 | Temático por **Valores Únicos** dinâmico | ❌ | Só existe pintura com **uma cor sólida** no filtro por atributo. → Etapa 1.10 |
| 33 | Temático por **Intervalo de Classes** dinâmico | ✅ | `MapaFullscreen.php:2783-2831` + choropleth `mapa-engine.js:6453-6580`. |
| 34 | Cores para Valores Únicos | ❌ | Depende do item 32. → Etapa 1.10 |
| 35 | Cores para Intervalo de Classes | ✅ | Cor inicial/final configuráveis (`:2825-2828`). |
| 36 | Nº de intervalos configurável | ✅ | Campo nº de intervalos (`:2819`). |
| 37 | Temáticos para qualquer camada | ⚠️ | Restrito às camadas suportadas pelo filtro avançado; ampliar junto com 32. → Etapa 1.10 |

### 2.7 Intranet — 2.6 Estatísticas (4 itens)

| # | Item | Status | Evidência / Observação |
|---|---|---|---|
| 38 | Estatísticas para qualquer camada | ⚠️ | `estatisticasAction` cobre **lotes/edificações/logradouros**. Ampliar (árvores, postes, chamados…). → Etapa 1.12 |
| 39 | Delimitar por Distrito/Setor/Bairro | ✅ | Área de interesse: bairros / setores fiscais / perímetros (`MapaFullscreen.php:2901`). |
| 40 | Quantitativo, percentual e gráfico (coluna/pizza) | ✅ | `MapDataController:1060-1253` + Chart.js. |
| 41 | Gráficos plotados **no mapa** (centro de cada área) | ✅ | Mini-pizza no centroide de cada área (`mapa-engine.js:7478-7520`). Ponto forte — destacar na demo. |

### 2.8 Intranet — 2.7 Edição Cartográfica (20 itens)

| # | Item | Status | Evidência / Observação |
|---|---|---|---|
| 42 | Incluir/geocodificar Lote (inscrição, área, testadas por seção, ocupação, situação) | ✅ | `HasLoteActions` + `LoteTestada` (logradouro_id + secao_logradouro_id) + `ocupacao`/`situacao_quadra` editáveis. |
| 43 | Incluir/geocodificar Edificação | ✅ | `Edificacao`: tipo, tp_construcao, estado, **pavimento**, area_geo. |
| 44 | Incluir/geocodificar Logradouro e Seções (código métrico + lado) | ⚠️ | Logradouro + `SecaoLogradouro` existem; **faltam campos "código da seção (métrico)" e "lado da seção"**. → Etapa 1.3 |
| 45 | Incluir/geocodificar Quadra | ✅ | `HasQuadraActions` + `area_geo` automática. |
| 46 | Incluir/geocodificar Distrito | ⚠️ | Coberto por `PerimetroUrbano` (campo `distrito`, camada "Distritos/Limites"). Falta apresentá-lo formalmente como Distrito (código/nome/área). → Etapa 2.1 |
| 47 | Incluir/geocodificar Setor | ✅ | `SetorFiscal` com `geo` + `area_geo`, CRUD e camada `setores_fiscais`. |
| 48 | Incluir/geocodificar Bairro | ✅ | `HasBairroActions` + `area_geo`. |
| 49 | Incluir/geocodificar Zoneamento (código, área, cor da lei) | ✅ | `Zona` (sigla/nome/cor/geo) + `area_geo`. |
| 50 | Excluir Lote | ✅ | Soft-delete via mapa/Resource. |
| 51 | Excluir Edificação (atualizar área construída, nº unidades, ocupação) | ✅ | Exclusão ok; agregados (área construída, nº unidades) são calculados dinamicamente. Ocupação é manual — cobrir no roteiro de demo. |
| 52–58 | Excluir Logradouro/Seções, Quadra, Distrito, Setor, Bairro, Meio-fio, Zoneamento | ✅ (6) ⚠️ (1) | Todos com exclusão via mapa/Resource (`HasMeioFioActions:165` etc.). **Distrito (54)** segue a ressalva do item 46 → Etapa 2.1. |
| 59 | Desmembramento com atualização completa | ⚠️ | `processarDesmembramentoLote` (ST_Split): recalcula `area_geo`, cria unidade, reparenteia edificações. **Não recalcula testadas (`LoteTestada`)**. → Etapa 2.2 |
| 60 | Unificação com atualização completa | ⚠️ | `processarUnificacaoLotes` (ST_Union): migra unidades/edificações, soma testada principal. **Não recalcula `LoteTestada` nem ocupação/situação.** → Etapa 2.2 |
| 61 | **Recodificação** de Lote, Edificação, Testadas, Logradouro/Seções, Quadra, Piscina, Distrito, Setor, Bairro, Meio-Fio, Zoneamento | ❌ | Não existe ferramenta de recodificação em cascata (inscrição/códigos propagados às unidades/filhos). Entidade **Piscina** também não existe. → Etapa 2.5 |

### 2.9 Intranet — 2.8 Edição de Atributos (15 itens)

| # | Item | Status | Evidência / Observação |
|---|---|---|---|
| 62–63 | Contribuinte/Proprietário e Pessoa | ✅ | `PessoaResource` + vínculo `proprietario_id` na unidade. |
| 64 | Atributos de Distrito | ⚠️ | Via `PerimetroUrbano` (ver item 46). → Etapa 2.1 |
| 65–70 | Atributos de Setor, Bairro, Quadra, Lote, Edificação, Logradouro/Seções | ✅ (6) | CRUDs completos (Resources + fichas do mapa). |
| 71 | Parâmetros de Zoneamento | ✅ | `ParametroUrbano` (área/testada mín/máx por zona). *Melhoria opcional: recuos/TO/CA — ver §5 Riscos.* |
| 72 | Usos de Zoneamento | ✅ | `ZoneamentoRegraResource` (permitido/permissível/proibido por CNAE/classificação). |
| 73 | Replicar unidades imobiliárias | ✅ | `replicarUnidadeAction` (`HasLoteActions.php:542`, replica N de uma vez). |
| 74 | Vincular imagem de documentos (CPF/RG/CNH) a imóvel | ✅ | `UnidadeImobiliaria::documentos()` morphMany + FileUpload no RelationManager. |
| 75 | **Itens de cadastro customizáveis** (campos dinâmicos por camada) | ❌ | Não existe mecânica EAV (gestor definir campo: nome + tipo numérico/texto/seleção/multisseleção). → **F2 (pós-PoC, decisão D2)** |
| 76 | Itens criados disponíveis em identificação, temático, consultas, heatmap e estatísticas | ❌ | Depende do 75. → **F2 (pós-PoC, decisão D2)** |

### 2.10 Intranet — 2.9 Navegação (6 itens)

| # | Item | Status | Evidência |
|---|---|---|---|
| 77–82 | Zoom in/out, pan, zoom extensão, **visão anterior**, scroll | ✅ (6) | `mapa-engine.js:1256-1295` (`zoomExtensao()` + `visaoAnterior()` com histórico de 50 estados) + controles padrão OL. |

### 2.11 Intranet — 2.10 Manutenção de Usuários (7 itens)

| # | Item | Status | Evidência |
|---|---|---|---|
| 83–85 | CRUD de Perfis, CRUD de Usuários, vínculo usuário↔perfil | ✅ (3) | `RoleResource` (Master/Manager protegidos) + `UserResource`. |
| 86 | Camadas por perfil | ✅ | CAIXA 20 — 23 permissões `ver_camada_*`. |
| 87 | Funcionalidades por perfil | ✅ | `toolbar_*` + permissões de páginas (CAIXAs 1–19). |
| 88 | Itens de Cadastro por perfil | ✅ | Atendido na leitura ampla (entidades + camadas + funcionalidades — ver seção "TR Tangará" no CLAUDE.md). Na leitura estrita (campos EAV), depende do item 75 → contemplado no F2 (pós-PoC, decisão D2). |
| 89 | Auditoria (usuário, operação, data/hora) | ✅ | `AuditoriaPage` (spatie/activitylog): busca por usuário, filtro por operação e período, modal Antes/Depois + croqui de geometria. |

### 2.12 Internet / Público (29 itens)

| # | Item | Status | Evidência / Observação |
|---|---|---|---|
| 1–8 | Localizar por endereço, inscrição, edifício, loteamento/quadra/lote, nº quadra, distrito, setor, bairro | ✅ (8) | `/api/search-lote?publico=1` — mesma busca da intranet, ocultando dados de proprietário (`MapDataController:557,574,608`). Mapa público sem login (`/mapa-publico` → `?t={slug}`). |
| 9 | Dados do imóvel com **imagem frontal** e planta | ⚠️ | Ficha pública mostra lote/inscrição/áreas/testada + croqui PDF + Street View, mas **não exibe a foto frontal cadastral**. → Etapa 1.4 |
| 10 | Logradouros com imagens das seções | ❌ | Logradouro não é clicável no público e seções não têm fotos no modelo. → Etapas 1.7 + 2.3 |
| 11 | Dados de Zoneamento | ⚠️ | Zonas visíveis com legenda/cores, mas **clicar na zona não mostra parâmetros/usos**. → Etapa 1.6 |
| 12 | Viabilidade Parcelamento/Desmembramento | ❌ | Motor existe (`analisarParcelamento`) mas **só é acionável na intranet**. → Etapa 2.4 |
| 13 | Viabilidade para Funcionamento | ✅ | `HasFichaImovelPublico::consultarViabilidadeAction` + PDF + protocolo. |
| 14 | Viabilidade com imagem, croqui, metragens, parâmetros, usos, código | ⚠️ | PDF tem imagem do mapa, usos e código de autenticação (`/v/{protocolo}` — validação pública funciona). **Faltam metragens e parâmetros detalhados.** → Etapa 1.5 |
| 15 | Medição linear e de área | ✅ | `mapa-cidadao-engine.js:1390-1480`. |
| 16–17 | Impressão A4 e A3 (R/P) | ✅ (2) | `mapa-cidadao-engine.js:1568-1670` (jsPDF A4/A3 retrato/paisagem). |
| 18 | Temático Valores Únicos | ❌ | Mesmo gap da intranet. → Etapa 1.10 |
| 19 | Temático Intervalo de Classes | ✅ | Choropleth público (`mapa-cidadao-engine.js:1125-1247`). |
| 20 | Cores para Valores Únicos | ❌ | Depende do 18. → Etapa 1.10 |
| 21 | Cores para Intervalo de Classes | ✅ | `MapaPublico::filtroAvancadoAction`. |
| 22 | Nº de intervalos | ✅ | Idem. |
| 23 | Temático para qualquer camada | ⚠️ | Ampliar junto com Etapa 1.10/1.12. |
| 24–29 | Navegação (zoom, pan, extensão, visão anterior, scroll) | ✅ (6) | `mapa-cidadao-engine.js:680-706` (histórico de 50 views, zoom extensão). |

---

## 3. Plano de Ação — do mais fácil ao mais difícil

> **Governança (CLAUDE.md):** antes de iniciar cada etapa, registrar os itens correspondentes em `docs/pendencias.md` (fonte de verdade do backlog) e marcar conclusão com data.
> Esforços estimados em horas de desenvolvimento; cada entrega inclui teste no tenant de demonstração.

### Etapa 0 — Preparação (sem código, imediato)

| Ação | Detalhe |
|---|---|
| 0.1 Tenant de demonstração "Tangará" | Criar tenant, importar base cartográfica de demonstração (ou reusar Santa Cecília), rodar seeds e `gis:recalcular-metadata`. Garantir dados que exercitem **cada** item (ex.: unidade com `nome_edificio`, proprietário com CNPJ, CNAEs nas regras de zoneamento). |
| 0.2 Validar dependências de servidor | `ogr2ogr` (export SHP), fontes de basemap com chave Azure válida, geração de PDFs (DomPDF/jsPDF). |
| 0.3 Roteiro de demonstração v1 | Documento item-a-item (124 linhas) com "como demonstrar" — os 95 ✅ já podem ser ensaiados desde já. |

### Etapa 1 — Quick wins (33–58 h) → eleva plenos para ~113/124 (**91,1%**)

Converte 5 ❌ e 13 ⚠️ em ✅. Ordenada do mais fácil ao mais difícil:

| # | Ação | Itens da PoC | Esforço | Notas técnicas |
|---|---|---|---:|---|
| 1.1 | Link "Ver histórico" na ficha do lote → `AuditoriaPage` pré-filtrada pelo registro | 2.1-14 | 2–3 h | A auditoria já loga tudo (inclusive geometria); é só criar o atalho com filtro por subject. |
| 1.2 | Memorial Descritivo: adicionar **contribuinte confrontante** (proprietário do lote vizinho via unidade principal) | 2.1-15 | 2–4 h | `MemorialDescritivoService` já identifica os lotes confrontantes. |
| 1.3 | `SecaoLogradouro`: campos `codigo` (métrico) e `lado` | 2.7-44 | 2–3 h | Migration + form/tabela no Resource e na trait do mapa. |
| 1.4 | Ficha pública do imóvel: exibir **foto frontal** (quando houver) + botão "Planta/Croqui" em destaque | 3-9 | 2–4 h | Foto já existe no modelo (`lotes.foto_frontal`); apenas exibir com fallback. |
| 1.5 | Viabilidade (funcionamento): incluir **metragens** (área do lote, testada, área construída) + parâmetros da zona no template; passar `mapImage` também na reimpressão | 2.1-21, 3-14 | 4–6 h | `viabilidade-template.blade.php` + `ViabilidadePdfService::142`. |
| 1.6 | Zona clicável no mapa público → popup com parâmetros (`ParametroUrbano`) e usos (`ZoneamentoRegra`) | 3-11 | 3–5 h | Adicionar handler de `singleclick` para camada zonas no `mapa-cidadao-engine.js`. |
| 1.7 | Fotos nas Seções de Logradouro: relação de imagens + upload no CRUD + galeria na ficha do logradouro (intranet) | 2.1-17 (+ base p/ 3-10) | 4–6 h | Reusar o padrão morphMany `documentos`/FileUpload já usado na UnidadeImobiliaria. |
| 1.8 | Filtro avançado: **combinar** condição de atributo + cruzamento espacial na mesma consulta | 2.1-1 | 4–8 h | `advancedSpatialQuery`: aplicar o WHERE de atributo também no ramo espacial. |
| 1.9 | Delimitadores adicionais: **Quadra** e **Logradouro** (buffer de N m sobre o eixo) como área de interesse no filtro/estatísticas | 2.1-2 | 3–5 h | Quadra = polígono direto; logradouro = `ST_Buffer` do eixo. |
| 1.10 | **Tematização por Valores Únicos** (intranet + público): novo modo no filtro avançado — agrupa valores distintos do atributo, paleta editável por valor, legenda dinâmica | 2.5-32, 2.5-34, 2.5-37, 3-18, 3-20, 3-23 | 8–12 h | Reusar o pipeline do choropleth (`:6453-6580`); backend devolve `valor → cor`. **Maior retorno da etapa: 6 itens.** |
| 1.11 | **Heatmap genérico**: opção "Mapa de calor" para qualquer camada carregada (pontos diretos; polígonos via centroide) | 2.3-24 | 4–6 h | Generalizar o padrão `syncColetaHeatmap` para `ol.layer.Heatmap` sobre `window.loadedLayers[layer]`. |
| 1.12 | Estatísticas: ampliar camadas de análise (árvores, postes, chamados, cemitério…) e agrupar por qualquer atributo | 2.6-38 (+ reforça 2.5-37, 3-23) | 4–6 h | `estatisticasAction` + query genérica por camada no `MapDataController`. |

**Resultado pós-Etapa 1: 113/124 plenos (91,1%) — critério dos 85% superado com folga.**

### Etapa 2 — Intermediária (36–58 h) → eleva plenos para ~121/124 (**97,6%**)

| # | Ação | Itens da PoC | Esforço | Notas técnicas |
|---|---|---|---:|---|
| 2.1 | **Distrito formal**: promover `PerimetroUrbano` (campos código/nome/área + rótulo "Distrito" na UI e busca) ou criar entidade dedicada reusando o molde Bairro | 2.7-46, 2.7-54, 2.8-64 | 4–8 h | Recomendado: promover o existente (menor risco); a camada e a busca já operam. |
| 2.2 | Desmembramento/Unificação: **recalcular testadas** (`LoteTestada` via interseção com seções de logradouro) e atualizar ocupação/situação na quadra | 2.7-59, 2.7-60 | 8–12 h | PostGIS: `ST_Intersection` do perímetro do lote com buffer das seções; situação (esquina/meio de quadra) derivável do nº de faces de logradouro. |
| 2.3 | Público: **logradouro clicável** → ficha com dados + seções + fotos (da Etapa 1.7) | 3-10 | 6–8 h | Handler no `singleclick` + Livewire action pública espelhando `HasFichaImovelPublico`. |
| 2.4 | Público: **Viabilidade de Parcelamento/Desmembramento** na ficha do imóvel (motor já existe) | 3-12 | 6–10 h | Portar `analisarParcelamento` + PDF para `HasFichaImovelPublico`, com o mesmo protocolo `/v/{protocolo}`. |
| 2.5 | **Recodificação em cascata**: ação "Recodificar" (mapa + Resource) para Lote (inscrição → propaga a unidades/edificações/testadas), Quadra, Logradouro/Seções, Setor, Distrito, Bairro, Zoneamento, Meio-fio — com registro na auditoria. Incluir **Piscina** como entidade leve (molde MeioFio, polígono) para cobrir a lista do edital | 2.7-61 | 14–24 h | Transação única por recodificação; log Antes/Depois no activitylog garante rastreabilidade exigida pelo item 89. |

**Resultado pós-Etapa 2: 121/124 plenos (97,6%) — meta de 95% atingida.**

### Etapa 3 — Complexa (16–24 h) → 122/124 (**98,4%**)

| # | Ação | Itens da PoC | Esforço | Notas técnicas |
|---|---|---|---:|---|
| 3.1 | **WMS servidor** no endpoint OGC (`GetCapabilities` + `GetMap`) | 1-3 | 16–24 h | Duas rotas: **(a)** implementar `GetMap` em PHP renderizando PNG das geometrias PostGIS (suficiente para PoC; controle total); **(b)** sidecar GeoServer/QGIS Server apontando para o PostGIS com proxy autenticado por tenant (mais robusto, mais infra). Recomendação: (a) para a PoC, (b) como evolução. |
| ~~3.2~~ | **Itens de Cadastro customizáveis (EAV)** — **movido para pós-PoC (decisão D2, ver §1.3)** como item **F2** do Plano de Ação Futura em [pendenciasTangara.md](pendenciasTangara.md). A integração com tematização/heatmap/estatísticas reaproveitará os motores genéricos da Etapa 1 (1.10/1.11/1.12). | 2.8-75, 2.8-76, 2.10-88 (estrito) | 40–80 h (pós-contrato) | Janela de 60 dias pós-contrato do edital cobre a entrega. |

**Resultado pós-Etapa 3: 122/124 (98,4%). Os 124/124 completam-se no pós-contrato com o F2 (EAV).**

### Etapa 4 — Ensaio da demonstração (paralela às etapas 2–3)

- Roteiro final item-a-item com responsável e caminho de clique;
- Ensaio cronometrado com a equipe (a PoC tem prazo de 10 dias úteis a partir da convocação — tudo deve estar pronto **antes** da convocação);
- Checklist impresso espelhando as tabelas do edital para a Comissão acompanhar;
- Plano B por item: para qualquer item que falhe ao vivo, saber se ele cabe na janela de 60 dias pós-contrato.

### Projeção consolidada

| Marco | Plenos | % | Esforço acumulado |
|---|---:|---:|---:|
| Hoje | 95/124 | 76,6% | — |
| Pós-Etapa 1 | 113/124 | 91,1% | 33–58 h |
| Pós-Etapa 2 | 121/124 | 97,6% | 69–116 h |
| Pós-Etapa 3 (3.1 WMS) | 122/124 | 98,4% | 85–140 h |
| Pós-contrato (F2 — EAV, plano futuro) | 124/124 | 100% | +40–80 h |

---

## 4. Pontos fortes a destacar na demonstração

1. **Estatísticas com gráficos plotados no mapa** (item 41) — pouco comum em concorrentes; demonstrar cedo.
2. **Impressão A0–A4 retrato/paisagem** direto do mapa (itens 25–29).
3. **Exportação SHP por camada** + relatórios em 4 formatos (30–31).
4. **Código de autenticação com verificação pública** (`/v/{protocolo}` + hash SHA-256) nas viabilidades (itens 21/14-público).
5. **Auditoria com croqui Antes/Depois de geometria** (item 89 + 14) — vai além do que o edital pede.
6. **Visão anterior com histórico de 50 estados** e navegação completa (77–82 / 24–29).
7. Busca unificada por 10+ critérios num único campo (itens 3–11).

## 5. Riscos e observações

| Risco | Mitigação |
|---|---|
| **Integração Betha Sistemas** é aspecto avaliado da PoC (não é item das tabelas, mas consta em "Aspectos avaliados") | Mesma estratégia validada para o GOVBR (Bom Princípio): `IntegraPrefeituraService` é o ponto de extensão genérico; para a PoC, importar dump JSON exportado do Betha via `tributario:importar` e demonstrar os dados tributários integrados. **Decidido (D1, 2026-07-08): PoC com dump JSON.** A API do Betha, em regra, só é liberada após aprovação da PoC + assinatura do contrato — integração em tempo real registrada como **F1** no plano futuro. |
| Edital cita **Internet Explorer** | IE foi descontinuado pela Microsoft em 15/06/2022; registrar em ata que o suporte se dá via Edge (sucessor oficial). Nenhum concorrente moderno suporta IE. |
| Parciais contestáveis ao vivo | Executar a Etapa 1 antes de qualquer agendamento da PoC — ela sozinha garante 91% plenos. |
| **EAV (75/76)** é o item mais caro e o edital exige 85%, não 100% | **Decidido (D2, 2026-07-08): fica pós-PoC (F2)**, dentro da janela de 60 dias pós-contrato prevista no edital. O escopo pré-PoC atinge 98,4% sem ele. |
| `ParametroUrbano` só tem área/testada mín/máx (sem recuos/TO/CA) | O edital de Tangará não exige recuos explicitamente (item 71 pede apenas CRUD de parâmetros — atendido). Enriquecer o modelo é melhoria opcional, útil também para o item 21 (viabilidade mais completa). |
| Ambiente de demonstração | Item 0.2: validar `ogr2ogr`, chave Azure Maps e geração de PDF no servidor que será usado na banca. |

---

*Documento gerado a partir de auditoria do código-fonte em 2026-07-08. Evidências detalhadas (arquivo:linha) constam nas tabelas da seção 2.*
