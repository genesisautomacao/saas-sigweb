# Release — Armazenamento em nuvem, Pacote de dados e Zerar dados (painel /admin)

> **Status:** 📋 Especificado, **não implementado**. Parado por decisão do usuário em 2026-07-30 para priorizar a **PoC de Tangará/SC (17/08/2026)**.
> **Retomar depois da PoC.** Este documento é a fonte de verdade da release — o levantamento foi feito no código e no banco reais, não por suposição.
>
> ✅ **Já executado desta release:** a correção do bug de exclusão em massa ([MapaFullscreen.php:878](../app/Filament/Pages/MapaFullscreen.php#L878)) — ver a seção final.

---

## 1. Contexto

Três necessidades levantadas em 2026-07-30, todas no painel `/admin`:

1. **Zerar dados** de uma prefeitura — hoje, se uma importação GIS entra errada ou uma PoC precisa recomeçar, não existe caminho no sistema: só SQL manual no servidor.
2. **Pacote de dados** por prefeitura — entregável de portabilidade (o município leva os dados dele) que, de quebra, vira a rede de segurança do item 1.
3. **Armazenamento em nuvem (AWS S3 + CloudFront)** — hoje 15,4 GB vivem em `public/` e cada município novo soma GBs à VPS.

### Medições (ambiente com 3 prefeituras, 2026-07-30)

| Onde | Volume | Servido por |
|---|---|---|
| `public/mapas/{slug}/{z}/{x}/{y}.png` (ortofoto) | **8,95 GB** · 99.382 arquivos | nginx direto, sem PHP |
| `public/nuvem-pontos/{slug}` (Potree/LiDAR) | **6,43 GB** · 40.487 arquivos | nginx direto |
| `public/potree` (biblioteca do viewer) | 62 MB · 711 arquivos | nginx direto |
| `storage/app/public` (uploads reais) | **126 MB** · 133 arquivos | Laravel (`Storage`) |
| Banco inteiro | 58 MB | — |

Ou seja: **99% do peso são ortofoto + LiDAR**, que nem passam pelo Laravel. Os anexos são 126 MB.

### Decisões do usuário (2026-07-30)

| Tema | Decisão |
|---|---|
| Backup | **Portabilidade** (entregar à prefeitura), **não** restauração |
| Zerar | Apagar **em cascata, avisando antes** |
| Provedor | **AWS S3 com CloudFront** |
| Credenciais | Na tabela `api_settings` (padrão do Resend) — **nunca no `.env`** |
| Escopo da nuvem | Anexos, fotos da coleta/chamados, ortofoto e nuvem de pontos |
| Migração | Comando artisan para os 126 MB; `aws s3 sync` manual para os GB |
| Privacidade | **Corrigir junto**: anexos sensíveis saem do disco público |

---

## 2. Vale a pena? (avaliação honesta)

**Nuvem — sim, com ressalva.** Para os 126 MB de anexos o ganho é redundância e LGPD, a custo irrisório. Para os 15 GB de ortofoto/LiDAR o ganho é grande (VPS enxuta, município novo sem `rsync` de GB), **mas só com CloudFront**: S3 puro adiciona latência por tile (cada tile vira requisição HTTPS a outro host); com CDN fica igual ou mais rápido que o nginx local e ainda tira o tráfego da VPS. Sem CDN, não vale mover.

**Pacote de dados — sim.** Entregável contratual/LGPD e pré-requisito psicológico do "zerar": só se apaga com sossego quando dá para entregar o pacote antes.

**Zerar — sim, mas nunca como "escolher uma tabela".** O grafo tem **43 arestas `SET NULL`, e `SET NULL` não dá erro** — muda os dados em silêncio. Apagar `quadras` sozinha faz `UPDATE lotes SET quadra_id = NULL` em 11 mil linhas sem uma única mensagem; apagar `pessoas` deixa `unidade_imobiliarias.proprietario_id` apontando para gente que não existe (essa coluna **não tem FK**). A ferramenta precisa operar em **conjuntos coerentes** em ordem topológica.

### Custo estimado (AWS `sa-east-1`, ordem de grandeza)

| Item | Mensal |
|---|---|
| Armazenamento 15 GB | ~US$ 0,60 |
| Documentos/anexos (126 MB) | ~US$ 0,01 |
| Saída via CloudFront (~45 GB/mês de tiles) | ~US$ 5 |
| Requisições | centavos |

---

## 3. Fatos do levantamento que condicionam tudo

Verificados no código e no banco (não presumidos):

1. **`league/flysystem-aws-s3-v3` NÃO está instalado.** O disco `s3` existe em `config/filesystems.php:50-61` mas `Storage::disk('s3')` hoje lança exceção de driver. `composer require` é passo 0.
2. **O disco padrão do `FileUpload` é `public`** (`config/filament.php` não publicado) — por isso anexos de processos e documentos de pessoas estão **publicamente acessíveis por URL, sem login**, hoje.
3. **7 tabelas têm `tenant_id` SEM FK** para `tenants`: `cnaes`, `zoneamento_regras`, `categorias_wms`, `fontes_wms` (dados) + `roles`, `model_has_roles`, `model_has_permissions` (Spatie). Excluir uma prefeitura hoje deixa essas linhas órfãs; e um `WHERE tenant_id` esquecido nelas apaga de **todos** os municípios.
4. **6 tabelas tenant-owned NÃO têm `tenant_id`**: `arvore_logradouro`, `poste_logradouro`, `movimentacao_itens`, `os_equipe`, `os_materiais`, `setor_user` — só saem pelo pai ou por subconsulta.
5. **SoftDelete não dispara `ON DELETE CASCADE`.** 83 models usam SoftDeletes; um `->delete()` deixaria metade da árvore viva e visível.
6. **`BelongsToTenant` desliga o escopo global em console** ([BelongsToTenant.php:15-17](../app/Traits/BelongsToTenant.php#L15-L17)) — em comando artisan o `where('tenant_id', …)` é obrigatório e explícito, sempre.
7. **Não existe worker de fila em produção.** `app/Jobs/` não existe, nenhum `ShouldQueue`; `queue:listen` só no `composer run dev`. Tudo roda no request.
8. **`AdminPanelProvider` não tem `->databaseNotifications()`** — toda notificação do /admin é flash de sessão e **some na primeira navegação**. Um link de download entregue assim se perde.
9. **`ZipArchive` está habilitado** e já é usado ([ShapefileExportService.php:81](../app/Services/Exports/ShapefileExportService.php#L81)); `ogr2ogr` tem pré-flight pronto no mesmo arquivo (linha 163).
10. **Os 36 ExportServices materializam `Collection` inteira** — não servem para 100 mil imóveis. O próprio `ShapefileExportService::buildGeoJSON` monta o array completo em memória.
11. **`Tables\Actions\Action` suporta `->steps([...])`** (herda `HasWizard` de `MountableAction`) — base do modal em 2 passos.
12. **Presigned URL do S3 (SigV4) expira em no máximo 7 dias.** Não existe link de 30 dias sem CloudFront signed URL.
13. **Arquivos gravados FLAT**, sem pasta por prefeitura (nomes ULID) — qualquer operação por município precisa resolver os caminhos **pelas linhas do banco**. Há duas pastas de foto de lote: `lotes_fotos/` (atual) e `lotes/fotos-frontais/` (legado com linhas ainda apontando).
14. **`tenant-logos/` nunca pode entrar em rotina de exclusão** (brasão da prefeitura).

---

## 4. FASE 0 — Pré-requisitos

Independentes entre si; podem ir em paralelo.

| # | Item | Detalhe |
|---|---|---|
| 0.1 | ~~Bug de exclusão em massa~~ | ✅ **JÁ FEITO** — ver seção 8 |
| 0.2 | Índices em FKs | Há 147 colunas de FK sem índice líder. Criar só as que importam para varredura por pai/tenant: filhas de `pessoas` (8), `logradouros`/`quadras` (4), processos e chamados (5), estoque (4), `documentos(documentable_type, documentable_id)`, `activity_log(subject_type, subject_id)` e as ~16 tabelas com `tenant_id` sem índice. Usar `CREATE INDEX CONCURRENTLY` com `public $withinTransaction = false` |
| 0.3 | Tabela `tenant_data_operations` | `tenant_id`, `user_id`, `tipo` (zerar\|pacote), `status`, `escopo` json, `previsao` json, `resultado` json, `arquivos_afetados`, `caminho`, `expira_em`, `erro`, timestamps. Serve de status do pacote e de registro do que foi apagado |
| 0.4 | S3 no padrão da casa | `composer require league/flysystem-aws-s3-v3` + linha `ApiSetting name='AWS S3'` + injeção no `AppServiceProvider::boot()` |
| 0.5 | `->databaseNotifications()` no admin | + `databaseNotificationsPolling('30s')`. A tabela `notifications` já existe |
| 0.6 | Faxina de temporários | `sigweb:limpar-temporarios {--dias=7}` agendado — `storage/app/tmp/*`, `exports/*`, `temp_shp_*`, `temp_dxf_*` já acumulam lixo hoje |

---

## 5. PARTE 1 — Armazenamento em nuvem

### 5.1 Dois discos, sem tocar nos 23 pontos de upload

Existem 23 pontos de upload espalhados. Em vez de mexer nos 23:

- **Disco `public` repontado para o S3** quando a credencial existir. Todo `->disk('public')`, `Storage::url()` e `FileUpload` segue funcionando: fotos de lote, logos, panorâmicas, ícones, fotos de chamado.
- **Disco `privado` novo** (S3, `visibility: private`) recebe só documento pessoal: `processos_anexos` ([ProcessoFormService.php:147](../app/Services/Processo/ProcessoFormService.php#L147), requerimento assinado, PDF do requerimento, PDF anotado) e `documentos/*` (pessoa, patrimônio, falecidos, unidades). Leitura por `temporaryUrl(now()->addMinutes(15))`.

Como o **caminho relativo gravado no banco não muda**, a migração é cópia pura — nenhum registro precisa ser atualizado.

Injeção no [AppServiceProvider::boot()](../app/Providers/AppServiceProvider.php#L56-L74), no mesmo bloco do Resend, guardado por `Schema::hasTable` + try/catch. Chaves da linha `ApiSetting name='AWS S3'`: `ATIVO`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` (`sa-east-1`), `AWS_BUCKET`, `AWS_URL` (CloudFront), `TILES_BASE_URL`. Sem a linha ou com `ATIVO ≠ sim`, **nada muda**.

⚠️ **Não mexer em `filesystems.default`** — ele governa o upload temporário do Livewire e os imports GIS/tributário, que devem continuar locais (são lidos com `file_get_contents`).

### 5.2 Correções obrigatórias

1. **13 templates DomPDF** usam `public_path('storage/...')` para a logo — em S3 o arquivo não existe localmente e a logo some. Criar um helper único que devolve *data-URI* (padrão de [BicPdfService.php:24-27](../app/Services/Gis/BicPdfService.php#L24-L27)) e trocar em: `bic-template`, `bic-massa-template`, `default-report`, `lote-detalhado-report`, `memorial_descritivo`, `relatorio-numeracao`, `notificacao-irregularidade`, `ordem-servico-pdf-template`, `processo-digital-template`, `requerimento-template`, `viabilidade-template`, `viabilidade-parcelamento`, `viabilidade-unificacao`.
2. **8 usos de `asset('storage/...')`** → `Storage::disk(...)->url(...)`: `Tenant.php:139`, `Api/ChamadoController.php:93`, `ProcessoDigitalPdfService.php:69`, `HasPontoPanoramicoActions.php:98`, `MapaPublico.php:391`, `HasPatrimonioPublicoActions.php:171` e os dois `DocumentosRelationManager`.
3. **Fixar `'disk' => 'local'`** em `config/livewire.php` (hoje `null`) — senão todo upload temporário/abortado vira PUT+DELETE no bucket.
4. **Consolidar o diretório de fotos de chamado**: painel grava em `chamados/fotos` ([ChamadoResource.php:65](../app/Filament/Resources/ChamadoResource.php#L65)), API do app em `chamados_fotos` ([ChamadoController.php:253](../app/Http/Controllers/Api/ChamadoController.php#L253)).
5. **Tratar a pasta legada** `lotes/fotos-frontais/` na migração (ainda há linhas apontando para lá).

### 5.3 Migração dos arquivos existentes

`sigweb:migrar-arquivos-nuvem {--tenant=} {--dry-run}` — resolve os caminhos **pelas linhas do banco** (`documentos.path`, `processo_anexos.caminho_arquivo`, `lotes.foto_frontal|foto_lateral_esq|foto_lateral_dir`, `chamados.fotos` JSON, `pontos_panoramicos.image_path`, fotos de OS/solicitação, `tenants.data['logo']`) e copia local→bucket mantendo o mesmo caminho relativo. Idempotente; relata faltantes.

### 5.4 Ortofoto e Potree (os 15 GB)

Nada em PHP toca esses arquivos — são 2 linhas de JS e um `file_exists`:

- [mapa-engine.js:101](../public/js/gis/mapa-engine.js#L101) e [mapa-cidadao-engine.js:36](../public/js/gis/mapa-cidadao-engine.js#L36): trocar a URL fixa por `${config.tilesBaseUrl}/mapas/...`, injetada pelo Blade a partir do `TILES_BASE_URL`.
- [MapaFullscreen.php:1934](../app/Filament/Pages/MapaFullscreen.php#L1934): o `file_exists(public_path(...))` vira checagem no bucket quando houver base remota.
- Upload uma vez: `aws s3 sync public/mapas s3://bucket/mapas`. **Exige CORS** no bucket (o Potree dispara milhares de requisições de octree) e `Cache-Control: max-age=31536000, immutable` nos tiles.

### 5.5 Privacidade

Hoje anexo de processo e documento de pessoa moram no disco público: **qualquer um com a URL baixa, sem login** — RG, CPF, plantas, requerimentos assinados. Pontos a revalidar ao mover para o disco privado: pré-visualização do `FileUpload` (o Filament gera URL temporária quando o disco suporta), lista de documentos do processo ([ProcessoFormService.php:621](../app/Services/Processo/ProcessoFormService.php#L621)), editor de anotação de PDF ([ProcessoAnexoController.php:24](../app/Http/Controllers/ProcessoAnexoController.php#L24)), blade `processo-anexos` e os RelationManagers de Documentos.

---

## 6. PARTE 2 — Pacote de dados da prefeitura

> ⚠️ **Nunca chamar de "backup"** — nem na tela, nem no e-mail, nem no nome do arquivo. Se o cliente entender que é backup, vira compromisso implícito de restauração que o sistema não cumpre.

### 6.1 Estrutura

```
pacote-{slug}-{AAAAMMDD-HHmm}.zip
├── LEIA-ME.txt          "cópia de LEITURA para arquivo e portabilidade. NÃO é backup
│                         e NÃO pode ser reinstalado no sistema."
├── manifesto.json       versão, prefeitura, data, partes, contagens, SHA-256 por arquivo
├── planilhas/           01-cadastro-imobiliario.xlsx (abas Lotes/Unidades/Edificações/…),
│                        02-base-cartografica, 03-pessoas, 04-processos, 05..13 por módulo
├── camadas/geojson/     lotes.geojson, quadras.geojson… (EPSG:4326)
├── camadas/shapefile/   lotes.zip, quadras.zip… (.shp/.shx/.dbf/.prj/.cpg)
├── anexos/INDICE.csv    entidade;id;numero;nome_original;caminho_no_pacote;bytes;sha256
├── anexos/…             arquivos renomeados por chave de negócio (não pelo ULID opaco)
├── processos-pdf/       {codigo_processo}.pdf
└── banco-bruto/         CSV por tabela (COPY), geo em WKT + esquema.sql
```

Planilha até 50k linhas/aba em XLSX multi-aba (padrão de [LoteExportService.php:41-80](../app/Services/Exports/LoteExportService.php#L41-L80)); acima disso, CSV UTF-8 **com BOM** (senão o Excel pt-BR estraga os acentos).

### 6.2 Como executar sem estourar memória

Os ExportServices atuais **não servem** — materializam `Collection`. O pacote é camada nova:

- **Planilhas**: `lazyById(2000)` (keyset, não `chunk` com OFFSET) + `SimpleExcelWriter::addRow` unitário. O openspout é streaming de verdade: memória constante. **Nunca `SELECT *`** — `geo` é binário e viria como lixo; whitelist de colunas por entidade.
- **Camadas**: deixar o **`ogr2ogr` ler direto do PostgreSQL** (`PG:"host=… dbname=…" -sql "SELECT … WHERE tenant_id = ?"`) — **zero bytes de PHP**, e gera GeoJSON e Shapefile com o mesmo comando. Reusar o pré-flight `ogr2ogrDisponivel()`. Fallback sem GDAL: escrita manual com `fopen`/`fwrite` + `DB::cursor()` e `ST_AsGeoJSON` (só GeoJSON, sem SHP); registrar no manifesto qual caminho foi usado.
- **Anexos**: `ZipArchive::addFile` (só referência; conteúdo lido no `close()`) + `setCompressionIndex($i, ZipArchive::CM_STORE)` — comprimir JPG/PDF gasta CPU para economizar ~2%.
- **PDFs**: extrair um `montar(): PDF` de `ProcessoDigitalPdfService::gerar()` e gravar um por vez com `unset()` (DomPDF gasta 30-80 MB por documento).

### 6.3 O gargalo real é o request, não a memória

`Artisan::call()` roda no mesmo processo do request: `set_time_limit(600)` não vence o `proxy_read_timeout` do nginx (60s por padrão) nem o timeout do Livewire. Dois modos:

| Modo | Quando | Como |
|---|---|---|
| **Rápido** | Só planilhas + camadas, ou pré-flight < 200 MB | `set_time_limit(600)` + `Artisan::call()`, padrão da casa |
| **Completo** | Com anexos e/ou PDFs | `Process::start()` — dispara e volta em < 1s; status em `tenant_data_operations` com `->poll('15s')` |

**Recomendação honesta:** `Process::start()` é remendo. Subir `php artisan queue:work` como serviço (supervisor/systemd) é ~15 min de infra e resolve isso e qualquer coisa longa que venha depois. Projetar já com `ShouldQueue` e manter o `Process::start()` como fallback quando `queue.default === 'sync'`.

### 6.4 Pacote grande e link temporário

- **Pré-flight**: estimar bytes e exigir `disk_free_space` ≥ 2,5× o estimado; abortar com número na mensagem em vez de morrer no meio.
- **Volumes independentes de ≤1 GB** (`-01.zip`, `-02.zip`), **não** multi-volume ZIP64 (descompactador de Windows não lida bem com `.z01`). O volume 1 leva sempre LEIA-ME, manifesto, planilhas e camadas — se o cliente só baixar o primeiro, já tem o essencial. Cada volume sobe ao bucket assim que fecha e é apagado local: pico de disco = 1 GB.
- **Link**: `Storage::disk('s3')->writeStream(...)` (não `put` — multipart automático) + `temporaryUrl(now()->addDays(7))`. **7 dias é o teto do SigV4**; oferecer botão "Gerar novo link". Lifecycle rule expirando `pacotes/` em 90 dias.
- **Sem S3**: rota assinada (`middleware(['signed','auth'])`) sobre `storage/app/private/pacotes/`. Entregar **este caminho primeiro** — funciona sem conta AWS.
- **Notificar por notificação persistente E e-mail** (Resend já configurado). Link de 7 dias em toast que morre na navegação não é entrega.

---

## 7. PARTE 3 — Zerar dados

### 7.1 Conjuntos, nunca tabela solta

Um conjunto encapsula três coisas que o operador não tem como saber: a **ordem** interna, os **`UPDATE … SET NULL` explícitos** que precisam vir antes, e as **dependências** com outros conjuntos. Registry declarativo em `app/Support/Zerar/MapaExclusao.php` (const, sem lógica), com `label`, `grupo`, `requer`, `nulls`, `logs`, `morphs`, `tabelas`, `arquivos`.

**21 conjuntos**, cobrindo as 99 tabelas de dados + as 6 sem `tenant_id`:

| Grupo | Conjuntos |
|---|---|
| Cartografia e cadastro | C1 Coleta em campo · C2 Cadastro imobiliário · C3 Base cartográfica *(requer C2, C4, C16)* · C4 PGV · C16 Viabilidade/urbanismo · C17 Imagens 360 |
| Atendimento ao cidadão | C5 Processos (movimento) · C5b Catálogo BPMN *(requer C5)* · C6 Chamados (movimento) · C6b Catálogo de chamados *(requer C6)* |
| Pessoas e social | C7 Cadastro social · C8 Pessoas *(requer C7, C9, C11, C12)* |
| Almoxarifado e serviços | C9 Manutenção/OS · C10 Estoque |
| Módulos temáticos | C11 Cemitérios · C12 Rural · C13 Iluminação · C14 Arborização · C15 Patrimônios |
| Configuração e avulsos | C18 Mensageria · C19 Camadas WMS · C20 Customizações do cadastro · C21 Documentos restantes |

Preset **"Deixar a prefeitura em branco"** = C1→C21 na ordem canônica (topológica por construção).

Detalhes que a ordem precisa respeitar:
- Em C3, `quadras` sai **antes** de `bairros`/`loteamentos`/`perimetros` — senão o DELETE do pai dispara `UPDATE quadras SET bairro_id = NULL` linha a linha.
- Em C8, `UPDATE pessoas SET conjuge_id = NULL, mae_id = NULL, pai_id = NULL` em bloco **antes** do DELETE (auto-referência `SET NULL` sem índice = seq scan por linha), e `UPDATE unidade_imobiliarias SET proprietario_id = NULL` (o banco não protege — não há FK).
- Em C10, `estoque_movimentacoes` antes de `locais_estoque` (fecha o `NO ACTION` de `origem_id`/`destino_id`).
- Separar **movimento** de **catálogo** em processos e chamados resolve o `RESTRICT` de `processos_digitais.bpmn_fluxo_id` e atende o caso mais frequente ("limpa os chamados de teste, mantém o fluxo configurado").
- Tabelas `sem_fk` (`cnaes`, `zoneamento_regras`, `categorias_wms`, `fontes_wms`) marcadas no registry; o service **recusa** montar SQL de qualquer tabela sem o binding de tenant — guarda em código, não em revisão de PR.
- **Nunca entram:** `users`, `roles`/`permissions`, `tenants`, `api_settings`, `sistemas_tributarios`, `tenant_user`.

### 7.2 Previsão de impacto (wizard de 2 passos)

- **Passo 1 — Escolher**: `CheckboxList` agrupado, sem contagem nenhuma (instantâneo). Marcar um conjunto marca e trava os `requer` (`->live()` + `->disableOptionWhen`), impedindo seleção inconsistente.
- **Passo 2 — Conferir**: a contagem roda só nas tabelas escolhidas, num **`UNION ALL` único** (~100 ms com os índices da Fase 0.2), não em 60 round-trips.

A tela precisa mostrar, em vermelho, os **efeitos colaterais fora dos conjuntos escolhidos** — é o que transforma "apagar quadras deixa 11 mil lotes órfãos" de armadilha em informação:

> ⚠️ `pgv_amostras` — 340 amostras perdem o vínculo com o lote
> ⚠️ `unidade_imobiliarias` — 4.707 unidades ficam sem proprietário
> ⚠️ `coleta_atribuicoes` — 12 atribuições ficam com quadras inexistentes na lista (JSON, sem FK)

Arquivos: `COUNT(*)` exato + amostragem de 200 arquivos para a média → "≈ 3.412 arquivos, ~1,2 GB", deixando claro que o tamanho é estimativa. A previsão vai para cache com hash da seleção; a execução **recusa** rodar se o hash não bater (fecha a janela entre ver e executar).

### 7.3 Mecânica

**SQL puro, `DELETE … WHERE tenant_id = ?`, filho antes do pai, uma transação por conjunto.**

Por que não Eloquent em chunks: o hook `deleting` de [EstoqueMovimentacao.php:35-60](../app/Models/EstoqueMovimentacao.php#L35-L60) estorna saldo numa tabela que será apagada duas linhas depois; `LogsActivity` (6 models) transformaria 100 mil lotes em 100 mil linhas novas de `activity_log` justamente enquanto se esvazia o banco. **Nenhum hook deve rodar num zerar** — SQL puro dá isso de graça.

Por que filho explícito mesmo com CASCADE: `ON DELETE CASCADE` é trigger `AFTER ROW` — uma varredura no filho por linha do pai. Sem índice (147 FKs sem índice), vira seq scan por linha. `DELETE FROM contatos WHERE tenant_id = ?` primeiro é **um** statement; depois o cascade não encontra nada.

```php
DB::transaction(function () use ($tenant, $def) {
    DB::statement("SET LOCAL statement_timeout = '600s'");
    DB::statement("SET LOCAL lock_timeout = '10s'");
    // 1) activity_log — set-based, sem precisar de tenant_id na tabela:
    //    DELETE FROM activity_log a USING lotes l
    //    WHERE a.subject_type = ? AND a.subject_id = l.id AND l.tenant_id = ?
    // 2) UPDATEs de desvínculo (o SET NULL que hoje é silencioso, agora contado)
    // 3) DELETEs, filho → pai
});
```

**Transação por conjunto, não uma para tudo**: falha isolada, relatório do tipo "C1..C12 ok, C13 falhou, C14..C21 não executados", e o operador roda de novo só o que falta. Depois do commit, `ANALYZE` nas tabelas afetadas — **nunca `VACUUM FULL`** (lock exclusivo derruba o site).

**Arquivos em duas fases, nunca dentro da transação:** antes do DELETE, `SELECT` dos caminhos por cursor → manifesto em `storage/app/operacoes/{slug}/{ts}-arquivos.txt`; depois do commit, valida cada caminho contra whitelist de prefixos (rejeitando `..`, path absoluto e `tenant-logos/`) e apaga em lotes de 500. Rollback não desfaz disco.

**Default recomendado: `removerArquivos = false`.** Apagar as linhas já é irreversível; apagar os arquivos é irreversível *e* inútil (sem as linhas ninguém os acha). O manifesto fica gravado, então uma faxina posterior é um comando — e um botão "Limpar arquivos órfãos" separado resolveria também os órfãos que já existem hoje.

Comando: `sigweb:zerar-dados --tenant= --conjuntos= --operacao= [--remover-arquivos] [--reiniciar-numeracao] [--dry-run]`. **Sem `--tenant` ele falha** — nunca assume "todos" (ao contrário de `gis:recalcular-metadata`, onde esse default é inofensivo e aqui seria catastrófico).

### 7.4 Travas

- **Master apenas na v1.** Registrar `admin_zerar_dados` em `User::CAPACIDADES_ADMIN` mas usar `->visible(fn () => static::ehMaster() && static::pode('admin_zerar_dados'))`; no dia em que houver equipe de suporte, tira-se o `ehMaster()`.
- **Quatro travas no passo 2**: digitar o nome exato da prefeitura (`Rule::in`), **senha atual** (`->currentPassword()` — a trava mais barata contra clique acidental e sessão aberta), checkbox "entendi que é definitivo e que o sistema NÃO tem backup automático", e confirmação de que o pacote foi baixado.
- **Dependência do pacote — a mitigação mais valiosa**: a ação fica `disabled` se não existir `TenantDataOperation` tipo `pacote`, status `concluido`, nos últimos 7 dias. É por isso que a Ferramenta 2 vem antes da 1.
- **Lock de concorrência** (`Cache::lock("zerar-tenant-{$id}", 900)`) — dois operadores simultâneos dão deadlock.
- **Aviso (não trava) se `is_active`**: banner "esta prefeitura está ATIVA — pode haver gente usando agora". Não travar porque o caso legítimo mais comum é limpar dados de teste com o cadastro já ativo.
- **Registro em três lugares**: `tenant_data_operations` (operacional, alimenta a trava dos 7 dias), `activity()->performedOn($tenant)` (o `subject_id` **é** o tenant — resolve o "activity_log não tem tenant_id" sem migration) e um JSON em `storage/app/operacoes/` (o único que sobrevive a um zerar acidental do próprio registro).

### 7.5 `sequential_id` e app mobile — tratar, não só avisar

**Numeração**: o reinício em 1 é esperado quando se zera tudo; o problema é o caso parcial (o "Lote 1" novo colide com o "Lote 1" dos PDFs já emitidos). Solução barata: tabela `tenant_sequence_floors (tenant_id, tabela, piso)` gravada no zerar com o `MAX(sequential_id)` anterior, e `HasTenantSequentialId` passando a usar `max($maxId, $piso) + 1`. Toggle **"Reiniciar a numeração em 1"** no formulário, **desligado por padrão** — vira escolha consciente em vez de efeito colateral.

**App de coleta** — aqui o diagnóstico inicial estava errado e a correção é outra:

- **Lote fantasma não é problema**: `salvarLotesDoServidorEmBatch` (`app-coletas/src/services/db.js`) já remove localmente todo lote ausente do pull, desde que `sync_status != 'updated'`. O purge-por-ausência cobre exclusão em massa.
- **O problema real é perda silenciosa de coleta**: [LoteSyncController.php](../app/Http/Controllers/Api/LoteSyncController.php) no `push` faz `if (! $lote) { continue; }` e responde 200 — o app marca como `synced` e **a ficha de campo evapora sem uma única mensagem**. Corrigir junto: servidor acumula os `code` não encontrados e devolve `nao_encontrados`; o app marca `sync_status = 'orfao'` e avisa "N fichas não puderam ser enviadas (o imóvel não existe mais no servidor)".
- **Complemento de 15 minutos**: gravar `tenants.data['coleta_reset_at']` no zerar e devolvê-lo no pull; o app compara e avisa o fiscal **antes** de sincronizar. Muito mais simples que tombstones.

---

## 8. Já executado desta release

### ✅ Bug de exclusão em massa de lotes — corrigido em 2026-07-30

[MapaFullscreen.php:878](../app/Filament/Pages/MapaFullscreen.php#L878), na `deletarArtefatoAction`:

```php
$lote->query()->delete();   // ERRADO — Model::query() é ESTÁTICO: builder sem WHERE
$lote->delete();            // CERTO
```

`$lote->query()` resolve para o método estático `Model::query()`, que devolve um builder novo **sem nenhum filtro de id**. Excluir um único lote pelo mapa soft-deletava **todos os lotes da prefeitura ativa** (só o escopo global de tenant impedia que fosse pior). Como `Lote` usa SoftDeletes, o estrago era recuperável pelo `TrashedFilter` + `RestoreBulkAction` — mas silencioso.

Testado em transação revertida: antes 4.434 lotes ativos → depois 4.433 (exatamente 1 apagado, o alvo na lixeira, demais prefeituras intactas). O SQL de `Lote::query()` foi impresso como prova: `select * from "lotes" where "deleted_at" is null`.

---

## 9. Ordem de execução (quando a release for retomada)

**Bloco A — Fase 0** (paralelizável): índices · `tenant_data_operations` · S3 no `AppServiceProvider` · `databaseNotifications` · faxina de temporários.

**Bloco B — Pacote de dados** (deliberadamente antes do zerar): whitelist de colunas → `PlanilhasWriter` + `CamadasWriter` (entregável testável isolado) → `PacoteZip` com volumes + `AnexosWriter` + `ProcessosPdfWriter` → entrega local **primeiro**, S3 depois → `Process::start()` + polling → Action no `TenantResource`.

**Bloco C — Zerar dados**: `MapaExclusao` (80% do trabalho intelectual, 0% do código) → **teste de ordem topológica antes do service** → `PrevisaoExclusaoService` → `ArquivosTenantService` → `ZerarDadosService` + comando com `--dry-run` → piso de numeração → Action com wizard e travas.

**Bloco D — Mobile**: `nao_encontrados` no push + `coleta_reset_at` no pull; lado do app.

**Bloco E — Nuvem dos 15 GB**: `aws s3 sync` + CloudFront + CORS + `Cache-Control`, depois que o resto estabilizar.

> O teste de ordem topológica (Bloco C) é o único ponto de TDD que eu defenderia: ele lê `pg_constraint` do banco real e valida que, para toda FK `RESTRICT`/`NO ACTION`, a filha aparece antes da mãe na ordem canônica, e que toda tabela com `tenant_id` está em exatamente um conjunto (ou explicitamente em `NUNCA_APAGAR`). Sem ele, o registry apodrece na primeira migration nova.

---

## 10. Riscos residuais e o que NÃO fazer

**Riscos que sobram:**

1. **Não existe backup.** Nada aqui cria um. `banco-bruto/*.csv` chega perto, mas restaurar 105 tabelas com FKs a partir de CSV é trabalho de horas com risco de erro. **Este é o risco dominante** — a mitigação real é operacional: `pg_dump` agendado antes de qualquer zerar. Conversar com quem opera o servidor **antes** de entregar a Ferramenta 1.
2. **`Process::start()` no Windows** é menos previsível que no Linux — testar no ambiente real; plano B é o `queue:work`.
3. **A ordem canônica apodrece** a cada migration nova — mitigado só se o teste rodar no CI.
4. **`ogr2ogr` pode não existir em produção**; o fallback PHP cobre GeoJSON mas **não** Shapefile — e SHP é o que a cartografia municipal pede. Verificar o servidor antes de prometer.
5. **Volume de anexos**: 126 MB para 3 tenants de desenvolvimento; um município real com 100 mil imóveis × 3 fotos são dezenas a centenas de GB. Daí os volumes e o fallback de índice com URLs.
6. **`sequential_id` já pode estar inconsistente hoje**: o importador GIS grava o valor do JSON via `forceFill`, contornando o trait — se o JSON trouxer números fora de ordem, o `MAX+1` já está errado antes de qualquer zerar.

**O que NÃO fazer:**

- ❌ Seleção tabela-a-tabela no zerar (43 arestas `SET NULL` silenciosas).
- ❌ Apagar via Eloquent `forceDelete` em chunks (estorno de estoque + `LogsActivity` + lentidão).
- ❌ `TRUNCATE` (não filtra por tenant; `CASCADE` atravessa a árvore inteira).
- ❌ Apagar o registro em `tenants` "e recriar" — levaria `tenant_user` (desvincula os usuários), os `roles` do município, e o `id` mudaria, quebrando tudo que grava `tenant_id` dentro de JSON e os mocks em `storage/app/mocks/{slug}.json`.
- ❌ Apagar arquivos dentro da transação (rollback não desfaz disco).
- ❌ `--tenant` opcional significando "todos" no `sigweb:zerar-dados`.
- ❌ Montar GeoJSON em array PHP (é o que o `ShapefileExportService` faz hoje e é a primeira coisa a estourar com 100 mil feições).
- ❌ `->deleteFileAfterSend()` no pacote (ele precisa sobreviver dias).
- ❌ Gerar pacote com anexos dentro do request.
- ❌ Dar `admin_zerar_dados` a Operador na v1.
- ❌ Chamar o pacote de "backup" em qualquer lugar.

---

## 11. Verificação (quando executada)

- **S3**: sem a linha `ApiSetting`, nada muda (testar). Com a linha: subir anexo pelo processo digital e conferir gravação no bucket + exibição no painel; gerar BIC e requerimento em PDF e conferir que **a logo aparece**; conferir que o upload temporário do Livewire continua local.
- **Migração**: `--dry-run` lista os 133 arquivos; depois, comparar contagem local × bucket e abrir um documento antigo pelo painel.
- **Pacote**: gerar para Santa Cecília (4.434 lotes) e Bom Princípio (6.610); abrir o ZIP; conferir planilhas, GeoJSON válido no QGIS, anexos presentes, link temporário funcionando e o LEIA-ME dizendo que não é backup.
- **Zerar**: em prefeitura de teste criada na hora — `--dry-run` primeiro; zerar "Cadastro imobiliário" e conferir que não sobrou unidade/edificação órfã, que os `SET NULL` previstos aconteceram e que as demais prefeituras estão intactas (`SELECT count(*) … WHERE tenant_id <> alvo` antes e depois).
