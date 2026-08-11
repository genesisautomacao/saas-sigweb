# Backlog de Pendências — PoC Tangará/SC

> **Fonte de verdade para todas as implementações futuras deste projeto.**
> Ao concluir um item: riscar o título (`#### ~~T1.1 — ...~~`), definir `**Status:** ✅ Concluído`, adicionar `**Concluído em:** YYYY-MM-DD` e atualizar os contadores abaixo.
>
> ⚠️ **Este arquivo substitui `docs/pendencias.md` e `docs/pendencias2.md`** (backlog da PoC de Nova Esperança do Sul/RS, **já vencida**). Aqueles arquivos são mantidos apenas como histórico — **não** usar como referência de trabalho.

**Contexto:** backlog derivado da análise de conformidade da PoC de Tangará/SC (124 itens obrigatórios). A tabela item-a-item completa, com status e evidências arquivo:linha, está em [PocTangara_plano.md](PocTangara_plano.md) — consultar antes de iniciar qualquer item.

**Critério do edital:** demonstração plena de **≥ 85%** (106/124), em até 10 dias úteis a partir da convocação. Itens não demonstrados (dentro dos 85%) → implementação em 60 dias pós-contrato.
**Meta interna pré-PoC:** ≥ 95% (118/124) — o plano leva a **122/124 (98,4%)**; os 124/124 completam-se no pós-contrato (item F2 do Plano de Ação Futura).

**Legenda:** ⏳ Pendente · 🔄 Em andamento · ✅ Concluído · 📋 Aguardando decisão externa

---

## Contadores de Conformidade PoC Tangará/SC

**Progresso atual (baseline da auditoria de 2026-07-08): 95/124 plenos ≈ 76,6%** (19 parciais ⚠️ + 10 não atendidos ❌)

| Marco | Plenos | % acumulado | Status |
|-------|-------:|------------:|--------|
| Baseline (auditoria 2026-07-08) | 95/124 | 76,6% | — |
| **Onda 1 — Estrutura de campos** | 95/124 | 76,6% | ✅ 2026-08-02 — fundacional, **destravou** as demais |
| **Onda 2 — T1.1, T1.2, T1.3, T1.5, T1.7** | **101/124** | **81,5%** | ✅ 2026-08-03 (itens 14, 15, 44, 17, 21, 3-14) |
| **Onda 3 — T1.4, T1.6 (mapa público)** | **103/124** | **83,1%** | ✅ 2026-08-03 (itens 3-9, 3-11) |
| **Onda 4 — T1.8, T1.9 + item 76① (filtro)** | **105/124** | **84,7%** | ✅ 2026-08-03 (itens 2.1-1, 2.1-2) |
| **Onda 5 — T1.10, T1.11, T1.12 (motores)** | **113/124** | **91,1%** | ✅ 2026-08-03 — **ETAPA 1 COMPLETA** (itens 2.5-32/34/37, 3-18/20/23, 2.3-24, 2.6-38) |
| **Onda 6 — T2.1, T2.2** | **118/124** | **95,2%** | ✅ 2026-08-03 — 🎯 **META INTERNA DE 95% ATINGIDA** (itens 46, 54, 64, 59, 60) |
| **Onda 7 — T2.3, T2.4 + UI dos mapas** | **120/124** | **96,8%** | ✅ 2026-08-03 (itens 3-10, 3-12; topbar glass + painel de camadas fixo à esquerda) |
| + Etapa 2 (T2.1–T2.5) | 121/124 | **97,6%** (meta 95% ✔) | ⏳ |
| + Etapa 3 (T3.1 — WMS) | 122/124 | 98,4% | ⏳ |
| + Itens 75/76 (D5 — dentro das Ondas 3 e 4) | **124/124** | **100%** | ⏳ |

> Referência dos itens da PoC: numeração `2.x-N` = seção Intranet do edital; `3-N` = seção Internet/Público; `1-N` = Características Técnicas (conforme `poc_tangara_sc.md` e tabelas do [PocTangara_plano.md](PocTangara_plano.md)).

---

## Decisões registradas (2026-07-08)

- **D1 — Integração Betha Sistemas: ✅ Decidido — dump JSON na PoC.** A demonstração usará dump JSON exportado do Betha → `php artisan tributario:importar` (mesma estratégia validada para o GOVBR em Bom Princípio; ponto de extensão: `IntegraPrefeituraService`). A API do Betha, em regra, só é liberada **após a aprovação da PoC e a assinatura do contrato** → a integração em tempo real está registrada como item futuro **F1**.
- **D2 — EAV (campos customizáveis): ✅ Decidido — pós-PoC.** Por ser o item mais complexo do plano (40–80 h), o ex-T3.2 foi movido para o plano futuro como **F2** (a janela de 60 dias pós-contrato prevista no edital o cobre). O escopo pré-PoC passa a mirar **122/124 (98,4%)** — bem acima da meta de 95%.

## Decisões registradas (2026-07-31)

- **D3 — Base de demonstração: ✅ Decidido — reusar `prefeitura-de-santa-cecilia`.** Não será criado tenant novo nem importada base cartográfica de Tangará para a PoC. Reduz a Etapa 0 de ~8–24 h para ~2 h. A T0.1 vira **inventário de dados de demo** (garantir que a base exercite cada item do edital) em vez de importação.
- **D4 — Seção de Logradouro é LINEAR, uma por lado. ✅ Decidido — sem entidade nova, sem polígono.** Confirmado no texto bruto do edital ([lista_txt_poc_tangara.txt](lista_txt_poc_tangara.txt)):
  - **Intranet 44** pede *"Código do Logradouro + Código da Seção (métrico) + **Lado da Seção**, **comprimento**"* — "lado" só existe em relação a um eixo e o edital pede **comprimento**, não área → geometria linear (a `SecaoLogradouro` atual, MULTILINESTRING, serve).
  - **Intranet 42** pede *"testada(s), **Logradouro e Seção de cada testada**"* — já implementado em `LoteTestada` (`logradouro_id` + `secao_logradouro_id`). O vínculo "o lote se integra à seção" **já existe**.
  - **Uma seção por lado:** o `lado` só é dado útil se a própria seção for de um lado só (senão o lote par e o ímpar apontariam para a mesma seção). Logo, **duas seções por trecho** — independentes entre si, com comprimentos diferentes (travessa que entra só de um lado, rio/praça no outro lado, faces de quadra assimétricas). Modelagem: N linhas independentes com `lado` como **atributo**, nunca estrutura pareada.
  - **Desenho sobre a testada, não sobre o eixo.** Coincidência geométrica exata quebra três coisas: (a) o `forEachFeatureAtPixel` do mapa só captura uma das linhas → a outra fica inclicável; (b) o **T2.2** fica impossível (buffer de linha no eixo pega os lotes dos DOIS lados e o `lado` fica indecidível); (c) a galeria de fotos do item 17 não distingue os lados. Fica como prática recomendada — **sem** constraint geométrica, para não quebrar importação de base legada desenhada no eixo.
  - **Vocabulário do `lado` é white-label** (`CampoDominioService`, padrão R67-2): default Par/Ímpar/Ambos, cada município ajusta.
  - **Pai hierárquico = Logradouro, único.** Já é assim (FK obrigatória + `hasMany` + `SecoesRelationManager` + "Nova Seção" pelo modal do logradouro) e o edital sempre escreve *"Logradouro **e** Seções"* (itens 44, 52, 70). **Não** adicionar `quadra_id` — a relação com a quadra é derivável por PostGIS no T2.2, e o PGV já tem `FaceQuadra` para face de quadra.
  - **Rótulo:** manter **"Seções de Logradouro"** (o termo do edital, usado em 8 itens). A ideia de renomear para "Trechos" foi descartada para a PoC — se necessário no futuro, vira rótulo white-label por município, nunca renomeação de tabela/model/rota (25 arquivos + FK + camada do mapa + permissões).

## Decisões registradas (2026-08-01)

- **D5 — Itens 75/76 (campos customizáveis) VOLTAM para o escopo pré-PoC. ✅ Decidido — cancela a D2.** O `CampoCustomizado` (R67-1) já existe e já está plumbado na **edição** (①). Falta integrá-lo a ② filtro avançado, ③ mapa temático, ④ heatmap e ⑤ estatísticas — e os quatro vivem no `MapDataController` + `mapa-engine.js`, exatamente os arquivos que as Ondas 3 e 4 vão abrir. Construir os motores **já cientes de campos customizados** custa **10–16 h** marginais, contra os 40–80 h estimados no F2. **Resultado: 124/124 = 100%.** O F2 sai do Plano de Ação Futura.
- **D6 — Régua de campos: 3 categorias. ✅ Decidido.** Detalhamento completo em [mapeamentoCamposImobiliario.md](mapeamentoCamposImobiliario.md).
  1. ⚙️ **Base fixa do sistema** — `id`, `sequential_id`, `code`, `geo`, FKs, derivados PostGIS. Não é item de cadastro.
  2. 🏷️ **Campo fixo white-label** — coluna do sistema com **valores (chaves) imutáveis** e **rótulos customizáveis** (nome do campo e texto de cada opção). O município **não inventa nem remove valor de select**.
  3. ➕ **Campo da prefeitura** — não existe como coluna; cada município cria e ele aparece em ficha, edição, filtro, busca, temático, heatmap e estatísticas.
  - Consequência direta: **`tipo_ocupacao` NÃO vira coluna** (era o N2). A `ocupacao` fica travada no binário `baldio`/`construido` que os itens 42/51/60 exigem, e o município que precisa de "em ruínas"/"em construção" cria o campo dele.
- **D7 — Corte das colunas fiscais de `unidade_imobiliarias`. ✅ Decidido.** As 13 colunas promovidas em 2026-07-01 foram moldadas pelo export de **Santa Cecília** e o rastreio mostrou que **as buscas e o PGV leem o JSON, não as colunas** (proprietário e `nome_edificio` em [MapDataController:571-576](../app/Http/Controllers/Api/MapDataController.php#L571); `valor_total_imposto` em [PgvSimulacaoIptuService:50](../app/Services/Pgv/PgvSimulacaoIptuService.php#L50)). **Saem 9 colunas**; ficam 11 (incluindo as 3 novas `nome_edificio`, `proprietario_nome`, `proprietario_cpf_cnpj`). O `dados_tributarios` continua sendo a verdade — nenhum dado se perde.
  - **Em aberto (decidir na implementação):** com o corte, `sistemas_tributarios.extras` (R67-5) e `CampoCustomizado` passam a disputar o papel de "mostrar campo do tributário que não é canônico". Unificar os dois é decisão adiada para quando o comportamento real estiver à vista.

## Plano de Ação Futura (pós-PoC / pós-contrato)

> Itens que só entram em desenvolvimento **após a aprovação da PoC e a assinatura do contrato**. Não contam para a demonstração.

#### F1 — Integração em tempo real com a API Betha Sistemas
**Origem:** decisão D1 · **Esforço:** 8–16 h (estimativa inicial; depende da documentação da API) · **Status:** 📋 Futuro (aguarda liberação da API pós-contrato)

- Criar `BethaService` consumindo a API de tributos do Betha (Strategy junto a `IntegraPrefeituraService`/`GovbrService`, configurável por tenant);
- `.env`: `BETHA_API_URL` + credenciais; sincronização periódica → upsert em `unidade_imobiliarias.dados_tributarios` (mesmo contrato do `tributario:importar`);
- Substitui o fluxo de dump JSON usado na PoC sem mudança de modelo de dados.

#### ~~F2 — Itens de Cadastro customizáveis (EAV)~~ *(ex-T3.2)*
**Status:** ❌ **CANCELADO como item futuro em 2026-08-01 — decisão D5.** Voltou para o escopo pré-PoC, distribuído nas **Ondas 3 e 4** (o motor `CampoCustomizado` do R67-1 já existe; falta integrá-lo ao filtro avançado, mapa temático, heatmap e estatísticas, que são justamente os arquivos daquelas ondas). Texto original mantido abaixo como referência de escopo.

**Itens PoC:** 2.8-75, 2.8-76, 2.10-88 (leitura estrita) · **Esforço original estimado:** 40–80 h · ~~**Status:** 📋 Futuro (decisão D2)~~

Entidades `ItemCadastro` (tenant, camada, nome, tipo: numérico/texto/seleção/multisseleção/multisseleção-com-quantitativo, opções) + `ItemCadastroValor` (polimórfico). Integração obrigatória em: ① ficha/edição da entidade; ② filtro avançado/expressões; ③ tematização (valores únicos + classes); ④ heatmap; ⑤ estatísticas; ⑥ visibilidade por perfil. As integrações ③④⑤ reaproveitam os motores genéricos de T1.10/T1.11/T1.12 — **por isso a ordem das etapas importa**. Registrar o design detalhado aqui antes de iniciar.

---

## Etapa 0 — Preparação (sem código)

#### T0.1 — Base de demonstração (`prefeitura-de-santa-cecilia`)
**Status:** 🔄 Em andamento — *inventário concluído em 2026-07-31; correção das lacunas pendente*

Decisão **D3**: sem tenant novo e sem importação cartográfica. A tarefa virou **inventário de dados** contra os itens do edital que dependem de dado. Resultado completo na seção 2 de [roteiroDemoTangara.md](roteiroDemoTangara.md). 20 itens com dado suficiente; **6 lacunas** a corrigir com seeder idempotente antes da Onda 1:

| # | Itens afetados | Lacuna medida |
|---|---|---|
| G1 | 2.1-11 | **0** pessoas jurídicas (item pede busca por CPF **/CNPJ**) |
| G2 | 2.1-21, 3-11, 3-14 | `parametros_urbanos` cobre **2 de 10** zonas |
| G3 | 2.7-42 | `ocupacao` / `situacao_quadra` em **4 de 4.434** lotes — **também esvazia a tematização da Onda 4** |
| G4 | 2.7-43 | `pavimento` em **7 de 7.894** edificações |
| G5 | 2.7-42, 2.7-44 | **5** testadas de lote · **2** seções de logradouro |
| G6 | 2.1-5, 3-3 | **1** unidade com `nome_edificio` (chave do JSON `dados_tributarios`) |

#### T0.2 — Validação de dependências do servidor de demo
**Status:** ✅ Concluído
**Concluído em:** 2026-07-31

PHP 8.3.30 · Laravel 12.52 · PostgreSQL 18.3 · **PostGIS 3.6.1** · **GDAL/ogr2ogr 3.13.0** no PATH (item 2.4-30 ok) · **DomPDF 3.1.4** com render validado em **A0/A1/A2/A3/A4 paisagem** (itens 2.4-25 a 29) · simple-excel 3.8.1 · activitylog 4.12.3 (13.277 registros).

> ⚠️ **Dois achados:**
> 1. **`AZURE_MAPS_KEY` está vazia** — os basemaps Azure Road/Satélite ([mapa-engine.js:83-96](../public/js/gis/mapa-engine.js#L83)) renderizam em branco. Pior: a chave é lida com `env()` dentro de um Blade ([mapa-fullscreen.blade.php:25](../resources/views/filament/pages/mapa-fullscreen.blade.php#L25)) e **`env()` fora de arquivo de config retorna vazio quando `config:cache` está ativo** — então em produção o basemap quebra mesmo com a chave no `.env`. Corrigir movendo para `config/services.php` + `config()`. O item 1-4 já é atendido por OSM + Esri Satélite.
> 2. **Não existe suíte de testes** — apenas os 2 testes de exemplo do Laravel, e o `Tests\Feature\ExampleTest` falha (espera 200 em `/`, recebe 302). Sem rede de segurança contra regressão → o teste de cada onda será por **script em transação revertida**, padrão já usado no projeto.

#### T0.3 — Roteiro de demonstração v1
**Status:** ✅ Concluído
**Concluído em:** 2026-07-31

[roteiroDemoTangara.md](roteiroDemoTangara.md) — checklist de ambiente, **matriz de dependência de dados** por item, estrutura em 7 blocos (A–G) espelhando as seções do edital e **5 riscos com plano B** (R1 Azure · R2 ausência de testes · R3 "itens de Cadastro" nos itens 24/37/38/3-23 · R4 lacunas de dado · R5 WMS). Os caminhos de clique item-a-item são preenchidos no T4.1, após as Ondas 1–4.

---

## Onda 1 — Estrutura de campos ✅ CONCLUÍDA em 2026-08-01 *(escopo ampliado pelas decisões D6/D7 e pela lista aprovada)*

> **Status:** ✅ Implementada e testada (23 asserções em transação revertida, 0 falhas de código).
> O escopo real executado foi muito além do planejado — a lista aprovada em
> [campos_imobiliario_para_aprovacao.txt](campos_imobiliario_para_aprovacao.txt) virou uma refatoração completa:
>
> - **E1.1 ✅** `codigo` municipal nas 7 entidades (+ forms/tabelas nos Resources);
> - **E1.2 ✅** chave/rótulo separados no `CampoDominioService` (+ normalização: `esquina`/`Esquina` unificados; push do app traduz rótulo→chave);
> - **E1.3 ✅** a grande refatoração:
>   - `jsonb` nas colunas de dado livre + `dados_customizados` em **13 camadas** (item 75) + 18 índices GIN + `pg_trgm`;
>   - tabela **`coleta_imobiliaria`** polimórfica (campanha/status/quem/quando/observação/inconformidade) — 48 coletas migradas; `lotes.status_cadastro` mantido como cache do mapa;
>   - **34 colunas removidas** com dado migrado antes do drop (5.395 edificações preservadas em `dados_customizados`);
>   - novas: `secoes_logradouro.lado` e `unidade_imobiliarias.nome_edificio` (backfill do JSON + trigram);
>   - busca unificada: proprietário via **`pessoas`** (funciona sem integração tributária; fallback no JSON), endereço só na unidade, `nome_edificio` na coluna;
>   - **KIT INICIAL** de campos customizados (`KitCamposCustomizadosService` + seeder + hook `Tenant::created`) — 8 campos por prefeitura, opções mescladas com o dado real;
>   - consumidores atualizados: sync API (pull/push com blindagem p/ app publicado), estatísticas (expressão JSONB **+ correção de injeção de SQL** no `group_field`), produtividade/monitoramento, Filament (LoteResource, traits, RelationManagers, ficha do mapa), PDFs (planta de quadra, produtividade, notificação, lote detalhado), exports, `MapaFiscalService::camposCanonicos()` mirando slugs;
>   - app RN (`app-coletas`): `CONFIG_PADRAO` atualizado — o form é config-driven e se adapta sozinho;
> - **E1.4 ✅** absorvido pelo kit (as 3 taxonomias hardcoded viraram campos do kit);
> - **E1.5 ✅** `ApiSetting` "Azure Maps"/"Google Maps" + cache + fim dos 4 `env()` em runtime + opções Azure ocultas sem chave;
> - **E1.6 ✅** comando `php artisan poc:inventario --tenant=<slug>` (rodar na VPS).
>
> ⚠️ **Implantação na VPS:** `php artisan migrate` (7 migrations, com migração de dado embutida) + `php artisan db:seed --class=KitCamposCustomizadosSeeder`. A integração bidirecional (GET + POST/PUT no tributário) ficou DESENHADA (de/para mira slug) — os métodos de escrita do driver entram com o 1º conector real.

### Escopo original (referência)

> Trabalho fundacional decidido pelas **D6/D7**. Vem **antes** das quick wins porque é pré-requisito delas: o T1.3 precisa do `codigo` do logradouro, e os motores da Onda 4 (itens 75/76) precisam da separação chave/rótulo.
> Não altera contadores de conformidade por si só — **destrava** os itens 44/45/46/47/48/49 (L1), 5/3-3 (L2), 11 (proprietário) e 75/76 (D5).

#### E1.1 — L1: coluna `codigo` municipal nas 7 entidades
**Itens PoC:** 2.7-44, 2.7-45, 2.7-46, 2.7-47, 2.7-48, 2.7-49 · **Esforço:** 6–8 h · **Status:** ⏳ Pendente

`logradouros`, `secoes_logradouro`, `bairros`, `quadras`, `zonas`, `perimetros_urbanos`, `setores_fiscais`. Hoje `code` é **UUID interno** e `sequential_id` é a **chave da importação GIS** (não pode virar editável). Sem isso, o código composto do item 44 não é formável e o **T2.5 (Recodificação) é impossível** — não se recodifica o que não tem código. Migration + forms + tabelas + exports + busca.

#### E1.2 — N1: separar chave e rótulo no `CampoDominioService`
**Pré-requisito da Onda 4** · **Esforço:** 4–6 h · **Status:** ⏳ Pendente

Hoje `campo_dominios.opcoes` guarda **array plano de rótulos**, então o Select grava o **rótulo** na coluna — a base já tem `esquina` **e** `Esquina` como valores distintos do mesmo conceito, e `ocupacao` com `"Em Contrução"` (com typo). Isso faria o **mapa temático por valores únicos (T1.10) pintar duas classes para o mesmo conceito**. Passar a gravar `chave => rótulo` (chave por slug, imutável, mesma disciplina do `CampoCustomizado.slug`) + normalização única dos registros existentes.

#### E1.3 — D7: corte fiscal em `unidade_imobiliarias`
**Itens PoC:** 2.1-5, 2.1-11, 3-3 · **Esforço:** 6–10 h · **Status:** ⏳ Pendente

Remover 9 colunas; criar `nome_edificio`, `proprietario_nome`, `proprietario_cpf_cnpj`. Ajustar `componentesFiscaisUnidade()` (R67-8), os modais de unidade, o BIC, os exports, o `MapaFiscalService` e a busca do `MapDataController` (que passa a ler coluna em vez de JSON). ⚠️ Base da VPS pode ser resetada — **exceto os processos digitais de Bom Princípio**.

#### E1.4 — L3: taxonomias hardcoded para o `CampoDominio`
**Esforço:** 2–3 h · **Status:** ⏳ Pendente

`secoes_logradouro.tipo_pavimentacao`, `meio_fios.material`, `meio_fios.estado_conservacao`.

> **L4 adiado (decisão do usuário, 2026-08-01):** o `PgvDepreciacaoResource` passar a listar `estado_conservacao` do `CampoDominioService` **fica fora desta onda** — o PGV terá uma revisão dedicada mais adiante. Nada no módulo PGV é tocado na Onda 1.

#### E1.5 — N4: credenciais no `ApiSetting` + fim dos `env()` em runtime
**Item PoC:** 1-4 · **Esforço:** 3–4 h · **Status:** ⏳ Pendente

Entradas `Azure Maps` e `Google Maps` no `ApiSettingResource`; `AppServiceProvider` carrega todas as `api_settings` numa consulta só (com cache invalidado no `saved()`) e injeta em `config('services.*')`. Trocar os **4 `env()` em runtime** ([mapa-fullscreen.blade.php:25](../resources/views/filament/pages/mapa-fullscreen.blade.php#L25) e [:3239](../resources/views/filament/pages/mapa-fullscreen.blade.php#L3239), [mapa-publico.blade.php:585](../resources/views/filament/cidadao/pages/mapa-publico.blade.php#L585), [MapaFullscreen.php:1483](../app/Filament/Pages/MapaFullscreen.php#L1483)) por `config()` — **`env()` fora de arquivo de config retorna vazio com `config:cache` ativo**. Ocultar os basemaps Azure quando não houver chave.

#### E1.6 — N3: comando `poc:inventario`
**Esforço:** 1–2 h · **Status:** ⏳ Pendente

Transformar o script de inventário da Onda 0 em comando artisan, para rodar **na VPS** (que é a base real da PoC — decisão do usuário em 2026-07-31) e medir a densidade de dado por item do edital.

---

## Etapa 1 — Quick Wins (36–61 h) → 113/124 (91,1%)

> Converte 5 ❌ e 13 ⚠️ em ✅ (+ corrige o falso positivo do item 2.7-52 dentro do T1.3). Ordenada do mais fácil ao mais difícil.
> **Ordem de execução por ondas** (agrupada por arquivo/subsistema, para não reabrir o mesmo arquivo várias vezes — acordada em 2026-07-31):
> **Onda 1** = T1.1, T1.2, T1.3, T1.7, T1.5 (fichas, PDFs e cadastros) · **Onda 2** = T1.4, T1.6 (mapa público) ·
> **Onda 3** = T1.8, T1.9 (`advancedSpatialQuery`) · **Onda 4** = T1.10, T1.11, T1.12 (motores genéricos do mapa — maior retorno: 9 itens).
> Cada onda: implementação → teste do agente → teste do usuário → ok → próxima.

#### ~~T1.1 — Link "Ver histórico" na ficha do lote~~
**Item PoC:** 2.1-14 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

Botão "Ver histórico" na ficha lateral do lote (gated por `view_auditoria`) → `AuditoriaPage?lote_id=X` pré-filtrada pelo lote **e seus filhos** (unidades, edificações, testadas — com `withTrashed`, para histórico de itens excluídos). Subheading indica o foco; header action "Limpar filtro do lote".

#### ~~T1.2 — Contribuinte confrontante no Memorial Descritivo~~
**Item PoC:** 2.1-15 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

`gerarDadosPerimetro` ganhou a coluna `confrontante_proprietario` — nome do contribuinte do lote vizinho via `pessoas` (`proprietario_id`, pós-refatoração) com fallback no JSON `dados_tributarios`. Sai no texto corrido ("confrontando com o Lote 111, de LEANDRO SOUZA GOTER") e na tabela do PDF.

#### ~~T1.3 — Seção de Logradouro: código métrico, lado e cascata de exclusão~~
**Itens PoC:** 2.7-44, **2.7-52** · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03 · *(colunas `codigo`/`lado` criadas na Onda 1; acabamento na Onda 2)*

Entregue: código composto `{cód. logradouro}-{cód. seção}` (accessor `codigo_composto`, exibido na tabela do Resource e na ficha do logradouro no mapa, com coluna Lado), índice único parcial `(tenant_id, logradouro_id, codigo, lado) WHERE deleted_at IS NULL` (dois lados com o mesmo código = ok; duplicar o mesmo lado = bloqueado; NULL nunca colide) e a **cascata de soft delete/restore Logradouro → Seções** (item 52 — eventos `deleted`/`restoring`/`restored` no model; o restore só ressuscita seções excluídas na MESMA cascata, pelo marco do `deleted_at`).

> 🐛 **Achado no teste do usuário (2026-08-03) — 22 botões de exclusão do mapa surdos a eventos.** O usuário excluiu um logradouro e a seção permaneceu: TODOS os botões "Excluir" das traits do mapa usavam `Model::where('id', $x)->delete()` — delete de **query builder**, que não dispara eventos Eloquent. Consequências: a cascata do item 52 não rodava E **nenhuma exclusão pelo mapa era registrada na Auditoria** (item 89 — o `LogsActivity` também depende de evento). Corrigidos os **22** para `Model::find($x)?->delete()`; o excluir do logradouro ganhou aviso com a contagem de seções e o front agora remove as seções da tela junto (`remover-secao_logradouro-mapa` por seção). Testado: cascata OK, log de exclusão na auditoria OK.

1. Migration em `secoes_logradouro`: **`codigo`** (Código da Seção, métrico) + **`lado`**;
2. **`lado` white-label** — registrar a entidade `secao_logradouro` em `CampoDominioService::PADROES` (default Par/Ímpar/Ambos). Fica fora de `ENTIDADES_NA_COLETA` (não é campo de coleta de campo);
3. **Código composto na UI** — o item 44 pede `Código do Logradouro + Código da Seção`; exibir concatenado na tabela, na ficha do mapa e no PDF;
4. **Índice único parcial** `(tenant_id, logradouro_id, codigo, lado) WHERE deleted_at IS NULL` — impede duplicar a mesma seção do mesmo lado, libera os dois lados (código igual + lado diferente). Como no Postgres `NULL ≠ NULL`, seção sem código nunca colide → importação legada passa limpo;
5. 🐛 **Cascata de exclusão Logradouro → Seções (item 52)** — ver achado abaixo;
6. Form/tabela no `SecaoLogradouroResource`, no `SecoesRelationManager` e na trait `HasSecaoLogradouroActions`. Orientação no formulário: desenhar a seção **sobre a testada** (frente dos lotes do lado), não sobre o eixo — sem constraint geométrica.

> 🐛 **Achado 2026-07-31 — item 2.7-52 estava marcado ✅ indevidamente.** O edital pede *"Excluir Logradouro **e Seções**"*, mas excluir um logradouro **não** exclui as seções: o `cascadeOnDelete` da FK é constraint de banco e só dispara em DELETE físico, enquanto [HasLogradouroActions.php:202](../app/Filament/Pages/Traits/HasLogradouroActions.php#L202) faz `->delete()` num model com `SoftDeletes` — só grava `deleted_at`. As seções continuam ativas e visíveis no mapa, apontando para um pai excluído (a coluna "Logradouro" da listagem fica vazia porque o `belongsTo` cai no escopo global de soft delete). Corrigir cascateando o **soft delete e o restore** do logradouro para as seções. Não altera os contadores (o item já era contado como ✅), mas era um falso positivo que quebraria ao vivo.

#### ~~T1.4 — Foto frontal + croqui na ficha pública do imóvel~~
**Item PoC:** 3-9 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

Imagem frontal no topo da ficha pública (clicável p/ ampliar; placeholder "Sem imagem frontal cadastrada" como fallback) e o botão do croqui promovido a **"Planta / Croqui do Imóvel"** em destaque (azul sólido) — é a "Planta Cartográfica" que o item 3-9 pede junto da imagem.

#### ~~T1.5 — Viabilidade: metragens + parâmetros no PDF; mapImage na reimpressão~~
**Itens PoC:** 2.1-21, 3-14 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

`enriquecerComMetragensEParametros()` no `ViabilidadePdfService` (ponto único — vale p/ intranet E público): área do lote, testada principal, área construída (SUM das edificações) + parâmetros da zona (`ParametroUrbano`), gravados **no snapshot** (a reimpressão herda; snapshot antigo é enriquecido na hora, idempotente). Template ganhou as seções "Metragens / Áreas" e "Parâmetros Urbanísticos da Zona". **Reimpressão com croqui:** o print do mapa é persistido em `storage/app/public/viabilidades/{protocolo}.b64` na emissão (viabilidade, parcelamento e unificação) e recuperado no `reimprimirPdf` — antes saía `mapImage=null`.

#### ~~T1.6 — Zona clicável no mapa público (parâmetros + usos)~~
**Item PoC:** 3-11 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

`singleclick` do `mapa-cidadao-engine.js` agora coleta TODAS as feições no pixel e escolhe por prioridade (**lote > ponto panorâmico > zona**) — a zona vira clicável sem roubar o clique de um lote sobreposto. Clique na zona → modal com swatch de cor + código, **Parâmetros Urbanísticos** (áreas/testadas mín-máx do `ParametroUrbano`) e **Usos do Solo** agrupados em permitido/permissível/proibido (badges por classificação, via `ZoneamentoRegra`), com nota direcionando análise de CNAE específico à Consulta de Viabilidade. View `ficha-zona-publica.blade.php`, listener `abrirFichaZona` no `HasFichaImovelPublico`.

#### ~~T1.7 — Fotos nas Seções de Logradouro~~
**Item PoC:** 2.1-17 (base para 3-10) · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

`SecaoLogradouro::fotos()` = morphMany na tabela polimórfica `documentos` (type `Foto`), mesmo padrão da UnidadeImobiliaria. Upload por Repeater (legenda + imagem, `secoes_logradouro/fotos/`, 5MB) no `SecaoLogradouroResource` **e** no `SecoesRelationManager` do Logradouro. **Galeria na ficha do logradouro** (modal do mapa): coluna Fotos com até 3 miniaturas clicáveis (+N) por seção, eager-load `with('fotos')`. Base pronta para o T2.3 (ficha pública do logradouro).

#### ~~T1.8 — Filtro avançado: atributo + espacial combinados~~
**Item PoC:** 2.1-1 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

Seção "Condição de atributo (opcional)" nas abas **espacial** e **desenho** do filtro: o WHERE de atributo entra na MESMA consulta SQL do cruzamento ("lotes na zona X **com área > 400**" → 292 de 1.110 num só passo). Vale também para **campos customizados** (`custom:slug`). Rótulo do resultado inclui a condição.

#### ~~T1.9 — Delimitadores Quadra e Logradouro~~
**Item PoC:** 2.1-2 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

**Logradouro** virou área de referência do cruzamento espacial via `ST_Buffer` do eixo (campo "Largura da faixa", padrão 20 m — "imóveis lindeiros da Rua X"); **Quadra** já constava como referência (polígono direto). O item 2.1-2 ("delimitar por Distrito/Setor/Bairro/Logradouro/Quadra") fica pleno na consulta; nas estatísticas, a delimitação segue Distrito/Setor/Bairro, que é exatamente o que o item 39 pede.

> **Onda 4 também entregou (2026-08-03):**
> - **Item 76① — campos customizados nas expressões de consulta (D5):** o select de atributo das 4 abas do filtro lista os campos do município (marcados com ★), resolvidos no backend contra o JSONB `dados_customizados` (cast numérico quando o campo é número; índice GIN da Onda 1 serve a consulta). A tematização por intervalo aceita campo customizado numérico (adianta parte do 76③).
> - 🐛 **Injeção de SQL corrigida** no `advancedSpatialQuery`: `$field` (rota atributo) e `$attr` (rota intervalo) iam interpolados crus no `selectRaw`. Agora todo campo passa por whitelist (schema da tabela ou `custom:slug` validado nas definições) — campo desconhecido → 403. Testado com payload malicioso real.

#### ~~T1.10 — Tematização por Valores Únicos (intranet + público)~~
**Itens PoC:** 2.5-32, 2.5-34, 2.5-37, 3-18, 3-20, 3-23 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

Rota `valores_unicos` no `advancedSpatialQuery` (atributo = coluna whitelisted OU `custom:slug` — item 76③; devolve feições rotuladas + resumo de valores). Aba "Tematização por Valores Únicos" no filtro da **intranet e do público**; paleta automática (ângulo áureo — cores bem separadas p/ N valores) e **legenda flutuante com `input color` por valor** — trocar a cor repinta as feições na hora (itens 34/3-20). NULL vira "Não informado". Integrada ao painel de filtros ativos e ao "limpar".

#### ~~T1.11 — Heatmap genérico por camada~~
**Item PoC:** 2.3-24 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

Aba "Mapa de Calor (qualquer camada)" no filtro avançado: reusa a rota `intervalo` com **atributo opcional** (sem atributo = densidade por contagem; com atributo numérico — inclusive `custom:` — o valor vira o **peso**, normalizado 0.15–1). Polígono entra pelo ponto interior, linha pelo centro do extent; raio configurável; camada `ol.layer.Heatmap` registrada no painel de filtros.

#### ~~T1.12 — Estatísticas: mais camadas e atributos~~
**Item PoC:** 2.6-38 (reforça 2.5-37, 3-23) · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

`getEstatisticas` ampliado: **+7 camadas** (quadras, seções, meios-fio, árvores, postes, chamados — com joins de categoria/fase — e grupos novos em lotes: ocupação, status). Agrupamento aceita, além da whitelist curada, **campo customizado (`custom:slug` — item 76⑤) ou qualquer coluna do schema**, validados (a injeção via `group_field` já estava fechada; a via `$field`/`$attr` do filtro foi fechada na Onda 4).

---

## Etapa 2 — Intermediária (36–58 h) → 121/124 (97,6%)

#### ~~T2.1 — Distrito formal~~
**Itens PoC:** 2.7-46, 2.7-54, 2.8-64 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

`PerimetroUrbano` apresentado formalmente como **Distrito**: menu/labels "Distritos" no Resource; o item 46 já estava coberto de fato pelas ondas anteriores — **código** (E1.1 + campo nos modais do mapa na correção de 2026-08-02), nome, área automática e cor. Busca por nome de Distrito e camada "Distritos/Limites" já operavam.

#### ~~T2.2 — Recalcular testadas e ocupação após desmembramento/unificação~~
**Itens PoC:** 2.7-59, 2.7-60 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

Novo [PosEdicaoLoteService](../app/Services/Gis/PosEdicaoLoteService.php), chamado ao confirmar **desmembramento** (nas DUAS partes) e **unificação**:
1. **Testadas**: `ST_Intersection` do perímetro (`ST_Boundary`) com buffer de 15 m das **seções de logradouro** (fallback: eixo dos logradouros, p/ município sem seção), com `ST_CollectionExtract` blindando o resultado; a maior vira `principal` e alimenta `main_facade_length` — **na unificação isso substitui a soma cega das testadas antigas** (que contava divisa interna extinta);
2. **Ocupação**: derivada de fato (tem edificação = `construido`; senão `baldio`);
3. **Situação na quadra**: campo do município (D6) — o sistema **sugere** pelo nº de logradouros distintos com testada (0=Encravado, 1=Meio de Quadra, 2+=Esquina), casa o rótulo com o vocabulário do kit municipal e grava a sugestão; a notificação orienta conferir/ajustar na ficha (decisão do usuário: sugestão confirmável, não automação cega).

#### ~~T2.3 — Logradouro clicável no mapa público~~
**Item PoC:** 3-10 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

Clique prioriza lote > ponto > **logradouro** > zona (a linha ganha do polígono de fundo). Modal `ficha-logradouro-publica`: código, extensão e nº de seções + lista de seções com **código composto, lado (rótulo do município), pavimentação (campo do kit) e as fotos da T1.7** (miniaturas clicáveis). Listener `abrirFichaLogradouro` no `HasFichaImovelPublico`.

#### ~~T2.4 — Viabilidade de Parcelamento/Desmembramento no público~~
**Item PoC:** 3-12 · **Status:** ✅ Concluído
**Concluído em:** 2026-08-03

Botão "Viabilidade de Parcelamento" na ficha pública do imóvel → mesmo motor da intranet (`analisarParcelamento`), modal com veredito + parecer técnico e **"Gerar PDF Oficial"** que captura o croqui do mapa (lote destacado) e emite via `generateParcelamentoPdf` — **com protocolo `/v/{protocolo}` e croqui persistido** (T1.5) para reimpressão.

#### T2.5 — Recodificação em cascata + entidade Piscina
**Item PoC:** 2.7-61 · **Esforço:** 14–24 h · **Status:** ⏳ Pendente

Ação "Recodificar" (mapa + Resource) para Lote (inscrição → propaga a unidades/edificações/testadas), Quadra, Logradouro/Seções, Setor, Distrito, Bairro, Zoneamento e Meio-fio — transação única + registro Antes/Depois no activitylog. Incluir entidade **Piscina** leve (molde `MeioFio`, polígono) para cobrir a lista do edital.

#### ~~T2.7 — Performance do carregamento de camadas do mapa (N+1 do geo_json + gzip)~~
**Origem:** lentidão relatada pelo usuário (2026-08-06) em municípios de 5-6 mil lotes · **Status:** ✅ Concluído
**Concluído em:** 2026-08-06 · Medido: lotes de Bom Princípio (6.610) **11,9 s → 0,31 s** (38×); Santa Cecília (4.435) **9,0 s → 0,21 s**; edificações 2,5 s → 0,77 s. Causa: accessor `geo_json` = 1 query/registro, acessado 3× por item nos loops do `MapDataController` (~20 mil queries por carregamento). Correção: `ST_AsGeoJSON` em **1 query por camada** (`buildFeatureCollection` + cases lotes/patrimônios/REURB). Extra: middleware [ComprimirJsonMapa](../app/Http/Middleware/ComprimirJsonMapa.php) nas rotas `gis-data`/`advanced-query` (3,7 MB → 0,47 MB na rede; 7/7 no teste). **Evolução futura registrada:** vector tiles (MVT) para municípios 50 mil+ lotes.

#### ~~T2.6 — Modos de importação GIS (Adicionar / Atualizar / Substituir) + campos custom nas 9 camadas~~
**Origem:** pedido do usuário em 2026-08-04 (reimportação pós-edição no QGIS) · **Status:** ✅ Concluído
**Concluído em:** 2026-08-04 · Teste: 23/23 (tinker c/ rollback — 3 modos, reconexão de unidade/edificação/coleta/quadra→lote, FK discovery com processos_digitais, rollback em falha)

(a) Importador do `TenantResource` passa a ler campos customizados nas **9 camadas** do seletor (antes só lote/edificação/unidade). (b) Select "Modo de importação": **Adicionar** (comportamento atual), **Atualizar** (upsert por `tenant_id`+`sequential_id` — preserva vínculos de processos/unidades/coletas; caso de uso: atualização externa no QGIS) e **Substituir** (soft-delete de toda a camada antes de importar, na mesma transação — falhou, nada é apagado). No Substituir, **reconexão automática dos filhos por `sequential_id`**: FKs descobertas via `information_schema` (unidades, edificações, testadas, processos digitais, faces etc.) + `coleta_imobiliaria` polimórfica passam a apontar para o registro novo de mesmo número. Extração da lógica para `App\Services\Gis\ImportadorGisService` (testável). Decisões do usuário: 3 modos + reconectar por sequential_id.

---

## Onda 8 — App de Coletas: fluxo completo de campo ✅ CONCLUÍDA em 2026-08-04

> **Status:** ✅ Implementada e testada — 24/24 no web (tinker, transação revertida: kit, pull c/ proprietário,
> map/data por região, push blindado, antes→depois c/ merge, relatório + PDF de 1,2MB renderizado) +
> 21/21 na simulação Node (helpers do app) + 8 arquivos do app validados (esbuild/JSX).
> Kit aplicado nas 3 tenants locais (2 campos novos cada). **Implantação VPS:** `php artisan migrate`
> (colunas `alteracoes` + `obrigatorio_coleta`) + `php artisan db:seed --class=KitCamposCustomizadosSeeder` + rebuild Expo.
>
> **Correções pós-entrega → SPEC FINAL do Boletim (2026-08-04, feedback do usuário):** TODOS os campos
> de lote/edificação/unidade listados no Boletim, cada um com **3 estados** — "Não usar" / "Apenas
> leitura" / "Preencher em campo" (+ Obrigatório só no Preencher, independente do sistema web).
> Inclui os dados do cadastro oficial como leitura configurável (área/testada do lote; endereço,
> inscrição, proprietário atual e fiscais da unidade — estes sem opção de Preencher: divergência
> entra por campo customizado). Colunas novas `obrigatorio_coleta` + `leitura_coleta` (campo_dominios
> e campos_customizados), `coleta_campos_base_config`/`coleta_leitura` no tenant.data, payload com
> `leitura` em tudo + `campos_base` legado derivado. Testes: 7/7 + 8/8 + 13/13 web · 10/10 + 21/21 Node.
>
> **Ajustes do teste em campo (2026-08-05):** botões do mapa do app reorganizados (Sync/Atualizar no canto
> inferior direito — fim das sobreposições); Salvar desabilitado com status "Não visitado"; **pin vermelho de
> inconformidade** na camada Coleta do mapa web (ponto GPS do coletor, popup com a descrição — 5/5 no teste).
> Decisão: divergência de proprietário PERMANECE por slug do kit (convenção documentada), sem status novo.

**Origem:** fluxo de 8 passos descrito pelo usuário (boletim → regiões → coleta offline → validação → tributário). Passos 1/2/4/5/7 já atendidos (boletim configurável, regiões, regras de status no app, offline, Produtividade). Frentes desta onda:

- **A — Proprietário divergente (decisão do usuário: via campo customizado):** pull envia `proprietario_nome`/`proprietario_cpf_cnpj` da unidade (pessoas com fallback no JSON tributário); kit ganha 2 campos custom de unidade (`proprietario_divergente`, `cpf_cnpj_divergente`, na_coleta); app exibe o dado oficial para comparação; divergências destacadas no Relatório de Validação. Convenção: divergência que impede confirmar o cadastro → status `inconformidade`.
- **B — Região no app (decisão: leveza primeiro):** `map/data?layer=lotes` filtrado pela região do coletor (só os delegados desenham; camada quadras segue integral como referência); push blindado (lote fora da região → rejeitado com aviso, sem derrubar o envio); app mostra o aviso.
- **C — Progresso do coletor (passo 6):** tela "Meu Progresso" no menu (total/coletados/pendentes/inconformidades/restantes/% + detalhe por quadra, 100% offline via SQLite) + contador resumido no mapa.
- **D — Validação e entrega (passos 7-8):** push grava antes→depois por campo em `coleta_imobiliaria.alteracoes` (jsonb); página **Validação da Coleta** (filtros campanha/coletor/status/período) com export PDF/Excel incluindo divergências de proprietário; ação "Marcar campanha como validada" (quem/quando no tenant.data) — marco antes da integração tributária.
- **Fora da onda:** escrita no sistema tributário (1º conector real) e reset/nova campanha.

---

## Etapa 3 — Complexa (16–24 h) → 122/124 (98,4%)

#### T3.1 — WMS servidor no endpoint OGC
**Item PoC:** 1-3 · **Esforço:** 16–24 h · **Status:** ⏳ Pendente

`OgcController` hoje só serve WFS. Implementar `GetCapabilities` + `GetMap` WMS. Rota recomendada para a PoC: renderização PNG em PHP (GD) a partir do PostGIS. Evolução futura: sidecar GeoServer/QGIS Server com proxy autenticado por tenant.

> **T3.2 (EAV)** foi movido para o **Plano de Ação Futura** como item **F2** (decisão D2 de 2026-07-08) — ver seção logo abaixo dos contadores.

---

## Etapa 4 — Ensaio da demonstração (paralela às Etapas 2–3)

#### T4.1 — Roteiro final + ensaio cronometrado
**Status:** ⏳ Pendente

- Roteiro item-a-item com responsável e caminho de clique;
- Ensaio cronometrado (a PoC tem 10 dias úteis a partir da convocação — tudo pronto **antes** da convocação);
- Checklist impresso espelhando as tabelas do edital para a Comissão;
- Plano B por item: mapear o que cabe na janela de 60 dias pós-contrato caso falhe ao vivo.

---

## Itens extra-backlog — Processos Digitais (implantação Bom Princípio/RS)

> Melhorias no motor BPMN solicitadas em 2026-07-14 para a implantação dos Processos Digitais
> em Bom Princípio/RS. Beneficiam todos os tenants (motor único).

#### PD-1 — Requerimento assinado antes da análise
**Status:** ✅ Concluído
**Concluído em:** 2026-07-14

- Check `exige_requerimento` no `BpmnFluxo` + `template_requerimento` (RichEditor com placeholders `{{nome}}`, `{{cpf}}`, `{{protocolo}}`, `{{campo:slug}}`…);
- **Etapa configurável:** toggle `exige_requerimento` na `BpmnEtapa` (etapas do solicitante) define ONDE o requerimento é exigido; sem marcação, vale a 1ª etapa. `{{campo:slug}}` resolve de todas as etapas já respondidas;
- Na etapa do requerimento, o processo fica `aguardando_solicitante`, em duas fases: **1)** o cidadão preenche/anexa os campos do formulário e envia (salva os dados); **2)** só então a seção do requerimento aparece — gera o PDF (preenchido com os dados enviados), baixa, assina fora do sistema e anexa o PDF assinado (só o assinado aparece na lista de documentos);
- Só com o requerimento assinado anexado o processo segue para análise. Fluxos sem o check seguem inalterados;
- Anexos `requerimento_gerado` (auditoria) e `requerimento_assinado` (versionados via `versao`/`anexo_origem_id`).

#### PD-2 — Checklist de análise por anexo
**Status:** ✅ Concluído
**Concluído em:** 2026-07-14

- Aprovar/reprovar cada anexo individualmente (`processo_anexos.status_analise` + `observacao_analise` + auditoria de quem/quando), apenas para anexos (`formulario` e `requerimento_assinado`) — campos de formulário ficam de fora;
- O analista marca os itens e julga a etapa normalmente; aprovar a etapa exige o checklist COMPLETO (item pendente ou reprovado bloqueia); reprovar concatena a lista de itens reprovados ao parecer (flui para tramitação e e-mail);
- Na correção, o cidadão só substitui os anexos reprovados (aprovados ficam travados); substituição = nova versão e o item volta a `pendente`;
- Vínculo campo↔anexo por nova coluna `campo_slug`.

#### PD-3 — Campos condicionais no construtor de formulário + blocos condicionais no requerimento
**Status:** ✅ Concluído
**Concluído em:** 2026-07-14

- Novo tipo de campo **"Seleção Única"** (select — ex.: Estado Civil) no construtor de formulário das etapas;
- **Exibição condicional** em qualquer campo: "Mostrar somente se o campo [X] tiver o valor [Y]" (gatilho = Seleção Única ou Múltipla Escolha, mesma etapa; campo oculto não é validado nem exigido);
- **Blocos condicionais no template do requerimento**: `{{#se campo:estado-civil = Casado}} ...texto com {{campo:nome-do-conjuge}}... {{/se}}` (operadores `=`, `!=` e `contem`).

#### PT-1 — Página Portal de links por município (`/portal/{slug}`)
**Status:** ✅ Concluído
**Concluído em:** 2026-07-14

- Landing pública responsiva por tenant (brasão + cor do município) para a prefeitura usar como link no site oficial;
- 4 botões: login do painel cidadão (`/cidadao/login`), cadastro (`/cidadao/register?prefeitura={slug}`), mapa público anônimo (`/cidadao/mapa-publico?t={slug}`) e vídeo tutorial global (URL em `ApiSetting name='Portal'`, chave `VIDEO_TUTORIAL_URL`, nova aba; botão oculto sem config);
- Action "Link do Portal" no `TenantResource` (admin) + remoção do provider fantasma `PortalPanelProvider` do bootstrap;
- Login do cidadão "limpo" (page custom `LoginCidadao` sem o link "registre-se" + remoção dos hooks "Acessar mapa sem cadastro") — os atalhos levavam ao seletor de município; a porta de entrada agora é o Portal. Rotas de registro/mapa seguem ativas;
- Registro sem o link "Trocar" prefeitura (passo 2) — a prefeitura vem fixada da URL do Portal; faixa mostra só o nome (sem o rótulo "Prefeitura"); o link "faça login na sua conta" carrega o `?prefeitura=` para o login manter o brasão;
- Brasão do município no login/cadastro/navbar do painel cidadão (`logo-cidadao.blade.php`; login do Portal passa `?prefeitura={slug}`; fallback = logo SIGWEB);
- Reset de senha em PT-BR com a marca do município e envio SÍNCRONO via Resend (`ResetSenhaNotification` + bind no container — a notification original do Filament é ShouldQueue e ficava presa na fila sem worker).

#### PD-4 — Refinamentos de cadastro e processos (CPF/CNPJ, múltipla escolha, correção focada, layout de documentos)
**Status:** ✅ Concluído
**Concluído em:** 2026-07-14

- Cadastro do cidadão com **CPF ou CNPJ** (máscara dinâmica; CNPJ → `Pessoa type=juridica`, dedup por `cnpj`);
- Construtor de campos: máscaras **CNPJ** e **CPF ou CNPJ (dinâmica)** no bloco "Campo com Máscara";
- "Múltipla Escolha" renderiza como **Select multiple** (bug do CheckboxList: marcar uma opção marcava todas);
- Correção focada: com itens reprovados no checklist, o cidadão vê **só os campos reprovados** (merge do save aprofundado para 2 níveis — etapa + campo — para não apagar o restante); reprovado fora do formulário (ex.: requerimento assinado) → nenhum campo da etapa, só a seção do item; botões do checklist só em item pendente (decidido → "↺ desfazer");
- Documentos na View do analista em **largura total**, um anexo por linha com Aprovar/Reprovar/Anotar na mesma linha.

#### PD-5 — Exigência de aprovação de anexos configurável por etapa
**Status:** ✅ Concluído
**Concluído em:** 2026-07-22

- Nova config `bpmn_etapas.aprovacao_anexos` (etapas do analista): **`nao_exige`** (aprova a etapa mesmo com anexos pendentes), **`novos`** (exige aprovar só os anexos enviados desde a última etapa de análise — ex.: comprovante de pagamento no Financeiro) e **`todos`** (exige o checklist completo do processo — ex.: análise final da Engenharia; default = comportamento atual);
- Documento **reprovado bloqueia sempre**, em qualquer modo (devolver ao cidadão ou desfazer a reprova);
- Caso de uso (Aprovação de Projeto Bom Princípio): Financeiro 1ª passagem `nao_exige` → cidadão paga/anexa comprovante → Financeiro `novos` aprova só o comprovante → Engenharia `todos` aprova os documentos iniciais e conclui.

#### PD-6 — Upload múltiplo no construtor de formulário das etapas
**Status:** ✅ Concluído
**Concluído em:** 2026-07-23

- Toggle "Permitir vários arquivos" (+ máximo configurável) no bloco "Upload de Arquivo" do construtor;
- Cada arquivo vira um `ProcessoAnexo` próprio (aprovável/reprovável individualmente no checklist PD-2);
- No campo múltiplo não há cadeia de versões: arquivo removido pelo cidadão (não aprovado) é soft-deletado na sincronização; travamento do campo na correção = todos aprovados; reprovado em qualquer arquivo exige substituição.

---

## Release 67 — Coleta cadastral (campos por município, boletim, regiões, integração fiscal)

> Ajustes no app de coleta (CTM) e no gerenciador web, solicitados em 2026-07-29 (antes da PoC Tangará 17/08).
> Beneficiam todos os municípios. Plano completo em `C:\Users\jesse\.claude\plans\precisamos-melhorar-alguns-aspectos-reflective-hopcroft.md`.
>
> **Organização do menu (revisada em 2026-07-29):** customização de campos e integração tributária **não** são da coleta —
> valem para o sistema todo. Grupo **Customizações** (Campos Customizados + Campos Padrão do Sistema) ·
> grupo **Configurações** (Integração Tributária) · grupo **Coleta cadastral** apenas Boletim de Coleta + Regiões dos Cadastradores.

#### R67-1 — Campos customizados por município (lotes, edificações, unidades)
**Status:** ✅ Concluído
**Concluído em:** 2026-07-29

- Entidade `CampoCustomizado` (tenant, entidade, label, slug snake_case, tipo [texto|numero|selecao|multipla|data|sim_nao], opções, obrigatório, na_coleta, ordem, ativo) + coluna `dados_customizados` JSON nas 3 tabelas;
- Presentes em: formulários web (LoteResource, modais do mapa, RelationManagers), boletim do app, exports (Excel/PDF/XML), BIC e **importação GIS** (property do GeoJSON com o slug → valor).

#### R67-2 — Campos padrão "white-label" por município
**Status:** ✅ Concluído
**Concluído em:** 2026-07-29

- `CampoDominio` (tenant, entidade, campo): **rótulo** + **lista de valores** + visibilidade + exigência na coleta, configuráveis por município para os campos taxonômicos (`ocupacao`, `situacao_quadra`, `tipo`, `tp_construcao`, `caracteristica_construcao`, `estado_conservacao`, `pavimento`);
- A coluna no banco **não muda** (relatórios, PGV, mapa e contrato do app seguem estáveis); fallback para o padrão do sistema quando não configurado;
- O vocabulário de `estado_conservacao` passa a servir também à Depreciação do PGV.

**Correção 2026-07-30 —** `lotes.ocupacao` e `lotes.situacao_quadra` eram `enum()` (varchar + CHECK no PostgreSQL) e recusavam o vocabulário do município ao salvar o lote. Migration `2026_07_30_100000_drop_dominio_check_constraints_from_lotes` remove as duas restrições (mantém `lotes_status_cadastro_check`, que é estrutural). Junto: `CampoDominioService::rotuloValor()` traduz valores gravados nas telas/PDFs/exports, `aplicar()` preserva no Select o valor legado fora da nova lista, a Planta de Quadra passa a contar por vocabulário e o modal "Criar Lote" do mapa (que ficara com a lista fixa) foi plumbado.

#### R67-3 — Boletim de coleta configurável
**Status:** ✅ Concluído
**Concluído em:** 2026-07-29

- `ConfiguracaoColetaPage` (painel do município): quais campos BASE são exigidos no boletim (lote/edificação) + rótulos/vocabulários + mapa fiscal;
- Novo `GET /api/coleta/config`: o app monta o boletim inteiro a partir do payload (campos base, campos padrão com rótulo/opções do município, campos customizados, região). Obrigatoriedade validada no app — o push nunca rejeita coleta offline.

#### R67-4 — Regiões por cadastrador
**Status:** ✅ Concluído
**Concluído em:** 2026-07-29

- `ColetaAtribuicao` (tenant, user, período, quadras[], ativo) + Resource no painel do município, com **seleção das quadras pelo MAPA** (clique alterna; quadra de outro cadastrador no mesmo período aparece em vermelho e é bloqueada — no mapa e no servidor) e **mapa geral na lista** com uma cor por cadastrador;
- `pull`/`nearest` filtram por quadra da atribuição vigente: **sem atribuição → pull vazio** (Master/Manager baixam tudo); resolve o travamento do app por carregar toda a base;
- Índice novo em `lotes.quadra_id`. ⚠️ Implantação: atribuir região a todos os cadastradores antes de atualizar o app.

#### R67-5 — Integração fiscal por SISTEMA (catálogo global no /admin)
**Status:** ✅ Concluído
**Concluído em:** 2026-07-29 · **Revisado em:** 2026-07-30

- **Revisão 2026-07-30 (decisão do usuário):** o de/para é característica do SISTEMA (Betha, GOVBR, IPM, Fiorilli…), não da prefeitura — e deixá-lo editável no painel do município era risco operacional. Virou **catálogo global** `SistemaTributario` no painel **/admin** (`SistemaTributarioResource`, Configurações Globais); o `TenantResource` ganhou a seção "Integração Tributária" com o select do sistema (`tenant.data['sistema_tributario_id']`). Export divergente do mesmo fornecedor = outra entrada no catálogo ("Betha — layout 2"). A página por-tenant e a permissão `gerenciar_integracao_fiscal` foram removidas;
- O JSON bruto (`dados_tributarios`) continua sendo a verdade; as 13 colunas fiscais seguem como **projeção canônica**; sem sistema apontado = passthrough. Aplicado em `tributario:importar`, sync da API e BIC (extras);
- **Futuro (fora desta release):** transformações/fórmulas, agendamento, **retorno SIGWEB → sistema tributário** (divergências da coleta), **BIC estruturalmente customizável**, tipo de campo "foto".

#### R67-10 — Chave simulação ↔ produção + arquitetura de conectores de API
**Status:** ✅ Concluído
**Concluído em:** 2026-07-30

- **A chave:** `tenant.data['tributario_modo']` (`simulacao` | `producao`) — Select na seção "Integração Tributária" do `TenantResource`; "Produção" desabilitado enquanto o sistema escolhido não tiver conector implementado;
- **Onde as APIs se configuram (2 níveis):** o **conector** (driver — endpoints/autenticação do fornecedor) é do SISTEMA, no catálogo (`sistemas_tributarios.driver` → registry `IntegraPrefeituraService::DRIVERS`, interface `Drivers\TributarioDriver`); as **credenciais** (URL/token da instância) são da PREFEITURA (`tenant.data['tributario_api']`, campos no TenantResource — molde da seção e-SUS);
- `IntegraPrefeituraService` virou despachante: produção → driver + credenciais; senão → mock. Falha da API real = log + vazio, **nunca** fallback silencioso ao mock. Selo da prefeitura reflete o modo ("— produção (API)"). Registry vazio: 1ª entrada será o `GovbrDriver` (Bom Princípio/RS, P1 do backlog) quando houver credenciais + documentação;
- Testado com driver fake: resolução pelo catálogo, credenciais chegando ao conector, payload da API traduzido pelo de/para, produção-sem-driver caindo honestamente na simulação e falha de API sem contaminar com o mock;
- **Ponto de ligação configurável (adição do mesmo dia):** `sistemas_tributarios.chave_ligacao` (código tributário padrão | inscrição imobiliária) declara qual campo da unidade localiza o imóvel no fornecedor. Novo `buscarImovel(array $identificadores)` — os 8 call sites passam código E inscrição e o service escolhe pelo sistema (o driver recebe chave+valor; o mock busca pelo nome de origem da chave via de/para invertido). Testado: sistema por inscrição acha pela inscrição e recusa busca só-por-código; sistema por código inalterado; syncs não apagam mais a inscrição quando o payload não a traz.

#### R67-9 — Simulação Tributária pelo /admin + de/para no ponto único
**Status:** ✅ Concluído
**Concluído em:** 2026-07-30

- Ação **"Simulação Tributária"** na listagem do `TenantResource`: upload do JSON (vira `storage/app/mocks/{slug}.json`, com validação/normalização e diagnóstico pós-de/para) + toggle "importar todos agora" (`tributario:importar`). O formulário da prefeitura mostra só o STATUS do arquivo;
- **Correção de furo:** o de/para só rodava em 1 dos 4 consumidores do `IntegraPrefeituraService`. Agora `buscarImovelPorCodigo()` aplica `MapaFiscalService::aplicar()` internamente (ponto único) e a **busca por código aceita o nome de origem** (de/para invertido) — JSON "cara do sistema" (ex.: `cdImovel`, `nrInscricao`) funciona em busca, sincronização e importação em massa (que também passou a traduzir ANTES dos lookups);
- **Prefeitura enxerga a integração:** selo "Sistema — simulação (JSON)" nas seções Dados Fiscais dos modais e no modal Sincronizar (via `rotuloIntegracao()`), sem expor configuração;
- Testado ponta a ponta: mock estilo IPM com sistema de/para → busca acha pelo código de origem, canônico preenchido, bruto preservado, extras no BIC, `tributario:importar --dry-run` casa pela inscrição traduzida; sem sistema apontado = passthrough como antes.

#### R67-8 — Campos Padrão por entidade + Unidade Imobiliária white-label
**Status:** ✅ Concluído
**Concluído em:** 2026-07-30

- "Campos Padrão do Sistema" virou **tabela Filament nativa** (busca, filtro por entidade e por "personalizado", agrupamento por entidade, toggle "usar este campo" inline) — linhas de `campo_dominios` semeadas sob demanda com valores-fallback; a ação "Personalizar" abre o detalhe da entidade (`campos-padrao/{entidade}`) com os fieldsets de rótulo/lista/uso. Extensível: nova entidade (ex.: Árvore) = registrar em `CampoDominioService::PADROES`;
- **Unidade Imobiliária** entrou nos campos padrão: as 13 colunas fiscais com **rótulo + ocultar** (sem lista de valores — decisão do usuário: os valores vêm do sistema tributário). Inputs dos modais Cadastrar/Editar Unidade agora nascem de `componentesFiscaisUnidade()` (white-label aplicado); campos padrão da unidade ficam **fora** do Boletim de Coleta e do app (`ENTIDADES_NA_COLETA`).

#### R67-6 — Resumo de Produtividade por região designada
**Status:** ✅ Concluído
**Concluído em:** 2026-07-30

- `ProdutividadePage` deixa de filtrar por **Setor Fiscal** (recorte que não corresponde ao trabalho de campo) e passa a filtrar por **data inicial + data final + cadastrador**;
- A tabela mostra **apenas as quadras designadas** (`ColetaAtribuicao` que se sobrepõe ao período), com cadastrador, período da atribuição, total de lotes, coletados no período e **percentual cumprido** de cada quadra;
- Botão **Exportar PDF** no topo da página (blade `pdf/produtividade-quadras.blade.php`, paisagem), respeitando os filtros da tela.

#### R67-11 — Operadores do painel /admin (delegar tarefas sem dar acesso total)
**Status:** ✅ Concluído
**Concluído em:** 2026-07-30

- **Decisão (usuário, 2026-07-30):** manter **um único painel** `/admin` com papéis, em vez de criar um painel de suporte separado — as tarefas a delegar (importar GIS, editar prefeitura) vivem dentro do `TenantResource`, e duplicá-lo custaria manutenção eterna;
- Papel **global** `Operador` (`roles.tenant_id = null`, mesmo mecanismo do Master) + 9 permissões `admin_*` marcadas **por usuário** (`AdminAcessoSeeder`). `User::papelAdmin()/isMaster()/podeNoAdmin()`; `canAccessPanel('admin')` passou a aceitar Master **ou** Operador;
- Novo **`UsuarioAdminResource`** (Configurações Globais → Usuários do Admin), **visível só para o Master**: cria/edita/exclui operadores e marca as capacidades (checkbox com descrição). `User::sincronizarAcessoAdmin()` mexe só nos vínculos globais — papéis que a pessoa tenha **dentro de prefeituras** (Manager) ficam intactos, o que `syncRoles()` do Spatie não garante com teams ligado;
- Travas: `TenantPolicy` (excluir prefeitura = só Master), `ApiSettingPolicy` e `SistemaTributarioPolicy` (só Master); ações do `TenantResource` com `visible()` por capacidade; seções e-SUS e Integração Tributária ocultas para o Operador;
- **Correção de furo achada no caminho:** o `EditTenant` não preservava as chaves do JSON `tenant.data` ausentes do formulário — salvar a prefeitura apagava `coleta_campos_base` e credenciais de seções ocultas. `mutateFormDataBeforeSave` agora faz merge com o `data` atual;
- Testado (38 asserções, transação revertida): operador entra no /admin e só executa o marcado, sem acesso a APIs/sistemas/exclusão; revogação; Master irrestrito; papel de prefeitura preservado; merge do JSON. ⚠️ **Implantação:** `php artisan db:seed --class=AdminAcessoSeeder`.

#### R67-12 — Importação GIS: referência não encontrada não pode virar vínculo cruzado
**Status:** ✅ Concluído
**Concluído em:** 2026-07-30

- O `resolveRelacionamento` do importador traduz o id do GeoJSON pelo par (`tenant_id`, `sequential_id`) — é isso que permite cada município ter numeração própria começando em 1. Quando **não achava**, caía no número do JSON como id global e podia amarrar o registro na entidade de **outra prefeitura** (a PK é sequência global), silenciosamente;
- Agora vínculo não resolvido fica **nulo** e a notificação final vira aviso **persistente** com a contagem por entidade ("⚠️ 38 × Quadra — importe a camada superior primeiro"). Sintoma típico: hierarquia importada fora de ordem;
- Testado ponta a ponta pela ação real (transação revertida): município novo com quadras 1–2, lotes apontando para elas ficam corretos, lote apontando para quadra inexistente fica com `quadra_id` nulo (antes apontaria para a quadra id 99 da prefeitura 1), campo customizado (`numero_teste`) importado do GeoJSON e base sem nenhum vínculo cruzado.

---

## Pós-PoC — releases especificadas e paradas

#### 🗂️ Nuvem (S3/CloudFront) + Pacote de dados + Zerar dados no /admin
**Status:** 📋 Especificado, **não implementado** — retomar depois da PoC de Tangará
**Especificado em:** 2026-07-30 · **Documento:** [releaseNuvemFerramentasAdmin.md](releaseNuvemFerramentasAdmin.md)

- Três frentes do painel `/admin`, levantadas no código e no banco reais: **(1)** armazenamento em nuvem — hoje 15,4 GB de ortofoto/LiDAR em `public/` e 126 MB de anexos, com anexos de processos e documentos de pessoas **publicamente acessíveis por URL sem login**; **(2)** pacote de dados por prefeitura (portabilidade, **não** backup); **(3)** zerar dados por conjuntos coerentes, nunca tabela a tabela;
- Decisões já tomadas com o usuário: AWS S3 + CloudFront, credenciais em `api_settings` (nunca `.env`), pacote de entrega (planilhas + camadas + anexos + PDFs) com link temporário, exclusão em cascata com previsão de impacto, e privacidade dos anexos corrigida junto;
- **Bloqueador conhecido:** o sistema **não tem backup automático**; a mitigação da ferramenta de zerar é operacional (`pg_dump` agendado) e a ferramenta só libera se houver pacote gerado nos últimos 7 dias.

#### 🐛 Bug corrigido no levantamento — exclusão em massa de lotes pelo mapa
**Status:** ✅ Concluído
**Concluído em:** 2026-07-30

- [MapaFullscreen.php:878](../app/Filament/Pages/MapaFullscreen.php#L878) fazia `$lote->query()->delete()` — `Model::query()` é **estático** e devolve builder **sem WHERE**: excluir um lote pelo mapa soft-deletava **todos os lotes da prefeitura ativa** (recuperável pelo `TrashedFilter`, mas silencioso). Corrigido para `$lote->delete()`;
- Testado em transação revertida: 4.434 → 4.433 lotes ativos (exatamente o alvo), demais prefeituras intactas.

---

## Pontos fortes a destacar na demonstração

1. Estatísticas com **gráficos plotados no mapa** (centroide de cada bairro) — item 2.6-41;
2. **Impressão A0–A4** retrato/paisagem direto do mapa (2.4-25 a 29);
3. **Exportação SHP** por camada + relatórios em 4 formatos (2.4-30/31);
4. **Código de autenticação com verificação pública** (`/v/{protocolo}` + SHA-256) nas viabilidades;
5. **Auditoria com croqui Antes/Depois** de geometria (2.10-89);
6. Busca unificada por 10+ critérios num único campo (2.1-3 a 11);
7. Navegação completa com **visão anterior** (histórico de 50 estados).
