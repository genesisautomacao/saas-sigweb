# Plano da PoC — Nova Santa Rosa/PR

> **Fonte:** `D:\Sistemas e Plataformas\WebGis\Pocs em andamento\Prova de Conceito - Nova Santa Rosa.pdf` (Anexo I, 114 itens).
> **Meta interna: 95% = 109 itens com SIM** (o edital exige 70%; o que faltar vai para entrega em até 1 mês).
> **Regra do edital:** só SIM ou NÃO — **parcial conta como NÃO**. Apresentação presencial, com notebook e base próprios, numa base única.
> A `poc_nova_santa_rosa_lista.txt` desta pasta é a versão que foi enviada à Líder; **o acompanhamento é feito aqui**.

**Como marcar um item:** troque a marca no começo da linha e rode `php docs/poc_atual/atualizar_resumo.php` — o resumo abaixo é refeito sozinho.

| Marca | Significa |
|---|---|
| `[x]` | **Concluído** — testado do jeito que a comissão vai pedir (executar na hora) |
| `[~]` | **Parcial** — existe no sistema, falta adequar (na PoC conta como NÃO) |
| `[ ]` | **Pendente** — não existe, precisa ser criado |
| `[-]` | **Fora da PoC** — fica para o mês seguinte |

<!-- RESUMO:INICIO -->
## Resumo

| Situação | Itens | % |
|---|---:|---:|
| ✅ Concluídos | 26 | 22,8% |
| 🟡 Parciais | 45 | 39,5% |
| ⏳ Pendentes | 41 | 36,0% |
| ⛔ Fora da PoC | 2 | 1,8% |

**Atendimento: 26/114 (22,8%) · Meta 95% = 109 itens · faltam 83**

| Etapa | ✅ | 🟡 | ⏳ | ⛔ |
|---|---:|---:|---:|---:|
| Etapa 0 — Já prontos (só ensaiar a demonstração) | 26 | 0 | 0 | 0 |
| Etapa 1 — Ferramentas do mapa (ajustes rápidos, horas cada) | 0 | 7 | 5 | 0 |
| Etapa 2 — Ajustes rápidos de cadastro, PGV e cidadão | 0 | 5 | 2 | 0 |
| Etapa 3 — Buscas, entorno e impressão | 0 | 2 | 5 | 0 |
| Etapa 4 — Anexos | 0 | 1 | 3 | 0 |
| Etapa 5 — Mapas temáticos salvos e com controle de acesso | 0 | 4 | 4 | 0 |
| Etapa 6 — Usuários e ambiente do cidadão | 0 | 3 | 1 | 0 |
| Etapa 7 — Cadastro imobiliário e edição cartográfica | 0 | 9 | 3 | 0 |
| Etapa 8 — PGV (Planta Genérica de Valores) | 0 | 2 | 5 | 0 |
| Etapa 9 — BCI e croqui montados pelo administrador | 0 | 0 | 2 | 0 |
| Etapa 10 — Levantamento contínuo por vídeo | 0 | 0 | 6 | 0 |
| Etapa 11 — App de coleta organizado por fluxos de trabalho (a mais longa) | 0 | 12 | 5 | 0 |
| Fora da PoC (entrega no mês seguinte) | 0 | 0 | 0 | 2 |

_Atualizado em 16/10/2026 23:16._
<!-- RESUMO:FIM -->

---

## Regras de trabalho

1. Um item só vira `[x]` depois de **testado na base de demonstração**, refazendo o passo a passo que a comissão vai pedir.
2. Mudança no mapa (`mapa-engine.js` / blade) = **varredura no navegador headless** antes de marcar (erro de JS não aparece em teste Livewire).
3. Cada entrega entra no `Releases.md` (release aberta) e, se mudar arquitetura, no `CLAUDE.md`.
4. As etapas vão do **mais fácil para o mais difícil**. Dentro de uma etapa os itens são independentes e podem andar em paralelo.

---

## Etapa 0 — Já prontos (só ensaiar a demonstração)

- [x] **01** — Funciona no Edge, Firefox e Chrome sem plugin.
- [x] **04** — Três níveis de acesso: visitante (só mapas), cidadão e restrito.
- [x] **06** — Tudo numa base única (montar a base de demonstração com todos os módulos).
- [x] **21** — Visualizador 360 com avanço e retrocesso.
- [x] **22** — Consulta de imóvel por inscrição, endereço, nome e CPF do proprietário.
- [x] **23** — Consulta por camadas (bairro, distrito, setor, quadra, lote, logradouro).
- [x] **27** — Localizar edifício pelo nome.
- [x] **31** — Impressão do mapa de navegação.
- [x] **35** — Temático por intervalo de classes.
- [x] **37** — Cor inicial e final gerando gradiente.
- [x] **39** — Mapa de calor por atributo numérico.
- [x] **41** — Temático de valores únicos.
- [x] **52** — BCI do imóvel selecionado.
- [x] **53** — Croqui do imóvel selecionado.
- [x] **54** — Editar lote e edificação só com permissão.
- [x] **62** — Módulo PGV na mesma base.
- [x] **64** — Total do IPTU proposto x lançado no último exercício.
- [x] **76** — Campo obrigatório no formulário do app.
- [x] **84** — Integração web x app (fluxo carregado no app, coleta volta ao sistema).
- [x] **85** — Celular e tablet (demonstrar em aparelho com Google Play; Huawei recente não serve).
- [x] **86** — Tocar na feição abre o formulário.
- [x] **89** — Câmera do aparelho, foto vinculada à coleta.
- [x] **93** — Trabalho offline com envio posterior.
- [x] **97** — Temático com dados da coleta.
- [x] **99** — Cidadão cria o próprio cadastro.
- [x] **114** — Navegação 360 estilo Street View.

---

## Etapa 1 — Ferramentas do mapa (ajustes rápidos, horas cada)

*Onde mexe:* `mapa-engine.js` + `mapa-fullscreen.blade.php` (e o mapa público quando o item vale lá). Varredura headless obrigatória.

- [~] **03** — Mapas de fundo: incluir o Google (já temos OSM, Esri, Azure/Bing e ortofotos).
- [~] **07** — Pelo menos 3 temas de cores para o usuário escolher (hoje só claro e escuro).
- [ ] **14** — Lista das feições encontradas no buffer; clicar numa leva até ela no mapa.
- [ ] **15** — Medir distância mostrando a medida de cada trecho, além do total.
- [~] **16** — Medir área mostrando cada lado, a área total e o perímetro.
- [ ] **18** — Girar o mapa, com a seta do norte acompanhando a rotação.
- [ ] **19** — Segundo mapa sobreposto ao principal, com cortina (swipe).
- [~] **20** — Botão "minha localização" no mapa interno e no público.
- [~] **28** — Ao localizar algo pela busca, destacar o contorno da feição (hoje só um marcador).
- [~] **36** — Temático por intervalo: número de faixas livre (hoje 3, 5 ou 7).
- [ ] **40** — Escolher a paleta de cores do mapa de calor a partir de uma lista.
- [~] **42** — Temático de valores únicos: listar só os atributos de texto.

---

## Etapa 2 — Ajustes rápidos de cadastro, PGV e cidadão

- [~] **05** — QGIS ligado direto no banco, vendo só o município, com área/extensão calculadas por gatilho no banco; a edição aparece na hora no mapa interno e no público.
- [~] **17** — Perfil altimétrico de superfície (com prédios e árvores) via serviço on-line, ao lado do de terreno que já existe.
- [~] **61** — Excluir a última edificação faz o lote voltar a "baldio".
- [~] **63** — Simulador de IPTU com "% de dedução do valor venal".
- [~] **65** — Tabela imóvel a imóvel: IPTU proposto x lançado (hoje só totais).
- [ ] **105** — Aceite obrigatório do termo de privacidade (LGPD) no primeiro acesso do cidadão.
- [ ] **106** — Cidadão pode excluir a própria conta a qualquer momento.

---

## Etapa 3 — Buscas, entorno e impressão

- [~] **08** — Carregar um shapefile no mapa (o WMS já existe).
- [~] **13** — Buffer a partir de qualquer feição selecionada, consultando todas as camadas ligadas.
- [ ] **24** — Busca escalonada: setor pelo código, filtrando pelo distrito.
- [ ] **25** — Busca escalonada: quadra pelo código, filtrando distrito e setor.
- [ ] **26** — Busca escalonada: lote pelo código, filtrando distrito, setor e quadra.
- [ ] **29** — Impressão personalizada: título, subtítulo, legenda e norte acompanhando a rotação.
- [ ] **30** — Carimbo na impressão: empresa, responsável, data e versão.

*Atenção 24–26:* hoje não existe vínculo quadra→setor nem setor→distrito no banco; criar o vínculo e preencher na base de demonstração.

---

## Etapa 4 — Anexos

- [~] **09** — Anexos (PDF e link) em lote, quadra, loteamento, bairro e imóvel rural, com tipo (contrato, matrícula, croqui...) e código do documento.
- [ ] **10** — Criar um tipo novo de anexo na hora de anexar.
- [ ] **11** — Tela de busca de anexos por código, tipo, extensão, entidade e data.
- [ ] **12** — Do anexo encontrado: ir até a feição no mapa e baixar o arquivo.

---

## Etapa 5 — Mapas temáticos salvos e com controle de acesso

*Atenção:* o edital diz que, se o controle de acesso (33/34) falhar na demonstração, **o item de tematização inteiro é reprovado**. Testar com dois usuários de perfis diferentes.

- [~] **32** — Salvar o temático criado e organizar em categorias.
- [ ] **33** — Definir quais perfis veem cada temático; quem não tem o perfil não vê.
- [ ] **34** — O mesmo controle de perfis nas categorias de temáticos.
- [ ] **38** — Editar os limites e as cores de cada faixa do temático.
- [~] **43** — Consulta do temático com várias condições (E/OU) e cruzamento de 3 ou mais camadas.
- [~] **68** — Usar os valores simulados da PGV (novo IPTU e diferença) para criar temáticos.
- [ ] **98** — Temático dos dados da coleta filtrando por período.
- [~] **103** — Temático visível ao cidadão quando quem criou permitir.

---

## Etapa 6 — Usuários e ambiente do cidadão

- [~] **100** — Pedido de acesso de servidor fica pendente até o administrador aceitar ou recusar.
- [~] **101** — Quem for recusado continua como cidadão.
- [~] **102** — No aceite, o administrador define setor e perfis.
- [ ] **104** — Esconder campos só para o cidadão (configurável pela prefeitura).

---

## Etapa 7 — Cadastro imobiliário e edição cartográfica

- [~] **44** — Seletor de módulo: ao trocar de módulo, mostrar só as funções daquele tema.
- [~] **45** — Carta de notificação para imóvel com área construída divergente.
- [~] **46** — Lista de imóveis com área CONSTRUÍDA divergente por percentual, exportando PDF e CSV.
- [~] **47** — Carta de um imóvel ou de todos acima do percentual.
- [ ] **48** — Configurar o texto da carta e os dados de assinatura do responsável.
- [ ] **49** — Painel: área construída mapa x cadastro por bairro, e divergentes por logradouro num bairro.
- [~] **55** — Quadra nova: código do distrito e do setor preenchidos sozinhos.
- [~] **56** — Seção de logradouro nova: código métrico e lado gerados sozinhos.
- [~] **57** — Lote novo: inscrição, testadas com logradouro e seção, e ocupação gerados sozinhos.
- [~] **58** — Edificação nova: inscrição com unidade e área pelo número de pavimentos.
- [~] **59** — Desmembramento redistribuindo também as unidades e dando inscrição ao lote novo.
- [ ] **60** — Recodificação de lote, recodificando junto as unidades (B1 do backlog).

---

## Etapa 8 — PGV (Planta Genérica de Valores)

- [ ] **66** — Salvar até 5 propostas de simulação e reabrir qualquer uma.
- [ ] **67** — Resumo de cada proposta salva: total do novo imposto e todos os parâmetros.
- [ ] **69** — Alterar o m² de uma face de quadra e recalcular na hora o IPTU dos imóveis dela.
- [~] **70** — Centro de valorização: mover e excluir no mapa, vinculado à zona homogênea.
- [~] **71** — Amostras: mover e excluir no mapa, atualizando coordenadas e distâncias na hora.
- [ ] **72** — Simulador de ITBI com impressão.
- [ ] **73** — Painel da PGV: aumentos de IPTU, venal atual x proposto, m² anterior x proposto.

---

## Etapa 9 — BCI e croqui montados pelo administrador

- [ ] **50** — Montar o BCI pela tela: campos exibidos ou ocultos, ordem e disposição.
- [ ] **51** — Montar o croqui pela tela: campos exibidos ou ocultos, ordem e disposição.

---

## Etapa 10 — Levantamento contínuo por vídeo

*Depende de dado:* vídeo 360 georreferenciado + GPX gravado no município da base (especificação em `especificacao_video_360.txt`). Sem vídeo 360, os itens 108 e 113 viram `[-]`.

- [ ] **107** — Importar vídeo MP4 + trilha GPX e tocar sincronizado com a rota no mapa.
- [ ] **109** — Controles: tocar, avançar, voltar, brilho e contraste.
- [ ] **110** — Tirar a foto da fachada do vídeo e gravar no imóvel.
- [ ] **111** — Exportar o quadro do vídeo com a coordenada gravada (geotag).
- [ ] **108** — Vídeo 360 com a posição e o cone do ângulo de visão no mapa.
- [ ] **113** — Exportar imagens 360 a cada 5 metros, em JPG, com geotag.

---

## Etapa 11 — App de coleta organizado por fluxos de trabalho (a mais longa)

*Onde mexe:* web (equipes, fluxos, formulários) + app React Native (exige build e instalação no aparelho da demonstração).

- [~] **74** — Formulários de campo também para eixo viário (hoje lote, edificação e unidade).
- [~] **75** — Campo do tipo assinatura no construtor do formulário.
- [ ] **77** — Informativo (texto de ajuda) por campo do formulário.
- [ ] **78** — Definir quais perfis podem ver cada formulário.
- [ ] **79** — Criar e gerenciar equipes de campo a partir dos usuários.
- [~] **80** — Supervisor por equipe, o único que ativa ou desativa o trabalho de campo.
- [~] **81** — Área de atuação desenhada livremente ou escolhida por bairros.
- [~] **82** — Dentro da área, escolher os lotes um a um ou todos.
- [ ] **83** — Mais de um formulário no mesmo trabalho de campo.
- [~] **87** — Informativos aparecendo no app durante o preenchimento.
- [ ] **88** — Assinar na tela do app e salvar como imagem.
- [~] **90** — Status "visita confirmada" e "refazer visita" (fechado, recusado), com cor no mapa.
- [~] **91** — Mapa do supervisor atualizando sozinho o status das feições.
- [~] **92** — Envio automático das coletas quando houver internet.
- [~] **94** — Validar (aprovar ou recusar) cada ficha que chega do app.
- [~] **95** — Gráfico de produtividade com filtro por data E hora e por agente.
- [~] **96** — Relatório da coleta escolhendo formulário e data/hora de início e fim.

---

## Fora da PoC (entrega no mês seguinte)

- [-] **02** — Sistema em inglês e espanhol, com seletor de idioma (mexe em todas as telas).
- [-] **112** — Localizar no mapa objetos vistos no vídeo/360 (precisão de demonstração frágil).
