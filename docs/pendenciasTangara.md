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
- O analista marca os itens e julga a etapa normalmente; aprovar a etapa com item reprovado é bloqueado; reprovar concatena a lista de itens reprovados ao parecer (flui para tramitação e e-mail);
- Na correção, o cidadão só substitui os anexos reprovados (aprovados ficam travados); substituição = nova versão e o item volta a `pendente`;
- Vínculo campo↔anexo por nova coluna `campo_slug`.

---

## Pontos fortes a destacar na demonstração

1. Estatísticas com **gráficos plotados no mapa** (centroide de cada bairro) — item 2.6-41;
2. **Impressão A0–A4** retrato/paisagem direto do mapa (2.4-25 a 29);
3. **Exportação SHP** por camada + relatórios em 4 formatos (2.4-30/31);
4. **Código de autenticação com verificação pública** (`/v/{protocolo}` + SHA-256) nas viabilidades;
5. **Auditoria com croqui Antes/Depois** de geometria (2.10-89);
6. Busca unificada por 10+ critérios num único campo (2.1-3 a 11);
7. Navegação completa com **visão anterior** (histórico de 50 estados).
