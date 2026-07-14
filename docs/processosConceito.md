# processosConceito.md — Estudo do Motor de Processos Digitais (BPMN)

> Documento conceitual dos módulos de Processo Digital do TR de Nova Esperança do Sul/RS:
> **XII** (Motor BPMN genérico), **XIII** (Aprovação de Projeto), **XIV** (Habite-se / Atestado de Conclusão de Obra),
> **XVIII** (REURB Digital) e **XIX** (Visualização do progresso).
> Objetivo: fixar a arquitetura **antes** de escrever código. Ler antes de mexer no motor de processos.
>
> Companheiro de [pgvConceito.md](pgvConceito.md). Fonte de verdade de execução: [pendencias.md](pendencias.md).

---

## 1. A ideia central

> **Todos os "módulos de processo" do TR são o MESMO motor, configurado por fluxos BPMN diferentes. Não são sistemas separados — são linhas na tabela `bpmn_fluxos`.**

A PoC lista "Aprovação de Projeto" (XIII), "Habite-se" (XIV) e "REURB Digital" (XVIII) como se fossem módulos distintos. **Não são.** São três exemplos de fluxo do mesmo motor genérico descrito em XII. A prova está no próprio edital e no código:

1. **XIII e XIV são a MESMA lista, item a item:**

   | Aprovação de Projeto | Habite-se | Requisito idêntico |
   |---|---|---|
   | 127 | 138 | Solicitante vê processo + etapa atual |
   | 128 | 139 | Rascunho / envio posterior |
   | 129 | 140 | Correção só em fase reprovada |
   | 130 | 141 | Selecionar imóvel no mapa + dados |
   | 131 | 142 | Campo obrigatório ou não |
   | 132 | 143 | Analista: acesso de gerenciamento |
   | 133 | 144 | Analista: encaminhar p/ outro analista |
   | 134 | 145 | Analista: deixar sem analista |
   | 135 | 146 | Analista: ver processos de outros + etapa |
   | 136 | 147 | Analista: consultar por código/nome/telefone/email |
   | 137 | 148 | Analista: filtrar fluxo por campos |

   São **11 requisitos gêmeos**. O TR não descreve dois sistemas; descreve **o mesmo motor rodando dois fluxos**.

2. **XVIII (REURB) é um superconjunto** do mesmo motor + recursos transversais (swimlanes, timeline, anotação de PDF, pintar lote por etapa). Nenhuma mecânica de processo nova.

3. **O código já nasceu como motor único:** existe **um** `ProcessoDigital`, **um** par `BpmnFluxo`/`BpmnEtapa`, **um** resource do analista e **um** do cidadão. "Aprovação de Projeto", "Habite-se" e "REURB" seriam apenas **registros** em `bpmn_fluxos`.

**Decorrência prática:** os "3 módulos" do TR viram **3 fluxos-semente** (ver B17 em [pendencias.md](pendencias.md)). O trabalho real é **completar as capacidades genéricas do motor**, compartilhadas por todos os fluxos — nunca duplicar código por tipo de processo.

---

## 2. Não confundir os 4 sistemas do TR

Processo Digital é **um** de quatro sistemas independentes. Erro comum: misturar processo com chamado ou com manutenção.

| # | Sistema | Painel | Público | Estado | Itens TR |
|---|---|---|---|---|---|
| 1 | **Processo Digital** (este documento) | `cidadao` (abre) + `app` (analista) | Cidadão + Prefeitura | ✅ Existe e funciona | 120–148, 205–224 |
| 2 | **Solicitação de Manutenção** (poste/árvore → OS) | `app` | Só prefeitura | ✅ Existe | 060–090 |
| 3 | **App de Chamados do Cidadão** (mato, buraco, árvore caída) | — | Cidadão | ❌ Não arquitetado | 149–174 |
| 4 | **App dos Fiscais / Recadastramento (CTM)** | API mobile | Fiscais | ✅ Existe, a aprimorar | 190–204 |

> Processo Digital (BPMN, formulários customizados, tramitação) **≠** Chamado (reclamação genérica) **≠** Solicitação de Manutenção (asset poste/árvore).

---

## 3. Arquitetura atual (o que já existe)

### 3.1 Modelos

| Model | Tabela | Papel |
|---|---|---|
| `BpmnFluxo` | `bpmn_fluxos` | O tipo de processo. Campos: `nome`, `descricao`, `xml_diagrama` (desenho BPMN), `ativo`. `hasMany(etapas)`. |
| `BpmnEtapa` | (etapas) | Uma fase do fluxo. Campos: `bpmn_fluxo_id`, `nome`, `codigo_etapa_bpmn`, `cor_mapa`, `tempo_medio_minutos`, `perfis_autorizados` (JSON), `campos_formulario` (JSON — o formulário construído). |
| `ProcessoDigital` | `processos_digitais` | Uma instância aberta pelo cidadão. Campos: `codigo_processo`, `requerente_id` (User), `lote_id`, `bpmn_fluxo_id`, `etapa_atual_id`, `status`, `dados_formulario` (JSON — respostas). |
| `ProcessoTramitacao` | `processo_tramitacoes` | O histórico de movimentação. Campos: `processo_digital_id`, `etapa_origem_id`, `etapa_destino_id`, `usuario_id`, `parecer`, `status_parecer`. |
| `ProcessoAnexo` | (anexos) | Documento anexado. Campos: `processo_digital_id`, `etapa_id`, `usuario_id`, `nome_arquivo`, `caminho_arquivo`, `tipo_anexo`. |

**Estados do processo (`ProcessoDigital.status`):** `rascunho` · `em_andamento` · `pendente_correcao` · `concluido` · `cancelado`.
**Parecer da tramitação (`status_parecer`):** `aprovado` · `encaminhado` · `reprovado` · `concluido`.
**Módulo tenant:** `processos` (via `HasTenantModule`).

### 3.2 Configuração do fluxo (prefeitura) — `BpmnFluxoResource` (painel `app`)

- Form: identificação (`nome`, `ativo`, `descricao`) + **editor visual BPMN** (`ViewField` → `filament.forms.components.bpmn-modeler`).
- Listagem: `ToggleColumn` de `ativo` (liga/desliga o fluxo — item 123/208).
- **`EtapasRelationManager`** (as etapas do fluxo):
  - **Aba "Regras e Mapa":** `nome`, `cor_mapa` (ColorPicker — usado no mapa do progresso), `tempo_medio_minutos` (SLA — item 124/209), `perfis_autorizados` (multi-select: analista/fiscal/procuradoria/secretario).
  - **Aba "Formulário da Etapa":** `Builder` com **4 blocos** = os 4 tipos exigidos (125/210):
    1. `texto` — Campo de texto simples;
    2. `checkbox` — Múltipla escolha (`TagsInput` de opções);
    3. `mapa` — Seleção de posição no mapa;
    4. `documento` — Campo com máscara CPF **ou** telefone.
  - Todo bloco tem toggle `obrigatorio` (item 131/142).

### 3.3 Abertura pelo cidadão — `Cidadao\ProcessoDigitalResource` (painel `cidadao`)

- **Wizard de 3 passos:**
  1. **Tipo de Pedido:** `Select` de `bpmn_fluxo_id` (só fluxos `ativo`).
  2. **Localização (Mapa):** `ViewField` `filament.forms.components.mapa-selecao-lote` → clica no lote → grava `lote_id`.
  3. **Documentação:** renderiza dinamicamente os `campos_formulario` **da 1ª etapa** do fluxo escolhido + upload de anexos (`anexos_temporarios` → `ProcessoAnexo`).
- Tabela: só os processos do próprio cidadão (`requerente_id = Auth::id()`); colunas protocolo, serviço, fase atual, status; ação "Corrigir Processo" quando `pendente_correcao`.
- `EditProcessoDigital`: ao salvar um processo `pendente_correcao`, devolve para o analista (`→ em_andamento`) e grava tramitação de reenvio. Mostra aviso vermelho com o motivo do parecer reprovado.

### 3.4 Análise pela prefeitura — `ProcessoDigitalResource` (painel `app`)

- "Caixa de Entrada": tabela filtrada por tenant, `status != rascunho`.
- Colunas: protocolo, solicitante (searchable por `users.name`/`users.email`), serviço, fase atual, status, aberto em. Filtro por serviço (fluxo).
- **Ação "Tramitar / Julgar":** decisão (`aprovado`/`encaminhado`/`reprovado`/`concluido`) + etapa destino + **encaminhar para analista específico** (`usuario_destino_id`, em branco = fila geral) + parecer. Grava `ProcessoTramitacao` e atualiza a capa do processo.
- `ViewProcessoDigital`: infolist com dados do solicitante (nome + email), respostas do formulário (`KeyValueEntry`), lote vinculado e anexos (links).

---

## 4. Matriz de capacidades (todos os 120–148 + 205–224 consolidados)

Requisitos agrupados pela **capacidade genérica** que os atende. Legenda: ✅ pronto · 🟡 parcial · ❌ a fazer.

### A. Modelagem do fluxo (config-time, prefeitura)

| Capacidade | Itens TR | Status | Nota |
|---|---|---|---|
| Editor BPMN visual | 120, 122, 205 | ✅ | `bpmn-modeler` |
| Ativar/desativar fluxo | 123, 208 | ✅ | ToggleColumn (A12) |
| Tempo médio por etapa (user task) | 124, 209 | ✅ | `tempo_medio_minutos` |
| Formulário 4 tipos | 125, 210 | 🟡 | texto/checkbox/documento ok; **"mapa simples" ainda renderiza como TextInput no cidadão** |
| Campo obrigatório ou não | 131, 142 | ✅ | toggle `obrigatorio` |
| Gerenciar permissões do formulário por etapa | 126, 211 | 🟡 | construtor por etapa existe; falta camada de permissão de acesso ao formulário por etapa |
| Associar perfis **ao fluxo** | 121, 207 | 🟡 | hoje é **por etapa** (`perfis_autorizados`), não no nível do fluxo |
| Organizar por setor/departamento (swimlanes) | 206 | ❌ | **B18** |

### B. Lado do solicitante (cidadão)

| Capacidade | Itens TR | Status | Nota |
|---|---|---|---|
| Ver processo + etapa atual | 127, 138, 215 | ✅ | |
| Rascunho / envio posterior | 128, 139 | ✅ | status `rascunho` |
| Correção só em fase reprovada | 129, 140, 220 | 🟡 | **B16** — lógica de estado existe, falta **travar os campos** |
| Selecionar imóvel no mapa + dados | 130, 141, 221 | 🟡 | **B14** — seleção existe; falta exibir cadastro/inscrição/localização. REURB (221) pede **+ loteamento, quadra, nº do lote** |
| Anexar documentos | 213 | ✅ | `ProcessoAnexo` |
| Histórico de fases com interações | 216 | 🟡 | dados em `ProcessoTramitacao`; falta **timeline na UI** |
| Anotação em PDF com cópia | 222 | ❌ | **B15** |

### C. Lado do analista (gestão)

| Capacidade | Itens TR | Status | Nota |
|---|---|---|---|
| Acesso de gerenciamento (caixa de entrada) | 132, 143 | ✅ | |
| Encaminhar p/ outro analista / pessoa específica | 133, 144, 212 | ✅ | `usuario_destino_id` |
| Deixar sem analista (fila geral) | 134, 145 | ✅ | em branco = fila |
| Ver processos de outros + etapa | 135, 146 | ✅ | tabela mostra todos |
| Ver "processos comigo" | 217 | 🟡 | falta filtro "meus" |
| Ver "minha fila não atribuída" | 218 | 🟡 | falta filtro fila da etapa |
| Consultar por código/nome/**telefone**/email | 136, 147, 219 | 🟡 | código+nome+email ok; **telefone/CPF não existem no `User`** |
| Ver dados do solicitante (nome/email/**tel/CPF**) | 214 | 🟡 | nome+email ok; **tel/CPF faltam** |
| Filtrar fluxo por campos | 137, 148 | 🟡 | filtro por fluxo existe; "por campos do fluxo" não |

### D. Visualização de progresso (XIX)

| Capacidade | Itens TR | Status | Nota |
|---|---|---|---|
| Lotes pintados por etapa no mapa | 223 | ❌ | **B11** — a cor já existe em `BpmnEtapa.cor_mapa` |
| Dashboards personalizáveis tempo real | 224 | ❌ | **B11** |

---

## 5. Lacunas conhecidas / decisões de arquitetura

1. ✅ **Dados do requerente (telefone + CPF).** → **Decisão #1 resolvida** (User ↔ Pessoa via `pessoas.user_id`). Ver §9.1.
2. ✅ **Formulário por etapa — quem preenche?** → **Decisão #2 resolvida** (fluxo híbrido: cada etapa tem `executor` + form próprio). Ver §9.2.
3. ✅ **"Mapa simples" como tipo de campo (125/210).** → **Decisão #3 resolvida** (seletor de ponto real). Ver §9.3.
4. ✅ **Perfis no nível do fluxo (121/207).** → **Decisão #4 resolvida** (`BpmnFluxo.perfis_autorizados`). Ver §9.4.
5. ✅ **Seleção de imóvel sempre obrigatória?** → **Decisão #5 resolvida** (configurável por fluxo via `BpmnFluxo.exige_imovel`). Ver §9.5.
6. ✅ **Setor/departamento — texto livre ou cadastro?** → **Decisão #6 resolvida** (entidade `Setor` gerenciável). Ver §9.6.

---

## 6. Onde os resultados aparecem

- **Cidadão:** "Meus Processos" (painel `cidadao`) — acompanha protocolo, fase atual e status; corrige quando reprovado.
- **Analista:** "Caixa de Entrada (Processos)" (painel `app`) — analisa, tramita, encaminha, defere.
- **Mapa:** progresso REURB (223) pintará os lotes pela `cor_mapa` da etapa atual (a fazer — B11).
- **Dashboard:** situação em tempo real (224) — a fazer (B11).

---

## 7. Plano proposto (motor único)

1. **Não criar módulos separados.** Manter `ProcessoDigital` + `BpmnFluxo` como motor único.
2. **B17** materializa os "3 módulos" do TR = seeds de fluxos (Aprovação de Projeto, Habite-se, REURB).
3. Completar as capacidades genéricas em ondas:
   **B16** (trava de correção) → **B14** (dados do imóvel) → dados do requerente (tel/CPF) → filtros do analista (217/218/136) → timeline (216) → **B18** (swimlanes) → **B11** (progresso REURB) → **B15** (anotação PDF).

> As decisões da seção 5 precisam ser fechadas antes de iniciar. As respostas serão registradas aqui e refletidas em [pendencias.md](pendencias.md).

---

## 8. Decisões tomadas

| # | Decisão | Escolha | Data |
|---|---|---|---|
| 1 | Telefone/CPF do requerente | **Vincular User ↔ Pessoa** via `pessoas.user_id` (Pessoa = registro canônico tenant-scoped). Ver §9.1. | 2026-07-01 |
| 2 | Quem preenche o formulário de cada etapa | **Fluxo híbrido:** cada etapa declara `executor` (solicitante\|analista) e tem seu próprio formulário. Ver §9.2. | 2026-07-01 |
| 3 | "Mapa simples" como campo real de coordenada | **Sim** — bloco `mapa` vira seletor de ponto real (lat/lon em `dados_formulario`), distinto do seletor de imóvel. Ver §9.3. | 2026-07-01 |
| 4 | Perfis no nível do fluxo | **Sim** — `BpmnFluxo.perfis_autorizados` (quem abre/vê o fluxo), além dos perfis por etapa. Ver §9.4. | 2026-07-01 |
| 5 | Seleção de imóvel | **`BpmnFluxo.modo_imovel`** (enum `nenhum`\|`mapa`\|`busca`) — refinado no §11. Ver §9.5. | 2026-07-01 |
| 6 | Setor/departamento | **Cadastro `Setor`** com **usuários** (§11); acesso à etapa = usuários do setor. Ver §9.6. | 2026-07-01 |

---

## 9. Desenho detalhado das decisões

### 9.1 User ↔ Pessoa (decisão #1)

**Regra:** `Pessoa` é o **registro canônico da pessoa** (tenant-scoped; já tem `cpf`, `telefone`, `rg`, `nis`, etc.). `User` é apenas a **conta de acesso** (multi-tenant via `tenant_user`). O elo é **`pessoas.user_id`** (FK nullable) — **não** `users.pessoa_id`.

**Por quê `pessoas.user_id` e não o inverso:**
- `User` é multi-tenant; `users.pessoa_id` ficaria ambíguo (qual Pessoa/tenant?).
- `Pessoa` já carrega `tenant_id` → o escopo resolve naturalmente ("nesta prefeitura, este login é esta pessoa").
- Unifica com o Módulo Social: cidadão que abre processo **e** é pessoa do cadastro social = o mesmo registro.

**Migration:** `pessoas` + `user_id` (FK nullable → `users`, `nullOnDelete`), índice único parcial `(tenant_id, user_id)`.

**`RegisterCidadao` (painel cidadão):**
1. Adicionar campos `cpf` e `telefone` (com máscara) ao formulário de cadastro.
2. No `handleRegistration`: cria o `User`, faz `attach` do tenant, e **cria/vincula** uma `Pessoa` no tenant escolhido (dedup por `cpf` dentro do tenant → se já existe, só seta `user_id`; senão cria com `nome`+`cpf`+`telefone`+`user_id`).
3. ⚠️ O cadastro roda **sem tenant no contexto Filament** → criar a `Pessoa` com `tenant_id` **explícito** (o selecionado), contornando a injeção automática do `BelongsToTenant`.

**Consumo no analista (214, 136, 147, 219):** resolver a `Pessoa` via `user_id + tenant_id` atual para **exibir** (nome/email do User + CPF/telefone da Pessoa) e **pesquisar** (join em `pessoas`).

### 9.2 Fluxo híbrido (decisão #2)

**Princípio:** cada etapa é **auto-descritiva** — declara **quem age** e **o que pede**. O roteamento entre etapas já é arbitrário (a ação "Tramitar" do analista escolhe qualquer etapa destino + opcionalmente um usuário específico). Falta tornar a etapa consciente do seu executor e persistir respostas por etapa.

**`BpmnEtapa` ganha:**
- `executor` — enum `solicitante` | `analista` (quem preenche/age nesta etapa). Default `analista`; a 1ª etapa é `solicitante`.
- `setor_departamento` — string; setor dono da etapa (serve também às swimlanes, item 206 / B18).
- `ordem` — int; sequência padrão (o roteamento real continua livre via tramitação).
- (já tem) `campos_formulario` (formulário **daquela** etapa), `perfis_autorizados` (qual setor analisa), `cor_mapa`, `tempo_medio_minutos`.

**Nova tabela `processo_respostas`:** `id`, `tenant_id`, `processo_digital_id`, `bpmn_etapa_id`, `usuario_id`, `dados` (JSON), timestamps. Cada envio de formulário (do cidadão ou do analista) grava **uma linha**, sem sobrescrever as anteriores. Alimenta o histórico (216) e o "quem preencheu o quê". (`ProcessoDigital.dados_formulario` fica como *snapshot* da abertura / compatibilidade.)

**Ciclo de vida:**
1. **Abertura** — cidadão preenche o formulário da 1ª etapa (`executor=solicitante`) → grava `processo_respostas` daquela etapa → status `em_andamento`, cai na fila do setor da 2ª etapa.
2. **Setor A** — analista abre, **preenche o formulário da etapa dele** (se houver), dá parecer, e **tramita** para a etapa destino (outro setor **ou** de volta ao cidadão).
3. **Volta ao cidadão** — etapa atual com `executor=solicitante` → painel do cidadão renderiza **o formulário da etapa atual** (não mais sempre o da 1ª). Status `aguardando_solicitante` (ou `pendente_correcao` se foi reprovação — 129/220, aí ele edita **só** aquela etapa).
4. **Conclusão** — analista escolhe "Deferir/Concluir" → status `concluido`.

**Estados (`ProcessoDigital.status`) refinados:**
`rascunho` · `em_andamento` (com um setor/analista) · `aguardando_solicitante` (na mão do cidadão, etapa normal) · `pendente_correcao` (cidadão deve corrigir etapa reprovada) · `concluido` · `cancelado`.

**Exemplo (Aprovação de Projeto):**
```
Etapa 1  [solicitante]  Abertura — dados do projeto + anexos
Etapa 2  [analista/Obras]      Análise técnica (form: checklist de vistoria)
Etapa 3  [analista/Procuradoria] Parecer jurídico
Etapa 4  [solicitante]  Ajustes solicitados (form: reenvio de plantas)  ← volta ao cidadão
Etapa 5  [analista/Secretário]  Deferimento → Alvará
```
Cada seta é uma tramitação; cada caixa `[solicitante]` renderiza seu form no painel do cidadão; cada `[analista/Setor]` cai na fila daquele setor.

**Renderização do formulário (mudança-chave):** tanto no painel do cidadão quanto no do analista, renderizar os `campos_formulario` **da `etapa_atual`** (hoje o cidadão só vê o da 1ª etapa). O `executor` da etapa decide em qual painel o form fica editável.

### 9.3 Campo "mapa simples" real (decisão #3)

O bloco `mapa` do construtor de formulário (125/210) passa a ser um **seletor de ponto de verdade**: mini-mapa clicável que grava `{lat, lon}` em `dados_formulario`/`processo_respostas`. É **distinto** da seleção do imóvel (130/221, que grava `lote_id` — ver B14). Hoje o bloco `mapa` renderiza como `TextInput` no cidadão — será trocado por um componente de coordenada.

### 9.4 Perfis no nível do fluxo (decisão #4)

`BpmnFluxo` ganha `perfis_autorizados` (JSON) — controla **quem pode abrir/ver o fluxo** (121/207). A abertura pelo cidadão passa a filtrar os fluxos também por perfil, além de `ativo`. Os `perfis_autorizados` **por etapa** continuam definindo **quem analisa** cada fase. São camadas complementares.

### 9.5 Seleção de imóvel configurável por fluxo (decisão #5)

`BpmnFluxo` ganha `exige_imovel` (bool, default `true`). O passo "Localização (Mapa)" do wizard do cidadão só aparece/é obrigatório quando `exige_imovel = true`. Aprovação de Projeto, Habite-se e REURB exigem lote; fluxos genéricos (sem imóvel) podem dispensar. Quando `true`, ao selecionar o lote traz-se cadastro imobiliário / inscrição / localização (130) e, no REURB, também loteamento / quadra / nº do lote (221) — ver B14.

### 9.6 Cadastro `Setor` (decisão #6)

Nova entidade **`Setor`** (`BelongsToTenant`): `id`, `tenant_id`, `nome`, `cor` (para swimlanes), timestamps. A etapa ganha `setor_id` (FK → `setores`), substituindo o texto livre `setor_departamento` do §9.2. Benefícios: swimlanes (206/B18) e filtros por setor consistentes, e base para ligar setor ↔ perfis. CRUD modal simples no painel `app`, permissão `gerenciar_setores` + Policy (padrão dos cadastros auxiliares).

---

## 10. Plano de execução

> Arquitetura fixada (§8/§9). Início pela **Fundação Híbrida** (decisão de sequência) — sem ela os demais itens não encaixam. Ao concluir cada onda, atualizar [pendencias.md](pendencias.md).

### Onda 0 — Fundação Híbrida (base do motor) · ✅ Concluído em 2026-07-02
Migrations + models + registro. Nada de UI de fluxo ainda.
1. **`pessoas.user_id`** (FK nullable) + ajuste no `RegisterCidadao` (pede CPF/telefone; cria/vincula `Pessoa` no tenant, dedup por CPF; `tenant_id` explícito) — §9.1.
2. **`BpmnFluxo`**: `+ perfis_autorizados` (JSON), `+ exige_imovel` (bool) — §9.4/§9.5.
3. **Entidade `Setor`** (migration + model + Resource CRUD + permissão) — §9.6.
4. **`BpmnEtapa`**: `+ executor` (enum solicitante|analista), `+ setor_id` (FK), `+ ordem` — §9.2. Expor no `EtapasRelationManager`.
5. **Tabela `processo_respostas`** (+ model) — respostas por etapa — §9.2.
6. **Estados** do `ProcessoDigital`: incluir `aguardando_solicitante` — §9.2.

### Onda 1 — Formulário da etapa atual + trava (o coração do híbrido) · ✅ Concluído em 2026-07-02
7. ✅ Renderizar `campos_formulario` **da `etapa_atual`** (cidadão **e** analista), gravando em `processo_respostas`; `executor` decide onde é editável — §9.2.
   - Cidadão: `ProcessoDigitalResource` renderiza a etapa atual (abertura = 1ª); `Create/EditProcessoDigital` gravam via `ProcessoFormService::salvarRespostaEtapa`.
   - Analista: ação **"Preencher Etapa"** (visível quando `etapa_atual.executor='analista'`); `tramitar` seta `aguardando_solicitante` quando o destino é etapa do solicitante.
   - `ViewProcessoDigital` mostra as respostas **agrupadas por etapa**.
8. ✅ **B16** — trava de correção: campos editáveis só quando o status ∈ `{rascunho, pendente_correcao, aguardando_solicitante}` (129/140/220).
9. ✅ **Campo `mapa` real** (125/210) — `ProcessoFormService` renderiza o bloco `mapa` como `ViewField` `filament.forms.components.mapa-ponto` (OpenLayers, grava `{lat,lon}`) — §9.3.

> **Serviço central:** `App\Services\Processo\ProcessoFormService` — `camposDaEtapa(etapa, disabled)` (4 tipos, respostas namespaced `dados_formulario.etapa_<id>.<slug>`) + `salvarRespostaEtapa()` (histórico em `processo_respostas`).

### Onda 2 — Imóvel e requerente · ✅ Concluído em 2026-07-02
10. ✅ **B14** — seleção de imóvel com dados (cadastro/inscrição/localização + loteamento/quadra/nº) respeitando `exige_imovel`.
    - `ProcessoFormService::dadosImovel()`/`dadosImovelHtml()` resolvem via `lote.quadra.loteamento` + `lote.unidadesImobiliarias`.
    - Cidadão: Placeholder reativo "Dados do Imóvel" no passo do mapa (entangle `.live` → feedback instantâneo).
    - Analista: `ViewProcessoDigital` seção "Imóvel Vinculado".
11. ✅ **Dados do requerente** no analista — `ViewProcessoDigital` exibe CPF/telefone (via `User::pessoaNoTenant`) e a tabela permite **buscar por telefone** (além de código/nome/email) — itens 214/136/147/219.

### Onda 3 — Gestão do analista e histórico · ✅ Concluído em 2026-07-02
12. ✅ Filtros do analista: "Meus processos" (217), "Fila geral / não atribuídos" (218), por Setor da etapa e "buscar nas respostas do formulário" (137/148). Novo campo **`processos_digitais.analista_id`** (dono atual) — o `tramitar` passa a persistir o encaminhamento (133/144/212); coluna "Analista" na tabela.
13. ✅ **Timeline** do histórico de fases (216) — seção "Histórico de Tramitação" no `ViewProcessoDigital` a partir de `processo_tramitacoes`.

### Onda 4 — Setores, progresso e anexos avançados · ✅ Concluído em 2026-07-02
14. ✅ **B18** — swimlanes: `EtapasRelationManager` agrupa por `Setor` (+ colunas executor/setor/ordem); filtro por setor na Caixa de Entrada (206).
15. ✅ **B17** — `ProcessoFluxoExemploSeeder`: cria Setores + fluxos **Aprovação de Projeto**, **Habite-se/Atestado** e **REURB** (motor híbrido completo). Idempotente. `PROCESSO_SEED_TENANT=<slug> php artisan db:seed --class=ProcessoFluxoExemploSeeder`.
16. ✅ **B11** — progresso: `MapDataController` já expõe `processo_etapa_cor/nome` por lote (ampliado p/ todos os status em trânsito) + toggle "Colorir por Etapa" no mapa (223, já existente); nova **`ReurbProgressoPage`** com cards + gráfico por etapa + poll 60s (224, permissão `view_processos_progresso`).
17. ✅ **B15** — anotação em PDF: editor `processos/anotar-pdf` (PDF.js + Fabric.js + jsPDF) via `ProcessoAnexoController`; salva **cópia versionada** (`processo_anexos.versao` + `anexo_origem_id`), sem sobrescrever o original (222). Botão "✏️ Anotar" nos PDFs do `ViewProcessoDigital`.

> **Nota de conformidade:** ao final, XIII/XIV/XVIII deixam de ser "módulos" e passam a ser fluxos-semente (item 15) do motor único; XIX é atendido pela Onda 4 (item 16).
>
> ⚠️ **Validação de UI pendente:** todas as ondas foram validadas por `php -l` + boot dos painéis + testes de query/serviço com bootstrap. As interações de navegador (wizard do cidadão, mapa reativo, dashboard Chart.js, editor de PDF) precisam ser conferidas clicando na aplicação.

---

## 11. Refinamentos — Rodada 1 (2026-07-02)

> Ajustes solicitados após o primeiro estudo. Substituem partes das decisões #5/#6.

1. **Setor com usuários (obrigatório).** `Setor` ⇄ `User` (pivot `setor_user`). O `SetorResource` exige selecionar os usuários do setor (escopo do tenant). **Os usuários do setor são quem pode ver/agir nas etapas daquele setor.** Um usuário pode pertencer a vários setores (`User::setores()`).

2. **`BpmnFluxo.modo_imovel`** (substitui `exige_imovel`): enum **`nenhum`** (sem imóvel) · **`mapa`** (cidadão clica no mapa) · **`busca`** (Select pesquisável por **nº do lote** ou **código tributário**, `unidadesImobiliarias.codigo_imovel_tributario`). O passo do imóvel no wizard do cidadão é montado por closure conforme o modo (um único componente `lote_id` por vez).

3. **Editor BPMN em accordion fechado** — a seção "Editor Visual BPMN" do `BpmnFluxoResource` é `->collapsible()->collapsed()`.

4. **Setor condicional na etapa** — `BpmnEtapa.executor` é `->live()`; o select de Setor só aparece (e é obrigatório) quando `executor = analista`.

5. **`BpmnEtapa.usuarios_autorizados`** (substitui `perfis_autorizados` da etapa): Select reativo com os **usuários do setor escolhido**. **Vazio = todos os usuários do setor** podem ver/julgar; **preenchido = apenas os escolhidos**. (O `perfis_autorizados` do **fluxo** — quem pode abrir — foi mantido.)

6. **Acesso à Caixa de Entrada por setor** — `ProcessoDigitalResource::modifyQueryUsing` filtra: o analista só vê processos onde é o `analista_id` **ou** a etapa atual é de um setor dele **e** (`usuarios_autorizados` vazio ou ele está na lista). **Bypass:** Master/Manager (via `Gate::before`) ou a nova permissão **`view_todos_processos`** veem tudo.

7. **Ordem do menu "Processos Digitais":** Setores (1) → Fluxos BPMN (2) → Caixa de Entrada (3) → Progresso (4).

**Migrations:** `setor_user`, `bpmn_fluxos.modo_imovel` (troca `exige_imovel`), `bpmn_etapas.usuarios_autorizados` (troca `perfis_autorizados`). **Permissão:** `view_todos_processos`. **Seeder** atualizado (`modo_imovel`, sem perfis de etapa).

---

## 12. Refinamentos — Rodada 2 (2026-07-02)

1. **Usuários do setor só com papel.** O select de usuários do `SetorResource` filtra `whereHas('tenants', tenant)->whereHas('roles')` — exclui cidadãos (que não têm papel) e outros tenants.

2. **Removido `perfis_autorizados` do fluxo** (não fazia sentido / perfis inexistentes). O campo saiu do `BpmnFluxoResource`.

3. **Campo de formulário `arquivo` (upload nomeado).** 5º bloco do construtor de etapa (ex.: "Foto do CPF", "Escritura"). Renderiza `FileUpload`; no salvamento vira `ProcessoAnexo` (tipo `formulario`) — aparece na lista de documentos e pode ser anotado. **Os anexos soltos (`anexos_temporarios`) foram removidos** do formulário do cidadão.

4. **Fluxos do cidadão escopados ao tenant.** A abertura de processo lista só os fluxos ativos **da prefeitura do cidadão** (antes mostrava de todos os tenants → duplicados).

5. **Telefone híbrido (8/9 dígitos).** Máscara dinâmica `RawJs` no campo `documento` (telefone) e no `RegisterCidadao`: `$input.length > 14 ? '(99) 99999-9999' : '(99) 9999-9999'`.

6. **Botão "Enviar para Análise" só na última etapa.** O `Wizard->submitAction()` (HtmlString `type=submit`) + `getFormActions() => []` nas páginas Create/Edit do cidadão. Antes o botão aparecia antes do formulário exigido.

7. **Auto-avanço de etapa (`ProcessoFormService::avancarProximaEtapa`).** Quando o **solicitante** envia (abertura, correção ou resposta), o processo avança para a **próxima etapa por `ordem`**: se a próxima é do analista → `em_andamento` (fila do setor); se do solicitante → `aguardando_solicitante`; se não há próxima → `concluido`. Registra tramitação automática. Chamado no `afterCreate`/`afterSave` do cidadão.

> **Acesso do analista (esclarecimento do item 7):** a `ProcessoDigitalPolicy::viewAny` exige `view_processos_digitais` (abre a Caixa de Entrada). A **visibilidade dos processos** é filtrada por setor (§11.6): o analista só vê processos cuja etapa atual seja de um **setor do qual ele é membro** (ou atribuídos a ele), salvo Master/Manager ou `view_todos_processos`. Ou seja: para o "Pedro" ver o processo, ele precisa **(a)** ter `view_processos_digitais` no papel **e (b)** ser membro do setor responsável pela etapa atual — e o processo precisa ter **avançado** até essa etapa (agora automático).

---

## 13. Refinamentos — Rodada 3 (2026-07-02)

1. **"Ver todos os processos" — onde:** `ProcessoDigitalResource::modifyQueryUsing` → `if ($user->hasRole('Master') || $user->hasPermissionTo('view_todos_processos')) return $query;`. Usa **`hasPermissionTo()`** (não `can()`) de propósito: `can()` passa pelo `Gate::before`, que dá bypass total ao **Manager** — então tirar a permissão do Gerente não o filtraria. Assim: **Master** sempre vê tudo; **Manager/qualquer** só vê tudo se tiver `view_todos_processos` explícito; senão é filtrado por setor. As 3 permissões do módulo (`gerenciar_setores`, `view_todos_processos`, `view_processos_progresso`) estão na CAIXA 16 da `RoleResource`.

2. **Ação única "Avançar Processo"** (substitui "Preencher Etapa" + "Tramitar/Julgar"). Um só botão de ação por linha (+ "Ver Processo"). O modal contém: o **formulário da própria etapa** do analista (checklist/uploads — opcional aqui, via `camposDaEtapa(..., forcarOpcional: true)`), a **Decisão** (Aprovar/Reprovar) e o **Parecer Técnico** (obrigatório).

3. **Roteamento (`ProcessoFormService::julgarEtapa`):**
   - **Aprovar** → avança para a **próxima etapa por `ordem`** (ou `concluido`). O analista **não escolhe** a etapa.
   - **Reprovar** → ver §14 (o analista escolhe o destino + marcador de retorno).
   - Removidos "Encaminhar para analista específico" (o processo já cai no setor certo). O parecer é mantido para justificar a decisão. Uploads da etapa do analista também viram `ProcessoAnexo`.

---

## 14. Refinamentos — Rodada 4 (2026-07-02): reprova direcionada + retorno ao reprovador

**Problema:** "reprovar volta para a etapa anterior por `ordem`" estava errado. Ex.: Pagamentos (etapa 2) aprova → Engenharia (etapa 3) confere a documentação do cidadão; se Engenharia reprova, a documentação é problema do **cidadão**, não de Pagamentos. Voltar para Pagamentos (etapa anterior) não faz sentido.

**Solução (2 partes):**

1. **Reprova direcionada.** Na ação "Avançar Processo", ao escolher **Reprovar**, aparece o select **"Retornar para qual etapa?"** (todas as etapas do fluxo, exceto a atual). O analista escolhe para onde devolver (ex.: de volta ao **cidadão** corrigir a documentação, pulando Pagamentos).

2. **Marcador de retorno (`processos_digitais.etapa_retorno_id`).** Ao reprovar, grava-se `etapa_retorno_id = etapa de quem reprovou`. Quando o destino (cidadão ou setor) **resolve a pendência** (cidadão reenvia **ou** setor aprova), o processo volta **direto para quem reprovou** — não repassa linearmente pelas etapas intermediárias — e o marcador é limpo.

**Fluxo do exemplo:** Engenharia (etapa 3) reprova → devolve ao cidadão (etapa 1), `etapa_retorno_id = 3`. Cidadão corrige e reenvia → volta **direto para Engenharia** (etapa 3), `etapa_retorno_id` limpo. Engenharia aprova → segue o fluxo normal (próxima por ordem).

**Implementação:** helper `ProcessoFormService::destinoAposResolver()` (se há `etapa_retorno_id` → volta a ele; senão → próxima por ordem), usado por `avancarProximaEtapa` (cidadão) e por `julgarEtapa` (aprovação do analista). `julgarEtapa($processo, $usuarioId, $decisao, $parecer, $etapaDestinoId)` ganhou o parâmetro do destino da reprova.

> **Limitação conhecida (PoC):** reprova em cascata (o destino da reprova reprova de novo) **sobrescreve** o `etapa_retorno_id`. Cobre o cenário de 1 nível (o pedido). Fluxos com múltiplos retornos aninhados precisariam de uma pilha de retorno — fora do escopo atual.

---

## 15. Refinamentos — Rodada 5 (2026-07-02): visão do cidadão (ver ≠ criar)

**Problema:** ao "Acompanhar" um processo já solicitado, o cidadão caía no **mesmo wizard de criação** e podia **trocar o lote** — erro.

**Solução:** separar por operação no `Cidadao\ProcessoDigitalResource::form()`:
- **`create`** → `schemaCriacao()` = o wizard (tipo → imóvel → dados).
- **`edit`/correção** → `schemaCorrecao()` = **aviso de reprovação + resumo read-only (protocolo/serviço/fase/imóvel) + apenas os campos da etapa atual** (sem trocar tipo/lote). O submit volta ao botão do form (`EditProcessoDigital::getFormActions`).

**Nova página "Acompanhar" (read-only)** — `Cidadao\...\Pages\ViewProcessoDigital` (rota `view`), no mesmo espírito da visão dos setores: Visão Geral · Imóvel Vinculado · Respostas Enviadas · Documentos Anexados · Histórico. A `ViewAction` "Acompanhar" da tabela aponta para ela.

**DRY:** a renderização de **respostas**, **histórico** e **documentos** foi movida para o `ProcessoFormService` (`respostasHtml`/`historicoHtml`/`documentosHtml`) e é reusada pelo `ViewProcessoDigital` do **analista** e do **cidadão**. (Campos do tipo `arquivo` não aparecem em "Respostas" — só em "Documentos".)

---

## 16. Refinamentos — Rodada 6 (2026-07-02): Caixa de Entrada + PDF

**Fix crítico:** o `form()` do cidadão usava `->schema(fn (string $operation) => ...)` — o `$operation` **não é resolvível** no closure de topo do `Form` (erro ao criar). Trocado por um `Group` com closure de componente injetando **`$record`** (null = criação).

**Lista da prefeitura (`ProcessoDigitalResource` app):**
- Telefone → coluna **oculta por padrão** (`toggleable`); Serviço e Fase Atual com **`->wrap()`**.
- **Removida a coluna "Analista"** — ficava sempre "Fila geral" (o `analista_id` deixou de ser preenchido quando a ação "Encaminhar para analista específico" foi removida na ação única).
- Ações agrupadas num **dropdown de 3 pontinhos** (`ActionGroup`).
- Filtros: removidos "Meus processos / Fila geral / Setor"; adicionado **Situação** (Em andamento / Concluído).
- Nova ação **"Mostrar no Mapa"** (quando há lote) → abre o mapa em nova aba e **navega até o lote** usando o mesmo padrão do `LoteResource` (`.../mapa-interativo?layer=lotes&focus_lat=..&focus_lon=..`, coords do centróide via `geo_json`). *(A 1ª tentativa com `?id=` abria o mapa mas não navegava.)*
- Nova ação **"Imprimir PDF"** → `ProcessoDigitalPdfService` + `pdf/processo-digital-template.blade.php` (com **brasão** do tenant, padrão dos ExportServices): dados gerais, solicitante, imóvel, respostas por etapa, documentos (como **links** clicáveis) e histórico. Layout **minimalista** (sem tarjas escuras).
