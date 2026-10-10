# Servidor de testes / PoC do SIGWEB — receita (preparada em 2026-10-10)

Ambiente **do zero** (sem copiar o banco de produção) para demonstrações (PoC) e para testar releases antes da produção. Mesmo código, mesmas versões e mesma montagem do servidor de produção (ver [migracao-servidor.md](migracao-servidor.md)).

## Decisões (alinhadas em 2026-10-10)

| Item | Como fica no teste |
|---|---|
| Banco | **Novo, vazio** — prefeituras, usuários e bases criados pelo /admin. Nenhum dado pessoal de produção. |
| Ortofotos | **Mesmas da produção**: no cadastro da prefeitura de teste, colar a mesma URL `https://tiles.sigwebmidia.com.br/...` (bucket público, só leitura por natureza). |
| Fotos dos lotes | Bucket **próprio** `sigweb-teste-fotos` (público), domínio próprio, chave própria. |
| 360 | **Mesmo bucket** `sigweb-midia` da produção com chave **SÓ LEITURA**. A prefeitura de teste precisa ter **o mesmo slug** da produção (o caminho das fotos é `{slug}/panoramicas/...`). |
| Fotos do "Fale conosco" | Com a chave só leitura no `sigweb-midia`, vão para o disco do próprio servidor de testes (fallback que já existe). |
| Anexos, LiDAR | Ficam para depois do INF-2 (buckets específicos de teste). |
| Backup | **Não tem** (decisão do usuário). Nada do `/root/backup-diario.sh` vai para lá. |
| E-mail | Chave **separada** do Resend (`sigweb-teste`), revogável sem afetar a produção. |
| Agendador / fila | Igual à produção: sem cron do Laravel, sem worker. |
| App de coleta | Não aponta para o teste (URL fixa no app). |

⚠️ **Nunca copiar as linhas "Cloudflare R2"/"Cloudflare R2 Fotos" da produção para o teste.** Com a chave de produção, excluir uma prefeitura de teste ou rodar o `tenant:limpar-orfaos` no teste apagaria pastas reais do bucket (o banco do teste não conhece as prefeituras de produção — todas pareceriam órfãs). A chave só leitura é o que impede isso.

---

## Parte A — com você (Cloudflare, Contabo, GitHub)

1. **VPS Contabo**: Ubuntu **26.04** (se não houver, 24.04 — avisar, muda a origem do PHP), mínimo 4 vCPU / 8 GB / 100 GB. Cadastrar a chave SSH `acesso-vps-genesis` na criação. Anotar o **IPv4 e o IPv6**.
2. **Endereço**: registro **A** (nuvem cinza) `demo.liderengenharia.eng.br` → IP da Contabo (+ **AAAA** se a Contabo der IPv6). O registro explícito ganha do curinga `*` (que aponta para a produção). *(nome decidido em 2026-10-10)*
3. **Token da Cloudflare para o certificado**: duplicar o `certbot-lider-vps` (Zona → DNS → Editar em `liderengenharia.eng.br`) com o filtro de IP da **Contabo** (IPv4 + IPv6 — o servidor pode sair pelo IPv6, erro 9109 se faltar).
4. **R2**:
   - Bucket **`sigweb-teste-fotos`** (público): Custom Domain `fotos-teste.sigwebmidia.com.br` + CORS igual ao do `sigweb-fotos`.
   - Bucket **`sigweb-teste-midia`** (privado) — fica pronto para o INF-2.
   - Token **`sigweb-teste-rw`**: Leitura e gravação de objeto, só `sigweb-teste-fotos` + `sigweb-teste-midia`, filtro de IP da Contabo.
   - Token **`sigweb-teste-ro-360`**: **Somente leitura** de objeto, só `sigweb-midia`, filtro de IP da Contabo.
5. **Resend**: criar a chave `sigweb-teste` (Sending access, domínio `liderengenharia.eng.br`).
6. **GitHub**: a deploy key é gerada no servidor (Parte B, passo 5) — você só cola em `genesisautomacao/saas-sigweb` → Settings → Deploy keys (só leitura).

## Parte B — instalação do servidor (eu faço, com acesso root por chave)

1. **Segurança base** (igual à Etapa 1 da produção): atualizar + reiniciar; fuso `America/Sao_Paulo`; hostname `sigweb-demo`; `unattended-upgrades`; `ufw` só 22/80/443; `fail2ban` no SSH; SSH só por chave (`PasswordAuthentication no`, `PermitRootLogin prohibit-password`). Conferir o `authorized_keys` uma chave por linha.
2. **Programas** (mesmas versões da produção): nginx; **PHP 8.4 do `packages.sury.org`** (FPM + CLI + pgsql, sqlite3, mbstring, xml, curl, zip, gd, intl, bcmath, gmp, soap, imagick, redis, opcache) fixado por `update-alternatives`; **PostgreSQL 18 + PostGIS 3.6**; Composer pelo instalador oficial; Certbot + `python3-certbot-dns-cloudflare`. Sem MySQL (o Criador de Sites não vai para o teste), sem Node (o SIGWEB não usa `public/build`), sem rclone.
3. **Certificado**: você grava o token em `/root/.secrets/cloudflare.ini` (600) com `read -rs`; `certbot certonly --dns-cloudflare -d demo.liderengenharia.eng.br` + hook de reload do nginx + `certbot renew --dry-run`.
4. **Banco**: usuário `sigweb_teste` com senha gerada no servidor (nunca exibida), banco `sigweb_teste`; extensões `postgis` + `pg_trgm` criadas pelo `postgres`; ouvindo só em localhost.
5. **Código**: usuário `webgis` (home `/srv/webgis`); deploy key `deploy-webgis@sigweb-demo` (só leitura) → `git clone` em `/srv/webgis/saas-sigweb` (branch `main`); `composer install --no-dev --optimize-autoloader`.
6. **`.env`** (640, dono `webgis`): `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://demo.liderengenharia.eng.br`, `APP_TIMEZONE=America/Sao_Paulo`, banco do passo 4, `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`, `LOG_LEVEL=warning`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log` (até cadastrar o Resend no /admin), `php artisan key:generate`.
7. **Instalação do sistema**: `php artisan migrate --seed --force` (papéis, Master, permissões, acessos do /admin); **trocar a senha do Master na hora** (ver ⚠️ abaixo); `db:seed --class=KitCamposCustomizadosSeeder`; `storage:link`; caches (`config/route/view/event/icons` + `filament:optimize`).
8. **PHP-FPM**: pool `webgis` igual ao da produção (socket próprio, memória 1 GB, upload 50 MB/post 55 MB, 900 s); pool `www` desativado.
9. **nginx**: cópia do `sites-available/webgis` da produção trocando `server_name`/certificado; upload 60 MB, `fastcgi_read_timeout 900`, ocultos negados, **`add_header X-Robots-Tag "noindex, nofollow"`** (o Google não indexa o ambiente de teste); `00-default` fechando host desconhecido (444).
10. **Conferência**: logins dos 3 painéis, mapa público, `/.env` = 403, `migrate:status` = 0 pendentes.

## Parte C — configuração no /admin do teste (com você)

1. Entrar como Master (senha nova) → **Configurações de APIs**:
   - **"Cloudflare R2"** = bucket `sigweb-midia` + endpoint da conta + **chave `sigweb-teste-ro-360`** (só leitura).
   - **"Cloudflare R2 Fotos"** = bucket `sigweb-teste-fotos` + chave `sigweb-teste-rw` + `R2_PUBLIC_URL=https://fotos-teste.sigwebmidia.com.br`.
   - **"Resend"** = chave `sigweb-teste` + `MAIL_FROM_ADDRESS=noreply@liderengenharia.eng.br` + `MAIL_FROM_NAME` (ex.: "SIGWEB — Ambiente de testes").
   - Azure Maps / Google Maps: opcional (são chaves de navegador; reusar ou deixar sem).
   - **Não criar** "Backup" nem "Cloudflare Turnstile".
2. **Prefeituras**: criar com o **mesmo slug** da produção quando for reaproveitar 360 (ex.: `prefeitura-municipal-de-bom-principio`); módulos; **Ortofotos** → mesma URL da produção, tile 512.
3. **Bases**: "Importar Mapa (GIS)" na ordem hierárquica (Distritos → … → Quadras → Lotes) + "Recalcular Áreas (GIS)".
4. **360**: "Importar Panorâmicas 360" com o **mesmo GeoJSON de imageamento** da produção — os caminhos batem com as fotos que já estão no bucket; o visualizador lê com a chave só leitura.

## Atualizar o teste (rotina)

```bash
ssh <alias-do-teste>          # cai como webgis em /srv/webgis/saas-sigweb
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize && php artisan filament:optimize
```
Fluxo sugerido: **release vai primeiro para o teste**, valida, depois produção.

## ⚠️ Pontos de atenção

- **Senha do Master no código:** o `RolesAndMasterUserSeeder` cria o Master com e-mail e senha **fixos no repositório**. No teste, trocar a senha logo após o `--seed`. Sugestão de backlog: ler e-mail/senha do Master de variável de ambiente (ou gerar aleatória e mostrar uma vez) e trocar a senha da produção se for a mesma.
- O teste é **público na internet** (precisa ser, para PoC). Dados fictícios ou de demonstração apenas; nada de base real com CPF/proprietários sem autorização da prefeitura.
- Chaves do teste sempre **separadas** e com filtro de IP da Contabo — revogar uma não afeta a produção.
