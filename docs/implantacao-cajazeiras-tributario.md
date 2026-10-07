# Cajazeiras/PB — vínculo das unidades imobiliárias com o tributário (simulação)

Roteiro executado na VPS em outubro/2026. Tenant: `prefeitura-municipal-de-cajazeiras`.

## Contexto dos dados

| Fonte | Conteúdo |
|---|---|
| `unidades_imobiliarias.json` (já importado pelo "Importar Mapa (GIS)") | 21.818 unidades, uma por código cadastral de lote (`setor.quadra.lote`, ex.: `02.190.0020`), `codigo_imovel_tributario` = `inscricao_imobiliaria` = esse código, `lote_id` resolvido, sem endereço |
| `cadastro_imobiliario.json` (export do tributário) | 39.705 imóveis em **GeoJSON** (campos em `properties`), `codigo_imovel_tributario` = código do lote (**não único**: sublotes/apartamentos repetem), `CODIGO_IMOVEL`/`INSCRICAO_IMOVEL` únicos, `N_PESSOA`, `CPF` (**muitas vezes é o código do imóvel com zeros**), `CNPJ`, campos `*_RES` = residência do proprietário (vazios) |

Cruzamento pelo código do lote: 20.049 unidades casam 1:1, 392 caem em lotes com mais de um imóvel no tributário (vale o primeiro do arquivo, com `DESCONHECIDO` ordenado por último), ~1.370 unidades não têm imóvel no tributário.

## Arquivo de simulação pronto

`D:\Sistemas e Plataformas\WebGis\Implantações\Cajazeiras\prefeitura-municipal-de-cajazeiras.json` (26,6 MB) — gerado a partir do GeoJSON: lista na raiz, `"NULL"` → `null`, espaços aparados, dentro de cada código os registros `DESCONHECIDO` vão para o fim.

> O /admin também aceita o GeoJSON cru desde 2026-10-07, mas 44 MB passa do `client_max_body_size` do nginx. Para bases grandes o caminho é copiar o arquivo direto.

## Passo a passo na VPS

1. **Deploy do código** (`git pull` + `php artisan optimize:clear`). Sem migration.

2. **Catálogo** — `/admin` → Configurações Globais → Sistemas Tributários → novo:
   - Nome: `Cajazeiras (tributário)` (renomeie quando souber o fornecedor)
   - Chave de ligação: **Código do imóvel (tributário)**
   - De/para (campo no sistema → campo no SIGWEB):

     | Origem | SIGWEB |
     |---|---|
     | `N_PESSOA` | `proprietario_name` |
     | `CPF` | `proprietario_cpf` |
     | `CNPJ` | `proprietario_cnpj` |

     `codigo_imovel_tributario` já vem com o nome canônico — não precisa de linha.
     **Não mapear** `INSCRICAO_IMOVEL` → `inscricao_imobiliaria` (sobrescreveria a inscrição das unidades, que é o código do lote) nem `NOME_LOGRADOURO_RES`/`NUMERO_RES` → `logradouro`/`numero_logradouro` (é o endereço de residência do dono, não do imóvel).
   - Campos extras (aparecem no BIC): `CODIGO_IMOVEL` → "Código do imóvel (tributário)", `INSCRICAO_IMOVEL` → "Inscrição no tributário", `INSCRICAO_ANTERIOR` → "Inscrição anterior", `N_OCUPANTE` → "Ocupante", `CPF_OCUPANTE` → "CPF do ocupante", `CNPJ_OCUPANTE` → "CNPJ do ocupante".

3. **Prefeitura** — `/admin` → Prefeituras → Cajazeiras → seção Integração Tributária: sistema = a entrada acima, modo = **Simulação**. Salvar.

4. **Copiar o mock** (no lugar do upload):
   ```bash
   scp "prefeitura-municipal-de-cajazeiras.json" usuario@vps:/var/www/sigweb/storage/app/mocks/
   sudo chown www-data:www-data /var/www/sigweb/storage/app/mocks/prefeitura-municipal-de-cajazeiras.json
   ```
   Conferir no /admin → Prefeituras → ação "Simulação Tributária": "Arquivo atual: 39705 imóvel(is)". **Não** ligar "Importar todos os imóveis agora" (casa por inscrição; aqui não serve).

5. **Sincronizar pela CLI** (o botão do painel pode estourar o tempo do navegador):
   ```bash
   cd /var/www/sigweb
   php artisan tinker --execute="echo App\Models\Tenant::where('slug','prefeitura-municipal-de-cajazeiras')->value('id');"
   php -d memory_limit=2048M artisan sigweb:sincronizar-imoveis <id>
   ```
   Resumo esperado: ~20 mil "sincronizados", ~1,4 mil "sem dados na fonte", algumas centenas "sem proprietário (nome vazio ou curinga)", milhares de "pessoa(s) criada(s)". Rodar de novo não cria pessoa nenhuma (idempotente).

6. **Conferir** (psql):
   ```sql
   SELECT count(*) FILTER (WHERE proprietario_id IS NOT NULL) AS com_dono,
          count(*) FILTER (WHERE proprietario_id IS NULL)     AS sem_dono,
          count(*) FILTER (WHERE dados_tributarios IS NULL)   AS sem_tributario
   FROM unidade_imobiliarias WHERE tenant_id = <id> AND deleted_at IS NULL;

   SELECT count(*), count(cpf) AS com_cpf, count(cnpj) AS com_cnpj FROM pessoas WHERE tenant_id = <id> AND deleted_at IS NULL;

   -- unidades que não acharam imóvel no tributário (para a Líder conferir os códigos)
   SELECT codigo_imovel_tributario FROM unidade_imobiliarias
   WHERE tenant_id = <id> AND deleted_at IS NULL AND dados_tributarios IS NULL ORDER BY 1;
   ```

## Limitações conhecidas

- Lote com vários imóveis no tributário recebe o **primeiro** (não `DESCONHECIDO`). Para ter todos os donos seria preciso uma unidade por imóvel do tributário (`CODIGO_IMOVEL` como código) — decisão futura.
- Este JSON **não tem endereço do imóvel**: logradouro/número ficam como estavam.
- CPF que não passa nos dígitos verificadores é ignorado (a Pessoa nasce só com o nome); `DESCONHECIDO` não vira Pessoa.
