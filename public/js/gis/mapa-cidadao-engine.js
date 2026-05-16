/**
 * SIGWEB - Engine Cartográfica Pública (Cidadão)
 * Fiel ao painel original, restrito a visualização, medição e filtros.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. CARREGA AS CONFIGURAÇÕES INJETADAS PELO PHP
    const config = window.mapConfig || {};
    let zonasAtivas = [];

    // 2. CONFIGURA A CÂMERA DO MAPA
    const view = new ol.View({
        center: ol.proj.fromLonLat([config.mapLon, config.mapLat]),
        zoom: config.mapZoom,
        maxZoom: 22
    });

    // 3. MAPAS BASE E ORTOFOTO DO MUNICÍPIO
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
            url: `/mapas/${config.tenantSlug}/{z}/{x}/{y}.png`, // Lê a variável injetada pelo PHP
            minZoom: 12,
            maxZoom: 22,
            crossOrigin: 'anonymous'
        }),
        visible: false,
        zIndex: 2
    });

    // 4. DICIONÁRIO DE ESTILOS FIEL AO ORIGINAL
    const layerConfigs = {
        'perimetros': { z: 10, minZoom: 0, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#ef4444', width: 3 }), fill: new ol.style.Fill({ color: 'rgba(239, 68, 68, 0.05)' }) }) },
        'zonas': {
            z: 20, minZoom: 0,
            style: function (feature) {
                const sigla = feature.get('sigla');
                const rgbBruto = feature.get('rgb');
                if (!zonasAtivas.includes(sigla)) return null;
                const rgbLimpo = rgbBruto ? rgbBruto.replace(/[()]/g, '') : '150,150,150';
                return new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: `rgb(${rgbLimpo})`, width: 2, lineDash: [4, 4] }),
                    fill: new ol.style.Fill({ color: `rgba(${rgbLimpo}, 0.25)` }),
                    text: new ol.style.Text({ text: sigla, font: 'bold 14px Arial', fill: new ol.style.Fill({ color: '#333' }), stroke: new ol.style.Stroke({ color: '#fff', width: 3 }) })
                });
            }
        },
        'bairros': {
            z: 30, minZoom: 0,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#3b82f6', width: 2 }), fill: new ol.style.Fill({ color: 'rgba(59, 130, 246, 0.1)' }) });
                if (zoom >= 14) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : '', font: 'bold 16px Arial, sans-serif', fill: new ol.style.Fill({ color: '#1e3a8a' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 4 }), overflow: true }));
                }
                return style;
            }
        },
        'loteamentos': {
            z: 35, minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#2563eb', width: 3, lineDash: [8, 4] }), fill: new ol.style.Fill({ color: 'rgba(37, 99, 235, 0.1)' }) });
                if (zoom >= 14) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : 'Loteamento', font: 'bold 15px Arial, sans-serif', fill: new ol.style.Fill({ color: '#1e3a8a' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), overflow: true }));
                }
                return style;
            }
        },
        'quadras': {
            z: 40, minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#f97316', width: 1 }), fill: new ol.style.Fill({ color: 'rgba(249, 115, 22, 0.2)' }) });
                if (zoom >= 16) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? 'Q ' + feature.get('name').toString() : '', font: 'bold 14px Arial, sans-serif', fill: new ol.style.Fill({ color: '#9a3412' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), overflow: true }));
                }
                return style;
            }
        },
        'logradouros': { z: 50, minZoom: 14, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#3675ce', width: 3 }) }) },
        'postes': {
            z: 100, minZoom: 14,
            style: function (feature) {
                const condition = feature.get('structural_condition');
                let fillColor = '#eab308';
                if (condition === 'Bom') fillColor = '#22c55e';
                if (condition === 'Ruim') fillColor = '#ef4444';
                return new ol.style.Style({ image: new ol.style.Circle({ radius: 6, fill: new ol.style.Fill({ color: fillColor }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) });
            }
        },
        'arvores': {
            z: 101, minZoom: 15,
            style: function (feature) {
                const condition = feature.get('phytosanitary_condition');
                const size = feature.get('size');
                let radius = size === 'grande' ? 8 : 6;
                let fillColor = '#22c55e';
                if (condition === 'Regular') fillColor = '#eab308';
                if (condition === 'Ruim') fillColor = '#ef4444';
                if (condition === 'Morta') fillColor = '#6b7280';
                return new ol.style.Style({ image: new ol.style.Circle({ radius: radius, fill: new ol.style.Fill({ color: fillColor }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) });
            }
        },
        'lotes': {
            z: 60, minZoom: 15.5,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#10b981', width: 1 }), fill: new ol.style.Fill({ color: 'rgba(16, 185, 129, 0.15)' }) });
                if (zoom >= 18) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : '', font: 'bold 12px Arial, sans-serif', fill: new ol.style.Fill({ color: '#064e3b' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), overflow: true }));
                }
                return style;
            }
        },

        'pontos_panoramicos': {
            style: new ol.style.Style({
                image: new ol.style.Icon({
                    src: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="%233b82f6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>',
                    scale: 1.0,
                    anchor: [0.5, 0.5]
                })
            }),
            z: 100, // Câmeras sempre por cima!
            minZoom: 14 // Só aparece com zoom próximo
        },

        'cemiterios': {
            z: 25, minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#9333ea', width: 2 }), fill: new ol.style.Fill({ color: 'rgba(147, 51, 234, 0.2)' }) });
                if (zoom >= 15) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : 'Cemitério', font: 'bold 14px Arial, sans-serif', fill: new ol.style.Fill({ color: '#581c87' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), overflow: true }));
                }
                return style;
            }
        },
        'quadras_cemiterio': {
            z: 26, minZoom: 16,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#6366f1', width: 2, lineDash: [4, 4] }), fill: new ol.style.Fill({ color: 'rgba(99, 102, 241, 0.3)' }) });
                if (zoom >= 17) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : 'Quadra', font: 'bold 13px Arial, sans-serif', fill: new ol.style.Fill({ color: '#312e81' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), overflow: true }));
                }
                return style;
            }
        },
        'logradouros_cemiterio': { z: 27, minZoom: 16, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#64748b', width: 3 }) }) },
        'jazigos': {
            z: 28, minZoom: 18,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#57534e', width: 1 }), fill: new ol.style.Fill({ color: 'rgba(87, 83, 78, 0.4)' }) });
                if (zoom >= 19.5) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : 'Jazigo', font: 'bold 11px Arial, sans-serif', fill: new ol.style.Fill({ color: '#1c1917' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }), overflow: true }));
                }
                return style;
            }
        },
        'rural-localidades': {
            z: 15, minZoom: 0,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#57534e', width: 2, lineDash: [4, 4] }), fill: new ol.style.Fill({ color: 'rgba(120, 113, 108, 0.2)' }) });
                if (zoom >= 13) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : '', font: 'bold 13px Arial, sans-serif', fill: new ol.style.Fill({ color: '#292524' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), overflow: true }));
                }
                return style;
            }
        },
        'rural-propriedades': {
            z: 16, minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#f59e0b', width: 2 }), fill: new ol.style.Fill({ color: 'rgba(245, 158, 11, 0.2)' }) });
                if (zoom >= 13) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : '', font: 'bold 12px Arial, sans-serif', fill: new ol.style.Fill({ color: '#78350f' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), overflow: true }));
                }
                return style;
            }
        },
        'rural-estradas': {
            z: 17, minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const pavimento = feature.get('tipo_pavimento');
                let strokeColor = '#78350f'; let lineDash = [];
                if (pavimento === 'Asfalto') strokeColor = '#374151';
                else if (pavimento === 'Cascalho') lineDash = [4, 4];
                const style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: strokeColor, width: 4, lineDash: lineDash }) });
                if (zoom >= 14) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : '', font: 'bold 12px Arial, sans-serif', fill: new ol.style.Fill({ color: strokeColor }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), placement: 'line', textBaseline: 'bottom', offsetY: -5 }));
                }
                return style;
            }
        },
        'rural-hidrografias': {
            z: 17, minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const geomType = feature.getGeometry().getType();
                let style;
                if (geomType === 'Point' || geomType === 'MultiPoint') style = new ol.style.Style({ image: new ol.style.Circle({ radius: 6, fill: new ol.style.Fill({ color: '#0ea5e9' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) });
                else if (geomType === 'LineString' || geomType === 'MultiLineString') style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#0ea5e9', width: 3 }) });
                else style = new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#0284c7', width: 2 }), fill: new ol.style.Fill({ color: 'rgba(14, 165, 233, 0.4)' }) });
                if (zoom >= 14 && feature.get('name')) {
                    style.setText(new ol.style.Text({ text: feature.get('name').toString(), font: 'bold 12px Arial, sans-serif', fill: new ol.style.Fill({ color: '#0c4a6e' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), placement: (geomType === 'LineString' || geomType === 'MultiLineString') ? 'line' : 'point', offsetY: (geomType === 'Point' || geomType === 'MultiPoint') ? -15 : 0 }));
                }
                return style;
            }
        },
        'rural-pontes': {
            z: 110, minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const estado = feature.get('estado_conservacao');
                let borderColor = '#f59e0b';
                if (estado === 'Ruim') borderColor = '#ef4444'; else if (estado === 'Interditada') borderColor = '#000000';
                const style = new ol.style.Style({ image: new ol.style.Circle({ radius: 6, fill: new ol.style.Fill({ color: '#78350f' }), stroke: new ol.style.Stroke({ color: borderColor, width: 2 }) }) });
                if (zoom >= 15) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : 'Ponte', font: 'bold 12px Arial, sans-serif', fill: new ol.style.Fill({ color: '#451a03' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), offsetY: -15 }));
                }
                return style;
            }
        },
        'rural-pontos-interesse': {
            z: 120, minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const categoria = feature.get('categoria');
                let dotColor = '#14b8a6';
                if (categoria === 'Escola') dotColor = '#3b82f6'; else if (categoria === 'Saúde') dotColor = '#ef4444'; else if (categoria === 'Igreja') dotColor = '#a855f7'; else if (categoria === 'Turismo') dotColor = '#f59e0b'; else if (categoria === 'Comércio') dotColor = '#84cc16';
                const style = new ol.style.Style({ image: new ol.style.Circle({ radius: 6, fill: new ol.style.Fill({ color: dotColor }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 }) }) });
                if (zoom >= 14) {
                    style.setText(new ol.style.Text({ text: feature.get('name') ? feature.get('name').toString() : 'PoI', font: 'bold 12px Arial, sans-serif', fill: new ol.style.Fill({ color: '#1c1917' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), offsetY: -15 }));
                }
                return style;
            }
        }
    };

    // 5. INICIA O MAPA COM AS 3 CAMADAS BASE (Incluindo Ortofoto)
    const map = new ol.Map({ target: 'sigweb-map', layers: [osmLayer, esriLayer, ortofotoLayer], view: view });

    // ── ZOOM EXTENSÃO + VISÃO ANTERIOR ──────────────────────────────
    // Guarda a view inicial diretamente do config do tenant
    const initialCenter = ol.proj.fromLonLat([config.mapLon, config.mapLat]);
    const initialZoom = config.mapZoom;

    // Histórico de navegação
    const viewHistory = [];
    let viewHistoryIndex = -1;
    let navegandoHistorico = false;

    map.getView().on('change:resolution', () => {
        if (navegandoHistorico) return;
        const v = map.getView();
        viewHistory.splice(viewHistoryIndex + 1);
        viewHistory.push({ center: v.getCenter().slice(), zoom: v.getZoom() });
        if (viewHistory.length > 50) viewHistory.shift();
        viewHistoryIndex = viewHistory.length - 1;
    });

    window.zoomExtensao = function () {
        map.getView().animate({ center: initialCenter, zoom: initialZoom, duration: 600 });
    };

    window.visaoAnterior = function () {
        if (viewHistoryIndex <= 0) return;
        viewHistoryIndex--;
        navegandoHistorico = true;
        const v = viewHistory[viewHistoryIndex];
        map.getView().animate({ center: v.center, zoom: v.zoom, duration: 400 }, () => {
            navegandoHistorico = false;
        });
    };
    // ────────────────────────────────────────────────────────────────

    const dblClickZoom = map.getInteractions().getArray().find(i => i instanceof ol.interaction.DoubleClickZoom);
    if (dblClickZoom) map.removeInteraction(dblClickZoom);

    window.loadedLayers = {};

    // 6. EVENTOS DE SATÉLITE
    let showSat = false;
    const btnSatelite = document.getElementById('btn-satelite');
    const sateliteText = document.getElementById('satelite-text');
    if (btnSatelite) {
        btnSatelite.addEventListener('click', () => {
            showSat = !showSat;
            osmLayer.setVisible(!showSat);
            esriLayer.setVisible(showSat);
            ortofotoLayer.setVisible(showSat); // ATIVA A ORTOFOTO DA CIDADE!

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

    // 7. CARREGAMENTO DE CAMADAS (API AJAX ORIGINAL)
    const fetchAndDrawLayer = (layerName, checkboxElement) => {
        if (window.loadedLayers[layerName]) {
            window.loadedLayers[layerName].setVisible(true);
            return;
        }

        const textSpan = checkboxElement.nextElementSibling.querySelector('.layer-text');
        let originalText = '';
        if (textSpan) {
            originalText = textSpan.innerHTML;
            textSpan.innerHTML = 'Carregando...';
            textSpan.classList.add('animate-pulse', 'text-primary-500');
        }

        fetch(`/api/gis-data?tenant_id=${config.tenantId}&layer=${layerName}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.features && data.features.length > 0) {
                    const parsedFeatures = new ol.format.GeoJSON().readFeatures(data, { featureProjection: 'EPSG:3857' });
                    parsedFeatures.forEach(f => f.set('layer', layerName));

                    // Carrega as configurações de estilo exatas para aquela camada
                    const layerConf = layerConfigs[layerName];

                    const vectorLayer = new ol.layer.Vector({
                        source: new ol.source.Vector({ features: parsedFeatures }),
                        style: layerConf ? layerConf.style : null,
                        zIndex: layerConf ? layerConf.z : 10,
                        minZoom: layerConf ? layerConf.minZoom : 0
                    });
                    map.addLayer(vectorLayer);
                    window.loadedLayers[layerName] = vectorLayer;
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
            else if (window.loadedLayers[layerName]) window.loadedLayers[layerName].setVisible(false);
        });
        // Se já estiver marcado no HTML, carrega
        if (checkbox.checked) fetchAndDrawLayer(checkbox.getAttribute('data-layer'), checkbox);
    });

    document.querySelectorAll('.zona-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const sigla = this.getAttribute('data-zona-sigla');
            if (this.checked) { if (!zonasAtivas.includes(sigla)) zonasAtivas.push(sigla); }
            else { zonasAtivas = zonasAtivas.filter(s => s !== sigla); }

            if (!window.loadedLayers['zonas']) fetchAndDrawLayer('zonas', this);
            else {
                window.loadedLayers['zonas'].changed();
                window.loadedLayers['zonas'].setVisible(zonasAtivas.length > 0);
            }
        });
    });

    // 8. INTERFACE JANELA DE CAMADAS
    const panel = document.getElementById("layers-panel");
    const btnToggleLayers = document.getElementById("btn-toggle-layers");
    if (btnToggleLayers && panel) {
        btnToggleLayers.addEventListener('click', () => panel.classList.toggle('hidden'));
    }

    // 9. EVENTO DE BUSCA (VOAR PARA LOTE - CORRIGIDO!)
    // O seu Alpine.js já faz a busca na API e manda o resultado no 'detail.coords'
    window.addEventListener('voar-para-lote', (e) => {
        const data = e.detail;
        if (data && data.coords) {
            const targetCoords = ol.proj.fromLonLat([data.coords[0], data.coords[1]]);
            view.animate({ center: targetCoords, zoom: 20, duration: 2000 });

            // Destaca o ponto da busca
            querySource.clear();
            const pointFeature = new ol.Feature(new ol.geom.Point(targetCoords));
            querySource.addFeature(pointFeature);
            // Limpa o ponto após 5 segundos
            setTimeout(() => querySource.clear(), 5000);
        }
    });

    // 10. FILTRO AVANÇADO (CÓPIA FIEL DA ORIGINAL)
    const querySource = new ol.source.Vector();
    const queryLayer = new ol.layer.Vector({
        source: querySource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({ color: '#f59e0b', width: 4 }), // Laranja forte
            fill: new ol.style.Fill({ color: 'rgba(245, 158, 11, 0.4)' }),
            image: new ol.style.Circle({
                radius: 8, fill: new ol.style.Fill({ color: '#f59e0b' }), stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
            })
        }),
        zIndex: 9999
    });
    map.addLayer(queryLayer);

    window.addEventListener('executar-filtro-avancado', (e) => {
        const dados = e.detail[0] || e.detail.dados || e.detail;
        const url = `/api/mapa/advanced-query?tenant_id=${config.tenantId}&layer=${dados.layer}&field=${dados.field}&operator=${encodeURIComponent(dados.operator)}&value=${encodeURIComponent(dados.value)}`;

        fetch(url)
            .then(async response => {
                if (!response.ok) throw new Error(`Erro ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.error) { alert("Erro: " + data.error); return; }
                querySource.clear();

                if (data.features && data.features.length > 0) {
                    const features = new ol.format.GeoJSON().readFeatures(data, {
                        dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857'
                    });
                    querySource.addFeatures(features);
                    map.getView().fit(querySource.getExtent(), { padding: [50, 50, 50, 50], duration: 1000 });
                } else {
                    alert('Nenhum resultado encontrado com esses critérios.');
                }
            })
            .catch(err => { console.error("Erro no filtro:", err); alert("Falha ao buscar filtro."); });
    });

    window.addEventListener('limpar-filtro-avancado', () => { querySource.clear(); });

    // 11. TOOLTIPS E HOVER (MÃOZINHA)
    const featureTooltip = document.getElementById('feature-tooltip');
    let hoveredFeature = null;

    map.on('pointermove', function (e) {
        if (currentMeasureInteraction) return; // Não interfere se estiver com a reguinha ligada

        // Limpa o destaque anterior
        if (hoveredFeature) {
            hoveredFeature.setStyle(undefined);
            hoveredFeature = null;
        }

        let hitFiltro = false;
        let featureNormal = null;

        // Detecta o que está debaixo do mouse
        map.forEachFeatureAtPixel(e.pixel, function (f) {
            if (f.get('titulo') && f.get('info')) {
                hitFiltro = true; // É um resultado da pesquisa/filtro avançado
                if (featureTooltip) {
                    featureTooltip.innerHTML = `<div style="font-size:14px; font-weight:900;">${f.get('titulo')}</div><div style="font-size:10px; color:#cbd5e1;">${f.get('info')}</div>`;
                }
            } else {
                featureNormal = f; // É um artefato normal (Rua, Lote, Bairro...)
            }
        }, { hitTolerance: 5 });

        // Lógica de exibição da caixinha
        if (hitFiltro) {
            featureTooltip.style.left = (e.originalEvent.clientX + 15) + 'px';
            featureTooltip.style.top = (e.originalEvent.clientY + 15) + 'px';
            featureTooltip.style.display = 'block';
            map.getTargetElement().style.cursor = 'pointer';
        }
        else if (featureNormal) {
            const layer = featureNormal.get('layer');
            const name = featureNormal.get('name') || featureNormal.get('titulo');

            // Define quais camadas mudam o cursor para "mãozinha"
            const hoverableLayers = ['lotes', 'logradouros', 'bairros', 'quadras', 'cemiterios', 'rural-estradas', 'pontos_panoramicos'];
            map.getTargetElement().style.cursor = hoverableLayers.includes(layer) ? 'pointer' : '';

            // Se for Logradouro (Rua), acende de azul e mostra a caixinha com o nome
            if (layer === 'logradouros' || layer === 'rural-estradas') {
                hoveredFeature = featureNormal;

                // Estilo de destaque (Rua mais grossa e azul claro)
                featureNormal.setStyle(new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#38bdf8', width: 6 })
                }));

                if (featureTooltip && name) {
                    featureTooltip.innerHTML = `<div style="font-size:12px; font-weight:bold;">${name}</div>`;
                    featureTooltip.style.display = 'block';
                    featureTooltip.style.left = (e.originalEvent.clientX + 15) + 'px';
                    featureTooltip.style.top = (e.originalEvent.clientY + 15) + 'px';
                }
            } else {
                if (featureTooltip) featureTooltip.style.display = 'none';
            }
        }
        else {
            map.getTargetElement().style.cursor = '';
            if (featureTooltip) featureTooltip.style.display = 'none';
        }
    });

    // 12. FERRAMENTAS DE MEDIÇÃO
    const measureTooltipElement = document.getElementById('measure-tooltip');
    const measureOverlay = new ol.Overlay({ element: measureTooltipElement, offset: [0, -15], positioning: 'bottom-center' });
    map.addOverlay(measureOverlay);

    const drawSource = new ol.source.Vector();
    const drawLayer = new ol.layer.Vector({
        source: drawSource,
        style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#ef4444', width: 3, lineDash: [5, 5] }), fill: new ol.style.Fill({ color: 'rgba(239, 68, 68, 0.2)' }) }),
        zIndex: 9999
    });
    map.addLayer(drawLayer);

    let currentMeasureInteraction = null;

    const resetToPan = () => {
        if (currentMeasureInteraction) map.removeInteraction(currentMeasureInteraction);
        drawSource.clear();
        measureTooltipElement.style.display = 'none';
        map.getTargetElement().style.cursor = '';

        ['btn-measure-line', 'btn-measure-area'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.classList.remove('bg-primary-100', 'text-primary-600');
        });
    };

    document.getElementById('btn-pan')?.addEventListener('click', resetToPan);

    const enableMeasure = (type, btn) => {
        resetToPan();
        btn.classList.add('bg-primary-100', 'text-primary-600');
        map.getTargetElement().style.cursor = 'crosshair';

        currentMeasureInteraction = new ol.interaction.Draw({
            source: drawSource,
            type: type === 'line' ? 'LineString' : 'Polygon',
            style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#fff', width: 2, lineDash: [5, 5] }) })
        });

        currentMeasureInteraction.on('drawstart', () => { drawSource.clear(); measureTooltipElement.style.display = 'block'; });
        currentMeasureInteraction.on('drawend', (e) => {
            const geom = e.feature.getGeometry();
            const output = type === 'line' ? (ol.sphere.getLength(geom)).toFixed(2) + ' m' : (ol.sphere.getArea(geom)).toFixed(2) + ' m²';
            measureTooltipElement.innerHTML = output;
            measureOverlay.setPosition(type === 'line' ? geom.getLastCoordinate() : geom.getInteriorPoint().getCoordinates());
            map.removeInteraction(currentMeasureInteraction);
            map.getTargetElement().style.cursor = '';
        });

        map.addInteraction(currentMeasureInteraction);
    };

    document.getElementById('btn-measure-line')?.addEventListener('click', function () { enableMeasure('line', this); });
    document.getElementById('btn-measure-area')?.addEventListener('click', function () { enableMeasure('area', this); });

    // ------------------------------------------------------------------------
    // CLIQUE NO LOTE OU PONTO PANORÂMICO (ABRIR FICHA/MODAL DO CIDADÃO)
    // ------------------------------------------------------------------------
    map.on('singleclick', function (e) {
        if (currentMeasureInteraction) return; // Não clica se estiver usando a reguinha

        const feature = map.forEachFeatureAtPixel(e.pixel, f => f, { hitTolerance: 5 });

        if (feature) {
            // Mudamos o nome da variável para evitar o erro "layer is not defined"
            const featureLayer = feature.get('layer');
            const featureId = feature.get('id');

            if (featureLayer === 'lotes') {
                const loteNome = feature.get('titulo') || feature.get('name') || 'S/N';
                // Avisa o Livewire para abrir a ficha do lote
                Livewire.dispatch('abrirFichaImovel', { loteId: featureId, loteNome: loteNome });
            }
            else if (featureLayer === 'pontos_panoramicos') {
                // Avisa o Livewire para abrir a modal de visualização 360º
                Livewire.dispatch('abrirVisualizadorPublico360', { id: featureId });
            }
        }
    });

    // ------------------------------------------------------------------------
    // RENDERIZAR EDIFICAÇÕES DO LOTE (APENAS LEITURA)
    // ------------------------------------------------------------------------
    const edificacoesSource = new ol.source.Vector();
    const edificacoesLayer = new ol.layer.Vector({
        source: edificacoesSource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({ color: '#d97706', width: 2 }), // Laranja/Amber
            fill: new ol.style.Fill({ color: 'rgba(217, 119, 6, 0.4)' })
        }),
        zIndex: 150
    });
    map.addLayer(edificacoesLayer);

    window.addEventListener('mostrar-edificacoes-lote', (event) => {
        const loteId = event.detail.id || event.detail[0]?.id;
        edificacoesSource.clear();

        if (loteId) {
            fetch(`/api/mapa/advanced-query?tenant_id=${config.tenantId}&layer=edificacoes&field=lote_id&operator=%3D&value=${loteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.features && data.features.length > 0) {
                        const features = new ol.format.GeoJSON().readFeatures(data, {
                            dataProjection: 'EPSG:4326',
                            featureProjection: 'EPSG:3857'
                        });
                        edificacoesSource.addFeatures(features);
                    }
                })
                .catch(err => console.error("Erro ao carregar edificações:", err));
        }
    });

    window.addEventListener('esconder-edificacoes-lote', () => {
        edificacoesSource.clear();
    });

}); // <-- Fim do DOMContentLoaded