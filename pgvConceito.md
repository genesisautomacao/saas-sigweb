# PGV — Conceito e Glossário do Módulo de Avaliação em Massa

> Documento de estudo do módulo **Gestão Tributária (PGV) / Planta Genérica de Valores**.
> Objetivo: entender como o motor calcula o valor dos imóveis e onde cada cadastro entra na conta.
> Cobre os itens 225–243 da PoC de Nova Esperança do Sul/RS (Sprint C1).

---

## 1. A ideia central

O motor responde uma pergunta: **quanto vale o m² de cada pedaço da cidade?** — e usa isso para simular o IPTU.

O princípio é que o valor do imóvel **cai conforme você se afasta de um ponto valorizado** (praça central, centro comercial). O motor mede essa queda com uma **reta** (regressão linear) e a projeta para toda a malha urbana.

```
Amostras + Pólo  ──►  REGRESSÃO       ──►  reta: valor = a + b·distância
                       (PgvRegressaoService)
                              │
Faces de quadra  ──►  CÁLCULO POR FACE  ──►  valor/m² gravado em cada face
                       (PgvFaceCalculoService)
                              │
Lotes + áreas    ──►  SIMULAÇÃO IPTU    ──►  tabela atual × simulado + somatório
                       (PgvSimulacaoIptuService)
```

---

## 2. Os três estágios do cálculo

### Estágio 1 — Regressão (`PgvRegressaoService`)

**Entradas:** as **amostras de mercado** (pontos onde se conhece o valor/m² real) + o **pólo valorizante** (ponto de referência).

**O que faz:**
1. Para cada amostra, o PostGIS calcula a **distância em metros até o pólo mais próximo** (`ST_Distance(::geography)`). Isso gera pares `(distância, valor)`.
2. Descarta as amostras marcadas como **espúrias**.
3. Roda **mínimos quadrados** em PHP puro e produz a reta:

   ```
   valor_m² = a + b · distância
   ```

   - **`a`** (intercepto) = valor/m² teórico junto ao pólo (distância 0).
   - **`b`** (inclinação) = quanto o m² varia por metro de afastamento — normalmente **negativo** (afastou, desvalorizou).
   - **`R²`** = qualidade do ajuste (0 a 1). Quão bem a reta explica os pontos.

**Remover espúria (item 232):** inverter o flag de uma amostra e **recalcular na hora**. É a ferramenta do avaliador para limpar dados ruins (venda entre parentes, erro de digitação). Ex. demonstrado no seed: tirar 1 outlier levou o R² de **0,70 → 0,996**.

---

### Estágio 2 — Cálculo por face (`PgvFaceCalculoService`)

As **faces de quadra** são os segmentos lineares das bordas das quadras (a frente para a rua). Cada face vira uma "célula de valor".

**O que faz (`recalcularTodas`):**
1. Pega a equação da regressão do Estágio 1.
2. Para cada face, acha o **pólo mais próximo** (KNN `<->`) e sua distância.
3. Aplica a reta + o **fator de homogeneização** global:

   ```
   valor_m²_face = (a + b · distância) × fatorGlobal
   ```

   Valores negativos são zerados.
4. Descobre o **setor fiscal** da face pelo ponto médio dela.
5. Grava na face: `valor_m2_calculado`, `distancia_polo`, `pgv_polo_id`, `setor_fiscal_id`.

É esse `valor_m2_calculado` que **colore as faces no mapa** por gradiente.

---

### Estágio 3 — Simulação de IPTU (`PgvSimulacaoIptuService`)

Traduz "valor/m²" em "quanto cada contribuinte pagaria". Para **cada lote:**

1. **Valor/m² do terreno:** a **face calculada mais próxima** do lote; se não houver, cai para o **parâmetro do setor fiscal**.
2. **Valor venal** = (área terreno × valor/m² terreno) + (área edificada × valor/m² edificação).
3. **IPTU simulado** = valor venal × **% do valor venal** (239) × **alíquota** (238).
4. **Limitador (240):** se o simulado ultrapassa o IPTU atual + X% de teto, é **capado** (proteção contra aumentos abruptos).
5. **IPTU atual:** vem do `valor_total_imposto` guardado em `unidade_imobiliarias.dados_tributarios` (integração tributária).

**Saída:** tabela linha-a-lote (venal, atual, simulado, Δ%, se foi capado) + os **totais** com a variação global. É o comparativo "arrecadação hoje × arrecadação com a PGV nova".

Os parâmetros (alíquota, %venal, limite) são digitados no modal do mapa em tempo de execução (item 243 — fórmula parametrizável).

---

## 3. Glossário dos itens do menu "Gestão Tributária (PGV)"

**Legenda de papel no cálculo:**
- 🟢 **entra direto** no motor
- 🔵 **cola espacial** (liga uma coisa à outra)
- ⚪ **referência/base** (cadastro do edital, ainda não multiplicado automaticamente)
- 🟡 **saída** (onde o resultado é gravado)

---

### 1. Parâmetros Base (PGV) — 🟢 `PgvParametro`
**O que é:** a tabela de valores fixos — `valor_m2_terreno`, `valor_m2_edificacao`, `fatores_adicionais`. É o simulador antigo (área × valor).

**Onde entra:** na **Simulação de IPTU** como **fallback** do valor/m² do terreno (quando o lote não tem face calculada por perto) e como **única fonte** hoje do valor/m² da **edificação**.
> Pense nele como o "plano B" do terreno e o "plano A" da construção.

---

### 2. Setores Fiscais — 🔵 `SetorFiscal`
**O que é:** polígonos que dividem a cidade em zonas de cálculo, cada um apontando para **um** Parâmetro Base (`pgv_parametro_id`).

**Onde entra:** é a **cola espacial**. Quando o motor precisa do parâmetro de um lote ou face, pergunta "em qual setor esse ponto cai?" via `ST_Intersects`. No cálculo de face, o setor é gravado na própria face (`setor_fiscal_id`). Sem setor, o fallback de parâmetro não é encontrado.

---

### 3. Valores Venais (Histórico) — 🟡 `LoteValorHistorico`
**O que é:** o registro de valor venal homologado **por lote e por ano** (`lote_id`, `ano_vigente`, `valor_terreno`, `valor_edificacao`, `valor_total`, `setor_fiscal_id`).

**Onde entra:** **não é entrada** — é o **arquivo de saída**. Quando você homologa uma avaliação no mapa (`homologarPgvAction`), o valor calculado é gravado aqui. Serve de trilha histórica/oficial e base de comparação entre exercícios.

---

### 4. Amostras de Mercado — 🟢 `PgvAmostra`
**O que é:** os pontos onde você **conhece** o valor/m² real (uma venda, uma avaliação). Cada amostra tem `geo` (ponto), `valor_m2`, atributos (idade, conservação, tipologia, áreas) e o flag `espuria`.

**Onde entra:** é a **matéria-prima da Regressão**. São os `(distância, valor)` que a reta tenta explicar. Marcar como **espúria** remove do cálculo e recalcula.

---

### 5. Pólos Valorizantes — 🟢 `PgvPolo`
**O que é:** os pontos de referência de valorização (praça, centro, orla). Só têm nome e `geo`.

**Onde entra:** definem a **variável X** de tudo. A "distância" que aparece na regressão e no cálculo de face é sempre **até o pólo mais próximo**. Sem pólo, não há distância e a equação não sai.

---

### 6. Tabela CUB — ⚪ `PgvCub`
**O que é:** o Custo Unitário Básico por `tipologia / tipo_estrutura / padrão` (coeficiente + valor/m²). Padrão sindical da construção (item 228).

**Onde entra:** **hoje, em lugar nenhum do cálculo automático.** É cadastro de referência que o avaliador consulta para justificar o valor da edificação. A simulação atual usa o `valor_m2_edificacao` do Parâmetro do Setor, **não** o CUB.
> Ponto de evolução: multiplicar `CUB × Depreciação × área` para obter o valor da construção.

---

### 7. Depreciação (Conservação × Idade) — ⚪ `PgvDepreciacao`
**O que é:** faixas de coeficiente por `estado_conservacao` × faixa de idade (item 229). Ex.: "Bom + 11 a 30 anos → 0,85".

**Onde entra:** **igual ao CUB — referência, ainda não multiplicada.** O par natural dela é o CUB: `valor_edificação = CUB × depreciação × área`. A infraestrutura (tabela + Resource) está pronta; falta o service consumi-la.

---

### 8. Faces de Quadra — 🟢🟡 `FaceQuadra`
**O que é:** os segmentos lineares das bordas das quadras (a frente para a rua). Cada face é uma "célula de valor" do território.

**Onde entra:** é o **coração da espacialização** — entrada e saída ao mesmo tempo:
- **Saída da Regressão:** o service mede a distância da face ao pólo, aplica `a + b·dist × fator` e grava `valor_m2_calculado`. É esse valor que **colore o mapa**.
- **Entrada da Simulação:** cada lote pega o valor da **face calculada mais próxima** como seu valor/m² de terreno.
> A face é a ponte: transforma "uma reta estatística" em "um valor concreto por quadra", e é dela que o IPTU de cada lote bebe.

---

### (bônus, sem menu próprio) Configuração PGV — `PgvConfiguracao`
1 registro por tenant. Guarda os **`fatores` de homogeneização** (multiplicador global aplicado no cálculo de face), o `percentual_valor_venal`, o `limite_aumento_iptu` e o `lote_paradigma_id`. É o "painel de ajustes" do motor, editável também no modal do mapa.

---

## 4. Quadro-resumo (uma frase por item)

| # | Item (menu) | Papel | Vira o quê no cálculo |
|---|---|---|---|
| 1 | Parâmetros Base | 🟢 | Valor/m² de fallback (terreno) e atual (edificação) |
| 2 | Setores Fiscais | 🔵 | Diz qual parâmetro vale em cada ponto |
| 3 | Valores Venais (Hist.) | 🟡 | Onde o resultado homologado é arquivado |
| 4 | Amostras de Mercado | 🟢 | Os pontos `(distância, valor)` da regressão |
| 5 | Pólos Valorizantes | 🟢 | A origem da "distância" (variável X) |
| 6 | Tabela CUB | ⚪ | Referência do custo da construção (não multiplicado ainda) |
| 7 | Depreciação | ⚪ | Referência do desgaste (não multiplicado ainda) |
| 8 | Faces de Quadra | 🟢🟡 | Recebe o valor da reta e alimenta o IPTU de cada lote |

**O fio condutor:** Pólo (5) + Amostras (4) → **reta** → Faces (8) recebem o valor/m² → cada Lote pega a face mais próxima → **IPTU simulado**. Setores (2) e Parâmetros (1) são a rede de segurança quando falta face. CUB (6) e Depreciação (7) são os dois cadastros prontos porém ainda "desconectados" do automático.

---

## 5. Onde os resultados aparecem ligados ao lote

### ✅ Visível hoje

1. **Simulador "Revisão da PGV" (fluxo antigo, no mapa)** — simulador simples (área × valor do setor), via `configurarPgvAction`:
   - Pinta cada lote no mapa por faixa de valor/variação (camada `previewPgvSource`).
   - Lista de revisão por lote com valor e **Δ%** (ícone de alerta quando variação > 5%).
   - "Salvar & Homologar" grava **um registro por lote** em `LoteValorHistorico`.

2. **Resource "Valores Venais (Histórico)" (menu #3)** — único lugar em formato de tabela onde se lê o valor final gravado por lote/ano. Filtrável por lote/setor/ano.

### ⚠️ Ainda NÃO desce ao lote (lacunas conhecidas)

| Local | O que falta |
|---|---|
| **Motor PGV (simulação por regressão)** | O painel só mostra **totais**. O service já retorna `linhas` por lote, mas o blade não renderiza a tabela e não grava em `LoteValorHistorico`. |
| **LoteResource (CRUD do lote)** | Sem coluna de valor venal e sem aba (RelationManager) de histórico PGV. |
| **Ficha lateral do lote (no mapa)** | `carregarFicha` mostra área, área construída, dados tributários, status e processos — **não** mostra valor venal / IPTU. |

**Resumo:** o elo direto lote → valor só está fechado no fluxo **antigo** (simulador simples → homologa → aparece em "Valores Venais"). O **motor novo** (regressão) para nos **totais** — calcula o valor de cada lote e mostra a soma, mas ainda não deixa navegar lote a lote nem persiste o resultado.

**Costuras para fechar (backlog de melhoria):**
1. Renderizar a tabela por-lote no modal de simulação do Motor PGV (dados já vêm prontos — só o blade).
2. Homologar a simulação da regressão em `LoteValorHistorico` (reaproveitando `homologarPgvAction`), unificando os dois fluxos.
3. Adicionar no `LoteResource` uma aba **"Valores Venais (PGV)"** (RelationManager) + coluna de valor venal vigente.

---

## 6. Limitação conhecida do modelo estatístico

A regressão é **univariável** (só valor × distância a um pólo). Atende plenamente o que a PoC pede e é demonstrável. Se a Prefeitura exigir um modelo **multivariável** (idade, padrão, testada etc. entrando juntos na regressão), isso é evolução do `PgvRegressaoService` — a arquitetura (amostras → equação → faces → IPTU) **não muda**.

---

## 7. Arquivos-chave (para consulta)

| Camada | Arquivo |
|---|---|
| Regressão | `app/Services/Pgv/PgvRegressaoService.php` |
| Cálculo de faces | `app/Services/Pgv/PgvFaceCalculoService.php` |
| Simulação IPTU | `app/Services/Pgv/PgvSimulacaoIptuService.php` |
| Relatório de faces | `app/Services/Pgv/PgvFaceExportService.php` |
| Orquestração no mapa | `app/Filament/Pages/Traits/HasPgvMotorActions.php` |
| UI do mapa (painel/modais) | `resources/views/filament/pages/mapa-fullscreen.blade.php` |
| Camada de faces no mapa | `public/js/gis/mapa-engine.js` (`pgvFacesLayer`) |
| Models | `app/Models/Pgv*.php`, `app/Models/FaceQuadra.php` |
| Dados de demonstração | `database/seeders/PgvExemploSeeder.php` |

**Seed de demonstração:**
```bash
PGV_SEED_TENANT=prefeitura-de-santa-cecilia php artisan db:seed --class=PgvExemploSeeder
```

---

*Documento gerado em 2026-07-01 como material de estudo interno do módulo PGV (Sprint C1).*
