# Backlog — SIGWEB

> **Fonte de verdade das pendências do projeto** (desde 2026-10-16; substitui o `pendenciasTangara.md`, hoje em [arquivo/](arquivo/)).
> Só entra aqui o que **ainda está aberto**. Item concluído **sai deste arquivo** e vai para o [Releases.md](../Releases.md) (e a página de novidades da release).
> Tarefa nova identificada durante o desenvolvimento: **registrar aqui antes de executar**.

**Legenda:** ⏳ Pendente · 🔄 Em andamento · 📋 Aguarda decisão ou dependência externa

---

## Desenvolvimento

#### B1 — Recodificação de lote em cascata (+ entidade Piscina) *(ex-T2.5)*
**Status:** ⏳ · **Esforço:** 14–24 h · **Também é:** item 60 da PoC de Nova Santa Rosa

Ação "Recodificar" (no mapa e na tela) para Lote — a inscrição nova propaga para unidades, edificações e testadas —, Quadra, Logradouro/Seções, Setor, Distrito, Bairro, Zoneamento e Meio-fio. Tudo numa transação, com Antes/Depois na Auditoria. Pré-requisito já pronto: coluna `codigo` municipal nas 7 entidades. Entidade **Piscina** leve (molde `MeioFio`, polígono) completa a lista de Tangará.

#### B9 — Parametrizador da inscrição imobiliária
**Status:** 📋 A especificar com o usuário

Montar a inscrição (ex.: distrito.setor.quadra.lote.unidade) a partir das entidades. A base já existe desde a Release 85: **Setor Cadastral** da inscrição (`setores_cadastrais`, `name` = número do setor) com `bairros.setor_cadastral_id` — quadra e lote chegam ao setor pelo bairro. Falta definir: segmentos e máscara por prefeitura, preenchimento com zeros, de onde sai cada segmento (código municipal × sequencial), geração em massa com prévia e relação com a Recodificação (B1).

#### B2 — WMS de verdade no endpoint OGC + WFS que o QGIS aceite *(ex-T3.1)*
**Status:** ⏳ · **Esforço:** 16–24 h

O `OgcController` só tem um WFS mínimo (GetCapabilities + GetFeature em GeoJSON). Falta: (a) **WMS** `GetCapabilities` + `GetMap` (PNG gerado do PostGIS); (b) **WFS que o provedor nativo do QGIS aceite** — `DescribeFeatureType`, capabilities com `<Capability><Request>` (DCPType) e formato de saída anunciado (GML2/GeoJSON). Hoje o paliativo é carregar no QGIS por "Protocolo HTTP(S) → GeoJSON" com a URL do GetFeature. Alternativa: GeoServer/QGIS Server ao lado, com proxy autenticado por prefeitura. Desde a R84-2 o endpoint só serve camadas públicas de prefeitura ativa — manter.

#### B3 — "Zerar dados" de uma prefeitura no /admin
**Status:** ⏳ · **Especificação:** parte 3 de [arquivo/releaseNuvemFerramentasAdmin.md](arquivo/releaseNuvemFerramentasAdmin.md)

Apagar por conjuntos coerentes (nunca tabela a tabela), com prévia de impacto e confirmação, para recomeçar uma importação GIS errada ou uma PoC. O bloqueador da época (sem backup automático) já foi resolvido pelo backup diário (INF-3). As partes 1 e 2 daquela especificação já estão feitas: armazenamento no R2 (R79/R82) e "Exportar dados" (R84-7).

#### B4 — Aviso de renumeração na importação "Substituir" *(ex-R84-9)*
**Status:** ⏳

Quando o mesmo número (`sequential_id`) troca de nome entre o registro antigo e o novo, a reconexão automática liga os filhos à entidade errada (caso Jucurutu, 141 quadras no bairro errado). Mostrar prévia/aviso antes de reconectar.

#### B5 — Impressão do mapa A0 no servidor *(ex-R84-10)*
**Status:** ⏳

Hoje a impressão é feita no navegador (jsPDF) e o A0 pesa. Levar para a fila exige renderizar o mapa no servidor (Chrome headless na VPS).

---

## Operacional (sem código)

#### B6 — Limpeza da VPS após a migração para o bucket *(sobra do INF-2)*
**Status:** ⏳

O código está em produção desde a Release 82. Falta: conferir o Visualizador 3D (LiDAR) servido pelo bucket e então apagar `public/nuvem-pontos` e `public/potree` da VPS; rodar `fotos:limpar-local --executar` (~2,5 GB de fotos de lotes já no bucket). Os 155 arquivos sem referência no banco (~133 MB) ficam na VPS por decisão do usuário.

#### B7 — Corrigir 21 coletas de Santa Cecília *(ex-R84-11)*
**Status:** ⏳ · **Só o tenant de teste**

Coletas criadas em 04/08 com `coletavel_type` gravado com barra dobrada (`App\\Models\\Lote`) — o `coletaVigente` não as enxerga. Um UPDATE resolve. As prefeituras reais foram conferidas em 2026-10-15 e estão certas.

---

## Depende de terceiros

#### B8 — Integração tributária em tempo real (1º conector real) *(ex-F1)*
**Status:** 📋 Aguarda API e credenciais da prefeitura

A arquitetura está pronta (R67-10): driver por sistema no catálogo do /admin + credenciais por prefeitura + modo simulação/produção. Falta o primeiro driver real (GOVBR em Bom Princípio ou Betha), que depende da documentação e do acesso à API. Inclui o sentido de volta (SIGWEB → tributário), decidido como bidirecional.

---

## Ideias futuras (sem compromisso)

- **BCI e croqui montáveis pelo administrador** (campos, ordem, disposição) — também itens 50/51 da PoC de Nova Santa Rosa.
- Tipo de campo "foto" nos campos customizados.
- Reset de campanha da coleta.
- Vector tiles (MVT) no mapa para municípios com 50 mil lotes ou mais.

---

## PoC em andamento

**Nova Santa Rosa/PR** — acompanhamento em [poc_atual/plano_poc_nova_santa_rosa.md](poc_atual/plano_poc_nova_santa_rosa.md) (114 itens do edital em 11 etapas, do mais fácil ao mais difícil, com resumo automático). Os itens da PoC são controlados lá, não neste backlog.
