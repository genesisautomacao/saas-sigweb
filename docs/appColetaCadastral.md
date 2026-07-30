# App de Coleta Cadastral — contrato de API (Release 67)

> ⚠️ **Este documento é o *delta* da R67.** O contrato **completo e atual** da API do app (todos os
> endpoints, payloads reais, fluxo de sincronização e armadilhas) está em
> **[MensagensAppAssistente.md](MensagensAppAssistente.md)** — é esse o arquivo a enviar ao time do app.
> Em caso de divergência, vale o outro.
>
> Mudança posterior a este texto (2026-07-30): o app teve o escopo reduzido ao cadastro imobiliário —
> os syncs de **árvores** e **manutenções** e as camadas `arvores`/`postes` foram **removidos** da API.

> Documento para o time do aplicativo. Descreve o que mudou no backend na R67 e o que o app
> precisa implementar. Nada do que existia foi removido — as mudanças são aditivas, **exceto**
> o filtro de região no `pull` (ver ⚠️ Ação necessária).

## Resumo do que mudou

| # | Mudança | Impacto no app |
|---|---|---|
| 1 | **Campos customizados por município** nas 3 entidades | novo campo `dados_customizados` (objeto) em lote/unidade/edificação no pull e aceito no push |
| 2 | **Campos padrão white-label** (rótulo e lista de valores por município) | o app deve montar os campos a partir de `/api/coleta/config`, não com listas fixas |
| 3 | **Boletim configurável** | novo endpoint `GET /api/coleta/config` |
| 4 | **Região por cadastrador** | o `pull` passa a devolver **só os lotes da região atribuída**; sem atribuição → vazio |

---

## 1. `GET /api/coleta/config` (novo)

`Authorization: Bearer {token}` · devolve tudo o que o app precisa para montar o boletim.

```jsonc
{
  "campos_base": {                       // campos do sistema que o município EXIGE
    "lote": ["ocupacao", "foto_frontal"],
    "edificacao": ["estado_conservacao"]
  },
  "campos_padrao": {                     // rótulo/valores definidos pelo município
    "lote": {
      "ocupacao": { "label": "Ocupação do Terreno", "opcoes": ["Baldio", "Construído"], "obrigatorio": false }
      // situacao_quadra ausente = município não usa este campo → NÃO exibir
    },
    "edificacao": {
      "tp_construcao": { "label": "Padrão da Obra", "opcoes": ["Alvenaria", "Madeira", "Taipa"], "obrigatorio": true },
      "pavimento":     { "label": "Nº de Pavimentos", "opcoes": [], "obrigatorio": false }
    }
  },
  "campos_customizados": {               // campos criados pelo município
    "lote": [
      { "slug": "padrao_construtivo", "label": "Padrão Construtivo", "tipo": "selecao",
        "opcoes": ["Alto", "Médio", "Baixo"], "obrigatorio": true, "ordem": 1 }
    ],
    "edificacao": [],
    "unidade": []
  },
  "regiao": {
    "modo": "restrita",                  // livre | restrita | sem_atribuicao
    "quadra_ids": [12, 13],
    "atribuicoes": [
      { "data_inicio": "2026-07-29", "data_fim": "2026-08-05",
        "quadras": [{ "id": 12, "name": "Q-12" }], "bairros": [] }
    ]
  }
}
```

### `regiao.modo` — a chave que decide o comportamento do app

| `modo` | Quem | O que o app faz |
|---|---|---|
| `livre` | Master/Gerente (supervisão) | pull traz a base inteira; não exibir aviso de região |
| `restrita` | cadastrador com região atribuída | pull traz só as quadras de `quadra_ids`; mostrar ao usuário onde ele está atuando (use `atribuicoes[].quadras[].name` e o período) |
| `sem_atribuicao` | cadastrador sem região hoje | pull volta vazio — exibir "Nenhuma região atribuída para hoje. Fale com o supervisor" e **não** tratar como erro nem como "base zerada" |

**Sempre leia `modo`** — não deduza pelo tamanho de `quadra_ids` (uma lista vazia acontece tanto em `livre` quanto em `sem_atribuicao`, com significados opostos).

**Regras de renderização:**
- `campos_padrao`: use `label` e `opcoes` **do payload** (não hardcode). Campo ausente = município não usa → não exibir. `opcoes: []` = entrada livre (texto/número).
- `campos_customizados`: renderizar na `ordem`. Tipos: `texto`, `numero`, `selecao` (uma opção), `multipla` (várias), `data` (YYYY-MM-DD), `sim_nao` (booleano).
- **Obrigatoriedade é validada no app.** O backend nunca rejeita um push por campo faltando (coleta offline não pode ser perdida).
- Vale chamar no login e a cada sincronização: o município pode mudar rótulos/listas sem nova versão do app.

O mesmo objeto vem em `POST /api/login`, na chave **`coleta`** (`null` para usuários cidadão).

---

## 2. `GET /api/sync/lotes/pull` — filtro de região ⚠️

Sem alteração de formato (WatermelonDB `changes.lotes.created[]`), com duas diferenças:

**a) Recorte por região.** Só vêm os lotes das quadras atribuídas ao cadastrador hoje. Sem atribuição vigente:
```jsonc
{
  "changes": { "lotes": { "created": [], "updated": [], "deleted": [] } },
  "timestamp": 1785000000,
  "aviso": "sem_regiao_atribuida",
  "mensagem": "Nenhuma região de trabalho atribuída para hoje. Fale com o supervisor."
}
```
→ O app deve exibir essa mensagem em vez de "0 lotes" seco (equivale a `regiao.modo = "sem_atribuicao"`). Usuários **Master/Gerente** (`modo = "livre"`) continuam baixando tudo.

⚠️ **A região pode mudar a qualquer momento** (o supervisor reatribui no painel, ou o período vence à meia-noite). O app deve **rebuscar `/api/coleta/config` a cada sincronização** — não cachear a região indefinidamente — e refletir a mudança na base local.

⚠️ **Purge-and-replace:** como o conjunto de lotes muda quando o supervisor troca a região, o app deve **substituir** a base local a cada pull completo (não acumular). Lotes de uma região anterior não devem permanecer no dispositivo.

**b) `dados_customizados`** em lote, unidade e edificação (objeto `{slug: valor}` ou `null`).

---

## 3. `POST /api/sync/lotes/push` — novos campos aceitos

```jsonc
{
  "changes": { "lotes": { "updated": [ {
    "id": "uuid-do-lote",
    "status_cadastro": "coletado",
    "ocupacao": "Construído",
    "dados_customizados": { "padrao_construtivo": "Alto", "possui_muro": true },

    "edificacoes_updates": [
      { "id": "uuid-edificacao", "estado_conservacao": "Bom",
        "dados_customizados": { "tem_piscina": true } }
    ],

    "unidades_updates": [                        // NOVO (opcional)
      { "id": "uuid-unidade", "dados_customizados": { "possui_alvara": "Sim" } }
    ]
  } ] } }
}
```

- Chaves não declaradas em `campos_customizados` são **ignoradas** (whitelist no servidor) — enviar não causa erro.
- Valores são convertidos pelo tipo declarado (`numero` → número, `sim_nao` → booleano, `multipla` → lista).
- O objeto `dados_customizados` **substitui** o anterior: envie o conjunto completo do formulário.

---

## 4. `GET /api/lotes/nearest` — respeita a região

Só considera lotes `nao_visitado` **dentro da região atribuída**. Sem atribuição vigente:
`404 { "message": "Nenhuma região de trabalho atribuída para hoje...", "aviso": "sem_regiao_atribuida" }`.

---

## Passo de implantação (importante)

Antes de publicar a versão do app com esta release, **cada município ativo precisa ter as regiões atribuídas** aos seus cadastradores (painel do município → **Coleta cadastral → Regiões dos Cadastradores**). Sem isso, o cadastrador comum não baixa lotes. Supervisores (Master/Gerente) não são afetados.

> Onde o gestor configura o que o app recebe: **Coleta cadastral → Boletim de Coleta** (quais campos o cadastrador preenche) e **Coleta cadastral → Regiões dos Cadastradores**. Criar campos novos e renomear rótulos/valores fica em **Customizações** (vale para o sistema inteiro, não só para a coleta).
