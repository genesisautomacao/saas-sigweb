# Roteiro de Demonstração — PoC Tangará/SC (v1)

> **Status:** esqueleto (T0.3). Os caminhos de clique detalhados de cada item são preenchidos no **T4.1**, após as Ondas 1–4.
> **Fonte dos itens:** [lista_txt_poc_tangara.txt](lista_txt_poc_tangara.txt) — 124 itens (6 Características Técnicas + 89 Intranet + 29 Internet).
> **Status por item:** [PocTangara_plano.md](PocTangara_plano.md) · **Backlog:** [pendenciasTangara.md](pendenciasTangara.md)

**Base de demonstração:** tenant `prefeitura-de-santa-cecilia` (id 1) — decisão **D3**.

---

## 1. Checklist de ambiente (verificado em 2026-07-31)

| Dependência | Necessária para | Status |
|---|---|---|
| PHP 8.3.30 / Laravel 12.52 | tudo | ✅ |
| PostgreSQL 18.3 + **PostGIS 3.6.1** | todas as camadas e cálculos | ✅ |
| **ogr2ogr / GDAL 3.13.0** no PATH | item 2.4-30 (exportar camada em SHP) | ✅ |
| **DomPDF 3.1.4** | itens 2.4-25 a 29 (A4→A0), BIC, memorial, viabilidade | ✅ — render testado em A0/A1/A2/A3/A4 paisagem |
| spatie/simple-excel 3.8.1 | item 2.4-31 (XLS/CSV) | ✅ |
| spatie/laravel-activitylog 4.12.3 | item 2.10-89 (auditoria) | ✅ — 13.277 registros na base |
| **AZURE_MAPS_KEY** | item 1-4 (fontes externas) — basemaps Azure Road/Satélite | ⚠️ **vazia** — ver Risco R1 |

---

## 2. Matriz de dependência de dados (inventário de 2026-07-31)

> Itens do edital que só demonstram se houver **dado** na base. Contagens reais do tenant 1.

### ✅ Dado suficiente

| Item | O que a Comissão vai pedir | Dado disponível |
|---|---|---|
| 2.1-3 / 3-1 | Localizar imóvel por endereço | 3.725 lotes com logradouro |
| 2.1-4 / 3-2 | Localizar por inscrição imobiliária | 3.957 unidades com inscrição |
| 2.1-6 / 3-4 | Loteamento, Quadra ou Lote de loteamento | 2 loteamentos · 378 quadras |
| 2.1-7 / 3-5 | Quadra por número | 378 quadras |
| 2.1-8 / 3-6 | Distrito por nome | 1 perímetro urbano |
| 2.1-9 / 3-7 | Setor por nome | 4 setores fiscais (todos com geometria) |
| 2.1-10 / 3-8 | Bairro por nome | 15 bairros |
| 2.1-12 | Dados de Pessoas/Contribuintes | 2.838 pessoas |
| 2.1-13 / 3-9 | Imóvel com imagem frontal e planta | 8 lotes com `foto_frontal` |
| 2.1-14 | Histórico de alterações | 13.277 registros de auditoria |
| 2.1-15 | Memorial Descritivo | 4.434 lotes com geometria |
| 2.1-16 | Planta de Quadra | 378 quadras com lotes |
| 2.1-18 / 3-11 | Dados de Zoneamento | 10 zonas |
| 2.1-20 / 3-13 | Viabilidade para Funcionamento (CNAE × zona) | 169 regras de zoneamento |
| 2.1-21 / 3-14 | Viabilidade com código de autenticação | 3 viabilidades já emitidas |
| 2.7-43 | Edificação com Tipo | 7.894 edificações · 4.746 com `tipo` |
| 2.7-47 | Setor com geometria e área | 4 setores fiscais |
| 2.7-57 | Excluir Meio-fio/Calçada | 3 meio-fios |
| 2.8-74 | Vincular imagem de documento a imóvel | 4 documentos |
| 2.10-83/84/85 | Perfis, Usuários, vínculo | 3 perfis · 7 usuários |

### ⚠️ Dado insuficiente — **precisa ser gerado antes da demo**

| # | Item(ns) | Lacuna | Impacto |
|---|---|---|---|
| **G1** | 2.1-11 / — | **0 pessoas jurídicas** (nenhum CNPJ) | O item pede busca por *"Nome ou parte do Nome ou **CPF/CNPJ**"*. Sem PJ, metade do requisito não demonstra. |
| **G2** | 2.1-21, 3-11, 3-14 | `parametros_urbanos` cobre **2 de 10 zonas** | A Viabilidade e a "zona clicável" (T1.6) mostram parâmetros vazios em 8 zonas. **Bloqueia o valor da Onda 2.** |
| **G3** | 2.7-42 | `ocupacao` e `situacao_quadra` em **4 de 4.434 lotes** | Item 42 pede os dois campos explicitamente. Também esvazia a Planta de Quadra e **a tematização por Valores Únicos da Onda 4** (mapa sairia todo cinza). |
| **G4** | 2.7-43 | `pavimento` em **7 de 7.894 edificações** | Item 43 pede "Pavimento da Unidade". |
| **G5** | 2.7-42, 2.7-44 | **5 testadas** e **2 seções de logradouro** | Item 42 pede "testada(s), Logradouro e Seção de cada testada"; item 44 pede código métrico + lado. Massa insuficiente para demonstrar par/ímpar e para testar o T2.2. |
| **G6** | 2.1-5 / 3-3 | **1 unidade** com `nome_edificio` (chave no JSON `dados_tributarios`) | Demonstra, mas com um único registro — anotar qual é no roteiro final. |

> **G3 é o mais crítico**: a Onda 4 (tematização/heatmap/estatísticas) é a de maior retorno do backlog — 9 itens — e depende de atributos preenchidos. Atributos com boa densidade hoje: `edificacoes.tipo` (4.746), `lotes.status_cadastro`, `lotes.logradouro`, zona, bairro.

---

## 3. Estrutura do roteiro (a detalhar no T4.1)

### Bloco A — Características Técnicas (6 itens)
Navegadores, ausência de plug-in, **OGC WMS+WFS** (item 1-3 → T3.1), fontes externas, SGBD, SaaS.

### Bloco B — Intranet · Consulta de Dados (itens 1–21)
Busca unificada (10+ critérios num só campo), ficha do imóvel, histórico (T1.1), memorial (T1.2), planta de quadra, logradouro com imagens das seções (T1.7), zoneamento, viabilidades (T1.5).

### Bloco C — Intranet · Análise Espacial, Heatmap, Impressão (itens 22–31)
Medição, buffer, **mapa de calor genérico** (T1.11), impressão A4→A0, exportação SHP + PDF/XLS.

### Bloco D — Intranet · Tematização e Estatísticas (itens 32–41)
**Valores únicos** (T1.10), intervalo de classes, cores e nº de intervalos, estatísticas por camada (T1.12), **gráficos plotados no mapa** (ponto forte).

### Bloco E — Intranet · Edição Cartográfica (itens 42–61)
Incluir/geocodificar cada entidade, exclusões, desmembramento/unificação (T2.2), **recodificação em cascata + Piscina** (T2.5).

### Bloco F — Intranet · Atributos, Navegação, Usuários (itens 62–89)
CRUDs, replicar unidades, documentos, navegação com visão anterior, perfis/camadas/funcionalidades, auditoria com croqui Antes/Depois.

### Bloco G — Internet / Público (29 itens)
Buscas públicas, ficha do imóvel (T1.4), logradouro clicável (T2.3), zona clicável (T1.6), viabilidades públicas (T2.4), tematização pública (T1.10), impressão A4/A3, navegação.

---

## 4. Riscos e planos B

| # | Risco | Mitigação |
|---|---|---|
| **R1** | **`AZURE_MAPS_KEY` vazia** — os basemaps Azure Road e Satélite ([mapa-engine.js:83-96](../public/js/gis/mapa-engine.js#L83)) renderizam tiles em branco. Pior: a chave é lida com `env()` dentro de um Blade ([mapa-fullscreen.blade.php:25](../resources/views/filament/pages/mapa-fullscreen.blade.php#L25)) — **com `config:cache` em produção, `env()` fora de arquivo de config retorna vazio mesmo com a chave no `.env`**. | Provisionar chave válida **e** mover para `config/services.php` + `config()`. Alternativa sem custo: ocultar as opções Azure quando não houver chave — OSM e Esri Satélite já atendem o item 1-4. |
| **R2** | **Não há suíte de testes** — só os 2 testes de exemplo do Laravel, e o `Tests\Feature\ExampleTest` falha (espera 200 em `/`, recebe 302 do redirect). Não existe rede de segurança contra regressão. | Teste por onda em **script com transação revertida** (padrão já usado no projeto). Corrigir/remover o `ExampleTest` para o baseline ficar limpo. |
| **R3** | Itens 24, 37, 38 e 3-23 dizem *"qualquer camada que possuir um ou mais **itens de Cadastro**"* — "item de cadastro" é o termo do item 75 (EAV, adiado para F2). | Sustentar a **leitura ampla** (item de cadastro = atributo da camada), que é o que T1.10/T1.11/T1.12 entregam. Preparar a resposta por escrito **antes** da demo — é o ponto onde a Comissão pode apertar. |
| **R4** | Lacunas de dado G1–G6 (seção 2). | Seeder de demonstração idempotente antes da Onda 1. |
| **R5** | Item 1-3 (WMS servidor) é o único ❌ estrutural restante (T3.1, 16–24 h, última onda). | Se não couber no prazo: 122→121 itens. O WFS já atende metade do requisito; argumentar entrega na janela de 60 dias pós-contrato. |

---

*Documento criado em 2026-07-31 (Onda 0). Atualizar ao fim de cada onda.*
