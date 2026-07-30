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
| + Etapa 1 (T1.1–T1.12) | 113/124 | 91,1% | ⏳ |
| + Etapa 2 (T2.1–T2.5) | 121/124 | **97,6%** (meta 95% ✔) | ⏳ |
| + Etapa 3 (T3.1 — WMS) | 122/124 | 98,4% | ⏳ |
| Pós-contrato (F2 — EAV) | 124/124 | 100% | 📋 plano futuro |

> Referência dos itens da PoC: numeração `2.x-N` = seção Intranet do edital; `3-N` = seção Internet/Público; `1-N` = Características Técnicas (conforme `poc_tangara_sc.md` e tabelas do [PocTangara_plano.md](PocTangara_plano.md)).

---

## Decisões registradas (2026-07-08)

- **D1 — Integração Betha Sistemas: ✅ Decidido — dump JSON na PoC.** A demonstração usará dump JSON exportado do Betha → `php artisan tributario:importar` (mesma estratégia validada para o GOVBR em Bom Princípio; ponto de extensão: `IntegraPrefeituraService`). A API do Betha, em regra, só é liberada **após a aprovação da PoC e a assinatura do contrato** → a integração em tempo real está registrada como item futuro **F1**.
- **D2 — EAV (campos customizáveis): ✅ Decidido — pós-PoC.** Por ser o item mais complexo do plano (40–80 h), o ex-T3.2 foi movido para o plano futuro como **F2** (a janela de 60 dias pós-contrato prevista no edital o cobre). O escopo pré-PoC passa a mirar **122/124 (98,4%)** — bem acima da meta de 95%.

## Plano de Ação Futura (pós-PoC / pós-contrato)

> Itens que só entram em desenvolvimento **após a aprovação da PoC e a assinatura do contrato**. Não contam para a demonstração.

#### F1 — Integração em tempo real com a API Betha Sistemas
**Origem:** decisão D1 · **Esforço:** 8–16 h (estimativa inicial; depende da documentação da API) · **Status:** 📋 Futuro (aguarda liberação da API pós-contrato)

- Criar `BethaService` consumindo a API de tributos do Betha (Strategy junto a `IntegraPrefeituraService`/`GovbrService`, configurável por tenant);
- `.env`: `BETHA_API_URL` + credenciais; sincronização periódica → upsert em `unidade_imobiliarias.dados_tributarios` (mesmo contrato do `tributario:importar`);
- Substitui o fluxo de dump JSON usado na PoC sem mudança de modelo de dados.

#### F2 — Itens de Cadastro customizáveis (EAV) *(ex-T3.2)*
**Itens PoC:** 2.8-75, 2.8-76, 2.10-88 (leitura estrita) · **Esforço:** 40–80 h · **Status:** 📋 Futuro (decisão D2 — janela de 60 dias pós-contrato)

Entidades `ItemCadastro` (tenant, camada, nome, tipo: numérico/texto/seleção/multisseleção/multisseleção-com-quantitativo, opções) + `ItemCadastroValor` (polimórfico). Integração obrigatória em: ① ficha/edição da entidade; ② filtro avançado/expressões; ③ tematização (valores únicos + classes); ④ heatmap; ⑤ estatísticas; ⑥ visibilidade por perfil. As integrações ③④⑤ reaproveitam os motores genéricos de T1.10/T1.11/T1.12 — **por isso a ordem das etapas importa**. Registrar o design detalhado aqui antes de iniciar.

---

## Etapa 0 — Preparação (sem código)

#### T0.1 — Tenant de demonstração "Tangará"
**Status:** ⏳ Pendente

Criar tenant, importar base cartográfica de demonstração (ou reusar `prefeitura-de-santa-cecilia`), rodar seeds e `gis:recalcular-metadata`. Garantir dados que exercitem **cada** item do edital: unidade com `nome_edificio`, proprietário com CNPJ, CNAEs nas `ZoneamentoRegra`, fotos de lote, seções de logradouro etc.

#### T0.2 — Validação de dependências do servidor de demo
**Status:** ⏳ Pendente

`ogr2ogr` no PATH (export SHP), chave Azure Maps válida, geração de PDF (DomPDF + jsPDF) e performance das camadas com a base de demo.

#### T0.3 — Roteiro de demonstração v1
**Status:** ⏳ Pendente

Documento item-a-item (124 linhas) com "como demonstrar" cada requisito. Os 95 itens ✅ do baseline já podem ser ensaiados desde já.

---

## Etapa 1 — Quick Wins (33–58 h) → 113/124 (91,1%)

> Converte 5 ❌ e 13 ⚠️ em ✅. Ordenada do mais fácil ao mais difícil.

#### T1.1 — Link "Ver histórico" na ficha do lote
**Item PoC:** 2.1-14 · **Esforço:** 2–3 h · **Status:** ⏳ Pendente

A `AuditoriaPage` já loga tudo (inclusive geometria via `LogsGeometryChanges`); criar atalho na ficha lateral do lote no mapa → auditoria pré-filtrada pelo subject (lote + suas unidades/edificações).

#### T1.2 — Contribuinte confrontante no Memorial Descritivo
**Item PoC:** 2.1-15 · **Esforço:** 2–4 h · **Status:** ⏳ Pendente

`MemorialDescritivoService` já identifica lotes confrontantes; adicionar o nome do proprietário do vizinho (via unidade imobiliária principal) no PDF `memorial_descritivo.blade.php`.

#### T1.3 — Campos `codigo` (métrico) e `lado` na Seção de Logradouro
**Item PoC:** 2.7-44 · **Esforço:** 2–3 h · **Status:** ⏳ Pendente

Migration em `secoes_logradouro` + form/tabela no `SecaoLogradouroResource` e na trait `HasSecaoLogradouroActions`.

#### T1.4 — Foto frontal + croqui na ficha pública do imóvel
**Item PoC:** 3-9 · **Esforço:** 2–4 h · **Status:** ⏳ Pendente

`lotes.foto_frontal` já existe; exibir na ficha do `MapaPublico` (com fallback) e dar destaque ao botão "Planta/Croqui".

#### T1.5 — Viabilidade: metragens + parâmetros no PDF; mapImage na reimpressão
**Itens PoC:** 2.1-21, 3-14 · **Esforço:** 4–6 h · **Status:** ⏳ Pendente

Incluir área do lote, testada e área construída + parâmetros da zona (`ParametroUrbano`) no `viabilidade-template.blade.php`; corrigir `mapImage=null` na reimpressão (`ViabilidadePdfService:142`).

#### T1.6 — Zona clicável no mapa público (parâmetros + usos)
**Item PoC:** 3-11 · **Esforço:** 3–5 h · **Status:** ⏳ Pendente

Handler de `singleclick` para a camada `zonas` no `mapa-cidadao-engine.js` → popup/ficha com `ParametroUrbano` + `ZoneamentoRegra`.

#### T1.7 — Fotos nas Seções de Logradouro
**Item PoC:** 2.1-17 (base para 3-10) · **Esforço:** 4–6 h · **Status:** ⏳ Pendente

Relação de imagens (reusar padrão morphMany `documentos` + FileUpload da UnidadeImobiliaria) + galeria na ficha do logradouro na intranet.

#### T1.8 — Filtro avançado: atributo + espacial combinados
**Item PoC:** 2.1-1 · **Esforço:** 4–8 h · **Status:** ⏳ Pendente

Em `MapDataController::advancedSpatialQuery`, aplicar o WHERE de atributo também no ramo de cruzamento espacial (ex.: "lotes na zona X **com área > Y**" numa só consulta).

#### T1.9 — Delimitadores Quadra e Logradouro
**Item PoC:** 2.1-2 · **Esforço:** 3–5 h · **Status:** ⏳ Pendente

Adicionar Quadra (polígono direto) e Logradouro (`ST_Buffer` do eixo, N metros) como áreas de interesse no filtro avançado e nas estatísticas.

#### T1.10 — Tematização por Valores Únicos (intranet + público)
**Itens PoC:** 2.5-32, 2.5-34, 2.5-37, 3-18, 3-20, 3-23 · **Esforço:** 8–12 h · **Status:** ⏳ Pendente

Novo modo no filtro avançado: agrupa valores distintos do atributo, paleta de cores editável por valor, legenda dinâmica. Reusar o pipeline do choropleth (`mapa-engine.js:6453-6580`); backend devolve mapa `valor → cor`. Portar para `MapaPublico`/`mapa-cidadao-engine.js`. **Maior retorno da etapa (6 itens).**

#### T1.11 — Heatmap genérico por camada
**Item PoC:** 2.3-24 · **Esforço:** 4–6 h · **Status:** ⏳ Pendente

Opção "Mapa de calor" para qualquer camada carregada: pontos diretos; polígonos via centroide. Generalizar o padrão `syncColetaHeatmap` sobre `window.loadedLayers[layer]`.

#### T1.12 — Estatísticas: mais camadas e atributos
**Item PoC:** 2.6-38 (reforça 2.5-37, 3-23) · **Esforço:** 4–6 h · **Status:** ⏳ Pendente

Ampliar `estatisticasAction` além de lotes/edificações/logradouros (árvores, postes, chamados, cemitério…) com agrupamento por qualquer atributo da camada.

---

## Etapa 2 — Intermediária (36–58 h) → 121/124 (97,6%)

#### T2.1 — Distrito formal
**Itens PoC:** 2.7-46, 2.7-54, 2.8-64 · **Esforço:** 4–8 h · **Status:** ⏳ Pendente

Promover `PerimetroUrbano` a Distrito (campos código/nome/área + rótulo "Distrito" na UI e na busca) — recomendado por menor risco, já que camada e busca já operam. Alternativa: entidade dedicada no molde de Bairro.

#### T2.2 — Recalcular testadas e ocupação após desmembramento/unificação
**Itens PoC:** 2.7-59, 2.7-60 · **Esforço:** 8–12 h · **Status:** ⏳ Pendente

Após `processarDesmembramentoLote`/`processarUnificacaoLotes`: recalcular `LoteTestada` (`ST_Intersection` do perímetro do lote com buffer das seções de logradouro) e atualizar `ocupacao`/`situacao_quadra` (esquina/meio de quadra derivável do nº de faces com logradouro).

#### T2.3 — Logradouro clicável no mapa público
**Item PoC:** 3-10 · **Esforço:** 6–8 h · **Status:** ⏳ Pendente

Handler de clique + ficha pública do logradouro (dados + seções + fotos da T1.7), espelhando o padrão `HasFichaImovelPublico`. Depende de T1.7.

#### T2.4 — Viabilidade de Parcelamento/Desmembramento no público
**Item PoC:** 3-12 · **Esforço:** 6–10 h · **Status:** ⏳ Pendente

Portar `ViabilidadeService::analisarParcelamento` + PDF para a ficha pública, com o mesmo protocolo de autenticação `/v/{protocolo}`.

#### T2.5 — Recodificação em cascata + entidade Piscina
**Item PoC:** 2.7-61 · **Esforço:** 14–24 h · **Status:** ⏳ Pendente

Ação "Recodificar" (mapa + Resource) para Lote (inscrição → propaga a unidades/edificações/testadas), Quadra, Logradouro/Seções, Setor, Distrito, Bairro, Zoneamento e Meio-fio — transação única + registro Antes/Depois no activitylog. Incluir entidade **Piscina** leve (molde `MeioFio`, polígono) para cobrir a lista do edital.

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

## Pontos fortes a destacar na demonstração

1. Estatísticas com **gráficos plotados no mapa** (centroide de cada bairro) — item 2.6-41;
2. **Impressão A0–A4** retrato/paisagem direto do mapa (2.4-25 a 29);
3. **Exportação SHP** por camada + relatórios em 4 formatos (2.4-30/31);
4. **Código de autenticação com verificação pública** (`/v/{protocolo}` + SHA-256) nas viabilidades;
5. **Auditoria com croqui Antes/Depois** de geometria (2.10-89);
6. Busca unificada por 10+ critérios num único campo (2.1-3 a 11);
7. Navegação completa com **visão anterior** (histórico de 50 estados).
