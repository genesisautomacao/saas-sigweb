/**
 * SIGWEB - Engine Cartográfica (OpenLayers 8.2)
 * Arquivo isolado para não poluir o Blade. 
 * Não requer Vite, apenas carregue via asset() no Laravel.
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
        'lotes': {
            z: 60,
            minZoom: 15.5,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#10b981', width: 1 }),
                    fill: new ol.style.Fill({ color: 'rgba(16, 185, 129, 0.15)' })
                });
                if (zoom >= 18) {
                    style.setText(new ol.style.Text({
                        text: feature.get('name') ? feature.get('name').toString() : '',
                        font: 'bold 12px Arial, sans-serif',
                        fill: new ol.style.Fill({ color: '#064e3b' }),
                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                        overflow: true
                    }));
                }
                return style;
            }
        },
        'edificacoes': { z: 70, minZoom: 16, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#b45309', width: 1 }), fill: new ol.style.Fill({ color: 'rgba(180, 83, 9, 0.5)' }) }) },
    };

    // 5. INICIA O MAPA
    const map = new ol.Map({ target: 'sigweb-map', layers: [osmLayer, esriLayer, ortofotoLayer], view: view });

    // Desativa o zoom de duplo clique para facilitar edições
    const dblClickZoom = map.getInteractions().getArray().find(i => i instanceof ol.interaction.DoubleClickZoom);
    if (dblClickZoom) map.removeInteraction(dblClickZoom);

    const loadedLayers = {};

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
        if (loadedLayers[layerName]) {
            loadedLayers[layerName].setVisible(true);
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
                    loadedLayers[layerName] = vectorLayer;
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
            else if (loadedLayers[layerName]) loadedLayers[layerName].setVisible(false);
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

            if (!loadedLayers['zonas']) {
                fetchAndDrawLayer('zonas', this);
            } else {
                loadedLayers['zonas'].changed();
                loadedLayers['zonas'].setVisible(zonasAtivas.length > 0);
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
        // 1. Limpa o efeito do último elemento que passamos o mouse
        if (hoveredFeature) {
            hoveredFeature.setStyle(undefined); // Devolve a cor original
            hoveredFeature = null;
        }

        const feature = map.forEachFeatureAtPixel(e.pixel, feature => feature);
        
        // 2. Define quais camadas ganham a "mãozinha" (pointer) ao passar o mouse
        const hoverableLayers = ['lotes', 'edificacao_ativa', 'logradouros', 'bairros', 'quadras'];
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

            if (layer === 'logradouros') {
                feature.setStyle(new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: '#38bdf8', width: 6 }) 
                }));

                if (featureTooltip) {
                    featureTooltip.innerHTML = name || 'Logradouro sem nome';
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
                }
            }
        } else {
            // Limpa tudo se clicar fora
            if (featureTooltip) featureTooltip.style.display = 'none';
        }
    });

    map.on('singleclick', function (evt) {
        if (activeTool !== 'pan') return;
        const features = map.getFeaturesAtPixel(evt.pixel);

        if (features && features.length > 0) {
            const clickedEdif = features.find(f => f.get('layer') === 'edificacao_ativa');
            if (clickedEdif) {
                Livewire.dispatch('abrirOpcoesEdificacao', { id: clickedEdif.get('id') });
                return;
            }

            const clickedLogradouro = features.find(f => f.get('layer') === 'logradouros');
            if (clickedLogradouro) {
                Livewire.dispatch('abrirOpcoesLogradouro', { id: clickedLogradouro.get('id') });
                return;
            }

            const clickedLote = features.find(f => f.get('layer') === 'lotes');
            if (clickedLote) {
                const loteId = clickedLote.get('id');
                if (featureEmEdicao) {
                    if (featureEmEdicao.get('id') != loteId) window.cancelarEdicaoGeometria();
                    else return;
                }
                const loteNome = clickedLote.get('name') || 'S/N';
                if (loteId) Livewire.dispatch('abrirFichaImovel', { loteId: loteId, loteNome: loteNome });
            }
        } else {
            if (featureEmEdicao) window.cancelarEdicaoGeometria();
        }
    });

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
        if (['arvore', 'poste'].includes(entityType)) geometryType = 'Point';
        if (entityType === 'logradouro') geometryType = 'LineString';

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

        if (['lote', 'edificacao'].includes(entityType) && loadedLayers['lotes']) {
            currentSnapInteraction = new ol.interaction.Snap({
                source: loadedLayers['lotes'].getSource(),
                pixelTolerance: 10
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
        if (checkbox && checkbox.checked && loadedLayers['lotes']) {
            const feature = new ol.Feature({
                geometry: new ol.format.GeoJSON().readGeometry(data.geo, { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }),
                id: data.id, name: data.numero_lote, layer: 'lotes'
            });
            loadedLayers['lotes'].getSource().addFeature(feature);
        }
    });

    window.addEventListener('atualizar-label-lote', (e) => {
        const data = e.detail[0] || e.detail;
        if (loadedLayers['lotes']) {
            const feature = loadedLayers['lotes'].getSource().getFeatures().find(f => f.get('id') == data.id);
            if (feature) { feature.set('name', data.numero_lote); feature.changed(); }
        }
    });

    window.addEventListener('remover-lote-mapa', (e) => {
        const data = e.detail[0] || e.detail;
        if (loadedLayers['lotes']) {
            const source = loadedLayers['lotes'].getSource();
            const feature = source.getFeatures().find(f => f.get('id') == data.id);
            if (feature) source.removeFeature(feature);
        }
    });

    // 13. EDIÇÃO DE GEOMETRIA (MODIFICAR)
    let currentModifyInteraction = null;
    let editSnapInteraction = null;
    let featureEmEdicao = null;
    let geometriaOriginal = null;

    window.addEventListener('iniciar-edicao-geometria', (e) => {
        const data = e.detail[0] || e.detail;
        if (!loadedLayers['lotes']) return;
        const source = loadedLayers['lotes'].getSource();
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
        if (!loadedLayers['logradouros']) return;
        const source = loadedLayers['logradouros'].getSource();
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
        const closeBtn = document.querySelector('.fi-modal-close-btn') || document.querySelector('button[x-on\\:click="close"]');
        if (closeBtn) closeBtn.click();
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

    window.resetToPan = function () {
        if (currentMeasureInteraction) map.removeInteraction(currentMeasureInteraction);
        if (currentDrawInteraction) map.removeInteraction(currentDrawInteraction);
        if (currentSnapInteraction) map.removeInteraction(currentSnapInteraction);

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

    // 16. ATUALIZA A CAMADA DE LOGRADOUROS APÓS CRIAR/EDITAR
    window.addEventListener('atualizar-camada-logradouros', () => {
        if (loadedLayers['logradouros']) {
            map.removeLayer(loadedLayers['logradouros']);
            delete loadedLayers['logradouros'];

            // Finge um clique no checkbox para baixar os dados atualizados do banco
            const checkbox = document.querySelector('input[data-layer="logradouros"]');
            if (checkbox && checkbox.checked) {
                fetchAndDrawLayer('logradouros', checkbox);
            }
        }
    });

}); // <-- Fim do DOMContentLoaded