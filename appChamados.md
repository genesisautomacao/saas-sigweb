# appChamados.md — Instruções para construir o **App de Chamados do Cidadão** (Módulo XVI)

> **Para o assistente (Claude) que abrir ESTE repositório do app:** este documento é a fonte de verdade da tarefa. Leia inteiro antes de escrever código. Você está partindo de uma **cópia do app de Coletas de Recadastramento** (o app dos fiscais de rua) e vai **remodelá-lo** para virar o **App de Chamados do Cidadão**. O **backend já existe, está pronto e no ar** (SIGWEB — Laravel/Filament). Todos os endpoints desta especificação **já respondem** (nada aqui é "a fazer no backend"). Sua função é o **app mobile** que consome a API da seção 4.
>
> **Leia junto o `CLAUDE.md` deste repositório:** ele documenta a base (app de Coletas) que você vai **reaproveitar como infraestrutura** (HTTP, mapa, câmera→base64, GPS, push, sessão) — é a sua principal fonte para o **inventário do passo 1**. Na dúvida entre os dois documentos, **este `appChamados.md` manda** (define a nova missão); o `CLAUDE.md` é o mapa de *onde está* o que será reaproveitado.

---

## 1. O que é este app (e como difere do app de origem)

O SIGWEB é uma plataforma SaaS de GIS para prefeituras. Ele já tem **dois apps**/superfícies mobile:

| App | Usuário | O que faz |
|---|---|---|
| **Coletas / Recadastramento** (a base que você está copiando) | **Fiscal** da prefeitura (tem papel/role) | Coleta CTM em campo: pull/push de lotes, fotos de fachada, GPS tracking, offline-first pesado |
| **Chamados** (o que você vai construir) | **Cidadão** (autocadastro, sem papel) | Abre chamados (reclamações/solicitações), marca o ponto no mapa, responde um boletim, anexa fotos, acompanha o status e conversa com a prefeitura por chat |

**Regra de ouro da remodelagem:** *reaproveite a infraestrutura, descarte o domínio.*

- ✅ **Reaproveitar** (já está pronto e testado na base de coletas): **cliente HTTP** com Bearer token, **armazenamento seguro de token/sessão**, **componente de mapa** (o mesmo que os fiscais usam), **captura de câmera/galeria** (a base já converte foto em **base64** pro push), **GPS/localização**, **push Expo**, tema/navegação/componentes de UI.
- ❌ **Remover** (é do fiscal, não serve pro cidadão): sincronização offline pesada, pull/push de lotes (`/sync/lotes/*`), "imóvel mais próximo", GPS tracking de cadastrador (`/cadastradores/*`), produtividade, mensagens fiscal↔fiscal, qualquer tela de coleta CTM.

> Não tente "adaptar" as telas de coleta. **Apague** as telas/stores/serviços de coleta e **crie telas novas** de chamado, reusando apenas os módulos-base (auth, http, mapa, câmera, gps, push).

---

## 2. Stack e dependências

O app de origem é **React Native + Expo** (o backend usa `expo_push_token` → push via Expo). **Não troque de stack** — use as libs que a base de coletas já usa para cada função:

- **Mapa:** a MESMA lib de mapa do app de coletas. O cidadão vai **soltar um pino** no local do problema.
- **Câmera/foto:** o MESMO módulo de captura + conversão **base64** já usado no push de fotos das coletas.
- **Localização (GPS):** o MESMO módulo (`expo-location` ou equivalente).
- **Push:** `expo-notifications` — o token é enviado **em todo login/cadastro** (campo `expo_push_token`) e ao tocar na notificação abre o detalhe do chamado.
- **Login social:** Google e Facebook (ver 2.1).
- **HTTP/estado:** reutilize o cliente HTTP e o padrão de estado (Context/Redux/Zustand — o que já existir).

> ⚠️ **App novo, não sobrescreva o de coletas:** use **novo `name`, `slug`, `bundleIdentifier`/`package` e ícone** no `app.json`/`app.config`. Os dois apps convivem.

### 2.1. Login social (Google e Facebook) — como o app obtém o token

O backend **não** faz o handshake OAuth; ele **recebe do app o token do provedor e valida**. Então o app precisa:

- **Google:** obter o **`idToken`** via `expo-auth-session/providers/google` (ou `@react-native-google-signin/google-signin`) e enviá-lo em `POST /api/auth/google` no campo `token`.
- **Facebook:** obter o **`accessToken`** via `expo-auth-session/providers/facebook` (ou `react-native-fbsdk-next`) e enviá-lo em `POST /api/auth/facebook` no campo `token`.

As credenciais dos provedores (Google Client IDs por plataforma, Facebook App ID) são configuração **do app** (no `app.config`/console do provedor). O backend só valida o token recebido.

---

## 3. Configuração de ambiente

- **Base URL da API (produção):** `https://webgis.liderengenharia.eng.br/api`
- Deixe a base URL numa constante/env (`API_BASE_URL`) para trocar entre dev e prod.
- **Headers em todo request:**
  - `Accept: application/json` (obriga o Laravel a responder JSON, inclusive em erros)
  - `Authorization: Bearer {token}` (em tudo, **exceto** os endpoints públicos da seção 4.1)
- **Isolamento por prefeitura (tenant):** o cidadão pertence a **uma** prefeitura.
  - No **cadastro** e no **login social**, o app informa a prefeitura escolhida (`tenant_id` **ou** `prefeitura_slug`) — obtida em `GET /api/prefeituras`.
  - No **login por e-mail/senha** e nas **demais chamadas**, o servidor resolve o tenant pelo token. Não envie `tenant_id` nessas.

---

## 4. Contrato da API (tudo LIVE)

Payload de autenticação **padrão** (retornado por **todos** os pontos de entrada — login, cadastro, Google, Facebook):
```json
{
  "token": "17|abcdef...",
  "user":   { "id": 12, "name": "João Munícipe", "email": "cidadao@exemplo.com" },
  "tenant": { "id": 3, "name": "Prefeitura de ...", "city": "...", "state": "..",
              "map_lat": -26.9599, "map_lon": -50.4268, "map_zoom": 15 },
  "layers": ["lotes", "quadras", "..."]
}
```
Guarde `token` (secure storage) e `tenant.map_lat/map_lon/map_zoom` → **centro inicial do mapa** ao abrir um chamado.

### 4.1. Autenticação — 3 tipos de login (todos PÚBLICOS, sem Bearer)

**a) Lista de prefeituras (para o picker de cidade)** — `GET /api/prefeituras`
```json
{ "data": [ { "id": 3, "name": "Prefeitura de Santa Cecília", "slug": "prefeitura-de-santa-cecilia", "city": "...", "state": ".." } ] }
```

**b) Cadastro por e-mail/senha** — `POST /api/cidadao/register`
```json
// request
{ "name": "João Munícipe", "email": "cidadao@exemplo.com", "password": "senha123",
  "cpf": "000.000.000-00", "telefone": "(49) 99999-0000",
  "tenant_id": 3, "expo_push_token": "ExponentPushToken[xxx]" }
```
- `name`, `email`, `password` (min 6) são obrigatórios; `cpf`/`telefone` opcionais; **`tenant_id` OU `prefeitura_slug`** obrigatório.
- Sucesso: `201` + **payload de autenticação** (seção 4). Erros: `422` (ex.: e-mail já usado), `404` (prefeitura não encontrada).

**c) Login por e-mail/senha** — `POST /api/login`
```json
{ "email": "cidadao@exemplo.com", "password": "senha123", "expo_push_token": "ExponentPushToken[xxx]" }
```
- `expo_push_token` opcional (salvo no login). Sucesso: `200` + payload. Erro: `401 { "message": "Credenciais inválidas." }`.

**d) Login/Cadastro Google** — `POST /api/auth/google`
```json
{ "token": "<google idToken>", "tenant_id": 3, "expo_push_token": "ExponentPushToken[xxx]" }
```
- O backend valida o `idToken` no Google, e **cria o cidadão automaticamente** na primeira vez (vinculado à prefeitura informada). Sucesso: `200` + payload. Erros: `401` (token inválido), `404`.

**e) Login/Cadastro Facebook** — `POST /api/auth/facebook`
```json
{ "token": "<facebook accessToken>", "tenant_id": 3, "expo_push_token": "ExponentPushToken[xxx]" }
```
- Idem Google, validando no Graph do Facebook. Se o Facebook não devolver e-mail, o backend gera um e-mail interno estável (a conta continua única). Sucesso: `200` + payload.

**f) Sessão** — `POST /api/logout` (auth) encerra o token do dispositivo · `GET /api/me` (auth) valida o token no boot do app.

### 4.2. Categorias de chamado — `GET /api/categorias-chamado` (auth)
```json
{ "data": [
  { "id": 1, "nome": "Iluminação Pública", "pai_id": null, "cor": "#f59e0b", "icone": "chamados/icones/luz.png", "privada": false },
  { "id": 2, "nome": "Lâmpada apagada",     "pai_id": 1,    "cor": "#f59e0b", "icone": null, "privada": false }
] }
```
- **Cidadão só recebe `privada = false`** (o servidor filtra pelo token).
- **Hierarquia:** `pai_id` monta pai→filho. Exiba agrupado (pai como seção e filhas dentro), ou seleção em dois níveis.
- **`cor`**: chip/badge da categoria e cor do pino no mapa.
- **`icone`**: quando não-nulo, caminho relativo → **URL absoluta = `https://webgis.liderengenharia.eng.br/storage/{icone}`**. Nulo → ícone padrão.

### 4.3. Fluxo + boletim da categoria — `GET /api/fluxos-chamado?categoria_id={id}` (auth)
Devolve os fluxos ativos (com o **boletim/questionário**) para renderizar o formulário dinâmico (seção 5):
```json
{ "data": [ {
  "id": 5, "nome": "Atendimento de Chamado", "categoria_chamado_id": 2, "ativo": true,
  "boletim": [
    { "type": "texto",    "data": { "label_campo": "Descreva o problema", "obrigatorio": true } },
    { "type": "checkbox", "data": { "label_campo": "Período do problema", "opcoes": ["Dia","Noite","Sempre"], "obrigatorio": false } }
  ]
} ] }
```
- Chame ao escolher a categoria. Se vier vazio, abra o chamado **sem** questionário (só descrição + ponto + fotos). Guarde o `id` do fluxo para enviar em `fluxo_chamado_id` no POST.

### 4.4. Abrir chamado — `POST /api/chamados` (auth)
```json
{
  "categoria_chamado_id": 2,
  "fluxo_chamado_id": 5,
  "descricao": "Poste apagado há 3 dias na Rua Central",
  "lat": -26.9601,
  "lon": -50.4258,
  "respostas_boletim": { "Descreva o problema": "Poste apagado", "Período do problema": ["Noite"] },
  "fotos": ["data:image/jpeg;base64,...", "data:image/jpeg;base64,..."]
}
```
- `descricao` **obrigatório**. Envie sempre **categoria + lat/lon + descrição**.
- `lat`/`lon` = **ponto principal** (pino que o cidadão soltou) → gravado como geometria PostGIS.
- `respostas_boletim` = objeto **chaveado pelo `label_campo`** do boletim (formatos na seção 5).
- **`fotos`** = array de **data-URI base64** (mesmo formato das coletas) → o backend salva e associa ao chamado. 1–3 fotos.
- `fase_atual_id` e `protocolo` são definidos **pelo servidor**. Não envie.
- Sucesso: `201 { "data": { ...chamado, "protocolo": "A1B2C3D4" } }`. Validação: `422 { "errors": {...} }`.

### 4.5. Meus chamados — `GET /api/chamados` (auth)
Só os chamados **do próprio usuário**, recentes primeiro (até 200):
```json
{ "data": [
  { "id": 88, "protocolo": "A1B2C3D4", "descricao": "...", "status": "aberto",
    "created_at": "2026-07-03T12:00:00Z",
    "categoria":  { "id": 2, "nome": "Lâmpada apagada" },
    "fase_atual": { "id": 5, "nome": "Aberto" } }
] }
```
- Cards: **protocolo**, badge da **categoria** (cor vem da lista de 4.2 → mapear por `categoria.id`), badge da **fase** (`fase_atual.nome`), trecho da descrição, data. Pull-to-refresh rechama este endpoint.

### 4.6. Chat do chamado — mensagens
**`GET /api/chamados/{id}/mensagens`** (auth) → **cidadão vê só `publica = true`**:
```json
{ "data": [ { "id": 1, "chamado_id": 88, "user_id": null, "texto": "Recebemos seu chamado.", "publica": true, "created_at": "..." } ] }
```
**`POST /api/chamados/{id}/mensagens`** (auth) — `{ "texto": "Obrigado!" }` → `201 { "data": {...} }`. Mensagem do cidadão é **sempre pública**.

> **Push:** quando a prefeitura muda a fase ou envia mensagem **pública**, o backend dispara um push Expo ao `expo_push_token` do cidadão (inclusive após encerrado). Basta o app ter registrado o token no login/cadastro e tratar a notificação.

---

## 5. Boletim dinâmico (questionário do fluxo) — renderização

O boletim (de 4.3) é um array de campos `{ "type": ..., "data": {...} }`. **4 tipos:**

| `type` | `data` | Como renderizar | Como enviar em `respostas_boletim` |
|---|---|---|---|
| `texto` | `{ label_campo, obrigatorio }` | `TextInput` | `{ [label_campo]: "string" }` |
| `checkbox` | `{ label_campo, opcoes: ["A","B"], obrigatorio }` | lista **multi-seleção** | `{ [label_campo]: ["A","B"] }` (**array**) |
| `mapa` | `{ label_campo, obrigatorio }` | **seletor de ponto no mapa** | `{ [label_campo]: { "lat": -26.9, "lon": -50.4 } }` |
| `documento` | `{ label_campo, mascara: "cpf"\|"telefone", obrigatorio }` | `TextInput` com **máscara** | `{ [label_campo]: "000.000.000-00" }` |

Regras:
- Respeite `obrigatorio` antes de habilitar "Enviar".
- Para `mapa`, você **pode reutilizar o pino principal** do chamado (o `lat`/`lon` do POST) como resposta — não precisa de dois mapas.
- `respostas_boletim` é sempre um **objeto plano chaveado pelo `label_campo`**. Não invente IDs.

---

## 6. Telas (navegação e comportamento)

1. **Login** — três opções: **e-mail/senha** (`POST /login`), **Continuar com Google** (`POST /auth/google`), **Continuar com Facebook** (`POST /auth/facebook`). Link **"Criar conta"** → tela de Cadastro. Sempre enviar `expo_push_token`.
2. **Cadastro (e-mail/senha)** — primeiro um **seletor de prefeitura** (`GET /api/prefeituras`), depois nome, e-mail, CPF, telefone, senha → `POST /cidadao/register`. No sucesso já vem logado (recebe token).
   - No **login social**, se ainda não houver prefeitura escolhida no dispositivo, mostre o **seletor de prefeitura** antes de acionar Google/Facebook (o `tenant_id` é obrigatório nesses endpoints).
3. **Meus Chamados (Home)** — `GET /api/chamados`. Cards (protocolo, categoria colorida, fase, descrição, data). Pull-to-refresh. Botão flutuante **"＋ Novo Chamado"**. Estado vazio amigável.
4. **Novo Chamado** (assistente em passos):
   1. **Categoria** — `GET /api/categorias-chamado`, agrupado por `pai_id`, com cor/ícone.
   2. **Boletim** — `GET /api/fluxos-chamado?categoria_id=` → renderize os campos (seção 5). Se vazio, pule.
   3. **Descrição** — texto obrigatório.
   4. **Local** — **mapa** centralizado em `tenant.map_lat/lon/zoom`; **solta o pino** (captura `lat`/`lon`). Botão "Usar minha localização" (GPS).
   5. **Fotos** — câmera/galeria → base64 (reaproveitar das coletas). 1–3 fotos.
   6. **Enviar** — `POST /api/chamados`. Sucesso → volta pra Home e mostra o **protocolo**.
5. **Detalhe do Chamado** — cabeçalho (protocolo, categoria, **fase atual** badge, status, data), respostas do boletim, fotos (galeria), e o **Chat** (4.6): mensagens públicas + campo "responder". Atualiza ao abrir e via pull-to-refresh.
6. **Notificações** — ao receber push, tocar abre o **Detalhe** do chamado (o backend pode mandar `chamado_id` no `data`; se não vier, abra a Home).

---

## 7. Modelo de dados no app (mínimo — sem offline pesado)

- **Sessão:** `token`, `user`, `tenant`, e a **prefeitura escolhida** (secure/async storage).
- **Chamados:** buscados **online** (não replique o banco offline das coletas). Cache leve em memória basta.
- **Remova** SQLite/WatermelonDB/fila de sync das coletas.

---

## 8. Push (Expo) — resumo

- No boot, peça permissão e obtenha o `ExponentPushToken`.
- Envie-o em **todo** login/cadastro (`expo_push_token`) — o backend salva no usuário.
- Handler de notificação: ao tocar, navegue ao **Detalhe do Chamado**.
- O backend dispara push em **mudança de fase** e **mensagem pública** da prefeitura.

---

## 9. Fora de escopo (não fazer)

- Sincronização offline pesada, pull/push de lotes, "imóvel mais próximo", GPS tracking de cadastrador, produtividade, mensagens entre fiscais, telas de coleta CTM. **Tudo isso é do app de origem e deve ser removido.**
- Painel administrativo (é o web, Filament, já existe).
- Login por papel/permissão de fiscal — o cidadão não tem papel.

---

## 10. Definition of Done (o que precisa rodar na demo da PoC)

- [ ] **Login nos 3 modos**: e-mail/senha, Google e Facebook (todos retornam token e entram no app).
- [ ] **Cadastro** por e-mail/senha com seleção de prefeitura.
- [ ] Listar **categorias** (sem as privadas), com cor/ícone e hierarquia.
- [ ] **Abrir chamado**: categoria → boletim dinâmico → descrição → **pino no mapa** → **foto** → enviar → recebe `protocolo`.
- [ ] **Meus Chamados** com **status/fase** e cores.
- [ ] **Detalhe** com dados + fotos + respostas do boletim + **chat** (enviar/ver mensagens públicas).
- [ ] **Push** ao tocar abre o chamado (mudança de fase / resposta da prefeitura).
- [ ] App com **identidade própria** (nome/ícone/bundle), sem restos visíveis do app de coletas.

---

## 11. Prompt inicial sugerido (cole no Claude deste repo)

> "Este repositório é uma cópia do nosso app de Coletas de Recadastramento (Expo/React Native). Leia o `appChamados.md` na raiz — é a especificação completa e o backend já está no ar. Faça, nesta ordem: (1) **leia o `CLAUDE.md` deste repositório e use-o para inventariar os módulos reutilizáveis** (cliente HTTP, mapa, câmera→base64, GPS, push Expo, storage de sessão), citando os arquivos/caminhos de cada um, e liste o que vamos manter; (2) remova telas/stores/serviços do domínio de coletas (sync de lotes, nearest, GPS de cadastrador, produtividade); (3) reconfigure a identidade do app (nome, slug, bundle, ícone) e configure o login social (Google/Facebook via expo-auth-session); (4) implemente as telas da seção 6 consumindo a API da seção 4, com os 3 tipos de login (4.1), o boletim dinâmico (4.3/5) e o push (8). Comece pelo item (1) e me mostre o inventário (com os caminhos do CLAUDE.md) antes de apagar qualquer coisa."

---

## 12. Referência rápida dos endpoints (todos LIVE)

| Método | Rota | Auth | Corpo principal |
|---|---|---|---|
| GET | `/api/prefeituras` | — | — |
| POST | `/api/cidadao/register` | — | name, email, password, cpf?, telefone?, **tenant_id\|prefeitura_slug**, expo_push_token? |
| POST | `/api/login` | — | email, password, expo_push_token? |
| POST | `/api/auth/google` | — | **token** (idToken), **tenant_id\|prefeitura_slug**, expo_push_token? |
| POST | `/api/auth/facebook` | — | **token** (accessToken), **tenant_id\|prefeitura_slug**, expo_push_token? |
| POST | `/api/logout` | Bearer | — |
| GET | `/api/me` | Bearer | — |
| GET | `/api/categorias-chamado` | Bearer | — |
| GET | `/api/fluxos-chamado?categoria_id=` | Bearer | — |
| POST | `/api/chamados` | Bearer | categoria_chamado_id, fluxo_chamado_id?, descricao, lat?, lon?, respostas_boletim?, **fotos[]** |
| GET | `/api/chamados` | Bearer | — |
| GET | `/api/chamados/{id}/mensagens` | Bearer | — |
| POST | `/api/chamados/{id}/mensagens` | Bearer | texto |

> **Nota de contrato:** todos os 4 pontos de entrada de auth (login, register, google, facebook) devolvem **o mesmo payload** `{ token, user, tenant, layers }`. Trate a resposta de auth num único lugar no app.
