# Migração dos sistemas da Líder — Hostgator → Hostinger

**Iniciado em:** 2026-10-09 · **Status:** 📋 Planejado (servidor contratado, instalação a começar)

## Escopo — 3 sistemas no mesmo servidor (atualizado 2026-10-09)

| Sistema | Endereço | Tecnologia | Banco | Origem hoje |
|---|---|---|---|---|
| **SIGWEB (webgis)** | `webgis.liderengenharia.eng.br` | Laravel 12 + Filament 3, PHP 8.3 | PostgreSQL + PostGIS | cPanel próprio |
| **Site da Líder** | `liderengenharia.eng.br` (+ `www`) | HTML/CSS/JS + JSON local (`C:\laragon\www\site-lider`) — futuro: Laravel + painel + MySQL | — (futuro MySQL) | cPanel compartilhado |
| **Criador de Sites (ferramenta)** | painel em `ferramenta.liderengenharia.eng.br/app` + **um subdomínio por prefeitura** (`{slug}.liderengenharia.eng.br`) | Laravel 13 + Filament 4, PHP ≥ 8.3 (`C:\laragon\www\ferramenta_sites`) | MySQL | cPanel compartilhado |

Deploy dos dois Laravel: commit no GitHub → `git pull` na VPS (cada um no seu repositório). Depois de tudo instalado e migrado, o usuário desativa **as contas da Líder** na VPS atual (que continua no ar com os outros clientes) e dá acesso root à VPS nova para a instalação ser feita pelo Claude.

### Implicações de ter os três juntos

- **Isolamento:** um usuário Linux por sistema + um pool do PHP-FPM por sistema + um usuário de banco por sistema — falha ou invasão em um não alcança os outros.
- **Dois bancos:** PostgreSQL/PostGIS (SIGWEB) e MySQL (Criador de Sites; futuro site da Líder) lado a lado. Manter a versão principal do MySQL de hoje (a conferir).
- **Subdomínio automático por prefeitura = curinga:** DNS `*.liderengenharia.eng.br` → VPS nova + **certificado SSL curinga** + bloco do nginx curinga para o Criador de Sites. Registros/blocos explícitos (`webgis`, `ferramenta`, `www`, `tiles`, `fotos`…) têm prioridade sobre o curinga — o SIGWEB não é "engolido".
- ⚠️ **Slugs reservados no Criador de Sites:** hoje o `TenantMiddleware` bloqueia só `app`, `www` e `admin`. Uma prefeitura cadastrada com slug `webgis`, `ferramenta`, `tiles`, `fotos`, `mail`, `webmail`, `ftp`… nunca abriria (o nome explícito ganha do curinga). Ajuste a fazer no projeto ferramenta_sites: lista de slugs reservados bloqueada na criação.
- ⚠️ **E-mails do domínio `liderengenharia.eng.br`:** se as caixas estão no cPanel da Hostgator, mover o DNS para a Cloudflare exige copiar os registros MX/SPF/DKIM, e cancelar a Hostgator acaba com as caixas — migrar o e-mail antes (Hostinger Mail, Google Workspace, Zoho…).
- **Memória:** três sistemas + PostgreSQL + MySQL pedem **pelo menos 8 GB de RAM** (confirmar o plano contratado).

## Decisões

| Tema | Decisão |
|---|---|
| Origem | VPS Hostgator — AlmaLinux + cPanel/WHM, PHP 8.3 (`ea-php83`), PostgreSQL + PostGIS, domínio `webgis.liderengenharia.eng.br`. ⚠️ VPS compartilhada com ~55 domínios de outros clientes da Gênesis: **continua ligada**; só as contas da Líder saem |
| Destino | VPS Hostinger — **Ubuntu 26.04 LTS** instalado "puro", **sem painel** (os modelos com CloudPanel etc. são feitos para MySQL e não gerenciam PostgreSQL/PostGIS) — recebe os **3 sistemas** (ver Escopo) |
| DNS | Domínio passa a ser gerenciado pela **Cloudflare** na migração |
| Ordem | **Servidor primeiro, arquivos para o bucket depois** (2026-10-09): anexos de processos, documentos etc. vão por **rsync como estão**; a ida deles para o bucket privado é release própria (ver "Depois da migração") — não mudar duas coisas de uma vez |

Trocar de distribuição não afeta o sistema (Laravel/PHP + PostgreSQL). O domínio continua o mesmo, então os apps (coletas e chamados) e o mapa público seguem funcionando sem publicar versão nova.

## Servidor novo (acesso conferido em 2026-10-09)

- **IP:** `179.199.155.36` · SSH porta 22 · Ubuntu **26.04.1 LTS** (`resolute`, kernel 7.0) · **8 vCPU · 31 GB RAM · 387 GB disco**.
- **Acesso da instalação:** chave dedicada `~/.ssh/claude_vps` (sem senha, criada só para a instalação — remover do `authorized_keys` ao final), autorizada pelo painel da Hostinger.

### ✅ Etapa 1 — segurança base (concluída em 2026-10-09)

- Sistema atualizado (kernel 7.0.0-38) + reinício; fuso `America/Sao_Paulo`; hostname `lider-vps`; `unattended-upgrades` ligado.
- Firewall `ufw`: só 22, 80 e 443 de entrada. `fail2ban` no SSH (5 erros em 10 min ⇒ bloqueio de 1 h).
- **SSH só por chave** (`/etc/ssh/sshd_config.d/00-lider-seguranca.conf`: `PasswordAuthentication no`, `PermitRootLogin prohibit-password`; o `50-cloud-init.conf` também passou para `no`). Chaves autorizadas para root: `claude-instalacao-vps-lider` (temporária) e `acesso-vps-genesis` (chave pessoal do Jessé, a mesma da VPS da Hostgator). Emergência: console do painel da Hostinger.
- ⚠️ Pegadinha: a chave adicionada pelo painel da Hostinger foi gravada **sem quebra de linha** no `authorized_keys` — anexar outra com `>>` grudou as duas numa linha só. Sempre conferir com `awk '{print NR, $NF}' ~/.ssh/authorized_keys`.

### ✅ Etapa 2 — programas (concluída em 2026-10-09)

| Programa | Versão | Origem |
|---|---|---|
| nginx | 1.28.3 | Ubuntu |
| PHP (FPM + CLI) | **8.4.26** | **`packages.sury.org`** (o PPA `ondrej/php` não atende o 26.04 — o próprio Ondřej indica o sury.org; o Ubuntu só traz 8.5). Fixado como padrão via `update-alternatives`; nenhum pacote 8.5 instalado |
| PostgreSQL | **18.6** | Ubuntu (não precisou do PGDG) |
| PostGIS | **3.6.2** | Ubuntu |
| MySQL | **8.4.11 LTS** | Ubuntu |
| GDAL | 3.12.2 | Ubuntu |
| Node | 22.22.1 | Ubuntu |
| Composer | 2.10.3 | instalador oficial (assinatura conferida) — o pacote do Ubuntu puxaria o PHP 8.5 |
| rclone | 1.75.2 | instalador oficial (o do Ubuntu é de 2022) |
| Certbot | 4.0.0 (+ plugin nginx) | Ubuntu |

Extensões PHP: pgsql/pdo_pgsql, pdo_mysql, sqlite3, mbstring, xml, curl, zip, gd, intl, bcmath, gmp, soap, imagick, redis, opcache. Bancos ouvindo **só em localhost** (5432/3306/33060) + firewall fechado para essas portas.

### ✅ Etapa 3 — certificado SSL (concluída em 2026-10-09)

- **Let's Encrypt validado por DNS na Cloudflare** (decisão do usuário: funciona antes da virada, cobre o curinga, serve com nuvem cinza ou laranja). Certificado único `liderengenharia.eng.br` + `*.liderengenharia.eng.br` em `/etc/letsencrypt/live/liderengenharia.eng.br/` (válido até 07/01/2027; renovação automática pelo `certbot.timer` + hook `renewal-hooks/deploy/reload-nginx.sh`; simulação de renovação OK).
- Token da Cloudflare `certbot-lider-vps`: só Zona→DNS→Editar em `liderengenharia.eng.br`, filtrado pelos IPs do servidor **`179.199.155.36` e `2a02:4780:c6:156::1`** — ⚠️ o servidor sai pela internet preferencialmente pelo **IPv6**; sem ele no filtro a Cloudflare recusa (erro 9109). Gravado pelo próprio usuário em `/root/.secrets/cloudflare.ini` (600), fora da conversa e do histórico.
- O curinga cobre um nível só (`webgis`, `ferramenta`, `www`, `{slug}` das prefeituras). Nome com dois níveis (ex.: `x.y.liderengenharia.eng.br`) precisaria de certificado próprio.

### Canal de cópia servidor novo → Hostgator

- Chave `migracao_hostgator` criada **no servidor novo** (`/root/.ssh/migracao_hostgator`, comentário `migracao-lider-vps-para-hostgator`) e autorizada no `/root/.ssh/authorized_keys` da Hostgator (linha 14; backup em `authorized_keys.bak-migracao`). O servidor novo **puxa** os dados: `rsync/ssh -p 22022 -i /root/.ssh/migracao_hostgator root@162.240.179.66`. **Remover essa linha da Hostgator ao final da migração.**

### ✅ Etapa 4.1 — site da Líder (montado e testado em 2026-10-09; aguardando a virada do DNS)

- Usuário de sistema `site-lider` (sem shell), arquivos em `/srv/site-lider/public` (copiados do `public_html`; permissões corrigidas de 666/777 para 644/755; `.htaccess` não copiado).
- nginx: `sites-available/site-lider` (http→https; `www` abre o site direto, igual a hoje; os 2 redirecionamentos do `.htaccess` — `/home-2/` e `/gestao-de-cidades/` → `/` — recriados), snippet `snippets/ssl-liderengenharia.conf` (certificado curinga + TLS 1.2/1.3 + HSTS) e `sites-available/00-default` (host desconhecido = conexão fechada, `444`; página padrão do nginx removida).
- Teste com `curl --resolve` no IP novo: redirecionamentos idênticos, conteúdo **idêntico** (mesmo hash do site atual), certificado novo servido, `.htaccess`/arquivos ocultos = 403.


### 🔄 Etapa 4.2 — SIGWEB (montado em 2026-10-09; cópia de teste — a definitiva é na janela)

- Usuário `webgis` (shell bash, home `/srv/webgis`, 750; `www-data` no grupo para ler o `public`), deploy key própria (`deploy-webgis@lider-vps`, só leitura, em `genesisautomacao/saas-sigweb`). Código em `/srv/webgis/saas-sigweb` (commit `4f861f7`, o mesmo da VPS antiga), `composer install --no-dev --optimize-autoloader`; `check-platform-reqs` OK.
- ⚠️ **Repositório versionava `bootstrap/cache/packages.php`/`services.php` (+ `.tmp`)** gerados no Laragon COM os pacotes de dev ⇒ com `--no-dev` o artisan quebrava (`Laravel\Pail\PailServiceProvider not found`). Correção **preparada no repo local (falta o commit do usuário)**: `git rm --cached` desses arquivos + `bootstrap/cache/.gitignore` (`*` / `!.gitignore`), padrão do Laravel. No servidor novo foram regenerados com `composer dump-autoload --no-dev`. ⚠️ **Na próxima atualização da VPS antiga** depois desse commit: o `git pull` vai apagar os arquivos (lá estão modificados) — rodar `git checkout -- bootstrap/cache` antes do pull, se reclamar, e `php artisan package:discover` depois.
- `.env` copiado da VPS antiga (640, dono `webgis`) com ajustes: **`APP_DEBUG=false`** (estava `true` em produção — mostrava páginas técnicas de erro até no mapa público), `LOG_LEVEL=warning`, `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`, **senha nova do banco** (gerada no servidor, nunca exibida).
- **Banco:** PostgreSQL 18, mesmo nome (`webgislider_saas_base`) e usuário (`webgislider_saas_user`); extensões `postgis 3.6.2` + `pg_trgm` criadas pelo `postgres`; dump `pg_dump -Fc` feito NA VPS antiga (PG 13) e restaurado com `pg_restore --no-owner --no-privileges --role=webgislider_saas_user -j 4` pulando as linhas `EXTENSION`; linhas customizadas do `spatial_ref_sys` restauradas como `postgres`. **Contagem de linhas idêntica nas 139 tabelas** (ex.: 78.564 lotes, 58.364 edificações, 104.836 pontos 360, 149 usuários). Scripts em `/root/dump-webgis.sh` e `/root/restore-webgis.sh` (reusar na janela).
- Tabela **`bkp_lotes_adotados_20260902`** (backup manual de 02/09, 271 linhas, dona = usuário da conta cPanel `webgislider`) **fica de fora** — confirmado pelo usuário que pode ser desconsiderada (o `--exclude-table` continua no `/root/dump-webgis.sh`).
- PHP-FPM: pool `webgis` (`/etc/php/8.4/fpm/pool.d/webgis.conf`, socket `/run/php/webgis.sock`, roda como `webgis`, memória 1 GB, upload 50 MB/post 55 MB, tempo 900 s); pool `www` padrão desativado. nginx: `sites-available/webgis` (http→https, upload 60 MB, `fastcgi_read_timeout 900`, ocultos negados). `storage:link`; caches `config/route/view/event/icons` + `filament:optimize`. `migrate:status` = 0 pendentes.
- Testado com `curl --resolve`: logins dos 3 painéis, mapa público, portal, `/api/gis-data` → 200 em 0,1–0,3 s; `/.env` → 403. R2 a partir do servidor novo: bucket público `fotos` ✓ e privado `midia` ✓ (token restrito por bucket, não por IP).
- **Teste funcional com dados (2026-10-09):** camadas do mapa comparadas feição a feição com o SIGWEB no ar — Santa Cecília (4.439 lotes, 375 quadras, 15 bairros, 199 logradouros, 7.889 edificações), Nova Esperança, Bom Princípio (6.610 lotes) e Piúma: **todas idênticas** (comparação interrompida depois por lentidão da VPS antiga, não do servidor novo); **648/648 anexos de processos** e **19/19 documentos** presentes no disco; foto de lote (bucket público) e foto 360 de Bom Princípio (URL assinada do bucket privado) abrem com HTTP 200; **BIC em PDF** gerado em 0,6 s.
- **Agendador do Laravel NÃO ativado** (decisão do usuário): o `sigweb:sync-esus` diário 02:00 nunca rodou na VPS antiga (sem cron) — manter igual; ativar só quando confirmarem. Fila: nenhum job usa fila (e-mails síncronos) ⇒ sem worker.
- Arquivos: `/root/sync-webgis.sh` (rsync incremental de `storage/app`, `public/potree`, `public/nuvem-pontos`; `public/mapas` fora) — rodar de novo na janela para pegar só a diferença.

### 🔄 Etapa 4.3 — Criador de Sites (montado em 2026-10-09; cópia de teste — a definitiva é na janela)

- Usuário `ferramenta` (deploy key `deploy-ferramenta@lider-vps` em `genesisautomacao/ferramenta_sites_lider`, só leitura). Código em `/srv/ferramenta/ferramenta_sites`, commit `111622a` (Release 1.00.1) = o mesmo da VPS antiga; `composer install --no-dev` + requisitos OK. Este repo já ignora `bootstrap/cache` corretamente; não usa `public/build`.
- `.env` copiado (640, dono `ferramenta`): `APP_BASE_DOMAIN=liderengenharia.eng.br`, `SESSION_DOMAIN=.liderengenharia.eng.br`, `QUEUE_CONNECTION=sync`, `APP_DEBUG=false`; ajustes `LOG_STACK=daily` + `LOG_DAILY_DAYS=14` e **senha nova do banco**.
- **MySQL 8.4:** banco `lider_ferramentas_site` (utf8mb4) + usuário `lider_user_ferramentas_site@localhost` com senha nova; `mysqldump --single-transaction --routines --triggers` na VPS antiga → restaurado. **Contagem idêntica nas 22 tabelas** (16 prefeituras, 254 mídias, 10 sugestões, 8 usuários). Scripts `/root/mysql-ferr.sh` e `/root/cnt-ferr.sh`.
- Arquivos: `storage/app` (150 MB) por rsync; `storage:link`; caches de produção + `filament:optimize`; 0 migrations pendentes; sem agendamentos; fila `sync` (sem worker).
- PHP-FPM: pool `ferramenta` (`/run/php/ferramenta.sock`, usuário `ferramenta`, 512 MB, upload 50 MB). nginx: `sites-available/ferramenta` com `server_name ferramenta.liderengenharia.eng.br *.liderengenharia.eng.br` — os nomes explícitos (raiz/`www` do site da Líder, `webgis`) ganham do curinga.
- Testado com `curl --resolve`: painel `/app/login` e os sites de Horizonte, Frutal, Jaboticabal, Socorro, Água Doce e Candeias do Jamari → 200 com o **mesmo título** do site no ar; imagem do `storage` → 200; slug inexistente → 404; `webgis` e raiz continuam nos seus sistemas.
- **E-mail (decidido 2026-10-09):** na Hostgator usava `sendmail` (o servidor novo não tem servidor de correio). O sistema envia e-mail no **formulário de sugestões dos sites** (`SuggestionReceived`, destino = campo `suggestions_email` da prefeitura ou o `MAIL_FROM_ADDRESS`) e na **recuperação de senha do painel**; não há tela de configuração de e-mail — só o `.env`. Configurado **Resend por SMTP** reaproveitando a chave do SIGWEB (copiada do `api_settings` direto para o `.env`, sem exibir): `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtp`, `MAIL_HOST=smtp.resend.com`, `MAIL_PORT=587`, `MAIL_USERNAME=resend`; remetente segue `noreply@liderengenharia.eng.br` (domínio verificado no Resend). Teste de conexão + autenticação OK, sem envio. Backup do `.env` anterior: `.env.bak-antes-resend`. Dá para trocar depois por uma chave própria do Resend.
## Versões (levantadas e decididas em 2026-10-09)

| Item | VPS atual | Servidor novo | Observação |
|---|---|---|---|
| PHP | 8.3.30 (`ea-php83`, as duas contas) | **8.4** nos dois Laravel (um pool por sistema) | o SIGWEB já roda em PHP 8.4.26 no Laragon; a ferramenta pode subir para 8.5 depois, isolada |
| PostgreSQL | **13.23** (sem suporte desde nov/2025) | **18** (fallback 17 se o PostGIS não tiver pacote p/ o 26.04) | migração por `pg_dump`/`pg_restore` (rodar o `pg_dump` da versão NOVA) |
| PostGIS | 3.5.0 | **3.6** | — |
| MySQL | **8.0.44** (sem suporte desde abr/2026) | **8.4 LTS** | `mysqldump` → restore |
| nginx / Node | — | versões atuais | — |

**Volumes:** banco do SIGWEB 535 MB · banco da ferramenta (`lider_ferramentas_site`) 0,7 MB · `saas-sigweb/storage/app` 3,6 GB (inclui as cópias locais das fotos de lote já migradas — encolhe com o `fotos:limpar-local`) · `public/nuvem-pontos` 6,4 GB (LiDAR, vai) · `public/potree` 65 MB (vai) · `public/mapas` **9 GB** (ortofotos antigas, já no bucket — **confirmado pelo usuário: não copiar**) · `ferramenta_sites/storage` 154 MB · `lider/public_html` (site da Líder) 19 MB · WordPress legados: medir quando decidir.

## O que o cPanel fazia e passa a ser configurado à mão

| cPanel hoje | Servidor novo |
|---|---|
| Servidor web + PHP | nginx + PHP-FPM |
| Certificado SSL | Certbot (Let's Encrypt) com renovação automática |
| Tarefas agendadas | crontab do Laravel (`schedule:run`), se usado |
| phpPgAdmin | pgAdmin/DBeaver no PC por túnel SSH (sem painel web exposto) |
| Backups | `pg_dump` diário automático com cópia para um bucket no R2 |
| Firewall / acesso | `ufw` (22, 80, 443) + login SSH só por chave |

## Pontos de atenção

- **Limites de upload ≥ 20 MB** (plantas nos processos): `upload_max_filesize` e `post_max_size` do PHP + `client_max_body_size` do nginx.
- **Cloudflare na frente do domínio ⇒ IP real do visitante:** configurar o Laravel para confiar no proxy da Cloudflare (`CF-Connecting-IP`). Sem isso, o limite de 10 chamados/hora por IP do "Fale conosco" (R80-1) vira limite para a cidade inteira.
- **E-mails do domínio:** antes de cancelar a Hostgator, conferir se há caixas de e-mail no cPanel (o sistema usa o Resend e não depende delas, mas caixas existentes precisam ir para outro lugar).
- **Turnstile:** se o domínio do sistema mudar, adicionar o host novo no widget da Cloudflare (as chaves continuam as mesmas).
- **O que já está fora do servidor:** ortofotos (`sigweb-ortofoto`), panorâmicas 360 (`sigweb-midia`) e fotos de lote (`sigweb-fotos`, R79-1). Copiar por rsync: `storage/app/public` (anexos de processos, documentos, fotos de OS/chamados, logos…), `storage/app/private`, `storage/app/mocks`, `public/potree`, `public/nuvem-pontos`, `public/mapas` (se ainda houver tiles locais) e o `.env`.

## Roteiro (a executar juntos)

1. Acesso e segurança (usuários por sistema, chave SSH, `ufw`, atualizações automáticas de segurança).
2. Pacotes: nginx, PHP-FPM 8.3 + extensões, PostgreSQL + PostGIS, MySQL, GDAL, Composer, Node, rclone.
3. DNS na Cloudflare: zona `liderengenharia.eng.br` com os registros atuais (incluindo **e-mail**) + curinga `*`; certificado SSL (inclui o curinga).
4. Um sistema por vez, do mais simples ao mais complexo:
   1. **Site da Líder** (estático) — copiar arquivos, bloco nginx, testar.
   2. **SIGWEB** — git, `composer install`, `.env`, `pg_dump`→`pg_restore`, rsync dos arquivos, cron, testar (3 painéis, mapa, mapa público, processos, app de coletas).
   3. **Criador de Sites** — git, `composer install`, `.env` (`base_domain`), dump/restore do MySQL, rsync do `storage`, bloco curinga, testar painel + 2 ou 3 sites de prefeitura.
5. Backup automático diário (PostgreSQL + MySQL + arquivos) com cópia para um bucket no R2.
6. Virada do DNS por sistema, com a VPS antiga ligada alguns dias como garantia; sincronização final de banco e arquivos no momento de cada virada.

## Pontos levantados em 2026-10-09 (2ª rodada)

- **Sites já criados no Criador de Sites:** migram junto com o banco MySQL + `storage` — o curinga `*` cobre todos os slugs sem configurar site por site. ⚠️ O cPanel costuma criar **registros DNS explícitos por subdomínio**; ao importar a zona na Cloudflare eles vêm apontando para o IP **antigo** e ganham do curinga. Revisar a zona importada: apagar os explícitos dos sites da ferramenta (deixar o curinga resolver) ou apontá-los para o IP novo.
- **Sites WordPress antigos de prefeituras no cPanel da Líder:** fazer **inventário** (domínio/subdomínio, ainda acessado?, já substituído na ferramenta?) e **backup completo** (arquivos + banco de cada um) antes de desligar a Hostgator. Se um WP antigo usa um subdomínio `*.liderengenharia.eng.br`, depois da virada esse endereço cai no curinga (Criador de Sites). Os que ainda precisarem ficar no ar entram depois, cada um isolado (usuário + pool próprios) — WordPress antigo desatualizado é porta de invasão.
- **Servidor exclusivo da Líder, com ferramentas futuras ⇒ padrão de independência por sistema:** usuário Linux próprio, pasta própria (`/srv/<sistema>`), pool PHP-FPM próprio (cada sistema pode até usar uma versão diferente do PHP), banco + usuário de banco próprios, bloco nginx próprio, chave de deploy do GitHub própria, `.env`, logs e cron próprios. Serviços compartilhados: nginx, PostgreSQL, MySQL. Novo sistema = repetir a receita.
- **Site da Líder dentro do Laravel do Criador de Sites (futuro):** viável — o roteamento por domínio aceita tratar o domínio raiz (`liderengenharia.eng.br`/`www`) como um site especial com painel próprio. Custo: acopla o site institucional ao ciclo de deploy da ferramenta (um deploy com erro derruba os dois). Decidir quando for evoluir o site; na migração ele segue estático.
- **E-mail:** a Líder usa **Google Workspace** (não usa e-mail no cPanel). Na Cloudflare: MX, SPF, DKIM, DMARC e TXT de verificação do Google, todos "somente DNS" (nuvem cinza).
- **Conta Cloudflare (revisto em 2026-10-09):** tudo na **conta do Jessé** — a Líder não tem e-mail corporativo acessível para abrir conta própria. O domínio `liderengenharia.eng.br` entra junto com o `sigwebmidia.com.br` e os buckets R2 do SIGWEB. A posse do domínio continua garantida pelo **registro.br** (titular = Líder); a Cloudflare só gerencia o DNS. Pessoas da Líder entram depois como **membros** com papéis específicos (confirmar no plano gratuito se dá para limitar um membro a um único domínio). Custos (R2) ficam no cartão da conta — combinar repasse. Se um dia a Líder quiser conta própria: o domínio muda com troca de nameservers; os buckets exigem cópia dos arquivos. Alternativa registrada: a Líder criar `ti@liderengenharia.eng.br` no Google Workspace para abrir contas no nome da empresa.
- **WordPress antigos (decidido):** decidir **caso a caso pelo inventário** — levantar endereço, se ainda é acessado e se já existe o substituto na ferramenta; backup completo de todos antes de desligar a Hostgator.

## DNS — zona `liderengenharia.eng.br` (levantamento 2026-10-09)

- **Nameservers hoje:** `ns1/ns2.genesisautomacao.com.br` = o DNS do **próprio WHM da VPS atual** (IP `162.240.179.66`, o mesmo do SIGWEB).
- ⚠️ **A VPS atual NÃO é exclusiva da Líder:** `/var/named` tem ~55 zonas de outros clientes da Gênesis (accountout, agmcontrol, alumni*, genesisautomacao, organosi, raidelog…). **Ela não pode ser desligada ao fim desta migração** — saem dela só as contas/zonas da Líder. Desligá-la seria outro projeto (mover os ~55 domínios).
- **Zonas da Líder nessa VPS (as três vão para a Cloudflare, dentro da zona `liderengenharia.eng.br`):** `liderengenharia.eng.br`, `webgis.liderengenharia.eng.br` e `sigweb.liderengenharia.eng.br`. Cópias em texto em `C:\laragon\www\docs\dns\` (fora do repositório).
- **Descartados (confirmado pelo usuário):** registros que apontam para `72.61.128.169` (não usados) e o CNAME `_118cda…` (lixo antigo).
- **A importação da Cloudflare perdeu registros de e-mail** que existem hoje e precisam ser adicionados à mão: `_dmarc` (TXT `v=DMARC1; p=none;`), `google._domainkey` (DKIM do Google Workspace), **`resend._domainkey` + TXT `send` (`v=spf1 include:amazonses.com ~all`) = e-mails do SIGWEB pelo Resend** — sem eles, os e-mails transacionais do SIGWEB deixam de ser entregues após a troca de nameservers.
- **SPF quebrado hoje:** a raiz tem **três** registros `v=spf1` (inválido — o padrão exige um só). Na Cloudflare fica um único: `v=spf1 include:_spf.google.com ip4:162.240.179.66 ~all` (o IP antigo sai depois da migração). TXT curinga `*` com SPF (sobra do cPanel) removido.
- `webgis` e `ferramenta` resolvem pelo curinga `*` → registros explícitos criados para virar cada sistema separadamente.
- Sobras do cPanel (CNAME `mail`, SRV `_autodiscover`/`_caldav(s)`/`_carddav(s)`, DKIM `default._domainkey`): a Líder usa Google Workspace — removidos após a migração.
- Transição: todos os A/CNAME em **"Somente DNS"** (nuvem cinza) até o servidor novo assumir.
- **Zona montada na Cloudflare (2026-10-09, conferida):** A `@`, `*`, `webgis`, `ferramenta` → `162.240.179.66` e CNAME `www` (todos somente DNS); MX `@` → `smtp.google.com` (1) e MX `send` → Amazon SES (Resend); TXT SPF único na raiz, `_dmarc`, `google._domainkey`, `resend._domainkey`, `send`. Removidos: `mail`, SRV do cPanel, SPFs duplicados, TXT `*`. Descartados também `webgis.santacecilia` (→ 45.226.189.51, não usado) e `sigweb` (será descontinuado).
- **Como a ferramenta funciona hoje:** subdomínio curinga `*` no cPanel da conta `lider` → `/home/lider/ferramenta_sites/public`; cada subdomínio explícito da conta "fura" o curinga.

### Inventário dos subdomínios legados (conta `lider`, 2026-10-09)

| Tipo | Subdomínios | Decisão |
|---|---|---|
| WordPress (20) | amerios, belo-jardim, belo-oriente, cabedelo, caete, cafezal-do-sul, cajazeiras, candeias-do-jamari, conde, conselheiro-lafaiete, gentiodoouro, ilhasolteira, iracema-do-oeste, itauna-do-sul, paranacity, paranapoema, patos-de-minas, sao-mateus, saomateus, valparaiso-de-goias | _caso a caso_ |
| Pasta sem WordPress (8) | capitolio, guariba, niopolis, novo, pacodolumiar, santa-cruz-do-capibaribe, sao-jose-do-cedro, saojosedocedro | _a conferir o conteúdo_ |

Conteúdo das 8 pastas sem WordPress: **vazias** (só `cgi-bin`) = guariba, niopolis, novo, pacodolumiar, santa-cruz-do-capibaribe, sao-jose-do-cedro → descartar; **com conteúdo** = `capitolio` (8,7 MB, sistema PHP com `admin`/`api`/`checkin.php`) e `saojosedocedro` (64 MB, site estático HTML) → decidir.

Prefeituras no Criador de Sites (produção, 16, todas ativas): aguadoce, candeiasdojamari, franciscoalves, frutal, graopara, horizonte, itaipulandia, jaboticabal, jucas, marlieria, mirassoldoeste, novaesperancadosul, riodaspedras, santoangelo, sapezal, socorro. **Nenhum slug coincide** com os subdomínios legados (o mais próximo: `candeiasdojamari` × WordPress `candeias-do-jamari`, endereços diferentes).

**Decisão (2026-10-10):** as 6 pastas vazias são descartadas; `candeias-do-jamari` sai (a cidade já tem site novo na ferramenta como `candeiasdojamari`); **ficam na Hostgator 21 sites** — 19 WordPress + `capitolio` + `saojosedocedro` — com registro A explícito (`{sub}` e `www.{sub}`) → `162.240.179.66`, somente DNS. Arquivo de importação BIND (42 registros) em `C:\laragon\www\docs\dns\importar-wordpress-legados.txt`; importar **antes** da virada (sem efeito até lá — o curinga já aponta para a Hostgator). A conta `lider` do cPanel continua ativa enquanto houver legado no ar.

Opções por subdomínio: **arquivar** (backup arquivos + banco; o endereço passa ao Criador de Sites) ou **manter no ar na VPS antiga** (que continua ligada) com registro A explícito → `162.240.179.66` criado na virada. Cruzar com os slugs já cadastrados no Criador de Sites (o WordPress explícito hoje esconde o site novo de mesma cidade).

### Troca de nameservers

1. Cloudflare → "Continuar para ativação" → anotar os 2 nameservers.
2. registro.br → `liderengenharia.eng.br`: conferir **DNSSEC** (se ativo, desligar/ajustar antes — trocar NS com DNSSEC ligado derruba o domínio).
3. "Alterar servidores DNS": `ns1/ns2.genesisautomacao.com.br` → nameservers da Cloudflare. Nada muda para o usuário (mesmos IPs).

✅ **Feito em 2026-10-09:** no registro.br os servidores eram `ns1/ns2.organosi.com.br` (também apontavam para a VPS atual; sem DNSSEC) e passaram a **`aldo.ns.cloudflare.com` / `tara.ns.cloudflare.com`**. Propagação imediata (registro.br, Google e Cloudflare já respondem). Conferido direto na Cloudflare: MX Google, SPF único, DKIM Google e Resend, `send` (SPF + MX), DMARC, raiz/`webgis`/`ferramenta` e subdomínios legados (via curinga) → `162.240.179.66`.

## Janela de virada (fim de semana, decidido em 2026-10-09)

O usuário vai fazer a virada num fim de semana, com `php artisan down` no SIGWEB e no Criador de Sites antes da cópia final.

**Antes do fim de semana (sem afetar ninguém):**
1. Servidor novo **todo instalado e testado** com uma cópia dos dados (teste pelo arquivo `hosts` do PC, sem mexer no DNS).
2. **Levar o DNS para a Cloudflare antes**, com os registros ainda apontando para os servidores atuais (nada muda para o usuário). A troca de nameservers no registro.br pode levar de horas até 48 h para propagar — não pode ficar para a janela. Conferir que a Cloudflare importou **todos** os registros, principalmente **e-mail** (MX, SPF, DKIM).
3. Se a migração das fotos (`fotos:migrar-bucket`) ainda não terminou, terminar antes.

**Na janela:**
1. `php artisan down` no SIGWEB e no Criador de Sites (servidores atuais) — congela as gravações. O app de coletas é offline-first: guarda as fichas e envia depois. ⚠️ No Criador de Sites o `down` também tira do ar os **sites das prefeituras** — janela curta, de preferência à noite.
2. Cópia final: `pg_dump` (SIGWEB) e `mysqldump` (Criador de Sites) → restaurar no novo; `rsync` incremental dos arquivos.
3. Trocar os registros A na Cloudflare para o IP novo (com proxy ligado a troca é quase imediata), incluindo o curinga `*`.
4. Testar no endereço real; `php artisan up` **só no servidor novo**. O antigo continua em manutenção (nada pode ser gravado lá depois da cópia final) e fica ligado alguns dias como garantia.

### Script da janela — `/root/janela.sh` (servidor novo; ensaiado em 2026-10-10)

Roda depois do `php artisan down` na Hostgator. Etapas: (0) confere que os dois sistemas da Hostgator respondem 503 (senão para); (1–2) dump final do SIGWEB **depois** do `down` → `dropdb --force` + recria + extensões + `pg_restore` (role do sistema) + `spatial_ref_sys`; (3) contagem de linhas das 139 tabelas antigo × novo; (4) **último registro** (max `updated_at`/`id`) de lotes, processos, anexos, usuários, chamados e auditoria; (5–6) mesmo para o MySQL do Criador de Sites (22 tabelas); (7) `rsync` incremental de todos os arquivos; (8) conferência por `rsync --dry-run` (0 pendentes); (9) caches + migrations; (10) teste das telas com `--resolve`. Termina com **✅ TUDO IGUAL** ou a lista do que falhou. Log em `/root/migracao/janela-AAAAMMDD-HHMM.log`. `ENSAIO=1` pula a etapa 0 (para ensaiar com a Hostgator no ar).

- **O ensaio pegou um problema real:** o `rsync -a` do site da Líder copiou as permissões da Hostgator (pasta `750`, arquivos `666`) e o site passou a dar **403**. Corrigido: esse `rsync` agora é `-rlt --chmod=D755,F644`. Segundo ensaio: tudo verde (bancos idênticos, últimos registros iguais até o segundo, 0 arquivos pendentes, telas 200).

### ✅ Virada executada em 2026-10-10

`down` na Hostgator → `janela.sh` real (log `janela-20261010-1052.log`): 139 + 22 tabelas idênticas, últimos registros iguais, 0 arquivos pendentes, telas 200 → registros A da raiz, `*`, `webgis` e `ferramenta` trocados para `179.199.155.36` (cinza). Conferido na Cloudflare, no Google (8.8.8.8) e no 1.1.1.1. Os 42 WordPress legados continuam na Hostgator. **A Hostgator fica em `down`** (ninguém pode gravar no banco antigo).

### Limpeza do DNS pós-virada (2026-10-10)

- **SPF:** `v=spf1 include:_spf.google.com ~all` (saiu o `ip4:162.240.179.66` da Hostgator). O `send.` (Resend/SES) ficou intacto.
- **DNSSEC:** ativado na Cloudflare + DS no registro.br (Keytag `2371`, alg. 13, digest SHA-256 `DA884FC1…500C3A2C`, conferido contra o DNSKEY publicado antes de salvar).
- **Mantidos:** nuvem cinza, DMARC `p=none` (endurecer para `quarantine` depois de algumas semanas sem problema), 42 WordPress legados apontando para a Hostgator.

## Acesso e operação do servidor novo

- `ssh lider-root` · `ssh lider-webgis` (cai em `/srv/webgis/saas-sigweb`) · `ssh lider-ferramenta` (cai em `/srv/ferramenta/ferramenta_sites`) — atalhos no `~/.ssh/config` do PC do Jessé, chave `acesso-vps-genesis`. Nas contas dos sistemas os comandos são os normais (`git pull`, `composer install --no-dev`, `php artisan …`), sem sudo; o PHP percebe arquivos alterados sozinho (`opcache.validate_timestamps=On`). Branch: SIGWEB `main`, Criador de Sites `master`.
- **Site da Líder** (`C:\laragon\www\site-lider`, sem git): `publicar-site.cmd` empacota e envia por `ssh lider-site`; a conta `site-lider` só executa `/usr/local/bin/publicar-site-lider` (forced command): extrai em `novo/`, recusa pacote sem `index.html`, troca o site inteiro e guarda o anterior (`ssh lider-site voltar`). Fora do pacote: `.htaccess` (redirecionamentos já no nginx), `*.md`, `error_log`, `_ferramentas/` (o `gerador_mapa.html`, que gera o `dados_mapa.json`, fica só local).
- **Formulário de contato do site:** `assets/enviar.php` envia pelo **Resend** (remetente `noreply@liderengenharia.eng.br`, chave = a do Criador de Sites, em `/srv/site-lider/segredos/resend.key`, fora da pasta pública). Destinatários/limite em `config/contato.json` (bloqueado no nginx). Pool PHP próprio `site-lider` (`open_basedir`, sem `exec`), só esse arquivo executa — qualquer outro `.php`, `config/` e `error_log` = 404. Log: `/var/log/php8.4-site-lider.log`. Na Hostgator o `mail()` entregava na caixa cPanel antiga (o e-mail da Líder é todo Google Workspace) — as mensagens do formulário não chegavam.

## Backup (decidido em 2026-10-10)

**Implantado (INF-3):** `/root/backup-diario.sh` (fora do git) rodado pelo `backup-diario.timer` do systemd às 03:00 (`Persistent=true`: se o servidor estava desligado, roda ao voltar). Remote rclone `backup` do root, chave R2 restrita aos 2 buckets e aos IPs do servidor (IPv4 + IPv6). Destinos: `sigweb-backup/banco/AAAA-MM-DD.dump` (`pg_dump -Fc`, validado com `pg_restore -l`) e `lider-backup/ferramenta/AAAA-MM-DD.sql.gz` (validado por `gzip -t` + "Dump completed"); se o nome do dia já existir, ganha `-HHMM` (a trava não deixa sobrescrever). Confere o tamanho no R2 depois de enviar. Buckets com **Bucket Lock 30 dias** + ciclo de vida apagando aos 31. Cada banco vira uma linha em `backup_execucoes` via `php artisan backup:registrar` → tela /admin "Backups"; falha = e-mail ao ApiSetting "Backup" (`ALERTA_EMAIL`); se nem o registro funcionar, o script manda direto pelo Resend para desenvolvimento@genesisautomacao.com.br. Log `/var/log/backup-diario.log` (logrotate mensal), cópia local dos últimos 3 dias em `/root/backups`. Rodar à mão: `systemctl start backup-diario.service`. **O servidor de testes não tem backup** (decisão do usuário).

**Restaurar:** `rclone copy backup:sigweb-backup/banco/AAAA-MM-DD.dump /root/` → mesmo `pg_restore` da janela (`--no-owner --no-privileges --role=webgislider_saas_user` + `spatial_ref_sys` em separado). MySQL: `zcat arquivo.sql.gz | mysql lider_ferramentas_site`.

- **Só os bancos** (PostgreSQL do SIGWEB + MySQL do Criador de Sites), diário, com cópia fora do servidor (bucket privado no R2). Os arquivos de `storage` (anexos, documentos, nuvem de pontos) **não** entram: vão para o bucket depois da migração testada e validada (INF-2).

## Depois da migração

- **Anexos de processos, documentos e demais arquivos no bucket PRIVADO** (`sigweb-midia`, link assinado, autorização por usuário) — corrige também o fato de hoje esses arquivos abrirem por link sem login. Registrado no backlog (`docs/pendenciasTangara.md`) e retoma a parte 1 de [releaseNuvemFerramentasAdmin.md](releaseNuvemFerramentasAdmin.md) (que previa AWS S3; o padrão agora é Cloudflare R2).
