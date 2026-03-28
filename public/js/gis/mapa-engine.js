/**
 * SIGWEB - Engine Cartográfica (OpenLayers 8.2)
 * Arquivo isolado para não poluir o Blade. 
 * Não requer Vite, apenas carregue via asset() no Laravel.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. CARREGA AS CONFIGURAÇÕES INJETADAS PELO PHP
    const config = window.mapConfig || {};
    let zonasAtivas = [];

    // VARIÁVEIS DE ESTADO SOCIAL (Filtros do Mapa de Calor)
    let filtroRiscoAtivo = false;
    let filtroBeneficioAtivo = false;
    let filtroPcdAtivo = false;

    // 2. CONFIGURA A CÂMERA DO MAPA
    const view = new ol.View({
        center: ol.proj.fromLonLat([config.mapLon, config.mapLat]),
        zoom: config.mapZoom,
        maxZoom: 22
    });

    // 3. MAPAS BASE (Raster)
    const osmLayer = new ol.layer.Tile({ source: new ol.source.OSM(), zIndex: 0 });

    const esriLayer = new ol.layer.Tile({
        source: new ol.source.XYZ({
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            maxZoom: 18,
            crossOrigin: 'anonymous'
        }),
        visible: false,
        zIndex: 1
    });

    const ortofotoLayer = new ol.layer.Tile({
        source: new ol.source.XYZ({
            url: `/mapas/${config.tenantSlug}/{z}/{x}/{y}.png`, // Lê a variável do PHP
            minZoom: 12,
            maxZoom: 22,
            crossOrigin: 'anonymous'
        }),
        visible: false,
        zIndex: 2
    });

    // 4. ESTILOS DAS CAMADAS VETORIAIS
    const layerConfigs = {

        'perimetros': { z: 10, minZoom: 0, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#ef4444', width: 3 }), fill: new ol.style.Fill({ color: 'rgba(239, 68, 68, 0.05)' }) }) },

        'zonas': {
            z: 20,
            minZoom: 0,
            style: function (feature) {
                const sigla = feature.get('sigla');
                const rgbBruto = feature.get('rgb');
                if (!zonasAtivas.includes(sigla)) return null;
                const rgbLimpo = rgbBruto ? rgbBruto.replace(/[()]/g, '') : '150,150,150';

                return new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: `rgb(${rgbLimpo})`, width: 2, lineDash: [4, 4] }),
                    fill: new ol.style.Fill({ color: `rgba(${rgbLimpo}, 0.25)` }),
                    text: new ol.style.Text({
                        text: sigla, font: 'bold 14px Arial', fill: new ol.style.Fill({ color: '#333' }),
                        stroke: new ol.style.Stroke({ color: '#fff', width: 3 })
                    })
                });
            }
        },

        'bairros': {
            z: 30,
            minZoom: 0,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#3b82f6', width: 2 }),
                    fill: new ol.style.Fill({ color: 'rgba(59, 130, 246, 0.1)' })
                });

                // Aparece num zoom mais aberto (14) para não sumir a cidade inteira
                if (zoom >= 14) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : '',
                        font: 'bold 16px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#1e3a8a' }), // Azul muito escuro
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 4 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },

        'loteamentos': {
            z: 35, // Fica acima dos Bairros (30) e abaixo das Quadras (40)
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#2563eb', width: 3, lineDash: [8, 4] }), // Azul (Blue 600) tracejado
                    fill: new ol.style.Fill({ color: 'rgba(37, 99, 235, 0.1)' }) // Fundo azul bem clarinho
                });

                if (zoom >= 14) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : 'Loteamento',
                        font: 'bold 15px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#1e3a8a' }), // Azul escuro
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },

        'quadras': {
            z: 40,
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#f97316', width: 1 }),
                    fill: new ol.style.Fill({ color: 'rgba(249, 115, 22, 0.2)' })
                });

                // Aparece num zoom intermediário (16)
                if (zoom >= 16) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? 'Q ' + feature.get('name').toString() : '',
                        font: 'bold 14px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#9a3412' }), // Laranja escuro
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },

        'logradouros': { z: 50, minZoom: 14, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#3675ce', width: 3 }) }) },

        'postes': {
            z: 100,
            minZoom: 14,
            style: function (feature) {
                const condition = feature.get('structural_condition');
                const temChamado = feature.get('tem_chamado'); // 🟢 LÊ DO BANCO

                let fillColor = '#eab308'; // Amarelo (Padrão)
                if (condition === 'Bom') fillColor = '#22c55e'; // Verde
                if (condition === 'Ruim') fillColor = '#ef4444'; // Vermelho

                // 🛑 SOBRESCREVE SE ESTIVER EM MANUTENÇÃO (Roxo brilhante)
                if (temChamado) fillColor = '#d946ef';

                return new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: temChamado ? 8 : 6, // 🟢 Fica maior se tiver chamado
                        fill: new ol.style.Fill({ color: fillColor }),
                        stroke: new ol.style.Stroke({ color: temChamado ? '#000000' : '#ffffff', width: temChamado ? 3 : 2 })
                    })
                });
            }
        },

        'arvores': {
            z: 101,
            minZoom: 15,
            style: function (feature) {
                const condition = feature.get('phytosanitary_condition');
                const size = feature.get('size');
                const temChamado = feature.get('tem_chamado'); // 🟢 LÊ DO BANCO

                let radius = 6;
                if (size === 'pequeno') radius = 6;
                if (size === 'grande') radius = 8;
                if (temChamado) radius += 2; // 🟢 Cresce mais um pouco

                let fillColor = '#22c55e'; // Verde padrão
                if (condition === 'Regular') fillColor = '#eab308';
                if (condition === 'Ruim') fillColor = '#ef4444';
                if (condition === 'Morta') fillColor = '#6b7280';

                // 🛑 SOBRESCREVE SE ESTIVER EM MANUTENÇÃO (Roxo brilhante)
                if (temChamado) fillColor = '#d946ef';

                return new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: radius,
                        fill: new ol.style.Fill({ color: fillColor }),
                        stroke: new ol.style.Stroke({ color: temChamado ? '#000000' : '#ffffff', width: temChamado ? 3 : 2 })
                    })
                });
            }
        },

        'lotes': {
            z: 60,
            minZoom: 15.5,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);

                // --- 🛑 A MÁGICA SOCIAL COMEÇA AQUI ---
                // Verifica se a API mandou as variáveis e se o botão do painel está ligado!
                const isRisco = feature.get('social_risco') && filtroRiscoAtivo;
                const isBeneficio = feature.get('social_beneficio') && filtroBeneficioAtivo;
                const isPcd = feature.get('social_pcd') && filtroPcdAtivo;

                // Definimos as cores. A prioridade máxima é a Área de Risco (Vermelho).
                let strokeColor = '#10b981'; // Verde Padrão (Emerald)
                let fillColor = 'rgba(16, 185, 129, 0.15)';

                if (isRisco) {
                    strokeColor = '#e11d48'; // Vermelho (Rose)
                    fillColor = 'rgba(225, 29, 72, 0.6)'; // Vermelho forte
                } else if (isPcd) {
                    strokeColor = '#9333ea'; // Roxo (Purple)
                    fillColor = 'rgba(147, 51, 234, 0.5)';
                } else if (isBeneficio) {
                    strokeColor = '#f59e0b'; // Amarelo (Amber)
                    fillColor = 'rgba(245, 158, 11, 0.5)';
                }
                // --- 🛑 FIM DA MÁGICA ---

                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: strokeColor, width: isRisco ? 2 : 1 }), // Borda mais grossa se for risco
                    fill: new ol.style.Fill({ color: fillColor })
                });

                if (zoom >= 18) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : '',
                        font: 'bold 12px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: isRisco ? '#ffffff' : '#064e3b' }), // Se for risco, texto branco para dar contraste
                        stroke: new ol.style.Stroke({ color: isRisco ? '#9f1239' : '#ffffff', width: 3 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },

        'edificacoes': { z: 70, minZoom: 16, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#b45309', width: 1 }), fill: new ol.style.Fill({ color: 'rgba(180, 83, 9, 0.5)' }) }) },

        'cemiterios': {
            z: 25, // Fica entre a Zona e o Bairro
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#9333ea', width: 2 }), // Roxo
                    fill: new ol.style.Fill({ color: 'rgba(147, 51, 234, 0.2)' }) // Roxo transparente
                });
                // Mostra o nome do cemitério a partir do zoom 15
                if (zoom >= 15) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : 'Cemitério',
                        font: 'bold 14px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#581c87' }),
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },

        'quadras_cemiterio': {
            z: 26, // Z-index maior que o cemitério (25) para a quadra ficar por cima e ser clicável
            minZoom: 16,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#6366f1', width: 2, lineDash: [4, 4] }), // Borda Indigo tracejada
                    fill: new ol.style.Fill({ color: 'rgba(99, 102, 241, 0.3)' }) // Fundo Indigo transparente
                });

                // Texto só aparece com zoom bem próximo
                if (zoom >= 17) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : 'Quadra',
                        font: 'bold 13px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#312e81' }),
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },

        'logradouros_cemiterio': {
            z: 27,
            minZoom: 16,
            style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#64748b', width: 3 }) })
        },

        'jazigos': {
            z: 28,
            minZoom: 18, // Só aparece bem de perto para não poluir
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#57534e', width: 1 }), // Stone 600
                    fill: new ol.style.Fill({ color: 'rgba(87, 83, 78, 0.4)' })
                });

                if (zoom >= 19.5) { // Texto só no ultra-zoom
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : 'Jazigo',
                        font: 'bold 11px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#1c1917' }),
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },

        'setores_fiscais': {
            z: 22, // Fica abaixo dos lotes mas acima dos bairros
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#f59e0b', width: 3, lineDash: [8, 8] }), // Laranja tracejado forte
                    fill: new ol.style.Fill({ color: 'rgba(245, 158, 11, 0.15)' })
                });

                if (zoom >= 14) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : 'Setor Fiscal',
                        font: 'bold 15px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#78350f' }),
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },

        // Dentro de const layerConfigs = { ... }
        'rural-localidades': {
            z: 15, // Acima do mapa base, abaixo dos lotes urbanos
            minZoom: 0,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: '#57534e', // Stone 600
                        width: 2,
                        lineDash: [4, 4]
                    }),
                    fill: new ol.style.Fill({
                        color: 'rgba(120, 113, 108, 0.2)' // Stone 500 translúcido
                    })
                });

                if (zoom >= 13) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : '',
                        font: 'bold 13px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#292524' }),
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },

        'rural-propriedades': {
            z: 16, // Fica uma camada ACIMA das localidades para ser clicável
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#f59e0b', width: 2 }), // Amber 500
                    fill: new ol.style.Fill({ color: 'rgba(245, 158, 11, 0.2)' }) // Fundo transparente
                });

                if (zoom >= 13) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : '',
                        font: 'bold 12px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#78350f' }),
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },

        'rural-estradas': {
            z: 17, // Acima das localidades, abaixo dos lotes
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const pavimento = feature.get('tipo_pavimento');

                let strokeColor = '#78350f'; // Marrom (Terra)
                let lineDash = [];

                if (pavimento === 'Asfalto') strokeColor = '#374151'; // Cinza Escuro (Asfalto)
                else if (pavimento === 'Cascalho') lineDash = [4, 4]; // Tracejado

                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: strokeColor,
                        width: 4,
                        lineDash: lineDash
                    })
                });

                if (zoom >= 14) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : '',
                        font: 'bold 12px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: strokeColor }),
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        placement: 'line', // 🛑 MÁGICA: O texto faz a curva junto com a estrada!
                        textBaseline: 'bottom',
                        offsetY: -5
                    }));
                }
                return style;
            }
        },

        'rural-hidrografias': {
            z: 17, // Abaixo das localidades e lotes, para a água ficar por baixo
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const geomType = feature.getGeometry().getType();
                let style;

                if (geomType === 'Point' || geomType === 'MultiPoint') {
                    style = new ol.style.Style({ image: new ol.style.Circle({ radius: 6, fill: new ol.style.Fill({ color: '#0ea5e9' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) });
                } else if (geomType === 'LineString' || geomType === 'MultiLineString') {
                    style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#0ea5e9', width: 3 }) });
                } else { // Polygon
                    style = new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#0284c7', width: 2 }),
                        fill: new ol.style.Fill({ color: 'rgba(14, 165, 233, 0.4)' })
                    });
                }

                if (zoom >= 14 && feature.get('name')) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name').toString(),
                        font: 'bold 12px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#0c4a6e' }), // Azul muito escuro
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        placement: (geomType === 'LineString' || geomType === 'MultiLineString') ? 'line' : 'point',
                        offsetY: (geomType === 'Point' || geomType === 'MultiPoint') ? -15 : 0
                    }));
                }
                return style;
            }
        },

        'rural-pontes': {
            z: 110, // Super alto para ficar por cima da água e da estrada
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const estado = feature.get('estado_conservacao');

                // Muda a cor da borda se estiver interditada ou ruim!
                let borderColor = '#f59e0b'; // Amber (Padrão)
                if (estado === 'Ruim') borderColor = '#ef4444'; // Vermelho
                else if (estado === 'Interditada') borderColor = '#000000'; // Preto

                const style = new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 6,
                        fill: new ol.style.Fill({ color: '#78350f' }), // Marrom Madeira Escuro
                        stroke: new ol.style.Stroke({ color: borderColor, width: 2 })
                    })
                });

                if (zoom >= 15) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : 'Ponte',
                        font: 'bold 12px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#451a03' }), // Marrom Quase Preto
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        offsetY: -15
                    }));
                }
                return style;
            }
        },

        'rural-pontos-interesse': {
            z: 120, // O mais alto de todos os pontos rurais
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const categoria = feature.get('categoria');

                console.log(categoria)

                let dotColor = '#14b8a6'; // Teal (Padrão/Outros)
                if (categoria === 'Escola') dotColor = '#3b82f6'; // Azul
                else if (categoria === 'Saúde') dotColor = '#ef4444'; // Vermelho
                else if (categoria === 'Igreja') dotColor = '#a855f7'; // Roxo
                else if (categoria === 'Turismo') dotColor = '#f59e0b'; // Laranja
                else if (categoria === 'Comércio') dotColor = '#84cc16'; // Verde Lima

                const style = new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 6,
                        fill: new ol.style.Fill({ color: dotColor }),
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
                    })
                });

                if (zoom >= 14) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : 'PoI',
                        font: 'bold 12px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#1c1917' }),
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        offsetY: -15
                    }));
                }
                return style;
            }
        },

    };

    // 5. INICIA O MAPA
    const map = new ol.Map({ target: 'sigweb-map', layers: [osmLayer, esriLayer, ortofotoLayer], view: view });

    // Desativa o zoom de duplo clique para facilitar edições
    const dblClickZoom = map.getInteractions().getArray().find(i => i instanceof ol.interaction.DoubleClickZoom);
    if (dblClickZoom) map.removeInteraction(dblClickZoom);

    window.window.loadedLayers = {};

    // 6. EVENTOS DE SATÉLITE
    let showSat = false;
    const btnSatelite = document.getElementById('btn-satelite');
    const sateliteText = document.getElementById('satelite-text');
    if (btnSatelite) {
        btnSatelite.addEventListener('click', () => {
            showSat = !showSat;
            osmLayer.setVisible(!showSat);
            esriLayer.setVisible(showSat);
            ortofotoLayer.setVisible(showSat);
            if (showSat) {
                btnSatelite.classList.add('bg-primary-50', 'text-primary-600', 'dark:bg-primary-900/20', 'dark:text-primary-400');
                btnSatelite.classList.remove('text-gray-600', 'dark:text-gray-300');
                if (sateliteText) sateliteText.innerText = 'Mapa Open';
            } else {
                btnSatelite.classList.remove('bg-primary-50', 'text-primary-600', 'dark:bg-primary-900/20', 'dark:text-primary-400');
                btnSatelite.classList.add('text-gray-600', 'dark:text-gray-300');
                if (sateliteText) sateliteText.innerText = 'Satélite';
            }
        });
    }

    // 7. CARREGAMENTO DE CAMADAS (API AJAX)
    const fetchAndDrawLayer = (layerName, checkboxElement) => {
        if (window.window.loadedLayers[layerName]) {
            window.window.loadedLayers[layerName].setVisible(true);
            return;
        }

        const textSpan = checkboxElement.nextElementSibling.querySelector('.layer-text');
        let originalText = '';
        if (textSpan) {
            originalText = textSpan.innerHTML;
            textSpan.innerHTML = 'Carregando...';
            textSpan.classList.add('animate-pulse', 'text-primary-500');
        }

        // Lê o ID do tenant vindo da variável PHP lá de cima
        fetch(`/api/gis-data?tenant_id=${config.tenantId}&layer=${layerName}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.features && data.features.length > 0) {
                    const parsedFeatures = new ol.format.GeoJSON().readFeatures(data, { featureProjection: 'EPSG:3857' });

                    // 🛑 CARIMBO OBRIGATÓRIO: Garante que o feature saiba de qual camada ele é, para o clique funcionar!
                    parsedFeatures.forEach(f => f.set('layer', layerName));

                    const vectorSource = new ol.source.Vector({
                        features: parsedFeatures
                    });
                    const vectorLayer = new ol.layer.Vector({
                        source: vectorSource,
                        style: layerConfigs[layerName].style,
                        zIndex: layerConfigs[layerName].z,
                        minZoom: layerConfigs[layerName].minZoom
                    });
                    map.addLayer(vectorLayer);
                    window.window.loadedLayers[layerName] = vectorLayer;
                }
            })
            .catch(err => console.error(`Erro ao carregar ${layerName}:`, err))
            .finally(() => {
                if (textSpan) {
                    textSpan.innerHTML = originalText;
                    textSpan.classList.remove('animate-pulse', 'text-primary-500');
                }
            });
    };

    document.querySelectorAll('.layer-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const layerName = this.getAttribute('data-layer');
            if (this.checked) fetchAndDrawLayer(layerName, this);
            else if (window.window.loadedLayers[layerName]) window.window.loadedLayers[layerName].setVisible(false);
        });
        if (checkbox.checked) fetchAndDrawLayer(checkbox.getAttribute('data-layer'), checkbox);
    });

    document.querySelectorAll('.zona-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const sigla = this.getAttribute('data-zona-sigla');
            if (this.checked) {
                if (!zonasAtivas.includes(sigla)) zonasAtivas.push(sigla);
            } else {
                zonasAtivas = zonasAtivas.filter(s => s !== sigla);
            }

            if (!window.window.loadedLayers['zonas']) {
                fetchAndDrawLayer('zonas', this);
            } else {
                window.window.loadedLayers['zonas'].changed();
                window.window.loadedLayers['zonas'].setVisible(zonasAtivas.length > 0);
            }
        });
    });

    // 8. INTERFACE E ARRASTO DO PAINEL DE CAMADAS
    const panel = document.getElementById("layers-panel");
    const btnToggleLayers = document.getElementById("btn-toggle-layers");
    btnToggleLayers.addEventListener('click', () => panel.classList.toggle('hidden'));

    function dragElement(elmnt) {
        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
        const header = document.getElementById(elmnt.id + "-header");
        header.onmousedown = dragMouseDown;

        function dragMouseDown(e) {
            e.preventDefault();
            pos3 = e.clientX;
            pos4 = e.clientY;
            document.onmouseup = closeDragElement;
            document.onmousemove = elementDrag;
            elmnt.classList.add('dragging-now');
        }

        function elementDrag(e) {
            e.preventDefault();
            pos1 = pos3 - e.clientX;
            pos2 = pos4 - e.clientY;
            pos3 = e.clientX;
            pos4 = e.clientY;

            requestAnimationFrame(() => {
                let newTop = elmnt.offsetTop - pos2;
                let newLeft = elmnt.offsetLeft - pos1;
                if (newTop < 10) newTop = 10;
                if (newLeft < 10) newLeft = 10;
                if (newTop > window.innerHeight - elmnt.clientHeight - 10) newTop = window.innerHeight - elmnt.clientHeight - 10;
                if (newLeft > window.innerWidth - elmnt.clientWidth - 10) newLeft = window.innerWidth - elmnt.clientWidth - 10;
                elmnt.style.top = newTop + "px";
                elmnt.style.left = newLeft + "px";
            });
        }

        function closeDragElement() {
            document.onmouseup = null;
            document.onmousemove = null;
            elmnt.classList.remove('dragging-now');
        }
    }
    dragElement(panel);

    // 9. VOO DA BUSCA E CLIQUE NO MAPA
    window.addEventListener('voar-para-lote', (e) => {
        const data = e.detail;
        if (data && data.coords) {
            const targetCoords = ol.proj.fromLonLat([data.coords[0], data.coords[1]]);
            view.animate({ center: targetCoords, zoom: 20, duration: 2000 });
        }
    });

    const featureTooltip = document.getElementById('feature-tooltip');
    let hoveredFeature = null;

    map.on('pointermove', function (e) {

        // 🛑 A TRAVA MESTRA: Se estiver editando geometria, desliga o hover na hora!
        if (featureEmEdicao) {
            if (hoveredFeature) {
                hoveredFeature.setStyle(undefined); // Limpa o hover atual
                hoveredFeature = null;
            }
            if (featureTooltip) featureTooltip.style.display = 'none'; // Esconde o texto
            return; // 🛑 Cancela a execução do resto do evento de hover!
        }

        // 1. Limpa o efeito do último elemento que passamos o mouse
        if (hoveredFeature) {
            hoveredFeature.setStyle(undefined); // Devolve a cor original
            hoveredFeature = null;
        }

        const feature = map.forEachFeatureAtPixel(e.pixel, feature => feature, { hitTolerance: 5 });

        // 2. Define quais camadas ganham a "mãozinha" (pointer) ao passar o mouse
        const hoverableLayers = ['lotes', 'edificacao_ativa', 'logradouros', 'bairros', 'loteamentos', 'quadras', 'postes', 'arvores', 'cemiterios', 'quadras_cemiterio', 'logradouros_cemiterio', 'setores_fiscais', 'rural-localidades', 'rural-propriedades', 'rural-estradas', 'rural-hidrografias', 'rural-pontes', 'rural-pontos-interesse'];
        const isHoverable = feature && hoverableLayers.includes(feature.get('layer'));
        map.getTargetElement().style.cursor = isHoverable ? 'pointer' : '';

        // 3. Aplica o efeito de Hover
        if (feature) {
            const layer = feature.get('layer');
            const name = feature.get('name') ? feature.get('name').toString() : '';
            const zoom = view.getZoom(); // Pega o zoom atual para respeitar as regras de texto

            if (hoverableLayers.includes(layer)) {
                hoveredFeature = feature;
            }

            if (layer === 'logradouros' || layer === 'logradouros_cemiterio') {
                feature.setStyle(new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: layer === 'logradouros' ? '#38bdf8' : '#94a3b8', width: 6 })
                }));

                if (featureTooltip) {
                    featureTooltip.innerHTML = name || 'Rua Interna sem nome';
                    featureTooltip.style.display = 'block';
                    featureTooltip.style.left = e.originalEvent.clientX + 'px';
                    featureTooltip.style.top = e.originalEvent.clientY + 'px';
                }

            } else {
                // Esconde o tooltip de rua se estivermos em outra coisa
                if (featureTooltip) featureTooltip.style.display = 'none';

                // EFEITO DE "ACENDER" (Mais opacidade no fundo e Texto Branco)
                if (layer === 'bairros') {
                    feature.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#3b82f6', width: 3 }),
                        fill: new ol.style.Fill({ color: 'rgba(59, 130, 246, 0.4)' }), // Fundo mais forte
                        text: zoom >= 14 ? new ol.style.Text({
                            text: name,
                            font: 'bold 16px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }), // Texto Branco
                            stroke: new ol.style.Stroke({ color: '#1e3a8a', width: 4 }), // Borda azul escura
                            overflow: true
                        }) : null
                    }));

                } else if (layer === 'quadras') {
                    feature.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#f97316', width: 2 }),
                        fill: new ol.style.Fill({ color: 'rgba(249, 115, 22, 0.5)' }), // Fundo mais forte
                        text: zoom >= 16 ? new ol.style.Text({
                            text: name ? 'Q ' + name : '',
                            font: 'bold 14px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }), // Texto Branco
                            stroke: new ol.style.Stroke({ color: '#9a3412', width: 3 }),
                            overflow: true
                        }) : null
                    }));
                } else if (layer === 'lotes') {
                    feature.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#0ea5e9', width: 2 }),
                        fill: new ol.style.Fill({ color: 'rgba(14, 165, 233, 0.4)' }), // Fundo mais forte
                        text: zoom >= 18 ? new ol.style.Text({
                            text: name,
                            font: 'bold 12px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }), // Texto Branco
                            stroke: new ol.style.Stroke({ color: '#0369a1', width: 3 }),
                            overflow: true
                        }) : null
                    }));

                } else if (layer === 'postes') {
                    const condition = feature.get('structural_condition');
                    const seqId = feature.get('sequential_id');
                    const temChamado = feature.get('tem_chamado'); // 🟢

                    const textoBase = seqId ? `Poste #${seqId}` : 'Poste S/N';
                    const textoHover = temChamado ? `🛠️ ${textoBase} (Em Manutenção)` : textoBase;

                    let fillColor = '#eab308';
                    if (condition === 'Bom') fillColor = '#22c55e';
                    if (condition === 'Ruim') fillColor = '#ef4444';
                    if (temChamado) fillColor = '#d946ef'; // 🛑

                    feature.setStyle(new ol.style.Style({
                        image: new ol.style.Circle({
                            radius: temChamado ? 10 : 9, // Cresce no hover
                            fill: new ol.style.Fill({ color: fillColor }),
                            stroke: new ol.style.Stroke({ color: temChamado ? '#000000' : '#ffffff', width: 3 })
                        }),
                        text: zoom >= 18 ? new ol.style.Text({
                            text: textoHover,
                            offsetY: -16,
                            font: 'bold 12px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }),
                            stroke: new ol.style.Stroke({ color: '#000000', width: 3 }),
                            overflow: true
                        }) : null
                    }));

                } else if (layer === 'arvores') {
                    const condition = feature.get('phytosanitary_condition');
                    const seqId = feature.get('sequential_id');
                    const nameStr = feature.get('name');
                    const temChamado = feature.get('tem_chamado'); // 🟢

                    const especie = nameStr && nameStr !== 'S/N' ? nameStr : 'Não Identificada';
                    const textoBase = seqId ? `Árvore #${seqId} - ${especie}` : `Árvore - ${especie}`;
                    const textoHover = temChamado ? `🛠️ ${textoBase} (Em Manutenção)` : textoBase;

                    let fillColor = '#22c55e';
                    if (condition === 'Regular') fillColor = '#eab308';
                    if (condition === 'Ruim') fillColor = '#ef4444';
                    if (condition === 'Morta') fillColor = '#6b7280';
                    if (temChamado) fillColor = '#d946ef'; // 🛑

                    feature.setStyle(new ol.style.Style({
                        image: new ol.style.Circle({
                            radius: temChamado ? 11 : 10,
                            fill: new ol.style.Fill({ color: fillColor }),
                            stroke: new ol.style.Stroke({ color: temChamado ? '#000000' : '#ffffff', width: 3 })
                        }),
                        text: zoom >= 18 ? new ol.style.Text({
                            text: textoHover,
                            offsetY: -16,
                            font: 'bold 12px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }),
                            stroke: new ol.style.Stroke({ color: '#000000', width: 3 }),
                            overflow: true
                        }) : null
                    }));


                } else if (layer === 'cemiterios') {
                    feature.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#a855f7', width: 3 }), // Roxo mais brilhante
                        fill: new ol.style.Fill({ color: 'rgba(147, 51, 234, 0.4)' }), // Fundo mais escuro
                        text: zoom >= 15 ? new ol.style.Text({
                            text: name,
                            font: 'bold 15px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }), // Letra branca
                            stroke: new ol.style.Stroke({ color: '#581c87', width: 4 }),
                            overflow: true
                        }) : null
                    }));

                } else if (layer === 'quadras_cemiterio') {
                    feature.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#818cf8', width: 3 }),
                        fill: new ol.style.Fill({ color: 'rgba(99, 102, 241, 0.5)' }),
                        text: zoom >= 17 ? new ol.style.Text({
                            text: name, font: 'bold 14px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }),
                            stroke: new ol.style.Stroke({ color: '#3730a3', width: 3 })
                        }) : null
                    }));

                } else if (layer === 'jazigos') {
                    feature.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#78716c', width: 2 }),
                        fill: new ol.style.Fill({ color: 'rgba(87, 83, 78, 0.7)' }),
                        text: zoom >= 19.5 ? new ol.style.Text({
                            text: name, font: 'bold 12px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }),
                            stroke: new ol.style.Stroke({ color: '#292524', width: 3 })
                        }) : null
                    }));

                } else if (layer === 'rural-localidades') {
                    feature.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#57534e', width: 4 }), // Stone 600 mais grosso
                        fill: new ol.style.Fill({ color: 'rgba(120, 113, 108, 0.5)' }), // Fundo mais opaco
                        text: zoom >= 13 ? new ol.style.Text({
                            text: name,
                            font: 'bold 14px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }),
                            stroke: new ol.style.Stroke({ color: '#292524', width: 3 }),
                            overflow: true
                        }) : null
                    }));

                } else if (layer === 'rural-propriedades') {
                    feature.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#d97706', width: 3 }), // Borda mais forte
                        fill: new ol.style.Fill({ color: 'rgba(245, 158, 11, 0.5)' }), // Fundo aceso
                        text: zoom >= 14 ? new ol.style.Text({
                            text: name,
                            font: 'bold 14px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }), // Texto branco
                            stroke: new ol.style.Stroke({ color: '#92400e', width: 3 }),
                            overflow: true
                        }) : null
                    }));

                } else if (layer === 'rural-estradas') {
                    feature.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#f59e0b', width: 6 }), // Acende Laranja Forte
                        text: zoom >= 14 ? new ol.style.Text({
                            text: name,
                            font: 'bold 14px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#b45309' }),
                            stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                            placement: 'line',
                            textBaseline: 'bottom',
                            offsetY: -5
                        }) : null
                    }));

                } else if (layer === 'rural-hidrografias') {
                    const geomType = feature.getGeometry().getType();
                    let hoverStyle;
                    if (geomType === 'Point' || geomType === 'MultiPoint') {
                        hoverStyle = new ol.style.Style({ image: new ol.style.Circle({ radius: 9, fill: new ol.style.Fill({ color: '#38bdf8' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }) }) });
                    } else if (geomType === 'LineString' || geomType === 'MultiLineString') {
                        hoverStyle = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#38bdf8', width: 5 }) });
                    } else {
                        hoverStyle = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#0284c7', width: 3 }), fill: new ol.style.Fill({ color: 'rgba(56, 189, 248, 0.6)' }) });
                    }

                    if (zoom >= 14 && name) {
                        hoverStyle.setText(new ol.style.Text({
                            text: name, font: 'bold 14px Arial, sans-serif', fill: new ol.style.Fill({ color: '#082f49' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                            placement: (geomType === 'LineString' || geomType === 'MultiLineString') ? 'line' : 'point',
                            offsetY: (geomType === 'Point' || geomType === 'MultiPoint') ? -18 : 0
                        }));
                    }
                    feature.setStyle(hoverStyle);

                } else if (layer === 'rural-pontes') {
                    feature.setStyle(new ol.style.Style({
                        image: new ol.style.Circle({
                            radius: 9, // Cresce a bolinha
                            fill: new ol.style.Fill({ color: '#b45309' }), // Marrom mais claro/vivo
                            stroke: new ol.style.Stroke({ color: '#fbbf24', width: 3 }) // Borda Amarela
                        }),
                        text: zoom >= 14 ? new ol.style.Text({
                            text: name,
                            font: 'bold 14px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#78350f' }),
                            stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                            offsetY: -18
                        }) : null
                    }));

                } else if (layer === 'rural-pontos-interesse') {
                    const cat = feature.get('categoria');
                    let hoverColor = '#0f766e'; // Teal escuro padrão
                    if (cat === 'Escola') hoverColor = '#1d4ed8';
                    else if (cat === 'Saúde') hoverColor = '#b91c1c';
                    else if (cat === 'Igreja') hoverColor = '#7e22ce';
                    else if (cat === 'Turismo') hoverColor = '#b45309';
                    else if (cat === 'Comércio') hoverColor = '#4d7c0f';

                    feature.setStyle(new ol.style.Style({
                        image: new ol.style.Circle({
                            radius: 10,
                            fill: new ol.style.Fill({ color: hoverColor }),
                            stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 })
                        }),
                        text: zoom >= 13 ? new ol.style.Text({
                            text: `${name} (${cat})`,
                            font: 'bold 14px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: hoverColor }),
                            stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                            offsetY: -18
                        }) : null
                    }));

                } else if (layer === 'loteamentos') {
                    feature.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#1d4ed8', width: 4, lineDash: [8, 4] }), // Azul mais forte
                        fill: new ol.style.Fill({ color: 'rgba(37, 99, 235, 0.3)' }), // Fundo mais visível
                        text: zoom >= 14 ? new ol.style.Text({
                            text: name,
                            font: 'bold 16px Arial, sans-serif',
                            fill: new ol.style.Fill({ color: '#ffffff' }), // Texto Branco
                            stroke: new ol.style.Stroke({ color: '#1e40af', width: 4 }), // Borda azul escura
                            overflow: true
                        }) : null
                    }));

                }

            }


        } else {
            // Limpa tudo se clicar fora
            if (featureTooltip) featureTooltip.style.display = 'none';
        }
    });

    map.on('singleclick', function (evt) {

        // 🛑 INTERCEPTADOR DA NUMERAÇÃO PREDIAL (NOVO FLUXO: DESENHAR TRAJETO)
        if (activeTool.startsWith('numeracao')) {
            if (activeTool === 'numeracao_step1') {
                const features = map.getFeaturesAtPixel(evt.pixel, { hitTolerance: 5 });

                const clickedLogradouro = features ? features.find(f => f.get('layer') === 'logradouros') : null;
                if (clickedLogradouro) {
                    ruaSelecionadaNumeracao = clickedLogradouro;
                    activeTool = 'numeracao_step2';

                    alert(`✅ Rua "${clickedLogradouro.get('name')}" selecionada!\n\n2️⃣ PASSO 2: Agora DESENHE O TRAJETO da numeração.\nClique no ponto inicial e vá clicando para contornar a rua.\nDê DOIS CLIQUES Rápidos para finalizar o percurso.`);

                    map.getTargetElement().style.cursor = 'crosshair';

                    // 🛑 Liga a ferramenta de desenhar Linha na tela!
                    currentDrawInteraction = new ol.interaction.Draw({
                        source: drawSource,
                        type: 'LineString',
                        style: new ol.style.Style({
                            stroke: new ol.style.Stroke({ color: '#eab308', width: 5, lineDash: [4, 4] }) // Linha amarela tracejada
                        })
                    });

                    currentDrawInteraction.on('drawend', function (e) {
                        // Quando terminar os dois cliques, pega o GeoJSON do trajeto
                        const drawnGeoJson = formatGeoJSON.writeGeometryObject(e.feature.getGeometry());

                        setTimeout(() => drawSource.clear(), 500);
                        map.removeInteraction(currentDrawInteraction);
                        window.resetToPan(); // Devolve a mãozinha azul

                        // Dispara para o PHP mandando a linha inteira desenhada!
                        Livewire.dispatch('abrirModalNumeracao', {
                            logradouro_id: ruaSelecionadaNumeracao.get('id'),
                            logradouro_nome: ruaSelecionadaNumeracao.get('name'),
                            drawn_line: drawnGeoJson
                        });
                    });

                    map.addInteraction(currentDrawInteraction);
                } else {
                    alert("❌ Você não clicou em uma rua. Aproxime o zoom e clique na linha colorida do logradouro.");
                }
            }
            return; // Impede que faça outras coisas no mapa
        }

        // 🛑 INTERCEPTADOR DE UNIFICAÇÃO (PASSO 1 E 2)
        if (activeTool.startsWith('unificar')) {
            const features = map.getFeaturesAtPixel(evt.pixel, { hitTolerance: 5 });
            const clickedLote = features ? features.find(f => f.get('layer') === 'lotes') : null;

            if (clickedLote) {
                const id = clickedLote.get('id');
                const highlightFeature = clickedLote.clone(); // Clona o desenho para pintar de roxo

                if (activeTool === 'unificar_step1') {
                    lotePrincipalId = id;
                    unificacaoSource.addFeature(highlightFeature);
                    activeTool = 'unificar_step2';
                    alert("✅ Lote Principal selecionado!\n\nPASSO 2: Agora clique no LOTE VIZINHO que será anexado/absorvido.");

                } else if (activeTool === 'unificar_step2') {
                    if (id === lotePrincipalId) {
                        alert("⚠️ Você clicou no mesmo lote! Clique no lote VIZINHO.");
                        return;
                    }

                    loteSecundarioId = id;
                    unificacaoSource.addFeature(highlightFeature);

                    // Limpa a tela e volta o mouse ao normal
                    setTimeout(() => unificacaoSource.clear(), 1000);
                    window.resetToPan();

                    // Dispara a Mágica no Livewire!
                    Livewire.dispatch('processarUnificacaoLotes', {
                        lotePrincipalId: lotePrincipalId,
                        loteSecundarioId: loteSecundarioId
                    });
                }
            } else {
                alert("❌ Clique dentro de um Lote válido.");
            }
            return; // Impede a ficha de abrir
        }

        if (activeTool !== 'pan') return;
        const features = map.getFeaturesAtPixel(evt.pixel, { hitTolerance: 5 });

        if (features && features.length > 0) {

            // 🛑 HIERARQUIA INTELIGENTE DE CLIQUES: Quem estiver mais no topo da lista "rouba" o clique!
            const clickPriority = [
                'edificacao_ativa', // Modo edição ganha de tudo
                'postes', 'arvores', 'rural-pontos-interesse', 'rural-pontes', // PONTOS (Maior prioridade)
                'logradouros', 'logradouros_cemiterio', 'rural-estradas', // LINHAS
                'jazigos', // Polígono Micro
                'rural-hidrografias', // Pode ser misto, prioridade alta
                'lotes', 'rural-propriedades', // Polígonos Pequenos
                'quadras_cemiterio', 'quadras', // Polígonos Médios
                'cemiterios', 'setores_fiscais', // Polígonos Grandes
                'bairros', 'loteamentos', 'rural-localidades' // Polígonos Gigantes (Menor prioridade)
            ];

            let clickedFeature = null;
            let clickedLayer = null;

            // Varre as entidades sob o mouse na ordem de prioridade definida acima
            for (const layerName of clickPriority) {
                const found = features.find(f => f.get('layer') === layerName);
                if (found) {
                    clickedFeature = found;
                    clickedLayer = layerName;
                    break; // Achou o mais prioritário? PARA A BUSCA NA HORA!
                }
            }

            if (clickedFeature) {
                const id = clickedFeature.get('id');

                // Envia a ação dependendo da camada que ganhou a prioridade
                switch (clickedLayer) {
                    case 'edificacao_ativa': Livewire.dispatch('abrirOpcoesEdificacao', { id: id }); break;
                    case 'postes': Livewire.dispatch('abrirOpcoesPoste', { id: id }); break;
                    case 'arvores': Livewire.dispatch('abrirOpcoesArvore', { id: id }); break;
                    case 'rural-pontos-interesse': Livewire.dispatch('abrirOpcoesRuralPontoInteresse', { id: id }); break;
                    case 'rural-pontes': Livewire.dispatch('abrirOpcoesRuralPonte', { id: id }); break;
                    case 'logradouros': Livewire.dispatch('abrirOpcoesLogradouro', { id: id }); break;
                    case 'logradouros_cemiterio': Livewire.dispatch('abrirOpcoesLogradouroCemiterio', { id: id }); break;
                    case 'rural-estradas': Livewire.dispatch('abrirOpcoesRuralEstrada', { id: id }); break;
                    case 'jazigos': Livewire.dispatch('abrirOpcoesJazigo', { id: id }); break;
                    case 'rural-hidrografias': Livewire.dispatch('abrirOpcoesRuralHidrografia', { id: id }); break;
                    case 'rural-propriedades': Livewire.dispatch('abrirOpcoesRuralPropriedade', { id: id }); break;
                    case 'quadras_cemiterio': Livewire.dispatch('abrirOpcoesQuadraCemiterio', { id: id }); break;
                    case 'quadras': Livewire.dispatch('abrirOpcoesQuadra', { id: id }); break;
                    case 'cemiterios': Livewire.dispatch('abrirOpcoesCemiterio', { id: id }); break;
                    case 'setores_fiscais': Livewire.dispatch('abrirOpcoesSetorFiscal', { id: id }); break;
                    case 'bairros': Livewire.dispatch('abrirOpcoesBairro', { id: id }); break;
                    case 'loteamentos': Livewire.dispatch('abrirOpcoesLoteamento', { id: id }); break;
                    case 'rural-localidades': Livewire.dispatch('abrirOpcoesRuralLocalidade', { id: id }); break;

                    case 'lotes':
                        if (featureEmEdicao) {
                            if (featureEmEdicao.get('id') != id) window.cancelarEdicaoGeometria();
                            else return;
                        }
                        const loteNome = clickedFeature.get('name') || 'S/N';
                        Livewire.dispatch('abrirFichaImovel', { loteId: id, loteNome: loteNome });
                        break;
                }

                return; // 🛑 FUNDAMENTAL: Encerra o evento aqui para não abrir modais empilhadas!
            }

        } else {
            if (featureEmEdicao) window.cancelarEdicaoGeometria();
        }

    });// Fim do map.on('singleclick')

    // 10. MEDIÇÕES E RASCUNHOS
    const measureTooltipElement = document.getElementById('measure-tooltip');
    const measureOverlay = new ol.Overlay({ element: measureTooltipElement, offset: [0, -15], positioning: 'bottom-center' });
    map.addOverlay(measureOverlay);

    const drawSource = new ol.source.Vector();
    const drawLayer = new ol.layer.Vector({
        source: drawSource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({ color: '#ef4444', width: 3, lineDash: [5, 5] }),
            fill: new ol.style.Fill({ color: 'rgba(239, 68, 68, 0.2)' })
        }),
        zIndex: 9999
    });
    map.addLayer(drawLayer);

    let currentMeasureInteraction = null;
    let currentDrawInteraction = null;
    let currentSnapInteraction = null;
    let activeTool = 'pan';

    // 11. DESENHO DE ARTEFATOS (FUNÇÃO GLOBAL PARA O HTML)
    let currentDrawEntity = null;
    const formatGeoJSON = new ol.format.GeoJSON({ featureProjection: 'EPSG:3857', dataProjection: 'EPSG:4326' });

    window.enableDrawing = function (entityType) {

        // 1. Limpa qualquer régua ou mãozinha ativa
        if (typeof window.resetToPan === 'function') window.resetToPan();

        activeTool = 'draw';
        currentDrawEntity = entityType;

        // 2. Apaga a cor azul da mãozinha visualmente
        const btnPan = document.getElementById('btn-pan');
        if (btnPan) {
            btnPan.classList.remove('bg-primary-100', 'text-primary-600', 'dark:bg-primary-900/30', 'dark:text-primary-400');
            btnPan.classList.add('hover:bg-gray-100', 'text-gray-600', 'dark:hover:bg-gray-700', 'dark:text-gray-300');
        }

        // 3. Limpa interações antigas
        if (currentMeasureInteraction) map.removeInteraction(currentMeasureInteraction);
        if (currentDrawInteraction) map.removeInteraction(currentDrawInteraction);
        if (currentSnapInteraction) map.removeInteraction(currentSnapInteraction);

        drawSource.clear();
        measureTooltipElement.style.display = 'none';

        let geometryType = 'Polygon';

        //point
        if (['arvore', 'poste', 'rural_hidro_ponto', 'rural_ponte', 'rural_ponto_interesse'].includes(entityType)) geometryType = 'Point';

        //linestring
        if (['logradouro', 'logradouro_cemiterio', 'rural_estrada', 'rural_hidro_linha'].includes(entityType)) geometryType = 'LineString';
        // Se for 'rural_hidro_poligono', ele cai no padrão Polygon!

        map.getTargetElement().style.cursor = 'crosshair';

        currentDrawInteraction = new ol.interaction.Draw({
            source: drawSource,
            type: geometryType,
            style: new ol.style.Style({
                stroke: new ol.style.Stroke({ color: '#3b82f6', width: 3, lineDash: [5, 5] }),
                fill: new ol.style.Fill({ color: 'rgba(59, 130, 246, 0.2)' }),
                image: new ol.style.Circle({ radius: 6, fill: new ol.style.Fill({ color: '#3b82f6' }) })
            })
        });

        currentDrawInteraction.on('drawend', function (e) {
            const geoJson = formatGeoJSON.writeFeatureObject(e.feature);
            setTimeout(() => drawSource.clear(), 500);
            map.getTargetElement().style.cursor = '';
            map.removeInteraction(currentDrawInteraction);
            if (currentSnapInteraction) map.removeInteraction(currentSnapInteraction);

            // Devolve a cor azul pra mãozinha ao terminar
            activeTool = 'pan';
            if (btnPan) {
                btnPan.classList.add('bg-primary-100', 'text-primary-600', 'dark:bg-primary-900/30', 'dark:text-primary-400');
                btnPan.classList.remove('hover:bg-gray-100', 'text-gray-600', 'dark:hover:bg-gray-700', 'dark:text-gray-300');
            }

            Livewire.dispatch('abrirModalCriacao', { entityType: currentDrawEntity, geoJson: geoJson.geometry });
        });

        map.addInteraction(currentDrawInteraction);

        if (['lote', 'edificacao'].includes(entityType) && window.window.loadedLayers['lotes']) {
            currentSnapInteraction = new ol.interaction.Snap({
                source: window.window.loadedLayers['lotes'].getSource(),
                pixelTolerance: 10
            });
            map.addInteraction(currentSnapInteraction);
        }

        // NOVO: ÍMÃ PARA OS SETORES FISCAIS (Cola as bordas perfeitamente)
        if (entityType === 'setor_fiscal' && window.window.loadedLayers['setores_fiscais']) {
            currentSnapInteraction = new ol.interaction.Snap({
                source: window.window.loadedLayers['setores_fiscais'].getSource(),
                pixelTolerance: 15 // Ímã um pouco mais forte para grandes escalas
            });
            map.addInteraction(currentSnapInteraction);
        }

        if (entityType === 'rural_localidade' && window.loadedLayers['rural-localidades']) {
            currentSnapInteraction = new ol.interaction.Snap({
                source: window.loadedLayers['rural-localidades'].getSource(),
                pixelTolerance: 15
            });
            map.addInteraction(currentSnapInteraction);
        }
    };

    window.addEventListener('limpar-rascunho-mapa', () => { if (drawSource) drawSource.clear(); });

    // 12. ATUALIZAÇÕES VISUAIS DO LIVEWIRE
    window.addEventListener('adicionar-lote-mapa', (e) => {
        const data = e.detail[0] || e.detail;
        if (drawSource) drawSource.clear();
        const checkbox = document.querySelector('input[data-layer="lotes"]');
        if (checkbox && checkbox.checked && window.loadedLayers['lotes']) {
            const feature = new ol.Feature({
                geometry: new ol.format.GeoJSON().readGeometry(data.geo, { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }),
                id: data.id, name: data.numero_lote, layer: 'lotes'
            });
            window.loadedLayers['lotes'].getSource().addFeature(feature);
        }
    });

    window.addEventListener('atualizar-label-lote', (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers['lotes']) {
            const feature = window.loadedLayers['lotes'].getSource().getFeatures().find(f => f.get('id') == data.id);
            if (feature) { feature.set('name', data.numero_lote); feature.changed(); }
        }
    });

    window.addEventListener('remover-lote-mapa', (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers['lotes']) {
            const source = window.loadedLayers['lotes'].getSource();
            const feature = source.getFeatures().find(f => f.get('id') == data.id);
            if (feature) source.removeFeature(feature);
        }
    });

    // 13. EDIÇÃO DE GEOMETRIA (MODIFICAR)
    let currentModifyInteraction = null;
    let editSnapInteraction = null;
    let featureEmEdicao = null;
    let geometriaOriginal = null;

    // OUVINTES
    window.addEventListener('iniciar-edicao-geometria', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['lotes']) return;
        const source = window.loadedLayers['lotes'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#10b981' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-edificacao', (e) => {
        const data = e.detail[0] || e.detail;
        if (!edifAtivasSource) return;
        featureEmEdicao = edifAtivasSource.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#ea580c' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: edifAtivasSource, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-logradouro', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['logradouros']) return;
        const source = window.loadedLayers['logradouros'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#38bdf8' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-poste', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['postes']) return;
        const source = window.loadedLayers['postes'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 7,
                        fill: new ol.style.Fill({ color: '#eab308' }), // Amarelo enquanto arrasta
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
                    })
                })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    // ARRASTAR ÁRVORE
    window.addEventListener('iniciar-edicao-geometria-arvore', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['arvores']) return;
        const source = window.loadedLayers['arvores'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 8, fill: new ol.style.Fill({ color: '#22c55e' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    // BAIRRO
    window.addEventListener('iniciar-edicao-geometria-bairro', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['bairros']) return;
        const source = window.loadedLayers['bairros'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#3b82f6' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    // LOTEAMENTOS
    window.addEventListener('iniciar-edicao-geometria-loteamento', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['loteamentos']) return;
        const source = window.loadedLayers['loteamentos'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({
                    image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#2563eb' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) })
                })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-quadra', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['quadras']) return;
        const source = window.loadedLayers['quadras'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({
                    image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#f97316' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) })
                }) // Bolinha Laranja para combinar com a quadra!
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    // 🛑 INJEÇÃO 2: Habilitar modo arraste para Cemitério
    window.addEventListener('iniciar-edicao-geometria-cemiterio', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['cemiterios']) return;
        const source = window.loadedLayers['cemiterios'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 7,
                        fill: new ol.style.Fill({ color: '#9333ea' }), // Bolinha Roxa
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
                    })
                })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-quadra_cemiterio', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['quadras_cemiterio']) return;
        const source = window.loadedLayers['quadras_cemiterio'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#6366f1' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-logradouro_cemiterio', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['logradouros_cemiterio']) return;
        const source = window.loadedLayers['logradouros_cemiterio'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#64748b' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-jazigo', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['jazigos']) return;
        const source = window.loadedLayers['jazigos'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 6, fill: new ol.style.Fill({ color: '#57534e' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-setor_fiscal', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['setores_fiscais']) return;
        const source = window.loadedLayers['setores_fiscais'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#f59e0b' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-rural_localidade', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['rural-localidades']) return;

        const source = window.loadedLayers['rural-localidades'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 7,
                        fill: new ol.style.Fill({ color: '#57534e' }), // Cor Stone-600 para manter o padrão
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
                    })
                })
            });

            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);

            // Dispara a barra flutuante animada lá em cima
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-rural_propriedade', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['rural-propriedades']) return;

        const source = window.loadedLayers['rural-propriedades'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 7,
                        fill: new ol.style.Fill({ color: '#57534e' }), // Stone-600
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
                    })
                })
            });

            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);

            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-rural_estrada', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['rural-estradas']) return;

        const source = window.loadedLayers['rural-estradas'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({
                    image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#78350f' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) })
                })
            });

            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);

            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-rural_hidrografia', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['rural-hidrografias']) return;

        const source = window.loadedLayers['rural-hidrografias'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 7, fill: new ol.style.Fill({ color: '#0ea5e9' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-rural_ponte', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['rural-pontes']) return;

        const source = window.loadedLayers['rural-pontes'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({
                    image: new ol.style.Circle({ radius: 8, fill: new ol.style.Fill({ color: '#f59e0b' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) })
                })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    window.addEventListener('iniciar-edicao-geometria-rural_ponto_interesse', (e) => {
        const data = e.detail[0] || e.detail;
        if (!window.loadedLayers['rural-pontos-interesse']) return;

        const source = window.loadedLayers['rural-pontos-interesse'].getSource();
        featureEmEdicao = source.getFeatures().find(f => f.get('id') == data.id);

        if (featureEmEdicao) {
            geometriaOriginal = featureEmEdicao.getGeometry().clone();
            currentModifyInteraction = new ol.interaction.Modify({
                features: new ol.Collection([featureEmEdicao]),
                style: new ol.style.Style({ image: new ol.style.Circle({ radius: 8, fill: new ol.style.Fill({ color: '#14b8a6' }), stroke: new ol.style.Stroke({ color: '#000000', width: 2 }) }) })
            });
            editSnapInteraction = new ol.interaction.Snap({ source: source, pixelTolerance: 10 });
            map.addInteraction(currentModifyInteraction);
            map.addInteraction(editSnapInteraction);
            window.dispatchEvent(new CustomEvent('iniciar-edicao', { detail: { id: data.id } }));
        }
    });

    // SALVAR GEOMETRIA PARA TODOS
    window.salvarEdicaoGeometria = function () {
        if (featureEmEdicao) {
            const geoJson = formatGeoJSON.writeGeometryObject(featureEmEdicao.getGeometry());
            const id = featureEmEdicao.get('id');
            const layerName = featureEmEdicao.get('layer');

            window._featureBackup = featureEmEdicao;
            window._geometriaBackup = geometriaOriginal;

            if (layerName === 'lotes') Livewire.dispatch('salvarNovaGeometria', { id: id, geoJson: geoJson });
            else if (layerName === 'edificacao_ativa') Livewire.dispatch('salvarNovaGeometriaEdificacao', { id: id, geoJson: geoJson });
            // Adicionado o despacho para salvar o Logradouro:
            else if (layerName === 'logradouros') Livewire.dispatch('salvarNovaGeometriaLogradouro', { id: id, geoJson: geoJson });

            // Disparo para salvar a nova posição do Poste
            else if (layerName === 'postes') Livewire.dispatch('salvarNovaGeometriaPoste', { id: id, geoJson: geoJson });

            // Disparo para salvar a nova posição da Árvore
            else if (layerName === 'arvores') Livewire.dispatch('salvarNovaGeometriaArvore', { id: id, geoJson: geoJson });

            else if (layerName === 'bairros') Livewire.dispatch('salvarNovaGeometriaBairro', { id: id, geoJson: geoJson });

            else if (layerName === 'loteamentos') Livewire.dispatch('salvarNovaGeometriaLoteamento', { id: id, geoJson: geoJson });

            else if (layerName === 'quadras') Livewire.dispatch('salvarNovaGeometriaQuadra', { id: id, geoJson: geoJson });

            // 🛑 INJEÇÃO 3: Disparo para salvar o Cemitério
            else if (layerName === 'cemiterios') Livewire.dispatch('salvarNovaGeometriaCemiterio', { id: id, geoJson: geoJson });

            else if (layerName === 'quadras_cemiterio') Livewire.dispatch('salvarNovaGeometriaQuadraCemiterio', { id: id, geoJson: geoJson });

            else if (layerName === 'logradouros_cemiterio') Livewire.dispatch('salvarNovaGeometriaLogradouroCemiterio', { id: id, geoJson: geoJson });

            else if (layerName === 'jazigos') Livewire.dispatch('salvarNovaGeometriaJazigo', { id: id, geoJson: geoJson });

            else if (layerName === 'setores_fiscais') Livewire.dispatch('salvarNovaGeometriaSetorFiscal', { id: id, geoJson: geoJson });

            else if (layerName === 'rural-localidades') Livewire.dispatch('salvarNovaGeometriaRuralLocalidade', { id: id, geoJson: geoJson });

            else if (layerName === 'rural-propriedades') Livewire.dispatch('salvarNovaGeometriaRuralPropriedade', { id: id, geoJson: geoJson });

            else if (layerName === 'rural-estradas') Livewire.dispatch('salvarNovaGeometriaRuralEstrada', { id: id, geoJson: geoJson });

            else if (layerName === 'rural-hidrografias') Livewire.dispatch('salvarNovaGeometriaRuralHidrografia', { id: id, geoJson: geoJson });

            else if (layerName === 'rural-pontes') Livewire.dispatch('salvarNovaGeometriaRuralPonte', { id: id, geoJson: geoJson });

            else if (layerName === 'rural-pontos-interesse') Livewire.dispatch('salvarNovaGeometriaRuralPontoInteresse', { id: id, geoJson: geoJson });

            encerrarModoEdicao();
        }
    };

    window.cancelarEdicaoGeometria = function () {
        if (featureEmEdicao && geometriaOriginal) {
            featureEmEdicao.setGeometry(geometriaOriginal);
            featureEmEdicao.changed();
        }
        encerrarModoEdicao();
    };

    window.addEventListener('desfazer-edicao-geometria', () => {
        if (window._featureBackup && window._geometriaBackup) {
            window._featureBackup.setGeometry(window._geometriaBackup);
            window._featureBackup.changed();
            window._featureBackup = null; window._geometriaBackup = null;
        }
    });

    window.addEventListener('fechar-modal-filament', () => {
        // O delay de 150ms é o segredo! Ele simula um clique humano logo após o Livewire processar a ação,
        // garantindo que a animação do Alpine.js termine em paz e leve o fundo escuro embora.
        setTimeout(() => {
            const closeBtn = document.querySelector('.fi-modal-close-btn');
            if (closeBtn) {
                closeBtn.click();
            }
        }, 150);
    });

    function encerrarModoEdicao() {
        if (currentModifyInteraction) map.removeInteraction(currentModifyInteraction);
        if (editSnapInteraction) map.removeInteraction(editSnapInteraction);
        currentModifyInteraction = null; editSnapInteraction = null; featureEmEdicao = null; geometriaOriginal = null;
        window.dispatchEvent(new Event('encerrar-edicao'));
    }

    // 14. CAMADA TEMPORÁRIA DE EDIFICAÇÕES (Laranja)
    const edifAtivasSource = new ol.source.Vector();
    const edifAtivasLayer = new ol.layer.Vector({
        source: edifAtivasSource,
        style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#ea580c', width: 3 }), fill: new ol.style.Fill({ color: 'rgba(234, 88, 12, 0.5)' }) }),
        zIndex: 9999
    });
    map.addLayer(edifAtivasLayer);

    window.addEventListener('mostrar-edificacoes-lote', (e) => {
        const edificacoes = (e.detail && e.detail.edificacoes) ? e.detail.edificacoes : (e.detail[0] || e.detail);
        edifAtivasSource.clear();
        if (edificacoes && Array.isArray(edificacoes) && edificacoes.length > 0) {
            const features = [];
            edificacoes.forEach(edif => {
                if (edif.geo) {
                    try {
                        const feature = new ol.Feature({
                            geometry: new ol.format.GeoJSON().readGeometry(edif.geo, { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }),
                            id: edif.id, layer: 'edificacao_ativa'
                        });
                        features.push(feature);
                    } catch (err) { console.error("Erro pintar edificação", err); }
                }
            });
            edifAtivasSource.addFeatures(features);
        }
    });

    window.addEventListener('esconder-edificacoes-lote', () => edifAtivasSource.clear());

    // 15. FERRAMENTAS DE MEDIÇÃO E NAVEGAÇÃO
    const btnPan = document.getElementById('btn-pan');
    const btnMeasureLine = document.getElementById('btn-measure-line');
    const btnMeasureArea = document.getElementById('btn-measure-area');

    const btnToolNumeracao = document.getElementById('btn-tool-numeracao');
    let ruaSelecionadaNumeracao = null;

    if (btnToolNumeracao) {
        btnToolNumeracao.addEventListener('click', function () {
            window.resetToPan();
            activeTool = 'numeracao_step1';
            ruaSelecionadaNumeracao = null;
            map.getTargetElement().style.cursor = 'help';
            alert("1️⃣ PASSO 1: Clique na RUA (Linha) que você deseja numerar.");
        });
    }

    // CAMADA VISUAL PARA A UNIFICAÇÃO (Roxa)
    const unificacaoSource = new ol.source.Vector();
    const unificacaoLayer = new ol.layer.Vector({
        source: unificacaoSource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({ color: '#9333ea', width: 4 }), // Roxo brilhante
            fill: new ol.style.Fill({ color: 'rgba(147, 51, 234, 0.4)' })
        }),
        zIndex: 10001
    });
    map.addLayer(unificacaoLayer);

    let lotePrincipalId = null;
    let loteSecundarioId = null;

    const btnToolUnificar = document.getElementById('btn-tool-unificar');
    if (btnToolUnificar) {
        btnToolUnificar.addEventListener('click', function () {
            window.resetToPan();
            activeTool = 'unificar_step1';
            lotePrincipalId = null;
            loteSecundarioId = null;
            map.getTargetElement().style.cursor = 'crosshair';
            alert("🔗 MODO UNIFICAÇÃO ATIVADO\n\nPASSO 1: Clique no LOTE PRINCIPAL (Este é o lote que vai 'absorver' o vizinho e herdar as edificações).");
        });
    }

    window.resetToPan = function () {
        if (currentMeasureInteraction) map.removeInteraction(currentMeasureInteraction);
        if (currentDrawInteraction) map.removeInteraction(currentDrawInteraction);
        if (currentSnapInteraction) map.removeInteraction(currentSnapInteraction);

        unificacaoSource.clear();

        drawSource.clear();
        measureTooltipElement.style.display = 'none';
        activeTool = 'pan';
        map.getTargetElement().style.cursor = ''; // Volta para a mãozinha

        if (btnPan) {
            btnPan.classList.add('bg-primary-100', 'text-primary-600', 'dark:bg-primary-900/30', 'dark:text-primary-400');
            btnPan.classList.remove('hover:bg-gray-100', 'text-gray-600', 'dark:hover:bg-gray-700', 'dark:text-gray-300');
        }
        if (btnMeasureLine) {
            btnMeasureLine.classList.remove('bg-primary-100', 'text-primary-600', 'dark:bg-primary-900/30', 'dark:text-primary-400');
            btnMeasureLine.classList.add('hover:bg-gray-100', 'text-gray-600', 'dark:hover:bg-gray-700', 'dark:text-gray-300');
        }
        if (btnMeasureArea) {
            btnMeasureArea.classList.remove('bg-primary-100', 'text-primary-600', 'dark:bg-primary-900/30', 'dark:text-primary-400');
            btnMeasureArea.classList.add('hover:bg-gray-100', 'text-gray-600', 'dark:hover:bg-gray-700', 'dark:text-gray-300');
        }
    };

    if (btnPan) btnPan.addEventListener('click', window.resetToPan);

    // 🛑 FERRAMENTA DE PERFIL ALTIMÉTRICO (GOOGLE ELEVATION)
    const btnToolAltimetria = document.getElementById('btn-tool-altimetria');
    if (btnToolAltimetria) {
        btnToolAltimetria.addEventListener('click', function () {
            window.resetToPan();
            activeTool = 'altimetria';

            map.getTargetElement().style.cursor = 'crosshair';
            alert("📈 MODO ALTIMETRIA: Desenhe o trajeto no mapa.\nClique para fazer curvas ao longo da rua e dê DOIS CLIQUES RÁPIDOS para finalizar e gerar o gráfico.");

            currentDrawInteraction = new ol.interaction.Draw({
                source: drawSource,
                type: 'LineString',
                style: new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#10b981', width: 4, lineDash: [4, 4] }) // Linha Verde Tracejada
                })
            });

            currentDrawInteraction.on('drawend', function (e) {
                const geometry = e.feature.getGeometry();

                // Converte as coordenadas do formato do Mapa (3857) para GPS padrão do Google (4326)
                const coords4326 = geometry.clone().transform('EPSG:3857', 'EPSG:4326').getCoordinates();

                setTimeout(() => drawSource.clear(), 500);
                map.removeInteraction(currentDrawInteraction);
                window.resetToPan();

                // Dispara o evento pro Livewire mandando o array de coordenadas
                Livewire.dispatch('gerarPerfilAltimetrico', { coords: coords4326 });
            });

            map.addInteraction(currentDrawInteraction);
        });
    }

    function enableMeasurement(type, buttonElement) {
        window.resetToPan();
        activeTool = type;

        if (btnPan) {
            btnPan.classList.remove('bg-primary-100', 'text-primary-600', 'dark:bg-primary-900/30', 'dark:text-primary-400');
            btnPan.classList.add('hover:bg-gray-100', 'text-gray-600', 'dark:hover:bg-gray-700', 'dark:text-gray-300');
        }
        buttonElement.classList.add('bg-primary-100', 'text-primary-600', 'dark:bg-primary-900/30', 'dark:text-primary-400');
        buttonElement.classList.remove('hover:bg-gray-100', 'text-gray-600', 'dark:hover:bg-gray-700', 'dark:text-gray-300');

        map.getTargetElement().style.cursor = 'crosshair';

        currentMeasureInteraction = new ol.interaction.Draw({
            source: drawSource,
            type: type === 'line' ? 'LineString' : 'Polygon',
            style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#fff', width: 2, lineDash: [5, 5] }) })
        });

        currentMeasureInteraction.on('drawstart', function () { drawSource.clear(); measureTooltipElement.style.display = 'block'; });
        currentMeasureInteraction.on('drawend', function (e) {
            const geom = e.feature.getGeometry();
            const output = type === 'line' ? (ol.sphere.getLength(geom)).toFixed(2) + ' m' : (ol.sphere.getArea(geom)).toFixed(2) + ' m²';
            measureTooltipElement.innerHTML = output;
            const position = type === 'line' ? geom.getLastCoordinate() : geom.getInteriorPoint().getCoordinates();
            measureOverlay.setPosition(position);
            map.removeInteraction(currentMeasureInteraction);
        });
        map.addInteraction(currentMeasureInteraction);
    }

    if (btnMeasureLine) btnMeasureLine.addEventListener('click', function () { enableMeasurement('line', this); });
    if (btnMeasureArea) btnMeasureArea.addEventListener('click', function () { enableMeasurement('area', this); });


    // =========================================================================
    // CIRURGIA EM MEMÓRIA: CEMITÉRIOS
    // =========================================================================

    // Adiciona apenas o novo Cemitério ao terminar de desenhar
    window.addEventListener('adicionar-cemiterio-mapa', (e) => {
        const data = e.detail[0] || e.detail;
        if (drawSource) drawSource.clear();
        const checkbox = document.querySelector('input[data-layer="cemiterios"]');

        if (checkbox && checkbox.checked && window.loadedLayers['cemiterios']) {
            const feature = new ol.Feature({
                geometry: new ol.format.GeoJSON().readGeometry(data.geo, { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }),
                id: data.id,
                name: data.name,
                layer: 'cemiterios' // Essencial para o clique e hover funcionarem
            });
            window.loadedLayers['cemiterios'].getSource().addFeature(feature);
        }
    });

    // Atualiza só o texto do hover se alterar o nome no banco
    window.addEventListener('atualizar-label-cemiterio', (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers['cemiterios']) {
            const feature = window.loadedLayers['cemiterios'].getSource().getFeatures().find(f => f.get('id') == data.id);
            if (feature) {
                feature.set('name', data.name);
                feature.changed(); // Força a re-renderização visual do polígono
            }
        }
    });

    // Arranca o polígono do mapa sem precisar baixar os outros do banco
    window.addEventListener('remover-cemiterio-mapa', (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers['cemiterios']) {
            const source = window.loadedLayers['cemiterios'].getSource();
            const feature = source.getFeatures().find(f => f.get('id') == data.id);
            if (feature) source.removeFeature(feature);
        }
    });

    // =========================================================================
    // CIRURGIA EM MEMÓRIA: LOGRADOUROS, POSTES E ÁRVORES ETC...
    // =========================================================================

    const entidadesSurgical = [
        { layer: 'logradouros', singular: 'logradouro' },
        { layer: 'postes', singular: 'poste' },
        { layer: 'arvores', singular: 'arvore' },
        { layer: 'bairros', singular: 'bairro' },
        { layer: 'loteamentos', singular: 'loteamento' },
        { layer: 'quadras_cemiterio', singular: 'quadra_cemiterio' },
        { layer: 'logradouros_cemiterio', singular: 'logradouro_cemiterio' },
        { layer: 'jazigos', singular: 'jazigo' },
        { layer: 'setores_fiscais', singular: 'setor_fiscal' },
        { layer: 'rural-localidades', singular: 'rural_localidade' },
        { layer: 'rural-propriedades', singular: 'rural_propriedade' },
        { layer: 'rural-estradas', singular: 'rural_estrada' },
        { layer: 'rural-hidrografias', singular: 'rural_hidrografia' },
        { layer: 'rural-pontes', singular: 'rural_ponte' },
        { layer: 'rural-pontos-interesse', singular: 'rural_ponto_interesse' },
        { layer: 'quadras', singular: 'quadra' },
    ];

    entidadesSurgical.forEach(entidade => {
        // 1. Adicionar no Mapa (Após Criar)
        window.addEventListener(`adicionar-${entidade.singular}-mapa`, (e) => {
            const data = e.detail[0] || e.detail;
            if (drawSource) drawSource.clear();
            const checkbox = document.querySelector(`input[data-layer="${entidade.layer}"]`);

            if (checkbox && checkbox.checked && window.loadedLayers[entidade.layer]) {
                const feature = new ol.Feature({
                    geometry: new ol.format.GeoJSON().readGeometry(data.geo, { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }),
                    id: data.id,
                    name: data.name || '',
                    layer: entidade.layer
                });
                window.loadedLayers[entidade.layer].getSource().addFeature(feature);
            }
        });

        // 2. Atualizar Label (Após Editar Nome)
        window.addEventListener(`atualizar-label-${entidade.singular}`, (e) => {
            const data = e.detail[0] || e.detail;
            if (window.loadedLayers[entidade.layer]) {
                const feature = window.loadedLayers[entidade.layer].getSource().getFeatures().find(f => f.get('id') == data.id);
                if (feature) {
                    feature.set('name', data.name);
                    feature.changed();
                }
            }
        });

        // 3. Remover do Mapa (Após Excluir)
        window.addEventListener(`remover-${entidade.singular}-mapa`, (e) => {
            const data = e.detail[0] || e.detail;
            if (window.loadedLayers[entidade.layer]) {
                const source = window.loadedLayers[entidade.layer].getSource();
                const feature = source.getFeatures().find(f => f.get('id') == data.id);
                if (feature) source.removeFeature(feature);
            }
        });

        // 4. Atualizar Status de Manutenção (Ficar Roxo no Mapa)
        window.addEventListener(`atualizar-manutencao-${entidade.singular}`, (e) => {
            const data = e.detail[0] || e.detail;
            if (window.loadedLayers[entidade.layer]) {
                const feature = window.loadedLayers[entidade.layer].getSource().getFeatures().find(f => f.get('id') == data.id);
                if (feature) {
                    // Injeta a propriedade "tem_chamado" no cache do OpenLayers e força redesenhar
                    feature.set('tem_chamado', data.tem_chamado);
                    feature.changed();
                }
            }
        });

    });

    // =========================================================================
    // 17. INTERCEPTADOR DE CRIAÇÃO GEOGRÁFICA (PONTOS E DESENHOS VIA URL)
    // =========================================================================
    const urlParams = new URLSearchParams(window.location.search);
    const actionParams = urlParams.get('action');
    const layerParams = urlParams.get('layer');

    // 🛑 1. MODO DE PONTOS (Árvores, Postes e Ponto de Nascente - Redireciona com Lat/Lon)
    if (actionParams === 'create' && (layerParams === 'postes' || layerParams === 'arvores' || layerParams === 'rural_hidro_ponto')) {

        const entityName = layerParams === 'postes' ? 'Poste' : 'Árvore';

        console.log(`Modo de inserção de ${entityName} ativado!`);
        alert(`📍 Clique no mapa exatamente onde o(a) novo(a) ${entityName} está localizado(a).`);

        const drawPoint = new ol.interaction.Draw({ type: 'Point' });
        map.addInteraction(drawPoint);
        map.getTargetElement().style.cursor = 'crosshair';

        drawPoint.on('drawend', function (event) {
            map.removeInteraction(drawPoint);
            map.getTargetElement().style.cursor = '';

            const geometry = event.feature.getGeometry();
            const coords4326 = ol.proj.transform(geometry.getCoordinates(), 'EPSG:3857', 'EPSG:4326');

            const lon = coords4326[0].toFixed(8);
            const lat = coords4326[1].toFixed(8);

            window.location.href = `/app/${config.tenantSlug}/${layerParams}/create?lat=${lat}&lon=${lon}`;
        });
    }

    // 🛑 2. MODO DE DESENHO (Polígonos e Linhas - Abre modal nativa do mapa)
    // 🛑 2. MODO DE DESENHO (Polígonos e Linhas - Abre modal nativa do mapa)
    else if (actionParams === 'create') {

        let drawKey = layerParams;

        // 🛑 TRUQUE CAMALEÃO: A hidrografia pergunta qual é a forma geométrica antes!
        if (layerParams === 'rural-hidrografias') {
            const opcao = prompt(
                "🌊 Múltiplas formas detectadas para Hidrografia.\nO que você deseja desenhar no mapa?\n\n1 - Rio / Córrego (Linha)\n2 - Lago / Represa (Polígono)\n3 - Nascente (Ponto)\n\nDigite o número da opção (1, 2 ou 3):"
            );

            if (opcao === '1') drawKey = 'rural_hidro_linha';
            else if (opcao === '2') drawKey = 'rural_hidro_poligono';
            else if (opcao === '3') drawKey = 'rural_hidro_ponto';
            else return; // Se o usuário cancelar ou digitar errado, aborta a ação!
        }

        // Dicionário inteligente de entidades que desenham na tela
        const drawableEntities = {
            'lotes': { label: 'do novo Lote', func: 'lote' },
            'logradouros': { label: 'da linha do novo Logradouro', func: 'logradouro' },
            'bairros': { label: 'do novo Bairro', func: 'bairro' },
            'loteamentos': { label: 'do novo Loteamento', func: 'loteamento' },
            'quadras': { label: 'da nova Quadra', func: 'quadra' },

            'edificacoes': { label: 'da nova Edificação', func: 'edificacao' },
            'cemiterios': { label: 'do novo Cemitério', func: 'cemiterio' },
            'quadras_cemiterio': { label: 'da nova Quadra', func: 'quadra_cemiterio' },
            'logradouros_cemiterio': { label: 'da nova Rua Interna', func: 'logradouro_cemiterio' },
            'jazigos': { label: 'do novo Jazigo', func: 'jazigo' },
            'setores_fiscais': { label: 'do novo Setor Fiscal', func: 'setor_fiscal' },
            'rural-localidades': { label: 'da nova Localidade Rural', func: 'rural_localidade' },
            'rural-propriedades': { label: 'da nova Propriedade Rural', func: 'rural_propriedade' },
            'rural-estradas': { label: 'da nova Estrada Rural', func: 'rural_estrada' },
            'rural-pontes': { label: 'da nova Ponte', func: 'rural_ponte' },
            'rural-pontos-interesse': { label: 'do novo Ponto de Interesse', func: 'rural_ponto_interesse' },

            // 👇 As 3 formas da Hidrografia mapeadas aqui para a ferramenta certa
            'rural_hidro_linha': { label: 'do novo Rio/Córrego (Linha)', func: 'rural_hidro_linha' },
            'rural_hidro_poligono': { label: 'do novo Lago/Represa (Polígono)', func: 'rural_hidro_poligono' },
            'rural_hidro_ponto': { label: 'da nova Nascente (Ponto)', func: 'rural_hidro_ponto' },
        };

        if (drawableEntities[drawKey]) {
            let labelEnt = drawableEntities[drawKey].label;
            let funcEnt = drawableEntities[drawKey].func;

            console.log(`Modo de inserção de ${funcEnt} ativado via Backoffice!`);

            setTimeout(() => {
                // Removemos o alert para não encher a tela de popups se já usou o Prompt
                if (layerParams !== 'rural-hidrografias') {
                    alert(`🗺️ Desenhe a geometria ${labelEnt} no mapa.`);
                }

                // Usa o layerParams original para achar o checkbox e ligar a camada azulzinha!
                const checkbox = document.querySelector(`input[data-layer="${layerParams}"]`);
                if (checkbox && !checkbox.checked) {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new Event('change'));
                }

                // Dispara a ferramenta de desenho!
                if (typeof window.enableDrawing === 'function') {
                    window.enableDrawing(funcEnt);
                }
            }, 800);
        }
    }

    // =========================================================================
    // 19. VOO DIRETO VIA URL (VER NO MAPA)
    // =========================================================================
    const focusLat = urlParams.get('focus_lat');
    const focusLon = urlParams.get('focus_lon');
    const targetLayer = urlParams.get('layer');

    const focusZoom = urlParams.get('zoom') ? parseInt(urlParams.get('zoom')) : 20;

    if (focusLat && focusLon) {
        // 1. Liga a camada no menu lateral automaticamente se ela existir
        if (targetLayer) {
            const checkbox = document.querySelector(`input[data-layer="${targetLayer}"]`);
            if (checkbox && !checkbox.checked) {
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change')); // Força o carregamento no banco

                // Abre a sanfona correspondente no menu de camadas para mostrar que ativou
                // O painel de postes fica na infraestrutura, então se for postes, abre ela.
                if (targetLayer === 'postes') {
                    // Aqui disparamos o Alpine para abrir a sanfona de Infraestrutura
                    const infraButton = document.querySelector('button[x-on\\:click*="infra"]');
                    if (infraButton) infraButton.click();
                }
            }
        }

        // 2. Faz o voo cinematográfico para o local exato
        setTimeout(() => {
            const coords = ol.proj.fromLonLat([parseFloat(focusLon), parseFloat(focusLat)]);
            view.animate({
                center: coords,
                zoom: focusZoom, // Zoom bem fechado para ver a rua e o poste de perto
                duration: 2500 // 2.5 segundos de animação suave
            });
        }, 800); // Um delay de 800ms para dar tempo do mapa renderizar o DOM e carregar os blocos
    }

    // =========================================================================
    // MOTOR DE PRÉVIA DE NUMERAÇÃO PREDIAL
    // =========================================================================

    const previewNumSource = new ol.source.Vector();
    const previewNumLayer = new ol.layer.Vector({
        source: previewNumSource,
        style: function (feature) {
            return new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 14,
                    fill: new ol.style.Fill({ color: '#2563eb' }), // Azul Blue-600
                    stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
                }),
                text: new ol.style.Text({
                    text: feature.get('numero').toString(),
                    font: 'bold 12px Arial, sans-serif',
                    fill: new ol.style.Fill({ color: '#ffffff' }),
                    offsetY: 0
                })
            });
        },
        zIndex: 10000 // Fica acima de TUDO no mapa
    });
    map.addLayer(previewNumLayer);

    window.addEventListener('mostrar-preview-numeracao', (e) => {
        const lotes = e.detail[0] || e.detail.dados || e.detail;
        previewNumSource.clear();

        if (lotes && Array.isArray(lotes)) {
            const features = [];
            lotes.forEach(lote => {
                if (lote.geo) {
                    try {
                        const feature = new ol.Feature({
                            geometry: new ol.format.GeoJSON().readGeometry(lote.geo, { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }),
                            numero: lote.novo_numero
                        });
                        features.push(feature);
                    } catch (err) { console.error("Erro no JSON da numeração", err); }
                }
            });
            previewNumSource.addFeatures(features);
        }
    });

    window.addEventListener('limpar-preview-numeracao', () => {
        previewNumSource.clear();
    });

    // =========================================================================
    // FERRAMENTA DE DESMEMBRAMENTO DE LOTES (A TESOURA)
    // =========================================================================
    let loteParaDesmembrarId = null;

    window.ativarFerramentaCorteLote = function (loteId) {
        window.resetToPan(); // Limpa qualquer outra ferramenta ativa
        activeTool = 'cortar_lote';
        loteParaDesmembrarId = loteId;

        // Fecha a ficha lateral suavemente para dar espaço na tela
        const livewireComponent = Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
        if (livewireComponent) livewireComponent.fecharFicha();

        alert("✂️ MODO DESMEMBRAMENTO ATIVADO\n\n1. Clique fora do lote para iniciar a linha de corte.\n2. Atravesse o lote.\n3. Dê dois cliques fora do lote do outro lado para finalizar o corte.");

        map.getTargetElement().style.cursor = 'crosshair';

        // Cria a interação de desenhar Linha (LineString) cor Laranja
        currentDrawInteraction = new ol.interaction.Draw({
            source: drawSource,
            type: 'LineString',
            style: new ol.style.Style({
                stroke: new ol.style.Stroke({ color: '#ea580c', width: 4, lineDash: [5, 5] }) // Laranja tracejado
            })
        });

        currentDrawInteraction.on('drawend', function (e) {
            const linhaDeCorteGeoJson = formatGeoJSON.writeGeometryObject(e.feature.getGeometry());

            // Limpa o mapa e devolve a mãozinha
            setTimeout(() => drawSource.clear(), 500);
            map.removeInteraction(currentDrawInteraction);
            window.resetToPan();

            // Manda a linha desenhada para a "Mágica do PostGIS" no Livewire
            Livewire.dispatch('processarDesmembramentoLote', {
                loteId: loteParaDesmembrarId,
                linhaCorte: linhaDeCorteGeoJson
            });
        });

        map.addInteraction(currentDrawInteraction);
    };

    // =========================================================================
    // LISTENERS DO MAPA DE CALOR SOCIAL
    // =========================================================================

    // Função utilitária para "piscar" os lotes e aplicar as novas cores
    function atualizarCoresDosLotes() {
        if (window.loadedLayers['lotes']) {
            window.loadedLayers['lotes'].changed(); // Força o OpenLayers a re-ler a função de "style"
        } else {
            // Se a camada de lotes estiver desligada, nós a ligamos à força para o gestor ver o resultado!
            const checkboxLotes = document.querySelector('input[data-layer="lotes"]');
            if (checkboxLotes && !checkboxLotes.checked) {
                checkboxLotes.checked = true;
                checkboxLotes.dispatchEvent(new Event('change'));
            }
        }
    }

    const checkRisco = document.getElementById('filtro-social-risco');
    if (checkRisco) {
        checkRisco.addEventListener('change', function () {
            filtroRiscoAtivo = this.checked;
            atualizarCoresDosLotes();
        });
    }

    const checkBeneficio = document.getElementById('filtro-social-beneficio');
    if (checkBeneficio) {
        checkBeneficio.addEventListener('change', function () {
            filtroBeneficioAtivo = this.checked;
            atualizarCoresDosLotes();
        });
    }

    const checkPcd = document.getElementById('filtro-social-pcd');
    if (checkPcd) {
        checkPcd.addEventListener('change', function () {
            filtroPcdAtivo = this.checked;
            atualizarCoresDosLotes();
        });
    }

    // =========================================================================
    // MOTOR DE PRÉVIA DA PLANTA GENÉRICA DE VALORES (PGV)
    // =========================================================================

    const previewPgvSource = new ol.source.Vector();
    const previewPgvLayer = new ol.layer.Vector({
        source: previewPgvSource,
        style: function (feature) {
            return new ol.style.Style({
                text: new ol.style.Text({
                    text: feature.get('valor_formatado'),
                    font: 'bold 13px Arial, sans-serif',
                    fill: new ol.style.Fill({ color: '#ffffff' }),
                    backgroundFill: new ol.style.Fill({ color: '#10b981' }), // Emerald/Verde Dinheiro
                    backgroundStroke: new ol.style.Stroke({ color: '#047857', width: 2 }),
                    padding: [4, 6, 4, 6],
                    offsetY: -15
                })
            });
        },
        zIndex: 10002 // Acima da numeração predial
    });
    map.addLayer(previewPgvLayer);

    window.addEventListener('mostrar-preview-pgv', (e) => {
        const lotes = e.detail[0] || e.detail.dados || e.detail;
        previewPgvSource.clear();

        if (lotes && Array.isArray(lotes)) {
            const features = [];
            lotes.forEach(lote => {
                if (lote.geo) {
                    try {
                        const feature = new ol.Feature({
                            geometry: new ol.format.GeoJSON().readGeometry(lote.geo, { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }),
                            valor_formatado: lote.valor_formatado
                        });
                        features.push(feature);
                    } catch (err) { console.error("Erro no JSON da PGV", err); }
                }
            });
            previewPgvSource.addFeatures(features);
        }
    });

    window.addEventListener('limpar-preview-pgv', () => previewPgvSource.clear());

}); // <-- Fim do DOMContentLoaded