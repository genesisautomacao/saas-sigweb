# Mapeamento de Campos — Módulo Imobiliário

> **Objetivo:** definir campo a campo o que o **sistema precisa para funcionar** (fica como coluna) e o que é **agregação individual de cada prefeitura** (sai da coluna e vira campo customizado).
> **Origem:** decisões do usuário em 2026-07-31.
> **Levantamento:** schema real + rastreio dos consumidores no código, em 2026-07-31/08-01. A coluna "preench." mede o % de linhas com valor — mostra se o campo é usado de fato.

---

## A régua (3 categorias)

| | Categoria | O que é | Quem mexe |
|---|---|---|---|
| **1** ⚙️ | **Base fixa do sistema** | `id`, `tenant_id`, `sequential_id`, `code`, `geo`, FKs, timestamps, campos derivados por PostGIS (`area_geo`, `extensao_geo`). Não é "item de cadastro" — não aparece como campo. | ninguém |
| **2** 🏷️ | **Campo fixo white-label** | Coluna do sistema. **Os valores (chaves) são fixos** — o município não inventa nem remove valor de select. **Os rótulos são customizáveis**: o nome do campo e o texto de cada opção. | `CampoDominio` |
| **3** ➕ | **Campo da prefeitura** | Não existe como coluna. Cada município cria o que precisa. Aparece em ficha, edição, **filtro, busca, mapa temático, heatmap e estatísticas** (itens 75/76). | `CampoCustomizado` |

**Chave × rótulo (categoria 2):** o banco guarda a **chave** (`baldio`), a tela mostra o **rótulo** (`Baldio`, ou `Terreno Vago` se o município preferir). Isso mata de vez a colisão `esquina` vs `Esquina` que existe hoje na base — hoje o `CampoDominio` grava o rótulo como valor (trabalho **N1**).

**Critério para um campo ficar na categoria 2** — precisa de **pelo menos um**:
1. o **código depende do valor** (cor de mapa, cálculo, automação, query);
2. um **contrato externo** depende dele (API do app de coleta, importação GIS, sync tributário);
3. o **edital nomeia o campo** como requisito;
4. é **identificação** da entidade (código, nome, número).

Não atendeu nenhum → **categoria 3**.

---

## 🔬 Estudo: `unidade_imobiliarias` — o caso mais crítico

As 13 colunas fiscais foram promovidas do JSON em 2026-07-01 "para busca/edição/relatórios". O rastreio no código mostra que **a premissa não se confirmou**: as buscas e o PGV leem o **JSON**, não as colunas.

### Quem consome o quê (evidência)

| Dado | Consumidor real | O código lê de |
|---|---|---|
| `inscricao_imobiliaria` | busca — item 4 | **coluna** ([MapDataController:559](../app/Http/Controllers/Api/MapDataController.php#L559)) |
| `codigo_imovel_tributario` | chave de ligação com o fornecedor (R67-10) | **coluna** |
| proprietário (nome / CPF) | busca por contribuinte — item 11 | **JSON** ([MapDataController:575](../app/Http/Controllers/Api/MapDataController.php#L575)) |
| `nome_edificio` | busca por edifício — itens 5 e 3-3 | **JSON** ([MapDataController:571](../app/Http/Controllers/Api/MapDataController.php#L571)) |
| `valor_total_imposto` | PGV — limitador do IPTU simulado | **JSON** ([PgvSimulacaoIptuService:50](../app/Services/Pgv/PgvSimulacaoIptuService.php#L50)) |
| as outras **11 colunas fiscais** | BIC + modais de edição + exports | coluna |

> **Conclusão:** das 13 colunas fiscais, apenas **2** (`inscricao_imobiliaria`, `codigo_imovel_tributario`) são lidas pelo código fora de exibição/edição. As outras 11 são **projeção de exibição — e o formato veio do export de Santa Cecília**, como você suspeitava.

### Corte decidido (2026-08-01)

**Fica (categoria 2) — 11 colunas:**

| Campo | Por quê |
|---|---|
| `codigo_imovel_tributario` | chave de ligação (R67-10) |
| `inscricao_imobiliaria` | chave de ligação + item 4 (busca) |
| `logradouro_nome` · `numero_imovel` | item 3 (busca por endereço) + propagação para o lote |
| 🆕 **`nome_edificio`** | itens 5 e 3-3 — **promover do JSON (L2)**: hoje só existe se a importação trouxer, e ninguém consegue cadastrar pela tela |
| 🆕 **`proprietario_nome`** · 🆕 **`proprietario_cpf_cnpj`** | **item 11** ("Localizar imóveis de Contribuinte através de Nome ou parte do Nome ou CPF/CNPJ") — hoje a busca varre o JSON; promovidas, ficam indexáveis e garantidas |
| `area_total_edificacao` | item 16 (Planta de Quadra mostra a área construída de cada unidade) + item 51 (atualizar área total construída) |
| `valor_venal_lote` · `valor_venal_edificacao` | quase universais no IPTU brasileiro — decisão do usuário |
| `valor_total_imposto` | PGV — limitador do IPTU simulado |

**Sai (vira categoria 3) — 9 colunas:** `tipo_construcao`, `descricao_classificacao`, `face`, `fracao_ideal`, `area_edificacao`, `valor_metro_terreno`, `valor_metro_edificacao`, `valor_imposto_territorial`, `valor_imposto_predial`.

> **Nota de nomenclatura:** `proprietario_cpf_cnpj` (e não `proprietario_cpf`) porque o campo guarda CPF **ou** CNPJ — a chave do JSON hoje é `proprietario_cpf` mesmo quando o valor é um CNPJ, e o item 11 pede os dois. O de/para do `MapaFiscalService` faz a ponte com o nome de origem.
>
> ⚠️ Não confundir com `proprietario_id` (FK para `pessoas`), que continua sendo o vínculo forte. As colunas novas são o que o **sistema tributário informa**, que nem sempre tem `Pessoa` correspondente — hoje 84% das unidades têm as duas coisas.

> ⚠️ **O dado não se perde:** `dados_tributarios` (JSON) continua sendo a verdade e recebe tudo da importação. O que muda é que a **projeção** deixa de ser fixa em 13 colunas moldadas por um município e passa a ser o que cada prefeitura declarar.
>
> ⚠️ **Ponto a resolver junto:** hoje existem **dois** mecanismos para "mostrar campo do sistema tributário que não é canônico" — `sistemas_tributarios.extras` (R67-5) e o `CampoCustomizado`. Com o corte, os dois passam a competir pelo mesmo papel. Precisam ser unificados.

---

## 🔴 Lacunas transversais

### L1 — Não existe "Código" municipal em nenhuma entidade ✅ *decidido: criar nas 7*

O edital pede **Código** explícito nos itens 44 (Logradouro + Seção), 45 (Setor + Quadra), 46 (Distrito), 47 (Setor), 48 (Bairro) e 49 (Zoneamento). O que existe hoje:

- **`code`** = **UUID interno** → `daca59fc-a1c0-4887-a8b2-3c07167fd26e`. Inútil como código de cadastro.
- **`sequential_id`** = número por prefeitura, mas é a **chave de resolução da importação GIS** — torná-lo editável quebraria a reimportação.

**Decidido:** criar `codigo` (varchar, nullable, indexado por tenant) em `logradouros`, `secoes_logradouro`, `bairros`, `quadras`, `zonas`, `perimetros_urbanos` e `setores_fiscais`. Categoria 2. É pré-requisito do **T2.5 (Recodificação)** — não se recodifica o que não tem código.

### L2 — `nome_edificio` só existe no JSON
Ver o estudo acima. Proposta: promover a coluna.

### L3 — Taxonomias hardcoded fora do `CampoDominio`
`secoes_logradouro.tipo_pavimentacao` ([SecaoLogradouroResource:28](../app/Filament/Resources/SecaoLogradouroResource.php#L28)), `meio_fios.material` e `meio_fios.estado_conservacao` têm lista fixa no código. Devem entrar na categoria 2 (rótulos white-label).

### L4 — Campo da categoria 2 que alimenta cálculo
`edificacoes.estado_conservacao` é dimensão da Depreciação do PGV (`PgvDepreciacao`). Com valores fixos (categoria 2) isso deixa de ser risco — mas o `PgvDepreciacaoResource` deve passar a listar as opções vindas do `CampoDominioService`, não texto livre.

---

## Mapeamento por entidade

Legenda: ⚙️ base fixa · 🏷️ campo fixo white-label · ➕ vira campo da prefeitura

### `lotes` — 11.044 linhas

| Campo | Preench. | Cat. | Justificativa |
|---|---:|:---:|---|
| `sequential_id` · `quadra_id` · `zona_id` · `area_geo` · `main_facade_length` | — | ⚙️ | chave de importação, relacionamento, derivados PostGIS |
| `numero_lote` | 87% | 🏷️ | identificação — item 42 |
| `status_cadastro` | 100% | 🏷️ | cor do mapa, produtividade, app. **CHECK mantido** |
| `ocupacao` | 0% | 🏷️ | **binário `baldio`/`construido`** — itens 42/51/60 cravam; PGV e Planta de Quadra dependem |
| `situacao_quadra` | 0% | 🏷️ | itens 42/60 cravam os 3 valores; o T2.2 vai sugerir por PostGIS — precisa de chave estável |
| `foto_frontal` · `foto_lateral_esq` · `foto_lateral_dir` | ~0% | 🏷️ | item 13/3-9 + contrato do app de coleta |
| `observacao` · `inconformidade_descricao` | 0% | 🏷️ | boletim do app; inconformidade pinta o lote de vermelho |
| `dados_vistoria` · `coletado_por_id` · `coletado_em` | 0% | ⚙️ | boletim livre e auditoria da coleta |
| `area_cadastrada` | 34% | 🏷️ | comparativo de áreas (P2) — vem do tributário |
| `numero_predial_antigo` | 0% | 🏷️ | numeração predial (itens 108/109) |
| `tipo_logradouro` · `logradouro` · `numero_logradouro` · `cep` | 34% | 🏷️ | endereço — item 3; herdados do tributário via hook `saved()` |

**➕ sugeridos:** `tipo_ocupacao` (em ruínas / em construção — **a demanda que originou o estudo**), `topografia`, `pedologia`, `possui_calcada`, `possui_muro`.

### `quadras` — 378 linhas
`name` 🏷️ (item 45) · `setor_codigo` 🏷️ · **`codigo`** 🏷️ *(L1)* · FKs e `area_geo` ⚙️

### `bairros` — 15 linhas
`name` 🏷️ (item 48) · `setor` 🏷️ · **`codigo`** 🏷️ *(L1)* · `area_geo` ⚙️

### `logradouros` — 199 linhas
`name` 🏷️ (item 44) · **`codigo`** 🏷️ *(L1 — bloqueia o código composto do item 44/T1.3)* · `extensao_geo` ⚙️
**➕ sugeridos:** `lei_de_denominacao`, `tipo_logradouro`.

### `secoes_logradouro` — 2 linhas
`name` 🏷️ · `tipo_pavimentacao` 🏷️ *(L3)* · **`codigo`** 🏷️ *(T1.3, métrico)* · **`lado`** 🏷️ *(T1.3)* · `extensao_geo` e `logradouro_id` ⚙️

### `edificacoes` — 7.894 linhas → **fica só a base** *(decisão de 2026-08-01)*

**Nenhum campo 🏷️ sobrevive.** A contagem real provou que os 5 atributos vinham do sistema tributário, não do SIGWEB:

| Campo | Preench. | Fora da lista do sistema | Valores reais encontrados |
|---|---:|---:|---|
| `tp_construcao` | 4.747 | **100%** | `Alvenaria (0)` · `Madeira/Mista (1)` · `Alvenaria e Madeira/Mista (2)` — **com o código do fornecedor embutido** |
| `caracteristica_construcao` | 5.330 | **100%** | `Pavimento 1` · `Pavimento 2` · `Pavimento 2 - Parcial` … |
| `tipo` | 4.746 | ~100% | `Casa` (3.928) · `Outros` · `Loja` · `Telheiro` · `Galpão` |
| `estado_conservacao` | 4.712 | 18% | `Mau` · `Nova/Ótima` · `Nenhum` |
| `pavimento` | **7** | — | praticamente vazio — o dado real foi parar em `caracteristica_construcao` |

**Os 5 saem para ➕ campo customizado**, associados na integração pelo de/para. Restam apenas ⚙️: `lote_id`, `area_geo`, `sequential_id`, `code`, `geo`, `dados_customizados`.

> ✅ **PGV não é afetado** (verificado): o `PgvSimulacaoIptuService` usa só `SUM(edificacoes.area_geo)`, e `PgvDepreciacao`/`PgvAmostra` têm **colunas próprias** de `estado_conservacao`. Nada do módulo PGV é tocado.
>
> ⚠️ **Consequência para a demonstração:** os itens **43** ("Tipo de Edificação, Pavimento da Unidade") e **16** (Planta de Quadra lista "Tipo de Edificação de cada unidade") passam a depender de campos customizados **existirem** no município. Mitigação: **kit inicial de campos customizados** — seeder que cria o conjunto padrão ao provisionar um município novo, para que nenhuma prefeitura comece com a edificação vazia.

### `unidade_imobiliarias` — 4.693 linhas
Ver o **estudo** acima. Ficam 11 🏷️: `codigo_imovel_tributario`, `inscricao_imobiliaria`, `logradouro_nome`, `numero_imovel`, 🆕 `nome_edificio`, 🆕 `proprietario_nome`, 🆕 `proprietario_cpf_cnpj`, `area_total_edificacao`, `valor_venal_lote`, `valor_venal_edificacao`, `valor_total_imposto`. **Saem 9** colunas fiscais. `dados_tributarios`, `lote_id` e `proprietario_id` ⚙️.

### `lote_testadas` — 5 linhas
`tipo` 🏷️ (principal/secundária/lateral/fundos) · `comprimento`, `logradouro_id`, `secao_logradouro_id` ⚙️ *(secao 0% preenchido — lacuna G5)*

### `zonas` — 23 linhas
`name` · `sigla` · `rgb` 🏷️ (item 49) · **`codigo`** 🏷️ *(L1)* · `area_geo` ⚙️

### `perimetros_urbanos` (→ Distrito, T2.1) — 1 linha
`name` · `distrito` · `fill_color` 🏷️ (item 46) · **`codigo`** 🏷️ *(L1)* · `area_geo` ⚙️

### `setores_fiscais` — 4 linhas
`nome` · `descricao` 🏷️ (item 47) · **`codigo`** 🏷️ *(L1)* · `pgv_parametro_id` e `area_geo` ⚙️

### `meio_fios` — 3 linhas
`material` 🏷️ *(L3)* · `estado_conservacao` 🏷️ *(L3)* · `observacoes` 🏷️ · `extensao_geo` e `logradouro_id` ⚙️

---

## ✅ NÚCLEO FINAL — os campos que ficam

Lista definitiva dos campos 🏷️ (fixos, com rótulo white-label) do módulo imobiliário. Tudo o que **não** está aqui e não é ⚙️ base vira **campo customizado da prefeitura**.

| Entidade | Campos que ficam | Qtd |
|---|---|---:|
| **lotes** | `numero_lote` · `status_cadastro` · `ocupacao` · `situacao_quadra` · `foto_frontal` · `foto_lateral_esq` · `foto_lateral_dir` · `observacao` · `inconformidade_descricao` · `area_cadastrada` · `numero_predial_antigo` · `tipo_logradouro` · `logradouro` · `numero_logradouro` · `cep` | 15 |
| **unidade_imobiliarias** | `codigo_imovel_tributario` · `inscricao_imobiliaria` · `logradouro_nome` · `numero_imovel` · 🆕`nome_edificio` · 🆕`proprietario_nome` · 🆕`proprietario_cpf_cnpj` · `area_total_edificacao` · `valor_venal_lote` · `valor_venal_edificacao` · `valor_total_imposto` | 11 |
| **edificacoes** | *(nenhum — só base + campos customizados)* | 0 |
| **quadras** | `name` · `setor_codigo` · 🆕`codigo` | 3 |
| **bairros** | `name` · `setor` · 🆕`codigo` | 3 |
| **logradouros** | `name` · 🆕`codigo` | 2 |
| **secoes_logradouro** | `name` · `tipo_pavimentacao` · 🆕`codigo` · 🆕`lado` | 4 |
| **lote_testadas** | `tipo` (principal/secundária/lateral/fundos) | 1 |
| **zonas** | `name` · `sigla` · `rgb` · 🆕`codigo` | 4 |
| **perimetros_urbanos** | `name` · `distrito` · `fill_color` · 🆕`codigo` | 4 |
| **setores_fiscais** | `nome` · `descricao` · 🆕`codigo` | 3 |
| **meio_fios** | `material` · `estado_conservacao` · `observacoes` | 3 |
| | **TOTAL** | **53** |

**Campos com lista de valores governada pelo sistema** (chave imutável, rótulo renomeável) — apenas **6**:

| Campo | Valores (chaves) | Por que a lista é do sistema |
|---|---|---|
| `lotes.status_cadastro` | `nao_visitado` `coletado` `pendente` `inconformidade` | cor do mapa, produtividade, contrato do app |
| `lotes.ocupacao` | `baldio` `construido` | itens 42/51/60 cravam "(Baldio ou Construído)" |
| `lotes.situacao_quadra` | `meio_quadra` `esquina` `encravado` | itens 42/60 cravam os 3; o T2.2 sugere por PostGIS |
| `secoes_logradouro.tipo_pavimentacao` | `asfalto` `paralelepipedo` `concreto` `cascalho` `terra` `outro` | taxonomia de engenharia, **não** vem do tributário |
| `secoes_logradouro.lado` | `par` `impar` `ambos` | item 44; o T2.2 deriva por geometria |
| `lote_testadas.tipo` | `principal` `secundaria` `lateral` `fundos` | item 42 |
| `meio_fios.material` · `.estado_conservacao` | listas de engenharia | não vêm do tributário |

## Movimentações

| | Qtd | O quê |
|---|---:|---|
| ➕ Colunas que **saem** | **14** | 9 fiscais da unidade + 5 da edificação (`tipo`, `tp_construcao`, `caracteristica_construcao`, `estado_conservacao`, `pavimento`) |
| 🆕 Colunas a **criar** | **11** | 7 × `codigo` (L1) + `nome_edificio` + `proprietario_nome` + `proprietario_cpf_cnpj` + `lado` da seção |
| 🔧 A trazer para o `CampoDominio` | **3** | `tipo_pavimentacao`, `meio_fios.material`, `meio_fios.estado_conservacao` (L3) |

**Regra que emergiu do levantamento:** *campo alimentado pelo sistema tributário nunca é campo padrão do SIGWEB.* Foi o que reprovou as 9 colunas fiscais e os 5 atributos da edificação — em todos, o vocabulário era do fornecedor, não do sistema.

## Escopo deste documento

Cobre o **módulo imobiliário**, que é onde vivem os 124 itens da PoC. Os demais módulos (iluminação, arborização, rural, patrimônio, social, estoque, cemitério, chamados) merecem o mesmo tratamento, mas **não são críticos para a PoC** — mapeá-los fica para depois da demonstração, reusando esta mesma régua.

---

*Criado em 2026-07-31, revisado em 2026-08-01. Validar antes de virar migration.*
