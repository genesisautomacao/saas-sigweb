# Upload de Panorâmicas 360 → Bucket Cloudflare R2 (via rclone)

> **Roteiro para rodar na máquina onde estão as fotos** (ex.: máquina da Líder).
> As imagens vão DIRETO da máquina local para o bucket `sigweb-midia` — sem HD
> externo, sem passar pela VPS. Pode rodar de noite; se a conexão cair, é só
> rodar o mesmo comando de novo que ele **retoma de onde parou**.

---

## 0. Antes de começar (na SUA conta Cloudflare — 2 min)

Por segurança, crie um **token descartável** para usar na máquina de terceiros
(as credenciais principais nunca saem da sua máquina):

1. Painel Cloudflare → **R2 Object Storage** → **`{}` API** → **Manage API tokens**
2. **Create Account API token** com:
   - **Token name:** `lider-upload-temporario`
   - **Permissions:** `Object Read & Write`
   - **Specify bucket(s):** *Apply to specific buckets only* → `sigweb-midia`
   - **TTL:** Forever (vamos revogar manualmente no fim)
3. Anote o **Access Key ID** e o **Secret Access Key** (o Secret aparece SÓ uma vez)

> ⚠️ **Ao terminar o upload, volte nessa tela e DELETE este token.**

---

## 1. Instalar o rclone (na máquina das fotos — Windows)

Abrir o **PowerShell** e colar o bloco inteiro:

```powershell
$dest = "$env:USERPROFILE\rclone"; New-Item -ItemType Directory -Force $dest | Out-Null
Invoke-WebRequest -Uri 'https://downloads.rclone.org/rclone-current-windows-amd64.zip' -OutFile "$env:TEMP\rclone.zip"
Expand-Archive "$env:TEMP\rclone.zip" -DestinationPath "$env:TEMP\rclone-extract" -Force
Copy-Item (Get-ChildItem "$env:TEMP\rclone-extract" -Recurse -Filter rclone.exe | Select-Object -First 1).FullName "$dest\rclone.exe" -Force
& "$dest\rclone.exe" version
```

✅ Deve imprimir a versão (ex.: `rclone v1.75.0`).

---

## 2. Conectar ao bucket

Colar o comando abaixo **trocando os dois placeholders** pelas chaves do token
temporário (passo 0):

```powershell
& "$env:USERPROFILE\rclone\rclone.exe" config create r2 s3 provider=Cloudflare access_key_id=COLE_AQUI_O_ACCESS_KEY secret_access_key=COLE_AQUI_O_SECRET endpoint=https://fc9328de42f0c0f3d34b93bc724b0a1c.r2.cloudflarestorage.com
```

Testar a conexão (sem erro = conectado; a lista pode vir vazia, é normal):

```powershell
& "$env:USERPROFILE\rclone\rclone.exe" lsd r2:sigweb-midia
```

---

## 3. Subir as fotos

A pasta de origem deve ser a **pasta-mãe que contém as pastas dos dias**
(`20260719\`, `20260724\`, `20260727\`...). Ajuste o caminho no início:

```powershell
& "$env:USERPROFILE\rclone\rclone.exe" copy "D:\CAMINHO\DAS\FOTOS\bom_principio" r2:sigweb-midia/prefeitura-municipal-de-bom-principio/panoramicas --exclude "_GEO_CSV/**" --exclude "*.geojson" --progress --transfers 8
```

O que esse comando faz:

- Replica a árvore de pastas: `20260724\foto.jpg` local vira
  `.../panoramicas/20260724/foto.jpg` no bucket — **exatamente** o caminho que
  o sistema espera (não renomeie as pastas dos dias!)
- **Ignora** as pastas `_GEO_CSV` e arquivos `.geojson` (não precisam subir)
- **Pula automaticamente** o que já existe no bucket (seguro rodar por cima —
  o dia 20260727 já enviado não duplica)
- `--progress` mostra o andamento em tempo real

**Tempo estimado para ~350 GB:** internet de 100 Mbps ≈ 8–9 h · 300 Mbps ≈ 3 h.
Pode deixar rodando de madrugada. **Se cair a conexão ou o PC reiniciar: rode o
mesmo comando de novo** — ele continua de onde parou.

---

## 4. Conferir no final

```powershell
& "$env:USERPROFILE\rclone\rclone.exe" size r2:sigweb-midia/prefeitura-municipal-de-bom-principio/panoramicas
```

✅ Esperado: **~46.000 arquivos / ~350 GB** (todos os dias somados).

---

## 5. Encerramento (limpeza e segurança)

Na máquina da Líder — apagar a configuração com as chaves:

```powershell
& "$env:USERPROFILE\rclone\rclone.exe" config delete r2
```

Na SUA conta Cloudflare — **revogar o token** `lider-upload-temporario`
(R2 → Manage API tokens → ⋯ → Delete).

---

## O que acontece no sistema (nada a fazer! 🎉)

Os 46.076 pontos panorâmicos **já estão importados** no SIGWEB. Conforme as
fotos chegam ao bucket, os avisos "📤 Foto ainda não enviada" desaparecem
sozinhos e cada ponto passa a abrir sua imagem 360 — com navegação pelas setas.

## Problemas comuns

| Sintoma | Solução |
|---|---|
| `AccessDenied` no lsd/copy | Chave ou Secret colados errado — repita o passo 2 |
| Upload muito lento | Reduza para `--transfers 4` (rede compartilhada) ou rode fora do horário comercial |
| PC não pode ficar ligado | O comando pode ser rodado em várias sessões — cada rodada continua do ponto anterior |
| Conferir um dia específico | `& "$env:USERPROFILE\rclone\rclone.exe" size r2:sigweb-midia/prefeitura-municipal-de-bom-principio/panoramicas/20260724` |
