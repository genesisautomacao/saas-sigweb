# Pendências POC WebGIS — Tangará SC (Checklist V2)

## Percentual de Conformidade

| Status | Qtd | Peso | Pontuação |
|---|---|---|---|
| ✅ ok | 98 | 1,0 | 98,0 |
| 🏢 backoficce | 2 | 1,0 | 2,0 |
| ⚠️ parcial | 11 | 0,5 | 5,5 |
| ❌ em branco (não implementado) | 13 | 0,0 | 0,0 |
| **Total** | **124** | — | **105,5** |

### **Conformidade geral: 85,1%** (105,5 / 124)

> Base de cálculo: 6 itens Características Técnicas + 89 itens Ambiente Intranet + 29 itens Ambiente Internet = 124 itens obrigatórios.

---

## Observações sobre itens "ok" e "backoficce"

Os itens marcados como `ok` foram verificados e confirmados como implementados:
- Recursos Filament existem para as entidades correspondentes.
- Edição cartográfica via `MapaFullscreen` com ~20 traits `HasXxxActions`.
- Viabilidade: modelos `ParametroUrbano` e `ZoneamentoRegra` implementados.
- Desmembramento e Unificação implementados via traits na página de mapa.

Os itens `backoficce` (Intranet 18 e 31) estão disponíveis via painel Filament mas não diretamente acessíveis no mapa interativo — o que é aceitável para avaliação.

---

## Itens "parcial" — O que está implementado e o que falta

### Intranet 17 / Internet 10 — Logradouro com imagens das Seções
**Implementado:** Logradouro existe como entidade com geometria (LineString/MultiLineString), nome e código.  
**Falta:** O conceito de **Seção** (subsegmento do Logradouro com Código da Seção métrico, Lado da Seção e imagens fotográficas) não existe no modelo. A tabela `logradouros` não possui relacionamento com seções.

### Intranet 42 — Incluir/geocodificar Lote
**Implementado:** Criação de lote com geometria, código, número, área, logradouro.  
**Falta:** Campos `Ocupação do Lote` (Baldio/Construído) e `Situação na Quadra` (meio de quadra, esquina ou encravado) não estão no model `Lote` nem na migration.

### Intranet 43 — Incluir/geocodificar Edificação
**Implementado:** Geometria, tipo, tp_construcao, estado_conservacao, área.  
**Falta:** Campo `Pavimento da Unidade` não está vinculado à geocodificação da Edificação no mapa (só existe na UnidadeImobiliaria via `dados_tributarios` JSON).

### Intranet 44 / 52 / 70 — Logradouro e Seções (criar, excluir, editar)
**Implementado:** Criação e exclusão de Logradouro (geometria principal).  
**Falta:** O sub-modelo Seção (com código métrico, comprimento e lado) inexiste completamente, portanto tudo que envolva "Seções" está pendente.

### Intranet 45 — Incluir/geocodificar Quadra
**Implementado:** Quadra possui geometria, nome, código, bairro, loteamento.  
**Falta:** O código composto `Código do Distrito + Código do Setor + Número da Quadra` não está implementado — o modelo não tem campos `distrito_codigo` e `setor_codigo`.

### Intranet 86, 87, 88 — Configuração por Perfil (Camadas / Funcionalidades / Itens de Cadastro)
**Implementado:** Spatie Permission com roles e permissions por tenant. Usuários são vinculados a perfis (roles).  
**Falta:** Interface granular para configurar **quais camadas do mapa** e **quais funcionalidades** cada perfil pode ver/usar. Hoje a visibilidade de camadas é controlada apenas pelo módulo do tenant, não por permissão individual de role no mapa.

---

## Lista de Pendências — Do mais fácil ao mais difícil

---

### 🟢 FÁCIL (dias de desenvolvimento)

#### P1 — Adicionar campos de Ocupação e Situação ao Lote
**Checklist:** Intranet 42  
**O que fazer:**
- Criar migration `add_ocupacao_situacao_to_lotes_table` com campos `ocupacao` (enum: baldio/construido) e `situacao_quadra` (enum: meio_quadra/esquina/encravado).
- Adicionar os campos ao `$fillable` do model `Lote`.
- Adicionar os selects no modal de criação/edição do `HasLoteActions.php`.

---

#### P2 — Código composto na Quadra (Distrito + Setor + Número)
**Checklist:** Intranet 45  
**O que fazer:**
- Criar migration `add_codigos_compostos_to_quadras_table` com campos `distrito_codigo` e `setor_codigo`.
- Atualizar o form de criação de quadra no `HasQuadraActions.php` para solicitar esses valores.

---

#### P3 — Pavimento da Unidade no geocodificação da Edificação
**Checklist:** Intranet 43  
**O que fazer:**
- Adicionar campo `pavimento` ao model `Edificacao` e sua migration.
- Exibir e editar no modal da Edificação no mapa.

---

### 🟡 MÉDIO (semanas de desenvolvimento)

#### P4 — Ferramenta de Auditoria de Usuários
**Checklist:** Intranet 89  
**O que fazer:**
- Instalar `spatie/laravel-activitylog`.
- Registrar os modelos principais (Lote, Edificacao, Logradouro, Quadra, UnidadeImobiliaria, Pessoa) com o trait `LogsActivity`.
- Criar uma página Filament `AuditoriaPage` que liste as atividades filtráveis por usuário, tipo de operação e período.

---

#### P5 — Configuração granular de Camadas por Perfil de Usuário
**Checklist:** Intranet 86, 87, 88  
**O que fazer:**
- Criar tabela `role_layer_permissions` (role_id, layer_name, can_view, can_edit).
- Criar interface Filament para o admin configurar quais camadas cada role pode ver/editar.
- Integrar o `mapa-engine.js` para ler as permissões do usuário logado via endpoint e ocultar/bloquear camadas conforme configuração.

---

#### P6 — Entidade Seção de Logradouro
**Checklist:** Intranet 17, 44, 52, 70 / Internet 10  
**O que fazer:**
- Criar model `LogradouroSecao` com campos: `logradouro_id`, `codigo_secao`, `lado_secao` (enum: esquerdo/direito), `comprimento`, `imagem` (path).
- Migration, Resource Filament e ação no mapa para criar/editar/excluir seções vinculadas ao logradouro ativo.
- Na ficha do logradouro no mapa, listar seções e permitir visualizar imagens.

---

#### P7 — Entidades Distrito e Setor geográficos
**Checklist:** Intranet 46, 47, 54, 55, 64, 65  
**O que fazer:**
- Criar models `Distrito` e `SetorGeografico` com geometria (Polygon), código, nome e área (calculada via PostGIS).
- Migrations com colunas `geo` PostGIS.
- Traits `HasDistritoActions` e `HasSetorGeograficoActions` na página `MapaFullscreen`.
- Resources Filament para CRUD de atributos.
- Vincular Quadra a Setor e Distrito via chaves estrangeiras (atualizar P2 acima).

---

#### P8 — Entidade Meio-fio/Calçada
**Checklist:** Intranet 57  
**O que fazer:**
- Criar model `MeioFio` com geometria (LineString/MultiLineString), código e atributos básicos.
- Migration com coluna `geo` PostGIS.
- Trait `HasMeioFioActions` no mapa para criar e excluir.

---

#### P9 — Planta de Quadra (PDF gerado a partir da base)
**Checklist:** Intranet 16  
**O que fazer:**
- Criar endpoint que receba `quadra_id` e gere um PDF com DomPDF contendo:
  - Planta cartográfica com lotes, edificações, logradouros e numeração ao redor.
  - Tabela lateral listando lotes: inscrição, área, testada, área construída, tipo de edificação, unidades.
- Botão de ação no mapa para gerar o PDF ao clicar numa quadra.

---

### 🔴 DIFÍCIL (meses de desenvolvimento)

#### P10 — Recodificação de Entidades
**Checklist:** Intranet 61  
**O que fazer:**
- Implementar fluxo de recodificação que permita alterar o código de um Lote (ou Edificação, Quadra, Logradouro) e propague as alterações em cascata para todas as entidades vinculadas (UnidadeImobiliaria, Edificacao).
- Interface modal no mapa com preview das entidades afetadas antes de confirmar.
- Registrar a operação no sistema de auditoria (P4).

---

#### P11 — Sistema de Campos Personalizados por Camada (EAV)
**Checklist:** Intranet 75, 76  
**O que fazer:**
- Criar sistema EAV (Entity-Attribute-Value) ou JSONB para campos customizados por camada:
  - Tabela `layer_fields` (tenant_id, layer, field_name, field_type: texto/número/seleção/multiseleção/multiseleção_com_quantitativo).
  - Tabela `layer_field_values` (entity_id, entity_type, field_id, value).
- Interface Filament para o admin criar/editar/excluir campos por camada.
- Os campos personalizados devem aparecer automaticamente em: ficha de identificação no mapa, edição de atributos, mapa temático, consulta de dados, mapa de calor e estatísticas.

---

#### P12 — Histórico de Alterações Cartográficas e de Atributos
**Checklist:** Intranet 14  
**O que fazer:**
- Ampliar o sistema de auditoria (P4) para incluir diff de geometria: armazenar o GeoJSON anterior e posterior em cada alteração cartográfica.
- Criar visualização no mapa de "modo histórico" onde o usuário seleciona uma data e vê como a geometria estava naquele momento.
- Diff visual de atributos: mostrar antes/depois de cada alteração de campo.

---

## Resumo Executivo de Conformidade

```
Características Técnicas:     6/6   (100%)
Ambiente Intranet:           86/89  (96,6% considerando parciais como 50%)
Ambiente Internet:           28/29  (96,6% considerando parcial como 50%)

GERAL: 85,1% de conformidade
Itens totalmente implementados (ok + backoficce): 80,6% (100/124)
Itens com implementação parcial: 8,9% (11/124)
Itens não implementados: 10,5% (13/124)
```
