CARACTERÍSTICAS BÁSICAS E OBRIGATÓRIAS DO SIG WEB (PROVA DE CONCEITO)

I - Características gerais
001 O sistema de informação geográfica deverá funcionar em ambiente WEB e ter suporte aos principais navegadores de internet atualmente disponíveis, no mínimo, Microsoft Edge, Mozilla Firefox e Google Chrome;
002 Deverá possuir controles de visualização automática (por nível de proximidade) dos componentes cartográficos do mapa;
003 Deverá permitir ao usuário a realização de medições de distâncias entre dois ou mais pontos, como também, medições da área diretamente no mapa. Deverá Permitir visualizar o perfil do terreno (altimetria);
004 Deverá permitir navegar, selecionar e identificar no mapa a parcela referente ao imóvel, visualizando todas as informações autorizadas pelo Município, referente a parcela e suas unidades imobiliárias;
005 Deverá Permitir a impressão de croqui de localização do imóvel previamente selecionado;
006 Deverá Permitir a pesquisa e localização de todos os elementos geográficos que possuam dados (bairro, loteamento, quadra, lotes, logradouro, etc), através de uma barra geral de consulta que organiza o resultado da pesquisa de forma categorizada;
007 Deverá permitir acompanhamento georreferenciado das atividades do cadastramento e recadastramento imobiliário, identificando e quantificando graficamente as parcelas imobiliárias pendentes de visita, visitadas, recadastradas, etc;
008 O sistema deverá permitir a inserção e configuração de camadas a serem utilizadas dentro do SIGWEB;
009 As funcionalidades de Edição Cartográfica devem ser integralmente em ambiente WEB, sem a necessidade de sistemas ou software desktop para inserir, editar ou remover Geometrias de diferentes entidades dentro do SIGWEB.

II - Controle de acesso de usuários
010 Deverá permitir login de usuário através de usuário e senha o qual estará atribuído a um perfil para o controle seletivo de acesso de informações cadastrais, pesquisas e manutenção;
011 O sistema deverá permitir ao usuário registrar-se para obter acesso às funcionalidades que necessitam de identificação;
012 Gerenciador do sistema no ambiente Web para a gestão de usuários e perfis;
013 Configuração do sistema para acesso seletivo aos dados através de usuário administrador;
014 Permitir atribuir a um usuário do sistema ser administrador dando acesso total a eventos, atributos e menus.

III - Módulo Imobiliário
015 Deverá permitir a manutenção (inserção, atualização e remoção), incluindo consultas e relatórios em formatos XLS, PDF, CSV e XML das seguintes entidades: Pessoa (Proprietário); Bairro; Logradouro; Boletim de Informação Cadastral (BIC); Loteamento; Quadra; Lote; Unidade Imobiliária (Edificações).
016 Deverá permitir a associação dos elementos geográficos ao cadastro imobiliário do SIG das seguintes entidades: Bairro; Logradouro; Loteamento; Quadra; Lote; Unidade Imobiliária (Edificações).
017 O lote deve possuir no mínimo campos como código, testada principal, secundária e área;
018 O cadastro do lote deve: Permitir a atribuição do Logradouro e Bairro; Permitir a atribuição Loteamento e Quadra; Permitir a atribuição dos dados territoriais, conforme Boletim de Informações Cadastrais.
019 Deverá permitir gerar memorial descritivo contendo: dados do imóvel; o mapa com a identificação dos vértices e as medidas das arestas; a descrição do perímetro contendo azimutes, distâncias e confrontantes; e as coordenadas de cada vértice. O documento deverá ser gerado no momento da requisição e em formato PDF;
020 A unidade imobiliária deve possuir no mínimo campos como cadastro imobiliário, inscrição imobiliária, face de quadra, número da unidade e área construída;
021 O cadastro da unidade imobiliária deve: Permitir a atribuição do Loteamento, Quadra e Lote; Permitir a atribuição do proprietário ou morador; Permitir a atribuição do Logradouro e Número Predial; Permitir a atribuição dos dados prediais, conforme Boletim de Informações Cadastrais; Permitir a inclusão de documentos digitalizados e imagens.
022 Deverá permitir a manutenção (inserção, atualização e remoção) de mapas temáticos de fontes WMS do sistema e fontes WMS externas, onde o cadastro destes mapas devem ser hierarquizados por categoria;
023 Deverá possuir mapa cartográfico nas telas onde a entidade possua relacionamento com elementos geográficos, tais como: Bairro, Logradouro, Loteamento, Quadra, Lote e Unidade Imobiliária (Edificação), para permitir navegar, identificar e medir os elementos cartográficos conforme necessidade;
024 Ao selecionar um registro na tabela de resultado de pesquisa, em "cases" de entidades com vinculação cartográfica. O sistema deverá localizar, posicionar e identificar o elemento no mapa;
025 Deverá permitir importação de dados referente ao cadastramento e recadastramento imobiliário, incluindo fotos de fachada e demais documentos, a partir de arquivo gerado pelos dispositivos móveis, utilizados para o cadastramento e recadastramento imobiliário;
026 Deverá permitir a vetorização, medição e registro de áreas de edificações irregulares, nas parcelas territoriais, diretamente no mapa do SIGWEB com uso de uma camada de ortofoto do Município;
027 Deverá permitir a emissão de notificação de irregularidade de edificação, de construções irregulares que foram previamente registradas, conforme descrito no item anterior;
028 Deverá permitir a visualização panorâmica da rua (Street View), através do Google Maps integrado ao SIGWEB;
029 Permitir a exibição dos patrimônios públicos no mapa do SIGWEB identificados de acordo com sua finalidade;
030 Permitir a exibição dos dados do patrimônio público ao selecionar no mapa do SIGWEB, incluindo os documentos digitalizados.

IV - Módulo de Edição Cartográfica
031 Possuir ferramenta de precisão (snap), no mínimo para fim de linha/polilinha ou ponto (endpoint) e meio de linha/polilinha (midpoint);
032 Possuir ferramentas de desenho: rotação, mover, espelhar, clonar, dividir e unir;
033 Possibilidade de adicionar/excluir linhas guia para auxiliar no desenho da geometria;
034 Possuir ferramenta de buffer (expandir ou contrair uma geometria paralelamente conforme o valor determinado pelo usuário);
035 Possibilidade de acrescentar camadas vetoriais ou raster para apoio nas operações cartográficas;
036 O sistema deverá possibilitar o desenho de linhas de forma ortogonal a partir de uma linha base;
037 Incluir/alterar/excluir e geocodificar Logradouro, Seções, Lotes, Edificações (unidades imobiliárias) e Zoneamentos (salvando no Banco de Dados a geometria e suas alterações);
038 Incluir/alterar/excluir e geocodificar Seções (salvando no Banco de Dados a geometria e suas alterações);
039 Incluir/alterar/excluir e geocodificar Lotes (salvando no Banco de Dados a geometria e suas alterações);
040 Incluir/alterar/excluir e geocodificar Edificações (unidades imobiliárias) (salvando no Banco de Dados a geometria e suas alterações);
041 Incluir/alterar/excluir e geocodificar Zoneamentos (salvando no Banco de Dados a geometria e suas alterações);
042 Realizar Desmembramentos (todos os procedimentos de cadastro envolvidos no desmembramento devem estar presentes e atualizados ao fim do processo);
043 Realizar Unificação de Lotes, Edificações, Quadras, Zoneamentos e Bairros (atualizar geometria e sua área exibida no mapa imediatamente após Salvar);
044 Visualização do histórico de alterações cartográficas do Lotes (demonstrando o Croqui do mesmo antes e após as alterações);
045 O sistema deverá permitir a criação de geometrias pela coordenada XY de cada vértice;
046 O sistema deverá permitir a criação de geometrias por azimutes, (ao entrar com coordenadas XY inicial e após o azimutes de distância de cada aresta; com possibilidade de obter o XY inicial clicando no mapa).

V - Módulo de Consulta de Viabilidade
047 Deverá permitir a visualização, reimpressão e controle das consultas de viabilidade emitidas pelo sistema;
048 Deverá emitir consulta de viabilidade de parcelas territoriais que demonstre os parâmetros para a construção de edificações;
049 Deverá emitir consulta de viabilidade de parcelas territoriais que demonstre os parâmetros para parcelamento do solo;
050 Deverá emitir consulta de viabilidade de parcelas territoriais para definição da possibilidade de abertura de estabelecimentos comerciais conforme a classificação nacional de atividades econômicas - CNAE;
051 Deverá permitir a busca da atividade econômica através do código do CNAE ou da descrição através de função de auto completar;
052 O sistema deverá criar um código de verificação/autenticação único e não sequencial para cada consulta emitida.

VI - Módulo de Estoque para iluminação pública
053 Deverá permitir a manutenção (inserção, atualização e remoção), incluindo consultas e relatórios em formatos XLS, PDF, CSV e XML das seguintes entidades: Estabelecimento; Produto; Marca Comercial (Fabricante e Embalagem); Fabricante; Fornecedor; Embalagem (Quantidade e Unidade de Medida); Unidade de Medida de Apresentação; Família de Produto; Locais de Estoque (Locais por estabelecimento); Tipo de Estoque; Operações Internas para Movimentação de Estoque.
054 Permitir inserção de nota de entrada de produto, através de operação interna de entrada, previamente configurada no sistema, para movimentação do estoque em seu devido local e tipo de estoque;
055 Permitir o controle de estoque (locais e tipo de estoque) por lote ou número de série, mantendo consistente o estoque de produtos (lâmpadas, luminárias, reatores, entre outros) através das diversas operações internas de entrada e saída configuradas e que movimentam estoque;
056 Permitir a realização de transferência de estoque de produtos entre os diversos locais e tipos de estoque cadastrado no sistema;
057 Emitir relatórios de movimentação de estoque por período, produto, lote, locais e tipo de estoque;
058 Emitir relatório de saldo geral e por lote filtrado por local e tipo de estoque, produto e família;
059 Emitir relatório de garantia de produto filtrado por local e tipo de estoque, produto e família.

VII - Módulo de Iluminação Pública
060 Deverá permitir a manutenção (inserção, atualização e remoção), incluindo consultas e relatórios em formatos XLS, PDF, CSV e XML das seguintes entidades: Poste; Itens de Produto para o Poste (reator, lâmpada, luminária, etc) com possibilidade de identificar o lote de estoque do item; Tipos de Defeito; Equipe de Manutenção; Ordem de Serviço;
061 Os postes devem possuir no mínimo campos como código (classificado por região), endereço (logradouro e número predial do qual o poste se encontra em frente) e tipo do poste (ornamental, concreto, etc);
062 Permitir que o usuário liste os registros dos postes em forma de tabela e o sistema automaticamente posicione e identifique no mapa localização geográfica do poste ao ser selecionado na tabela;
063 Permitir que o usuário selecione no mapa um determinado poste e o sistema o exiba automaticamente na tabela, para posterior edição ou visualização dos dados;
064 Permitir a abertura da solicitação de reparo, a partir de um poste selecionado no mapa do SIG WEB, informando os seguintes dados: Tipo de Defeito; Comentário;
065 O sistema deve alterar a identificação gráfica do poste no mapa, quando houver a abertura de uma solicitação, indicando que existe defeito no poste, e esta identificação deverá ser modificada durante o processo de atendimento;
066 Permitir o filtro das solicitações de reparo em todos os seus estados, apresentando uma listagem em forma de tabela;
067 Permitir que o usuário selecione a solicitação de reparo na listagem em forma de tabela e o sistema automaticamente posicionar e identificar no mapa localização geográfica do poste relacionado a solicitação;
068 Permitir que o usuário selecione no mapa um determinado poste e o sistema liste automaticamente todas as solicitações de reparo relacionadas ao poste, exibindo uma listagem em forma de tabela;
069 Permitir a abertura da ordem de serviço, a partir de um poste selecionado no mapa do SIGWEB ou a partir de uma solicitação de reparo anteriormente aberta, informando os seguintes dados: Equipe de Manutenção Responsável; Tipo de Defeito; Comentário; Itens da ordem de serviço.
070 O sistema deve alterar a identificação gráfica do poste no mapa, quando houver a abertura de uma ordem de serviço, indicando que está sendo realizado manutenção no mesmo, e esta identificação deverá ser alterada conforme a fase do processo de atendimento;
071 Permitir o filtro das ordens de serviços em todos os seus estados, apresentando uma listagem em forma de tabela;
072 Permitir que o usuário selecione a ordem de serviço na listagem em forma de tabela e o sistema automaticamente posicione e identifique no mapa localização geográfica do poste relacionado a ordem de serviço;
073 Permitir que o usuário selecione no mapa um determinado poste e o sistema liste automaticamente todas as ordens de serviço relacionadas ao poste, exibindo uma listagem em forma de tabela;
074 Impressão da ordem de serviço com o mapa de localização do poste;
075 Deve ser integrado com módulo de estoque para desta forma movimentar os locais e tipos de estoque conforme operação interna de saída por ordem de serviço, previamente cadastrada e configurada no módulo de estoque.

VIII - Módulo de Arborização
076 Deverá permitir a manutenção (inserção, atualização e remoção), incluindo consultas e relatórios em formatos XLS, PDF, CSV e XML das seguintes entidades: Árvore; Boletim Cadastral (Características e Situações); Tipos de Serviço (poda, plantio, remoção, manejo, tratamento, etc); Manutenção conforme tipo de serviço; Solicitação conforme tipo de serviço.
077 As árvores devem possuir no mínimo campos como código único e incremental, endereço (logradouro e número predial do qual a árvore se encontra mais próxima) e data do cadastro;
078 Permitir que o usuário liste os registros das árvores em forma de tabela e o sistema automaticamente posicione e identifique no mapa a localização geográfica da árvore, quando esta for selecionada na tabela;
079 Permitir que o usuário selecione no mapa uma determinada árvore e o sistema a exiba automaticamente na tabela, para posterior edição ou visualização dos dados;
080 Permitir a abertura da solicitação de manutenção, a partir de uma árvore selecionada no mapa do SIG WEB, informando os seguintes dados: Tipo de Manutenção; Comentário;
081 O sistema deve alterar a identificação gráfica da árvore no mapa, quando houver a abertura de uma solicitação, indicando que existe manutenção sendo realizada na árvore, e esta identificação deverá ser modificada durante o processo de manutenção;
082 Permitir o filtro das solicitações de manutenção em todos os seus estados, apresentando uma listagem em forma de tabela;
083 Permitir que o usuário selecione a solicitação de manutenção na listagem em forma de tabela e o sistema automaticamente posicione e identifique no mapa localização geográfica da árvore correspondente a solicitação;
084 Permitir que o usuário selecione no mapa uma determinada árvore e o sistema liste automaticamente todas as solicitações de manutenção registradas àquela árvore, exibindo uma listagem em forma de tabela;
085 Permitir abertura de ordem de serviço, a partir de uma árvore selecionada no mapa do SIG WEB ou a partir de uma solicitação de manutenção anteriormente aberta, informando os seguintes dados: Equipe de Manutenção Responsável; Tipo de Serviço; Comentário;
086 O sistema deve alterar a identificação gráfica da árvore no mapa, quando houver a abertura de uma ordem de serviço, indicando que está sendo realizado manutenção na mesma, e esta identificação deverá ser alterada conforme a fase do processo de atendimento;
087 Permitir o filtro das ordens de serviços em todos os seus estados, apresentando uma listagem em forma de tabela;
088 Permitir que o usuário selecione a ordem de serviço na listagem em forma de tabela e o sistema automaticamente posicione e identifique no mapa localização geográfica da árvore relacionada a ordem de serviço;
089 Permitir que o usuário selecione no mapa uma determinada árvore e o sistema liste automaticamente todas as ordens de serviço relacionadas à árvore, exibindo uma listagem em forma de tabela;
090 Impressão da ordem de serviço com o mapa de localização da árvore.

IX - Módulo de Gestão do Cadastro Social
091 Deverá permitir a manutenção (inserção, atualização e remoção), incluindo consultas e relatórios em formatos XLS, PDF, CSV e XML das seguintes entidades: Pessoa - Social; Tipo de Renda; Entidade; Tipo de Entidade; Serviço Social; Programa; Evento; Informações Sociais; Empreendimento; Família.
092 A Pessoa Social deve possuir no mínimo campos código único e incremental, nome, RG, CTPS, PIS, CPF, data de nascimento, certidão de nascimento, telefone, NIS, estado civil, sexo, pai, mãe, cônjuge;
093 O cadastro da Pessoa - Social deve: Permitir adicionar os endereços; Permitir adicionar as deficiências físico/mental com seus respectivos números do CID; Permitir adicionar as rendas, com opção de especificar se compõe ou não a renda familiar; Permitir o registro de ocorrências sociais (alteração cadastral, atendimentos sociais, etc.); Permitir adicionar documentos digitalizados (.pdf) e imagens (.jpeg).
094 A Familia deve possuir no mínimo campos código único e incremental, situação do cadastro (cadastrado, beneficiado, aprovado, sorteado, não localizado, apresentou documentos, etc...) e empreendimento;
095 O cadastro da Família deve: Permitir a composição familiar, informando os membros familiares (Pessoa - Social), grau de parentesco e representatividade familiar; Permitir o registro de ocorrências sociais; Permitir a definição social através das informações sociais previamente cadastradas; Permitir a atribuição do imóvel de moradia; Especificar se a família possui terreno, informando a localização geográfica (Loteamento/Quadra/Lote) e titularidade.
096 Calcular automaticamente o indice de vulnerabilidade baseado nas informações sociais especificadas no cadastro da Família;
097 Calcular automaticamente a renda bruta familiar e a renda per capta familiar, baseadas nas rendas cadastradas dos membros familiares, respeitando se a renda do membro compõe ou não renda familiar,
098 Exibir gráfico analítico (pizza ou similar) que interage diretamente com mapa para identificar as famílias em diferentes situações cadastrais. Este gráfico deve permitir a seleção das porções do gráfico de forma que o sistema identifique no mapa onde estas famílias estão localizadas, de acordo com o campo de identificação da moradia atual ou moradia de benefício da família.

X - Numeração predial
099 O sistema deverá permitir selecionar no mapa o logradouro que deseja executar o processo de numeração predial;
100 Sistema deve identificar automaticamente no mapa as parcelas (terrenos/lotes) envolvidas no processo de numeração com base no logradouro selecionado e também identificar automaticamente no mapa as parcelas que receberão números pares ou impares (exibindo estas em cores diferentes) e as que não receberão números prediais;
102 Deverá Permitir excluir e inserir de volta parcelas do processo de numeração predial a partir do mapa;
103 Deverá Permitir inverter os lados pares e impares;
104 Deverá Permitir informar no mapa o ponto de partida para iniciar a numeração predial;
105 Deverá Permitir informar os números iniciais para o lado par e lado impar,
106 Sistema deve gerar a numeração predial para os cadastros (edificação) que estão vinculados ao logradouro selecionado inicialmente;
107 Sistema deve listar os cadastros (edificações) de cada parcela e exibir a faixa de numeração disponível para que o usuário possa escolher qual é o mais adequado quando o sistema não estabelecer o correto;
108 Deverá Permitir salvar a numeração predial definida para posteriormente executar processo de comparação entre o número atual do cadastro;
109 Exibir no mapa as parcelas que possuem divergências de numeração com base no número atual e o gerado pelo processo de numeração predial.

XI - Gestão de cemitérios
110 Deverá permitir inserir, salvar, remover e consultar: Cemitério;
111 Deverá permitir inserir, salvar, remover e consultar: Quadra;
112 Deverá permitir inserir, salvar, remover e consultar: Jazigo;
113 Deverá permitir inserir, salvar, remover e consultar: Logradouro;
114 Deverá permitir inserir, salvar, remover e consultar: Falecido;
115 Deverá permitir inserir, salvar, remover e consultar: Proprietário do jazigo;
116 Deverá permitir a visualização no mapa de Cemitérios, Quadras e Jazigos;
117 Deverá Permitir selecionar um jazigo no mapa e o sistema exibir os dados dos falecidos associados;
118 O sistema deve exibir dados básicos para o falecido como nome, data do falecimento e data de nascimento;
119 Permitir inserção de documentos (.pdf) e imagens (.jpg) ao cadastro do falecido.

XII - Módulo de Processo Digital
120 Possibilidade de criar e desenhar um fluxo através de editor BPMN (Business Process Model and Notation) onde permite incorporar objetos no processo de modelagem;
121 Dentro do Editor BPMN deverá permitir associar um ou mais perfis de usuário para ter permissão de acesso a esse fluxo;
122 Deverá permitir a criação, alteração ou modificação de um fluxo através do Editor BPMN;
123 Deverá permitir ativar o fluxo através do Editor BPMN;
124 Em cada etapa em que existe uma tarefa de usuário (user task) possibilidade de configurar o tempo médio da etapa;
125 Possibilidade de inserir um formulário com no mínimo 04 tipos de preenchimento: Texto simples, Seleção múltipla de opções (Checkbox), mapa simples para seleção de posição e campo CPF ou campo telefone com a devida máscara;
126 Possibilidade de inserir, editar, visualizar e gerenciar as permissões do formulário;

XIII - Módulo de Processo Digital - Aprovação de Projeto
127 Permite o solicitante visualizar seu processo aberto e em qual etapa se encontra quando estiver logado;
128 Permite o solicitante iniciar o preenchimento e salvar em rascunho para envio posterior;
129 Permita o solicitante fazer correções somente na fase onde o parecer da referida fase estiver reprovado pelo analista;
130 Permita ao solicitante que selecione o imóvel no mapa, mostrando as seguintes informações: número do cadastro imobiliário, inscrição imobiliária e localização do mesmo;
131 Na elaboração do formulário possibilidade de deixar o campo como obrigatório ou não;
132 O sistema deverá permitir o analista, um acesso de gerenciamento dos processos;
133 Como analista possibilidade de encaminhar o processo para outro analista da fase;
134 Como analista possibilidade de deixar o processo sem analista caso necessário;
135 Como analista permitir a visualização dos processos pertencentes a outros analistas e em qual etapa se encontra;
136 Como analista ter a possibilidade de consultar um ou vários processos por: (Códigos dos processos, nome de requerente, telefone ou e-mail do requerente);
137 Como analista possibilidade filtrar um fluxo por campos do fluxo.

XIV - Módulo de Processo Digital - Habite-se online Atestado Conclusão de Obra
138 Permite o solicitante visualizar seu processo aberto e em qual etapa se encontra quando estiver logado;
139 Permite o solicitante iniciar o preenchimento e salvar em rascunho para envio posterior;
140 Permita o solicitante fazer correções somente na fase onde o parecer da referida fase estiver reprovado pelo analista;
141 Permita ao solicitante que selecione o imóvel no mapa, mostrando as seguintes informações: número do cadastro imobiliário, inscrição imobiliária e localização do mesmo;
142 Na elaboração do formulário possibilidade de deixar o campo como obrigatório ou não;
143 O sistema deverá permitir o analista, um acesso de gerenciamento dos processos;
144 Como analista possibilidade de encaminhar o processo para outro analista da fase;
145 Como analista possibilidade de deixar o processo sem analista caso necessário;
146 Como analista permitir a visualização dos processos pertencentes a outros analistas e em qual etapa se encontra;
147 Como analista ter a possibilidade de consultar um ou vários processos por: (Códigos dos processos, nome de requerente, telefone ou e-mail do requerente);
148 Como analista possibilidade filtrar um fluxo por campos do fluxo.

XV - Módulo de Gestão do Aplicativo Móvel
149 Deverá Permitir a manutenção (inserção, atualização e remoção) de fluxos de trabalho onde é possível incluir fases para esse determinado fluxo de trabalho;
150 Deverá Permitir atribuir cor, aviso de duração e duração da fase em minutos;
151 Deverá Permitir Incluir usuários que serão autorizados para visualizar as informações de cada fase do Fluxo de Trabalho;
152 Deverá Permitir definir uma fase como encerrado, dizendo que essa fase é a última para o Fluxo de Trabalho;
153 Deverá Permitir alterar a ordem da fase se necessário;
154 Deverá Permitir a inserção de boletim (Questionário) para cada Fluxo de Trabalho para que o cidadão possa realizar a resposta dentro do aplicativo;
155 Deverá Permitir a manutenção (inserção, atualização e remoção) de categorias para o Fluxo de Trabalho;
156 Deverá Permitir organizar as Categorias entre Categorias Pai e Categorias Filho;
157 Deverá Permitir atribuir cor e adicionar ícones nos formatos png e .jpg;
158 Deverá Permitir atribuir essa categoria para um determinado Fluxo de Trabalho pré-cadastrado;
159 Deverá Permitir informar se é uma Categoria Privada (somente para fiscais da Prefeitura);
160 Deverá Permitir realizar filtros (Código, Data de Criação, Última atualização, Observações, Anotações) para pesquisa das solicitações;
161 Deverá Permitir filtrar as solicitações por categorias;
162 Deverá Permitir que o usuário selecione uma solicitação na listagem em forma de tabela e o sistema automaticamente posiciona e identifica no mapa localização geográfica da solicitação;
163 Deverá Permitir que o usuário selecione no mapa uma determinada solicitação e o sistema liste automaticamente a solicitação, exibindo uma listagem em forma de tabela;
164 Deverá Permitir visualizar os detalhes da solicitação;
165 Deverá Permitir alterar a Categoria da solicitação;
166 Notificar que a Categoria foi alterada;
167 Deverá Permitir alterar a Fase Atual do Chamado;
168 Notificar que a Fase Atual foi alterada;
169 Deverá Permitir enviar mensagens públicas onde o cidadão receberá em seu dispositivo móvel uma notificação;
170 Deverá Permitir enviar mensagens privadas para comunicação interna da prefeitura em relação a solicitação em si onde o cidadão não poderá visualizar essas mensagens;
171 Possibilidade de enviar mensagem pública mesmo após a solicitação tenha sido finalizada a fim da Prefeitura comunicar o cidadão;
172 Deverá Permitir visualizar as respostas do Boletim criado no Fluxo de Trabalho;
173 Deverá Permitir incluir fotos referente a solicitação;
174 Deverá Permitir a impressão da solicitação com o mapa de localização da solicitação, mensagens da solicitação, questionário do fluxo de trabalho e histórico de alteração de fases.

XVI - Características do aplicativo para dispositivos móveis para abertura de chamados
175 Deverá ser desenvolvido para plataforma Android e IOS;
176 Deverá ser integrado ao SIG WEB;
177 Deverá permitir a criação de um login ao aplicativo;
178 Deverá permitir Login de usuário via Facebook e conta Gmail;
179 Deverá permitir selecionar camadas previamente configuradas no SIG WEB para mostrar no aplicativo móvel;
180 Deverá permitir a criação de solicitações;
181 Possibilidade de mover o mapa para posicionar o marcador na hora de realizar a abertura da solicitação;
182 Inclusão de uma ou mais imagens;
183 Deverá permitir editar a foto, recortar, rotacionar;
184 Busca automática do endereço para referência, possibilidade de alterar caso o endereço não seja o correto;
185 Deverá permitir escrever observações finais;
186 Deverá permitir visualizar todas as suas solicitações;
187 Deverá permitir alterar seu cadastro como, Nome, Data de Nascimento, E-mail, Celular e Senha;
188 Deverá permitir compartilhar o aplicativo com outras pessoas;
189 Deverá permitir os fiscais da prefeitura utilizarem o aplicativo quando houver alguma categoria específica para os fiscais.

XVII - Características do aplicativo para Recadastramento Imobiliário
190 Deverá ser desenvolvido para plataforma Android;
191 Deverá ter integração direta com o SIGWEB;
192 Deverá ter credenciais de acesso configuradas pelo sistema;
193 Deverá listar os lotes conforme loteamento acessado;
194 Deverá permitir selecionar o lote pelo mapa;
195 Deverá permitir selecionar o lote por uma lista de lotes;
196 Deverá ter a opção de habilitar e desabilitar as camadas configuradas pelo SIGWEB;
197 Deverá ter camada que indica a situação do recadastramento;
198 Deverá permitir armazenamento em cache das camadas acessadas, para correto funcionamento offline;
199 Deverá permitir gerar arquivo ZIP contendo todas as informações coletadas, em forma de backup de informações;
200 Deverá permitir enviar as informações coletadas diretamente para o sistema SIGWEB, gerando um novo cadastro vinculado ao lote selecionado, com as fotos, croquis e demais documentos;
201 Deverá exibir a lista dos boletins (bics) inseridos durante a coleta em campo;
202 Deverá permitir a manutenção dos boletins (bics) - inserção, atualização e remoção;
203 Deverá permitir o rastreio da coordenada geográfica do ponto de coleta de dados relacionado a parcela imobiliária;
204 Deverá ter a opção de trabalhar online e offline, através de internet móvel ou de armazenar os dados para sincronização em ambiente com wi-fi disponível.

XVIII - Módulo de Processo de REURB Digital
205 Possibilidade de criar e alterar um fluxo através de editor BPMN (Business Process Model and Notation) configurável de acordo com as necessidades do processo utilizado;
206 Organizar por setor/departamento os objetos do fluxo, facilitando a leitura e interpretação do desenho do processo;
207 Dentro do Editor BPMN deverá permitir associar um ou mais perfis de usuário para ter permissão de acesso a esse fluxo;
208 Deverá permitir ativar sim ou não um fluxo através do Editor BPMN;
209 Em cada etapa em que existe uma tarefa de usuário (user task) possibilidade de configurar o tempo médio da etapa;
210 Possibilidade de inserir um formulário com no mínimo 04 tipos de preenchimento: Texto simples, Seleção múltipla de opções (Checkbox), mapa simples para seleção de posição e campo CPF ou campo telefone com a devida máscara;
211 Possibilidade de gerenciar as permissões de acesso ao formulário de acordo com as etapas criadas no Editor BPMN;
212 Dentro do Processo Digital possibilidade de encaminhar o processo para uma pessoa em específico dentro da fase em que o processo se encontra;
213 Possibilidade de anexar documentos dentro do processo digital;
214 No Processo Digital possibilidade de visualizar os dados do solicitante como, Nome, e-mail, telefone e CPF;
215 Permitir o usuário a visualizar o fluxo e identificar em qual etapa o mesmo se encontra;
216 Permitir ao usuário visualizar o histórico de fases do processo com todas as interações no mesmo;
217 Permitir no gerenciamento de processos a visualização dos processos que estão com o analista;
218 Permitir no gerenciamento de processos a visualização dos processos em etapas que o usuário participa e ainda não foram atribuídos a outro analista;
219 Como analista ter a possibilidade de consultar um ou vários processos por: (Códigos dos processos, nome de requerente, telefone ou e-mail do requerente);
220 No Processo Digital, depois de enviado o processo para análise, o requerente poderá ter permissão de alterar somente os formulários onde o analista deu o parecer de reprovado;
221 Permitir que o usuário selecione o lote para abrir o processo pelo mapa e trazer as informações de loteamento, quadra, número do lote, cadastro imobiliário e inscrição imobiliária do mesmo;
222 Permitir que o usuário insira anotações em documentos PDF anexados ao processo e ao salvá-lo criar uma cópia, sem sobrescrever o documento original.

XIX - Visualização do progresso do trabalho
223 Exibir os lotes participantes do processo de REURB pintados no mapa de acordo com a etapa ou fase em que se encontram;
224 Exibir dashboards personalizáveis que mostrem a situação em tempo real do trabalho.

XX - Planta Genérica de Valores
225 Deverá permitir o cadastro de amostras dos imóveis através do clique no mapa georreferenciado;
226 Deverá permitir o preenchimento das informações necessárias de cada amostra para o cálculo e homogeneização. (ex: Idade aparente, estado de conservação, tipologia, padrão do CUB, etc.);
227 O sistema deve permitir desenhar e definir os setores de cálculo e pólos valorizantes;
228 Deverá ter a possibilidade de inserir os valores básicos do CUB do mês de referência para cada tipologia, tipo de estrutura, padrão da construção, e coeficiente adotado;
229 O sistema deve permitir a inserção dos coeficientes para o cálculo de depreciação conforme o estado de conservação e idade aparente;
230 O sistema deve permitir a configuração da fórmula de homogeneização, os fatores e as informações do lote paradigma;
231 O sistema deve mostrar a equação encontrada, demonstrar no gráfico de regressão linear a distribuição das amostras conforme os valores e a distância ao pólo, contendo linha de tendência;
232 Deverá ser possível retirar as amostras espúrias e recalcular a equação;
233 O sistema deverá calcular à distância de cada face de quadra até o polo valorizante;
234 O sistema deverá calcular os valores das faces de quadra dentro de cada setor em relação ao seu polo valorizante de forma automática, com base na equação encontrada;
235 O sistema deverá mostrar de forma georreferenciada as faces de quadra com o respectivo valor calculado na PGV;
236 Emitir relatório com os valores das faces de quadra, contendo o código da seção, logradouro, e valor calculado;
237 O sistema deve permitir a simulação do cálculo do IPTU com os novos valores calculados na Planta Genérica de Valores;
238 Deverá permitir que o(a) usuário(a) defina os valores de alíquotas a serem utilizados;
239 Possibilidade de inserir o percentual do valor venal a ser utilizado no cálculo do IPTU;
240 Possibilidade de limitar o aumento do valor da simulação do IPTU (referente ao último valor lançado);
241 Ao fim da simulação deve ser realizado um comparativo entre o IPTU atual e IPTU simulado;
242 Apresentar ao fim da simulação uma tabela com o valor do IPTU anterior e o IPTU sugerido e a somatório dos valores;
243 Deverá possibilitar a parametrização da fórmula em tempo de execução.

XXI - Visualização de Nuvem de Pontos 3D
244 Visualização da nuvem de pontos decorrente do recobrimento aerofotogramétrico;
245 Visualização das coordenadas tridimensionais e o valor de intensidade dos pontos;
246 As nuvens de pontos deverão ser disponibilizadas em ambiente web integrado ao sistema de informações geográficas;
247 Permitir a navegação e interação (zoom, rotação, movimentações);
248 Disponibilizar ferramentas de medições (distâncias, área, volumes e cortes em seções da nuvem);
249 Personalização (ajustar cores, intensidade e filtro de classificação de pontos) e Marcadores e Anotações;
250 A manipulação em ambiente web deverá permitir a alternância de densificação da quantidade de pontos da nuvem de pontos, ângulo de visualização, seleção de qualidade da nuvem de pontos e determinação do tamanho minimo dos pontos.

XXII - Módulo de Cadastro Rural
251 Deverá permitir a manutenção (inserção, atualização e remoção), incluindo consultas e relatórios em formatos XLS, PDF, CSV e XML das seguintes entidades: Pontos de Interesse, Estradas Principais, Secundárias e Vicinais, Corpos e Cursos d'água, Localidades, Distritos Pontes Dados do Instituto Nacional de Colonização e Reforma Agrária (INCRA), Sistema de Gestão Fundiária (SIGEF) e Cadastro Ambiental Rural (CAR);
252 Devem possuir no mínimo campos como código único e incremental, endereço e data do cadastro;
253 Permitir que o usuário liste os registros dos pontos de interesse em forma de tabela e o sistema automaticamente posicione e identifique no mapa a localização geográfica, quando esta for selecionada na tabela;
254 Permitir que o usuário selecione no mapa um determinado item e o sistema a exiba automaticamente na tabela, para posterior edição ou visualização dos dados;
255 Possuir ferramenta de precisão (snap), no mínimo para fim de linha/polilinha ou ponto (endpoint) e meio de linha/polilinha (midpoint);
256 Possuir ferramentas de desenho: rotação, mover, espelhar, clonar, dividir e unir;
257 Possibilidade de adicionar/excluir linhas guia para auxiliar no desenho da geometria;
258 Possuir ferramenta de buffer (expandir ou contrair uma geometria paralelamente conforme o valor determinado pelo usuário);
259 Possibilidade de acrescentar camadas vetoriais ou raster para apoio nas operações cartográficas;
260 O sistema deverá possibilitar o desenho de linhas de forma ortogonal a partir de uma linha base.
