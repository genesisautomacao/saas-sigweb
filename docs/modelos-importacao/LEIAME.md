# Modelos de importação — Seções de Logradouro, Testadas de Lote e Faces de Quadra

Arquivos GeoJSON aceitos pelo **/admin → Prefeituras → Importar Mapa (GIS)** (Release 84).
Os três modelos desta pasta foram gerados com geometria real de Santa Cecília e testados na
importação; os números (`id`, `logradouro_id`…) são exemplos e precisam ser trocados pelos da
prefeitura de destino.

## Regras que valem para os três

- **Coordenadas em WGS 84 (EPSG:4326)**, longitude e latitude (o padrão do GeoJSON).
- **Geometria:** `LineString` ou `MultiLineString`.
- **`id`** = o **número do registro na prefeitura** (não é o id do banco).
  - Modo **Atualizar**: o `id` que já existe é atualizado; o que não existe é criado.
  - Feição **nova**: deixe o `id` vazio ou use um número ainda não usado.
- **Vínculos** (`logradouro_id`, `lote_id`, `quadra_id`…) usam também o **número do pai na
  prefeitura**. Vínculo que não for encontrado fica vazio e aparece no aviso ao final.
- **Ordem de importação** (o pai precisa existir antes):
  Logradouros → **6.1 Seções** → Lotes → **7.1 Testadas** → … → **10. Faces de Quadra**.
- **Campos customizados** da prefeitura entram com o **identificador (slug)** do campo como nome
  da propriedade (ex.: `tipo_pavimentacao`).
- Propriedade que não existe na camada é **ignorada** (não derruba a importação).
- Extensão e comprimento são **recalculados pela geometria** — não precisa mandar.
- O jeito mais fácil de ter um arquivo no formato certo: **/admin → Exportar dados** da própria
  prefeitura (o arquivo exportado é exatamente este formato).

## 6.1 — Seções de Logradouro (`secoes_logradouro.geojson`)

| Propriedade | Obrigatória | Conteúdo |
|---|---|---|
| `id` | recomendada | Número da seção na prefeitura |
| `logradouro_id` | sim | Número do logradouro (rua) |
| `codigo` | recomendada | Código municipal da seção |
| `lado` | não | `par`, `impar` ou `ambos` (também aceita "Par", "Ímpar", "Ambos") |
| `name` | não | Nome/descrição da seção (sem nome = "Sem Nome") |
| `<slug>` | não | Campos customizados de Seção (ex.: `tipo_pavimentacao`) |

⚠️ **Logradouro + código + lado não pode repetir.** Se o arquivo trouxer repetição, nada é
importado e o aviso lista as linhas (ex.: "id 4 repete o id 3").

## 7.1 — Testadas de Lote (`lote_testadas.geojson`)

| Propriedade | Obrigatória | Conteúdo |
|---|---|---|
| `id` | recomendada | Número da testada na prefeitura |
| `lote_id` | sim | Número do lote |
| `logradouro_id` | recomendada | Número do logradouro para onde a testada dá frente |
| `secao_logradouro_id` | não | Número da seção do logradouro |
| `tipo` | não | `principal`, `secundaria`, `lateral` ou `fundos` (sem tipo = `principal`) |
| `comprimento` | não | Metros — **só vale para testada sem geometria**; com geometria, é calculado |
| `<slug>` | não | Campos customizados de Testada |

A geometria pode vir vazia (`"geometry": null`) quando só se conhece o comprimento.

## 10 — Faces de Quadra (`face_quadras.geojson`) — módulo PGV

| Propriedade | Obrigatória | Conteúdo |
|---|---|---|
| `id` | recomendada | Número da face na prefeitura |
| `quadra_id` | sim | Número da quadra |
| `logradouro_id` | recomendada | Número do logradouro da face |
| `zona_id` | não | Número da zona |
| `setor_fiscal_id` | não | Número do setor fiscal |
| `name` | não | Nome/descrição da face |

O valor do m² (`valor_m2_calculado`) **não** é importado: quem calcula é o Motor da PGV.
