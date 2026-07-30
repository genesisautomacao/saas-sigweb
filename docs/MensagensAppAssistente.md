# SIGWEB — Contrato da API do App de Coleta Cadastral (CTM)

> **Para quem é este documento:** o assistente/dev responsável pelo aplicativo **React Native** de coleta
> cadastral em campo. Aqui está **tudo** o que a API envia e recebe, com exemplos reais de payload.
> Qualquer divergência entre este documento e o app deve ser tratada como bug do app.
>
> **Versão:** Release 67 · atualizado em 30/07/2026.

---

## 1. O que este aplicativo é (e o que deixou de ser)

O app tem **um único escopo**: o **cadastro imobiliário** do município — **Lote**, **Edificação** e
**Unidade Imobiliária**. O cadastrador percorre a rua, abre o lote no mapa e preenche o boletim de campo.

**Foi removido da API em 30/07/2026** (não existe mais, retorna 404):

| Endpoint removido | Era |
|---|---|
| `GET /api/sync/pull` · `POST /api/sync/push` | sync de **árvores** (arborização) |
| `GET /api/sync/manutencoes/pull` · `POST /api/sync/manutencoes/push` | sync de **solicitações de manutenção** |
| camadas `arvores` e `postes` em `/api/map/layers` e `/api/map/data` | pontos de arborização/iluminação no mapa |

> ⚠️ Se o app ainda tiver telas, models locais (WatermelonDB/SQLite) ou filas de sincronização de árvores
> ou manutenções, **remova**. Chamar essas rotas agora resulta em erro de rota.

As duas regras que governam todo o app:

1. **O cadastrador só enxerga os lotes da região atribuída a ele.** Sem atribuição vigente, ele não baixa
   nada — e o app precisa dizer isso claramente ao usuário (§ 4).
2. **O boletim não é fixo no app.** Cada prefeitura define quais campos existem, como se chamam, quais
   valores aceitam e quais são obrigatórios. O app **monta o formulário em runtime** a partir do payload
   de configuração (§ 5). Mudar o vocabulário no painel **não** exige nova versão do app.

---

## 2. Convenções gerais

- **Base URL:** `{BASE_URL}/api` — confirme a URL de produção com o time do SIGWEB.
- **Autenticação:** Laravel Sanctum. Toda rota protegida exige:
  ```http
  Authorization: Bearer {token}
  Accept: application/json
  Content-Type: application/json
  ```
- **Multi-tenant:** o token já carrega o município. O app **nunca** envia `tenant_id`.
- **Identificadores:** em todos os payloads de sincronização, o campo `id` é o **UUID `code`** do registro
  (ex.: `510b3a08-d713-4d53-ab7d-54aaed7a7864`), **não** o id numérico interno. Ao devolver dados no push,
  reenvie exatamente o mesmo UUID recebido no pull.
- **Geometria:** GeoJSON padrão, **WGS84 / EPSG:4326**, coordenadas em `[longitude, latitude]`
  (atenção: o `react-native-maps` usa `{latitude, longitude}` — a inversão é responsabilidade do app).
- **Datas:** `YYYY-MM-DD` para datas; timestamps em horário de Brasília (`America/Sao_Paulo`).
- **Fotos:** no **pull** vêm como caminho relativo (`lotes_fotos/uuid.jpg`); para exibir, monte
  `{BASE_URL}/storage/{caminho}`. No **push**, envie **data URI base64** (`data:image/jpeg;base64,...`).

### Códigos de erro

| HTTP | Quando | O que o app faz |
|---|---|---|
| 401 | token inválido/expirado | desloga e volta para a tela de login |
| 403 | usuário sem prefeitura vinculada | mensagem "usuário sem município"; falar com o supervisor |
| 404 | nenhum lote encontrado / sem região atribuída (`/lotes/nearest`) | mensagem informativa, **não** é erro fatal |
| 422 | validação (ex.: troca de senha, mensagem) | exibir `errors` campo a campo |
| 500 | falha no push (transação revertida) | **manter a fila local** e tentar de novo; nada foi gravado |

---

## 3. Login — `POST /api/login`

**Request** (público, sem token):

```json
{
  "email": "pedro@gmail.com",
  "password": "senha-do-cadastrador",
  "expo_push_token": "ExponentPushToken[xxx]"
}
```

`expo_push_token` é opcional, mas **envie sempre** — é o que permite o supervisor mandar mensagem push.

**Response 200** (exemplo real):

```json
{
  "token": "12|aBcD...",
  "user": { "id": 9, "name": "Pedro Silva", "email": "pedro@gmail.com" },
  "tenant": {
    "id": 1,
    "name": "Prefeitura de Santa Cecília",
    "city": "Santa Cecília",
    "state": "SC",
    "map_lat": -26.9668663,
    "map_lon": -50.41582,
    "map_zoom": 14
  },
  "layers": ["lotes", "quadras", "logradouros", "bairros", "zonas"],
  "coleta": { "...": "idêntico ao GET /api/coleta/config — ver § 5" }
}
```

**Response 401:** `{"message": "Credenciais inválidas."}`

Notas:

- `map_lat` / `map_lon` / `map_zoom` são o **enquadramento inicial do mapa** definido pela prefeitura.
  Vêm sempre como **número** (ou `null`). Use-os para centralizar o mapa no primeiro carregamento.
- `coleta` já vem embutido no login — **não é preciso** chamar `/api/coleta/config` logo após logar.
  Chame esse endpoint depois, para atualizar (§ 5).
- `layers` é um atalho; o catálogo completo com rótulo/cor/zoom está em `/api/map/layers`.

**`POST /api/logout`** (com token) revoga o token do dispositivo: `{"message": "Sessão encerrada com sucesso no dispositivo."}`

---

## 4. Região do cadastrador — a regra mais importante

Cada cadastrador recebe no painel web uma **atribuição de região**: um conjunto de **quadras** com
**data de início e fim**. O servidor aplica esse recorte automaticamente em `/api/sync/lotes/pull` e
`/api/lotes/nearest`.

O objeto `regiao` (dentro de `coleta`) traz o campo **`modo`**, que é **explícito**. O app deve ler o
`modo` e **nunca** deduzir o comportamento pelo tamanho da lista:

| `modo` | Significa | Comportamento esperado no app |
|---|---|---|
| `restrita` | cadastrador com região atribuída | baixa **apenas** os lotes das quadras listadas — é o caso normal |
| `livre` | supervisor (Master/Manager) | baixa a base inteira; usar só para supervisão/teste, avisar que pode ser pesado |
| `sem_atribuicao` | nenhuma região vigente hoje | **não baixa nada**; exibir aviso e bloquear a coleta |

```json
"regiao": {
  "modo": "restrita",
  "quadra_ids": [363, 151, 372, 238, 362, 256, 153, 152, 239, 183, 237, 223, 179, 236],
  "atribuicoes": [
    {
      "data_inicio": "2026-07-29",
      "data_fim": "2026-07-31",
      "quadras": [{ "id": 363, "name": "014" }, { "id": 151, "name": "002" }],
      "bairros": []
    }
  ]
}
```

- `data_fim: null` = atribuição **em aberto** (sem prazo).
- A região **muda com o tempo** (a atribuição vence, o supervisor redistribui quadras). Por isso o app deve
  reconsultar `/api/coleta/config` a cada abertura/sincronização, e **não** guardar a região para sempre.
- Sugestão de UI: mostrar no topo "Você está trabalhando em **14 quadras** — período 29/07 a 31/07".

### Quando não há região

O `pull` responde **HTTP 200** (não é erro) com a lista vazia e um aviso:

```json
{
  "changes": { "lotes": { "created": [], "updated": [], "deleted": [] } },
  "timestamp": 1785426117,
  "aviso": "sem_regiao_atribuida",
  "mensagem": "Nenhuma região de trabalho atribuída para hoje. Fale com o supervisor."
}
```

O `/api/lotes/nearest` responde **404** com o mesmo `aviso`. Trate os dois exibindo a `mensagem` ao usuário.

---

## 5. Configuração do boletim — `GET /api/coleta/config`

**Este é o coração do app.** O formulário de coleta é montado em runtime a partir daqui. Sem token válido
não há resposta; sem prefeitura vinculada, 403.

**Response 200** (exemplo real do município Santa Cecília):

```json
{
  "campos_base": {
    "lote": [],
    "edificacao": []
  },
  "campos_padrao": {
    "lote": {
      "ocupacao": {
        "label": "Ocupação do Lote",
        "opcoes": ["Baldio", "Em Contrução", "Construído"],
        "obrigatorio": false
      },
      "situacao_quadra": {
        "label": "Situação na Quadra",
        "opcoes": ["Meio de Quadra", "Esquina", "Encravado", "Outros"],
        "obrigatorio": false
      }
    },
    "edificacao": {
      "tipo": { "label": "Finalidade / Uso", "opcoes": ["Residencial", "Comercial", "Industrial", "Misto", "Outro"], "obrigatorio": false },
      "tp_construcao": { "label": "Padrão da Obra", "opcoes": ["Alvenaria", "Taipa"], "obrigatorio": true },
      "caracteristica_construcao": { "label": "Característica da Construção", "opcoes": [], "obrigatorio": false },
      "estado_conservacao": { "label": "Estado de Conservação", "opcoes": ["Ruim", "Regular", "Médio", "Bom"], "obrigatorio": false },
      "pavimento": { "label": "Nº de Pavimentos", "opcoes": [], "obrigatorio": false }
    }
  },
  "campos_customizados": {
    "lote": [
      { "slug": "padrao_construtivo", "label": "Padrão Construtivo", "tipo": "selecao", "opcoes": ["Alto", "Médio", "Baixo"], "obrigatorio": true, "ordem": 1 },
      { "slug": "possui_muro", "label": "Possui Muro?", "tipo": "sim_nao", "opcoes": [], "obrigatorio": false, "ordem": 2 }
    ],
    "edificacao": [],
    "unidade": [
      { "slug": "teste_de_campo", "label": "Teste de campo", "tipo": "texto", "opcoes": [], "obrigatorio": false, "ordem": 0 }
    ]
  },
  "regiao": { "modo": "restrita", "quadra_ids": [363, 151], "atribuicoes": [] }
}
```

### 5.1 `campos_base` — registros obrigatórios sem vocabulário próprio

Lista de campos nativos que a prefeitura marcou como **obrigatórios** no boletim. Lista vazia = nenhum obrigatório.

> **`campos_base` e `campos_padrao` NÃO se sobrepõem** — são conjuntos disjuntos por construção. A tela
> "Boletim de Coleta" monta a lista de `campos_base` excluindo tudo que tem vocabulário próprio, então
> `ocupacao` e `situacao_quadra` **nunca** aparecem aqui. Não existe conflito de obrigatoriedade:
>
> - **`campos_padrao[entidade][campo].obrigatorio`** → manda nos campos taxonômicos (`ocupacao`, `situacao_quadra`, `tipo`, `tp_construcao`, `caracteristica_construcao`, `estado_conservacao`, `pavimento`).
> - **`campos_base`** → manda só na observação e nas fotos.

Valores que `campos_base` pode conter:

| Chave | Só pode conter | Rótulo a exibir (fixo — estes 4 não são white-label) |
|---|---|---|
| `lote` | `observacao`, `foto_frontal`, `foto_lateral_esq`, `foto_lateral_dir` | "Observações", "Foto frontal", "Foto lateral esquerda", "Foto lateral direita" |
| `edificacao` | sempre `[]` hoje | — |

São a **única exceção** à regra "não hardcode rótulo": eles não têm entrada em `campos_padrao` porque não
têm lista de valores para o município configurar.

Se `foto_frontal` estiver na lista, o app **não deixa concluir a coleta** sem a foto frontal. E assim por diante.
Tratar a obrigatoriedade como **união das duas fontes** é seguro (na prática nunca coincidem) e à prova de
mudanças futuras — pode implementar assim.

### 5.2 `campos_padrao` — rótulo e vocabulário do município

Campos que existem em toda prefeitura, mas cujo **nome exibido** e **lista de valores** cada uma define.
Regras para o app:

- **Renderize o campo com o `label` recebido**, nunca com um rótulo fixo no código.
- **O picker usa exatamente a lista `opcoes`.** O valor selecionado é gravado como **string literal**
  (ex.: `"Em Contrução"`), não um código.
- **`opcoes: []`** = campo livre (texto ou número). `pavimento` é numérico; `caracteristica_construcao`, texto.
- **Campo ausente do payload** = a prefeitura **desligou** aquele campo. **Não renderize.**
  (No exemplo acima, `edificacao.area_geo` não aparece em `campos_padrao` porque é calculado pela geometria.)
- `obrigatorio: true` aqui significa "exigido no boletim de coleta".
- Ao editar um registro antigo cujo valor **não está** mais na lista (a prefeitura trocou o vocabulário),
  **preserve o valor** e mostre-o como opção extra marcada, em vez de apagar.

> Exceção importante: **`status_cadastro` NÃO é configurável.** É sempre um dos quatro valores fixos
> `nao_visitado` · `coletado` · `pendente` · `inconformidade` (§ 6.3).

### 5.3 `campos_customizados` — campos criados pela prefeitura

Campos extras, por entidade (`lote`, `edificacao`, `unidade`). Os valores viajam na coluna JSON
`dados_customizados`, **com o `slug` como chave**.

| `tipo` | Componente sugerido no RN | Formato do valor enviado no push |
|---|---|---|
| `texto` | `TextInput` | string |
| `numero` | `TextInput` numérico | número (o servidor converte para float) |
| `selecao` | Picker de escolha única | string (um item de `opcoes`) |
| `multipla` | Multi-select | array de strings |
| `data` | Date picker | string `"YYYY-MM-DD"` |
| `sim_nao` | Switch | booleano |

- Renderize **na ordem** do campo `ordem`.
- `obrigatorio: true` → o app bloqueia a conclusão da coleta sem preenchimento.
- O servidor aplica **whitelist por slug**: chave desconhecida é descartada silenciosamente. Envie só o que veio na config.

### 5.4 Validação de obrigatoriedade é responsabilidade do APP

**O push nunca rejeita uma coleta por campo obrigatório em branco.** Isso é proposital: uma coleta feita
offline jamais pode ser perdida por validação de servidor. Portanto:

- O app valida **antes** de deixar o cadastrador marcar o lote como `coletado`.
- Se faltar algo, o caminho correto é salvar como **`pendente`**, não bloquear o envio.

---

## 6. Sincronização de lotes

### 6.1 `GET /api/sync/lotes/pull`

Sem parâmetros. Devolve **todos** os lotes da região do cadastrador, com a ficha completa para uso offline
(unidades imobiliárias + edificações aninhadas). No exemplo real, o cadastrador Pedro Silva, com 14 quadras
atribuídas, recebeu **111 lotes**.

**Response 200:**

```json
{
  "changes": {
    "lotes": {
      "created": [ { "...": "lotes" } ],
      "updated": [],
      "deleted": []
    }
  },
  "timestamp": 1785426117
}
```

> ⚠️ **O pull NÃO é incremental.** O servidor sempre devolve o conjunto atual inteiro dentro de `created`;
> `updated` e `deleted` vêm sempre vazios. O app deve tratar o pull como **purge-and-replace**: substituir
> a base local pelo conjunto recebido, **depois de enviar a fila de push pendente**. Como a região muda
> (a atribuição vence, o supervisor redistribui), lotes que saíram da região devem sumir do dispositivo.

**Estrutura de um lote** (exemplo real, com unidade e edificações):

```json
{
  "id": "510b3a08-d713-4d53-ab7d-54aaed7a7864",
  "numero_lote": "235",
  "quadra_id": 372,
  "zona_id": 9,
  "area_geo": 504.52,
  "main_facade_length": 15,
  "foto_frontal": null,
  "foto_lateral_esq": null,
  "foto_lateral_dir": null,
  "observacao": null,
  "status_cadastro": "nao_visitado",
  "ocupacao": null,
  "situacao_quadra": null,
  "inconformidade_descricao": null,
  "dados_vistoria": null,
  "dados_customizados": null,
  "coletado_por_id": null,
  "coletado_em": null,
  "sequential_id": 4005,
  "geo_json": {
    "type": "MultiPolygon",
    "coordinates": [[[[-50.427477, -26.952769], [-50.427247, -26.95256], [-50.427134, -26.952658], [-50.427477, -26.952769]]]]
  },
  "unidades_imobiliarias": [
    {
      "id": "538e1817-5fc3-4f88-ae09-991328974584",
      "inscricao_imobiliaria": "01.07.006.0031.001.1",
      "codigo_imovel_tributario": "0000000338",
      "logradouro_nome": "Rua ANTONIO RIBEIRO DE MELO",
      "numero_imovel": "0",
      "complemento": null,
      "dados_tributarios": {
        "proprietario_name": "MARISTELA FURTADO PEREIRA TRAIN",
        "tipo_construcao": "Especial",
        "descricao_classificacao": "Residencial",
        "area_geo": "450.00",
        "area_edificacao": "175.62",
        "valor_venal_lote": "5048.21",
        "valor_total_imposto": "416.23",
        "face": "Esquerdo",
        "fracao_ideal": "450.00"
      },
      "dados_customizados": null
    }
  ],
  "edificacoes": [
    {
      "id": "393ae4d4-a3ba-45e0-b5cd-57c9066fe3c9",
      "tipo": "Casa",
      "tp_construcao": "Madeira/Mista (1)",
      "caracteristica_construcao": null,
      "estado_conservacao": "Bom",
      "pavimento": null,
      "area_geo": 62.94,
      "dados_customizados": null
    }
  ]
}
```

Observações sobre o payload:

- **`dados_tributarios` é JSON livre e somente-leitura**, vindo do sistema tributário da prefeitura
  (Betha, GOVBR, IPM, Fiorilli…). As chaves **variam de município para município**. Use para **exibir**
  ao cadastrador ("o cadastro diz que aqui mora Fulano, área 450 m²") e nunca assuma que uma chave existe.
- `geo_json` já vem como **objeto**, não como string.
- `coletado_por_id` / `coletado_em` são preenchidos pelo servidor, nunca pelo app.
- Valores de `tipo`, `tp_construcao` etc. podem estar **fora** da lista atual de `campos_padrao` (dados
  legados do sistema tributário, como `"Madeira/Mista (1)"`). Preserve-os na edição.

### 6.2 `POST /api/sync/lotes/push`

Envia **apenas os lotes alterados**. O app **não pode criar nem excluir lotes** — isso é exclusivo do painel web.

**Request:**

```json
{
  "changes": {
    "lotes": {
      "updated": [
        {
          "id": "510b3a08-d713-4d53-ab7d-54aaed7a7864",

          "status_cadastro": "coletado",
          "ocupacao": "Em Contrução",
          "situacao_quadra": "Esquina",
          "observacao": "Portão à direita, cão bravo",
          "inconformidade_descricao": null,

          "dados_customizados": {
            "padrao_construtivo": "Médio",
            "possui_muro": true
          },

          "dados_vistoria": { "qualquer": "json livre — legado" },

          "foto_frontal": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
          "foto_lateral_esq": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
          "foto_lateral_dir": null,

          "edificacoes_updates": [
            {
              "id": "393ae4d4-a3ba-45e0-b5cd-57c9066fe3c9",
              "tipo": "Residencial",
              "tp_construcao": "Alvenaria",
              "caracteristica_construcao": "Padrão médio",
              "estado_conservacao": "Regular",
              "pavimento": 2,
              "area_geo": 220.5,
              "dados_customizados": { "slug_da_edificacao": "valor" }
            }
          ],

          "unidades_updates": [
            {
              "id": "538e1817-5fc3-4f88-ae09-991328974584",
              "dados_customizados": { "teste_de_campo": "valor digitado" }
            }
          ]
        }
      ]
    }
  }
}
```

**Response 200:** `{"success": true}` · **Nada a enviar:** `{"message": "Nada para sincronizar"}` ·
**Erro:** `{"error": "<mensagem>"}` com HTTP 500 — **a transação inteira é revertida**, então mantenha a fila local e repita.

Regras do push:

- **Campos aceitos no lote:** `status_cadastro`, `ocupacao`, `situacao_quadra`, `observacao`,
  `inconformidade_descricao`, `dados_vistoria`, `dados_customizados` e as 3 fotos. **Qualquer outro campo é ignorado** —
  inclusive `numero_lote`, `area_geo` e a geometria, que só o painel web altera.
- **Fotos:** só são processadas se começarem com `data:image`. Para **manter** a foto que já existe, ou não
  envie a chave, ou reenvie o caminho relativo recebido no pull (será ignorado). Não há como apagar foto pelo app.
- **`coletado_por_id` e `coletado_em`** são preenchidos automaticamente pelo servidor com o usuário do token e
  o horário do envio, sempre que `status_cadastro` for diferente de `nao_visitado`.
- **`edificacoes_updates`** só **atualiza** edificações existentes (casa o `id`/UUID **e** o lote). Edificação
  nova não pode ser criada pelo app.
- **`unidades_updates`** aceita **somente `dados_customizados`**. Dados tributários não são editáveis pelo app.
- **`dados_customizados`** passa por whitelist de slug + conversão de tipo. Valor `null` ou string vazia é
  descartado (a chave simplesmente não é gravada).
- ⚠️ **Envie UM lote por request.** O servidor processa o array `updated` inteiro dentro de **uma única
  transação**: se um lote falhar (foto corrompida, JSON inválido), **todos** os lotes daquele request são
  revertidos. Mandar 1 por vez isola a falha — o lote problemático fica na fila e os demais entram.
  Não há rate limit nas rotas `/api` (throttle desativado), então centenas de requests sequenciais não
  tomam 429; só espace o suficiente para não saturar a rede do celular em campo.

### 6.3 `status_cadastro` — os quatro estados fixos

| Valor | Quando usar | Cor no mapa (web e app) |
|---|---|---|
| `nao_visitado` | estado inicial, ninguém foi lá | `#9CA3AF` cinza |
| `coletado` | boletim completo, tudo conferido | `#10B981` verde |
| `pendente` | visitou mas faltou algo (morador ausente, faltou foto) | `#F59E0B` amarelo |
| `inconformidade` | divergência entre o cadastro e a realidade | `#EF4444` vermelho |

Ao usar `inconformidade`, preencha também `inconformidade_descricao` com o texto do que foi encontrado.

---

## 7. Lote mais próximo — `GET /api/lotes/nearest?lat={lat}&lon={lon}`

Retorna o lote **`nao_visitado` mais próximo dentro da região do cadastrador** (cálculo por PostGIS,
distância real em metros). Serve para o botão "Qual o próximo?".

**Response 200:**

```json
{
  "id": "7efe7a2c-9df7-40c0-8161-ef7f2a47159d",
  "numero_lote": "195",
  "sequential_id": 4191,
  "status_cadastro": "nao_visitado",
  "distancia_metros": 1536.2,
  "geo_json": { "type": "MultiPolygon", "coordinates": [[[[-50.424909, -26.952812]]]] }
}
```

**404** quando não há lote pendente (`{"message": "Nenhum lote pendente encontrado."}`) ou quando o
cadastrador está sem região (`aviso: "sem_regiao_atribuida"`). Ambos são situações normais, não falhas.

Parâmetros obrigatórios e validados: `lat` entre -90 e 90, `lon` entre -180 e 180.

---

## 8. Mapa

### 8.1 `GET /api/map/layers`

Catálogo das camadas que este município liberou para o app. O app monta o seletor de camadas e o estilo
**a partir daqui**, sem hardcode.

```json
{
  "data": [
    { "key": "lotes",       "label": "Lotes",       "tipo": "polygon", "cor": "#3388ff", "modulo": null, "visivel_padrao": true,  "min_zoom": 15 },
    { "key": "quadras",     "label": "Quadras",     "tipo": "polygon", "cor": "#ff7800", "modulo": null, "visivel_padrao": false, "min_zoom": 14 },
    { "key": "bairros",     "label": "Bairros",     "tipo": "polygon", "cor": "#8e44ad", "modulo": null, "visivel_padrao": false, "min_zoom": 12 },
    { "key": "logradouros", "label": "Logradouros", "tipo": "line",    "cor": "#7f8c8d", "modulo": null, "visivel_padrao": false, "min_zoom": 15 },
    { "key": "zonas",       "label": "Zonas",       "tipo": "polygon", "cor": "#2ecc71", "modulo": null, "visivel_padrao": false, "min_zoom": 13 }
  ]
}
```

A prefeitura pode **restringir** essa lista no painel (curadoria). Não presuma que as 5 camadas sempre virão.

### 8.2 `GET /api/map/data?layer={key}&bbox={oeste,sul,leste,norte}`

GeoJSON `FeatureCollection` da camada. `bbox` é opcional mas **fortemente recomendado** (recorta pela
área visível e reduce muito o payload).

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": {
        "id": 4005,
        "name": "235",
        "codigo": "510b3a08-d713-4d53-ab7d-54aaed7a7864",
        "sequential_id": 4005,
        "status_cadastro": "nao_visitado",
        "ocupacao": null,
        "layer": "lotes"
      },
      "geometry": { "type": "MultiPolygon", "coordinates": [] }
    }
  ]
}
```

- Só a camada `lotes` traz `status_cadastro` e `ocupacao` — use para colorir os polígonos (tabela do § 6.3).
- `properties.codigo` é o UUID que casa com o `id` do pull — é por ele que se abre a ficha offline.
- Camada inexistente → **404** `{"error": "Camada não encontrada"}`.
- ⚠️ Este endpoint é **online**. A base offline do app vem do `pull`, não daqui.

---

## 9. GPS do cadastrador — `POST /api/cadastradores/location`

O supervisor acompanha a equipe em tempo real no painel web. O app deve postar a posição
**a cada ~60 segundos**, em background, enquanto o cadastrador estiver em campo.

**Request:** `{"lat": -26.9668, "lon": -50.4158}` → **Response:** `{"ok": true}`

É um *upsert* por usuário (uma linha por cadastrador, sempre atualizada — não gera histórico).
No painel, o cadastrador é considerado "ativo" se pingou nos **últimos 10 minutos**.

`GET /api/cadastradores/locations` existe para o supervisor e devolve a posição de todos os cadastradores
ativos com o total coletado no dia — normalmente o app de campo não precisa consumir.

---

## 10. Chat com o supervisor

Canal direto cadastrador ↔ supervisor, com push via Expo.

| Endpoint | Uso |
|---|---|
| `GET /api/contatos` | lista com quem se pode conversar (usuários da mesma prefeitura com papel atribuído, exceto você) |
| `GET /api/mensagens` | últimas 200 mensagens; `?contato_id=X` filtra a conversa |
| `POST /api/mensagens` | envia — body `{"destinatario_id": 3, "texto": "..."}` (máx. 2000 caracteres) |
| `PUT /api/mensagens/{id}/lida` | marca como lida — **só o destinatário pode** |

```json
// GET /api/contatos
{ "data": [ { "id": 3, "name": "Maria Supervisora", "email": "maria@...", "role": "Master" } ] }
```

- O envio dispara **push Expo** automaticamente para o destinatário (por isso o `expo_push_token` no login).
- Destinatário de outra prefeitura → **422**.
- Marcar como lida sem ser o destinatário → **403**.
- Sem WebSocket: faça **polling** (o painel web usa 5s; no app, 30s é suficiente).

---

## 11. Produtividade — `GET /api/reports/productivity`

Estatísticas de campo, úteis para uma tela de acompanhamento do próprio cadastrador ou do supervisor.
Parâmetros opcionais: `data` (`YYYY-MM-DD`, padrão hoje) e `quadra_id`.

```json
{
  "data": "2026-07-30",
  "total_lotes": 4434,
  "coletados": 25,
  "pendentes": 3,
  "inconformidades": 1,
  "nao_visitados": 4405,
  "percentual_geral": 0.6,
  "por_cadastrador": [ { "user_id": 9, "nome": "Pedro Silva", "coletados_hoje": 5, "coletados_total": 25 } ],
  "por_quadra": [ { "quadra_id": 363, "nome_quadra": "014", "total": 13, "coletados": 7, "pendentes": 1, "inconformidades": 0, "nao_visitados": 5, "percentual": 53.8 } ]
}
```

> Este relatório **não** aplica o filtro de região — ele cobre o município inteiro.

---

## 12. Perfil — `GET /api/me` e `PUT /api/me`

**GET** devolve os dados do usuário + `telefone`, `cpf` e `data_nascimento` (do cadastro de Pessoa no município).

**PUT** atualiza **só os campos enviados**:

```json
{
  "name": "Pedro da Silva",
  "email": "pedro@novoemail.com",
  "telefone": "(49) 99999-0000",
  "cpf": "000.000.000-00",
  "data_nascimento": "1990-05-20",
  "current_password": "senha-atual",
  "password": "nova-senha",
  "expo_push_token": "ExponentPushToken[xxx]"
}
```

- Trocar a senha **exige** `current_password` correta — senão **422** com
  `{"errors": {"current_password": ["Senha atual incorreta."]}}`.
- E-mail duplicado → **422**. Senha mínima: 6 caracteres.
- **Response 200:** `{"data": {"id", "name", "email", "telefone", "cpf", "data_nascimento"}}`

---

## 13. Fluxo recomendado do app

```
1.  LOGIN                POST /api/login  (com expo_push_token)
                         └─ guarda token + tenant + `coleta` (config do boletim)

2.  ABERTURA / SYNC      a) POST /api/sync/lotes/push   ← envia a fila pendente PRIMEIRO
                         b) GET  /api/coleta/config     ← região e boletim podem ter mudado
                         c) se regiao.modo == "sem_atribuicao" → avisa e para
                         d) GET  /api/sync/lotes/pull   ← purge-and-replace da base local

3.  EM CAMPO             - mapa com a camada `lotes` colorida por status_cadastro
                         - POST /api/cadastradores/location a cada 60s
                         - GET /api/lotes/nearest para "qual o próximo?"
                         - abre o lote → boletim montado a partir de `coleta`
                         - salva OFFLINE na fila local

4.  ENVIO                POST /api/sync/lotes/push — UM lote por request
                         └─ 500 → mantém aquele lote na fila e repete depois (nada foi gravado)

5.  COMUNICAÇÃO          polling de /api/mensagens a cada 30s + push Expo
```

**Ordem crítica:** o push **sempre** vem antes do pull. Como o pull substitui a base local, inverter a
ordem apaga coleta ainda não enviada.

---

## 14. Armadilhas conhecidas

1. **Não hardcode rótulo nem lista de valores.** Cada prefeitura tem o seu vocabulário. O que estava
   `"Ocupação do Lote: Baldio | Construído"` pode virar `"Uso do Terreno: Vazio | Edificado | Em obras"`.
2. **Não deduza a região pelo tamanho da lista.** Leia `regiao.modo`. Lista vazia com `modo: "livre"`
   (supervisor) significa "baixa tudo", o oposto de `sem_atribuicao`.
3. **`id` é o UUID (`code`)**, não o inteiro. Enviar o id numérico no push faz o servidor **ignorar o lote
   silenciosamente** (ele não acha o registro e segue em frente, sem erro).
4. **Pull não é incremental.** `updated` e `deleted` vêm sempre vazios; tudo está em `created`.
5. **Coordenadas são `[lon, lat]`** no GeoJSON e `{latitude, longitude}` no react-native-maps.
6. **Nunca bloqueie o envio por validação.** Falta de campo obrigatório → salvar como `pendente`.
7. **Fotos pesam.** Redimensione (sugestão: 1280px no lado maior, JPEG qualidade ~70) antes do base64,
   senão o push estoura o limite de upload do servidor.
8. **Lotes sem quadra** (`quadra_id: null`) **não entram** no pull filtrado por região — é esperado.
9. **Valores fora da lista atual** existem no dado legado do sistema tributário
   (ex.: `tp_construcao: "Madeira/Mista (1)"`). Exiba e preserve; não force o valor para o vocabulário novo.
10. **`dados_vistoria` é caixa-preta.** Continua aceito no push e devolvido no pull, mas é JSON opaco: no
    painel web aparece só como editor bruto de chave/valor na ficha do lote — não vai para relatório, mapa,
    BIC nem export. Só guarde ali o que ninguém precisa consultar. Dado que a prefeitura vai querer ver
    deve virar **campo customizado** (`dados_customizados`) ou campo nativo.

---

## 15. Referência rápida — todos os endpoints do app

| Método | Rota | Auth | O que faz |
|---|---|---|---|
| POST | `/api/login` | — | autentica; devolve token, tenant, layers e **config da coleta** |
| POST | `/api/logout` | ✔ | revoga o token do dispositivo |
| GET | `/api/me` | ✔ | perfil do usuário |
| PUT | `/api/me` | ✔ | edita perfil / troca senha / atualiza push token |
| GET | `/api/coleta/config` | ✔ | **boletim + região** (fonte do formulário) |
| GET | `/api/sync/lotes/pull` | ✔ | baixa os lotes da região (ficha completa, offline) |
| POST | `/api/sync/lotes/push` | ✔ | envia o boletim de campo |
| GET | `/api/lotes/nearest` | ✔ | lote não visitado mais próximo, dentro da região |
| GET | `/api/map/layers` | ✔ | catálogo de camadas do município |
| GET | `/api/map/data` | ✔ | GeoJSON de uma camada (com bbox) |
| POST | `/api/cadastradores/location` | ✔ | ping GPS (a cada 60s) |
| GET | `/api/cadastradores/locations` | ✔ | posição da equipe (supervisor) |
| GET | `/api/reports/productivity` | ✔ | estatísticas de coleta |
| GET | `/api/contatos` | ✔ | contatos para o chat |
| GET | `/api/mensagens` | ✔ | mensagens (opcional `?contato_id=`) |
| POST | `/api/mensagens` | ✔ | envia mensagem (dispara push) |
| PUT | `/api/mensagens/{id}/lida` | ✔ | marca como lida |

---

## 16. Checklist de implementação

- [ ] Remover do app todo código de **árvores** e **manutenções** (telas, models locais, filas, rotas).
- [ ] Remover as camadas `arvores` e `postes` do mapa.
- [ ] Guardar o objeto `coleta` do login e reconsultar `/api/coleta/config` a cada sincronização.
- [ ] Renderizar o boletim **dinamicamente** (campos base + padrão + customizados, na ordem recebida).
- [ ] Tratar os três valores de `regiao.modo`, com tela de aviso para `sem_atribuicao`.
- [ ] Implementar o ciclo **push → config → pull** com purge-and-replace.
- [ ] Validar obrigatórios no cliente; nunca bloquear o envio (usar `pendente`).
- [ ] Comprimir fotos antes do base64 e enviar **um lote por request** (transação única no servidor).
- [ ] Ping GPS a cada 60s em background.
- [ ] Enviar `expo_push_token` no login e no `PUT /api/me`.
- [ ] Colorir o mapa pelos quatro `status_cadastro`.

---

## 17. Antes de subir para produção (time SIGWEB)

- Toda prefeitura ativa precisa ter **região atribuída a cada cadastrador** antes de o app novo entrar no ar —
  sem atribuição, o pull volta vazio (Master/Manager não são afetados).
- Confirmar `upload_max_filesize` / `post_max_size` do PHP e `client_max_body_size` do nginx compatíveis
  com o volume de fotos base64 do push.
