/**
 * SIGWEB - Engine Cartográfica Pública (Cidadão)
 * Fiel ao painel original, restrito a visualização, medição e filtros.
 */

document.addEventListener("DOMContentLoaded", function () {
    // 1. CARREGA AS CONFIGURAÇÕES INJETADAS PELO PHP
    const config = window.mapConfig || {};
    let zonasAtivas = [];

    // 2. CONFIGURA A CÂMERA DO MAPA
    const view = new ol.View({
        center: ol.proj.fromLonLat([config.mapLon, config.mapLat]),
        zoom: config.mapZoom,
        maxZoom: 22,
    });

    // ══ MOBILIDADE URBANA — versão SÓ LEITURA do engine da intranet (D8, 2026-09-05) ══
    // Mesmas cores e símbolos do mapa-engine.js: o cidadão vê o que a prefeitura vê,
    // sem criar/editar/classificar. Sem "Colorir por", sem coroplético, sem caneta.
    const MOB_VIA_CORES = { mao_unica: "#2563eb", mao_dupla: "#dc2626", nenhum: "#9ca3af" };
    const MOB_EIXO_CORES = { ciclovia: "#16a34a", eixo_comercial: "#f59e0b", rota_carga: "#7c3aed", rodovia: "#dc2626" };
    const MOB_POI_CORES = {
        comercio_servicos: "#f59e0b", educacao: "#2563eb", saude: "#dc2626", religioso: "#7c3aed",
        turismo_lazer_esporte: "#16a34a", industria: "#64748b", posto_combustivel: "#ea580c",
    };
    const MOB_ZONA_CORES = {
        zona_od: { stroke: "#2563eb", fill: "rgba(37,99,235,0.10)" },
        quadrante: { stroke: "#f97316", fill: "rgba(249,115,22,0.08)" },
        polo_industrial: { stroke: "#7c3aed", fill: "rgba(124,58,237,0.12)" },
        setor_censitario: { stroke: "#b45309", fill: "rgba(217,119,6,0.14)" },
    };
    const MOB_TRECHO_COR = "#0ea5e9"; // cor única dos trechos (sem tema)
    const MOB_ROTULOS = config.mobRotulos || {}; // { poi, eixo, zona } — listas do PHP p/ a ficha
    // "Colorir por" dos trechos (pedido 2026-09-05 — mesma tematização da intranet):
    // atributo ativo (null = cor única) + mapa valor→cor montado com as feições carregadas
    const MOB_TEMA_PALETA = [
        "#2563eb", "#dc2626", "#16a34a", "#f59e0b", "#7c3aed", "#0891b2",
        "#db2777", "#65a30d", "#ea580c", "#4b5563", "#0d9488", "#9333ea",
    ];
    window.mobTrechoTema = null;
    window._mobTemaMap = {};
    window.mobTrechoValorTema = function (feature) {
        const tema = window.mobTrechoTema;
        if (!tema) return null;
        let v = feature.get(tema);
        if (v === undefined || v === null || v === "") {
            const custom = feature.get("custom");
            v = custom ? custom[tema] : null;
        }
        if (Array.isArray(v)) v = v.join(", ");
        return v === undefined || v === null || v === "" ? null : String(v);
    };
    function mobTrechoCor(feature) {
        if (!window.mobTrechoTema) return MOB_TRECHO_COR;
        const v = window.mobTrechoValorTema(feature) ?? "—";
        return window._mobTemaMap[v] || "#9ca3af";
    }
    const mobViaCor = (f) => MOB_VIA_CORES[f.get("sentido")] || MOB_VIA_CORES.nenhum;
    // Fluxos O/D: a cor vem PRONTA do backend por zona de DESTINO (MobFluxo::distribuicao)
    const mobFluxoCor = (f) => f.get("cor") || "#6b7280";
    function mobFluxoRotulo(f) {
        const pct = f.get("percentual");
        if (pct === undefined || pct === null || Number(pct) <= 0) return "";
        return Number(pct).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "%";
    }
    // Filtros dos mini-checkboxes: camada → Set de valores DESLIGADOS
    window._mobFiltros = {};
    window.mobFiltrado = function (mobLayer, valor) {
        const s = window._mobFiltros[mobLayer];
        return !!(s && s.has(String(valor)));
    };
    // Ícone de câmera (SVG inline, cacheado por cor|escala)
    const mobCameraIconeCache = new Map();
    function mobCameraIcone(cor, escala) {
        const chave = cor + "|" + escala;
        let icone = mobCameraIconeCache.get(chave);
        if (!icone) {
            const svg =
                '<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 34 34">' +
                '<circle cx="17" cy="17" r="15" fill="' + cor + '" stroke="#ffffff" stroke-width="2.5"/>' +
                '<rect x="8" y="12" width="13" height="10" rx="2" fill="#ffffff"/>' +
                '<path d="M21 15.5 L26.5 12.5 L26.5 21.5 L21 18.5 Z" fill="#ffffff"/>' +
                "</svg>";
            icone = new ol.style.Icon({
                src: "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg),
                scale: escala,
                anchor: [0.5, 0.5],
            });
            mobCameraIconeCache.set(chave, icone);
        }
        return icone;
    }
    // Setas de sentido (vias) e tiques de direção (trechos) — motor idêntico ao da intranet
    const MOB_SETA_PASSO = 70; // px de tela entre setas
    const MOB_FLUXO_VEL = 45; // px/s do simulador
    const mobSetaCache = new Map();
    window.mobFluxoSimulando = false;
    window._mobFluxoFase = 0;
    function mobSetaImagem(raio, rot, cor, contorno, escala) {
        const chave = raio + "|" + rot.toFixed(2) + "|" + cor + "|" + contorno + "|" + escala;
        let img = mobSetaCache.get(chave);
        if (!img) {
            img = new ol.style.RegularShape({
                points: 3,
                radius: raio,
                rotation: rot,
                rotateWithView: true,
                scale: [1, escala],
                fill: new ol.style.Fill({ color: cor }),
                stroke: new ol.style.Stroke({ color: contorno, width: raio >= 8 ? 1.5 : 1 }),
            });
            if (mobSetaCache.size > 6000) mobSetaCache.clear();
            mobSetaCache.set(chave, img);
        }
        return img;
    }
    function mobSetasAoLongo(feature, resolution, cfg) {
        const estilos = [];
        const geom = feature.getGeometry();
        if (!geom) return estilos;
        const passo = cfg.passo * resolution;
        const desloc = cfg.duplo ? cfg.raio * 0.9 * resolution : 0;
        const linhas = geom.getType() === "MultiLineString" ? geom.getLineStrings() : [geom];
        linhas.forEach((linha) => {
            const total = linha.getLength();
            if (total / resolution < 24) return;
            const fase = cfg.animado ? (window._mobFluxoFase * resolution) % passo : null;
            const alvosF = [];
            if (fase !== null) {
                for (let d = fase; d < total; d += passo) alvosF.push(d);
            } else {
                const n = Math.max(1, Math.floor(total / passo));
                const esp = total / (n + 1);
                for (let i = 1; i <= n; i++) alvosF.push(esp * i);
            }
            let alvosB = null;
            if (cfg.duplo) {
                if (fase !== null) {
                    alvosB = [];
                    for (let d = total - fase; d > 0; d -= passo) alvosB.unshift(d);
                } else {
                    alvosB = alvosF.slice();
                }
            }
            const colocar = (alvos, invertido) => {
                let acumulado = 0;
                let k = 0;
                linha.forEachSegment((a, b) => {
                    if (k >= alvos.length) return;
                    const dx = b[0] - a[0];
                    const dy = b[1] - a[1];
                    const comp = Math.hypot(dx, dy);
                    if (comp === 0) return;
                    const rot = Math.atan2(dx, dy) + (invertido ? Math.PI : 0);
                    const off = invertido ? -desloc : desloc;
                    const ux = (dx / comp) * off;
                    const uy = (dy / comp) * off;
                    while (k < alvos.length && alvos[k] <= acumulado + comp) {
                        const t = (alvos[k] - acumulado) / comp;
                        estilos.push(
                            new ol.style.Style({
                                geometry: new ol.geom.Point([a[0] + dx * t + ux, a[1] + dy * t + uy]),
                                image: mobSetaImagem(cfg.raio, rot, cfg.cor, cfg.contorno, cfg.escala),
                                zIndex: cfg.zIndex,
                            }),
                        );
                        k++;
                    }
                    acumulado += comp;
                });
            };
            colocar(alvosF, false);
            if (alvosB) colocar(alvosB, true);
        });
        return estilos;
    }
    function mobSetasSentido(feature, resolution, duplo) {
        const zoom = view.getZoomForResolution(resolution);
        return mobSetasAoLongo(feature, resolution, {
            passo: duplo ? MOB_SETA_PASSO * 2 : MOB_SETA_PASSO,
            raio: Math.round(Math.max(8, Math.min(12, 8 + (zoom - 15.5) * 1.6))) - (duplo ? 1 : 0),
            cor: "#ffffff",
            contorno: "#111827",
            escala: 1.4,
            animado: !!window.mobFluxoSimulando,
            duplo: !!duplo,
            zIndex: 5,
        });
    }
    function mobSetasDirecaoTrecho(feature, resolution) {
        return mobSetasAoLongo(feature, resolution, {
            passo: 110,
            raio: 5,
            cor: "#ffffff",
            contorno: "#475569",
            escala: 1.3,
            animado: false,
            duplo: false,
            zIndex: 4,
        });
    }
    // Simulador de fluxo (só visual): anima as setas SÓ da camada de vias
    let mobFluxoUltimoTs = 0;
    function mobFluxoTick(ts) {
        if (!window.mobFluxoSimulando) return;
        requestAnimationFrame(mobFluxoTick);
        if (!mobFluxoUltimoTs) {
            mobFluxoUltimoTs = ts;
            return;
        }
        const dt = ts - mobFluxoUltimoTs;
        if (dt < 33) return;
        mobFluxoUltimoTs = ts;
        window._mobFluxoFase = (window._mobFluxoFase + (dt / 1000) * MOB_FLUXO_VEL) % MOB_SETA_PASSO;
        const camada = window.loadedLayers && window.loadedLayers["mob_vias"];
        if (camada && camada.getVisible()) camada.changed();
    }
    window.addEventListener("sigweb-mob-fluxo-simular", (e) => {
        const ligar = !!(e.detail && e.detail.ligado);
        if (ligar === window.mobFluxoSimulando) return;
        window.mobFluxoSimulando = ligar;
        mobFluxoUltimoTs = 0;
        if (ligar) {
            requestAnimationFrame(mobFluxoTick);
        } else {
            const camada = window.loadedLayers && window.loadedLayers["mob_vias"];
            if (camada) camada.changed();
        }
    });

    // 3. MAPAS BASE — mesmo seletor da intranet (2026-09-05): OSM, satélite Esri e as
    //    ortofotos CADASTRADAS da prefeitura (tabela `ortofotos`), cada uma um basemap
    //    `ortofoto_<id>`. A config manda sozinha: sem cadastro, sem opção (a pasta legada
    //    /mapas/{slug}/ saiu). Azure Maps fica FORA do público: a chave iria para o HTML de
    //    qualquer visitante anônimo. Tile 512 = fato da pirâmide (gdal2tiles --tilesize):
    //    grade 512 pede z−1 → min/maxZoom 11/21 (≡ 12/22 na grade 256).
    const basemaps = {
        osm: new ol.layer.Tile({ source: new ol.source.OSM(), visible: true, zIndex: 0 }),
        esri_sat: new ol.layer.Tile({
            source: new ol.source.XYZ({
                url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
                maxZoom: 18,
                crossOrigin: "anonymous",
            }),
            visible: false,
            zIndex: 1,
        }),
    };
    (config.ortofotos || []).forEach((orto) => {
        const tileSize = parseInt(orto.tile_size, 10) === 512 ? 512 : 256;
        basemaps["ortofoto_" + orto.id] = new ol.layer.Tile({
            source: new ol.source.XYZ({
                url: orto.url,
                tileSize: [tileSize, tileSize],
                minZoom: tileSize === 512 ? 11 : 12,
                maxZoom: tileSize === 512 ? 21 : 22,
                crossOrigin: "anonymous",
            }),
            visible: false,
            zIndex: 2,
        });
    });
    const basemapLayers = Object.values(basemaps);

    // 4. DICIONÁRIO DE ESTILOS FIEL AO ORIGINAL
    const layerConfigs = {
        perimetros: {
            z: 10,
            minZoom: 0,
            // Rótulo com o NOME do distrito (2026-08-06): o público tinha estilo
            // estático sem texto — espelha o comportamento da intranet.
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#ef4444", width: 3 }),
                    fill: new ol.style.Fill({ color: "rgba(239, 68, 68, 0.05)" }),
                });
                if (zoom >= 12) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "",
                            font: "bold 14px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#991b1b" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        zonas: {
            z: 20,
            minZoom: 0,
            style: function (feature) {
                const sigla = feature.get("sigla");
                const rgbBruto = feature.get("rgb");
                if (!zonasAtivas.includes(sigla)) return null;
                const rgbLimpo = rgbBruto
                    ? rgbBruto.replace(/[()]/g, "")
                    : "150,150,150";
                return new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: `rgb(${rgbLimpo})`,
                        width: 2,
                        lineDash: [4, 4],
                    }),
                    fill: new ol.style.Fill({
                        color: `rgba(${rgbLimpo}, 0.25)`,
                    }),
                    text: new ol.style.Text({
                        text: sigla,
                        font: "bold 14px Arial",
                        fill: new ol.style.Fill({ color: "#333" }),
                        stroke: new ol.style.Stroke({
                            color: "#fff",
                            width: 3,
                        }),
                    }),
                });
            },
        },
        bairros: {
            z: 30,
            minZoom: 0,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#3b82f6", width: 2 }),
                    fill: new ol.style.Fill({
                        color: "rgba(59, 130, 246, 0.1)",
                    }),
                });
                if (zoom >= 14) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "",
                            font: "bold 16px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#1e3a8a" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 4,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        loteamentos: {
            z: 35,
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "#2563eb",
                        width: 3,
                        lineDash: [8, 4],
                    }),
                    fill: new ol.style.Fill({
                        color: "rgba(37, 99, 235, 0.1)",
                    }),
                });
                if (zoom >= 14) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "Loteamento",
                            font: "bold 15px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#1e3a8a" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        quadras: {
            z: 40,
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#f97316", width: 1 }),
                    fill: new ol.style.Fill({
                        color: "rgba(249, 115, 22, 0.2)",
                    }),
                });
                if (zoom >= 16) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? "Q " + feature.get("name").toString()
                                : "",
                            font: "bold 14px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#9a3412" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        logradouros: {
            z: 50,
            minZoom: 14,
            style: new ol.style.Style({
                stroke: new ol.style.Stroke({ color: "#3675ce", width: 3 }),
            }),
        },
        postes: {
            z: 100,
            minZoom: 14,
            style: function (feature) {
                const condition = feature.get("structural_condition");
                let fillColor = "#eab308";
                if (condition === "Bom") fillColor = "#22c55e";
                if (condition === "Ruim") fillColor = "#ef4444";
                return new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 6,
                        fill: new ol.style.Fill({ color: fillColor }),
                        stroke: new ol.style.Stroke({
                            color: "#ffffff",
                            width: 2,
                        }),
                    }),
                });
            },
        },
        arvores: {
            z: 101,
            minZoom: 15,
            style: function (feature) {
                const condition = feature.get("phytosanitary_condition");
                const size = feature.get("size");
                let radius = size === "grande" ? 8 : 6;
                let fillColor = "#22c55e";
                if (condition === "Regular") fillColor = "#eab308";
                if (condition === "Ruim") fillColor = "#ef4444";
                if (condition === "Morta") fillColor = "#6b7280";
                return new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: radius,
                        fill: new ol.style.Fill({ color: fillColor }),
                        stroke: new ol.style.Stroke({
                            color: "#ffffff",
                            width: 2,
                        }),
                    }),
                });
            },
        },
        lotes: {
            z: 60,
            minZoom: 15.5,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#10b981", width: 1 }),
                    fill: new ol.style.Fill({
                        color: "rgba(16, 185, 129, 0.15)",
                    }),
                });
                if (zoom >= 18) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "",
                            font: "bold 12px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#064e3b" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },

        pontos_panoramicos: {
            style: new ol.style.Style({
                image: new ol.style.Icon({
                    src: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="%233b82f6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>',
                    scale: 1.0,
                    anchor: [0.5, 0.5],
                }),
            }),
            z: 100, // Câmeras sempre por cima!
            minZoom: 14, // Só aparece com zoom próximo
        },

        cemiterios: {
            z: 25,
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#9333ea", width: 2 }),
                    fill: new ol.style.Fill({
                        color: "rgba(147, 51, 234, 0.2)",
                    }),
                });
                if (zoom >= 15) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "Cemitério",
                            font: "bold 14px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#581c87" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        quadras_cemiterio: {
            z: 26,
            minZoom: 16,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "#6366f1",
                        width: 2,
                        lineDash: [4, 4],
                    }),
                    fill: new ol.style.Fill({
                        color: "rgba(99, 102, 241, 0.3)",
                    }),
                });
                if (zoom >= 17) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "Quadra",
                            font: "bold 13px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#312e81" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        logradouros_cemiterio: {
            z: 27,
            minZoom: 16,
            style: new ol.style.Style({
                stroke: new ol.style.Stroke({ color: "#64748b", width: 3 }),
            }),
        },
        jazigos: {
            z: 28,
            minZoom: 18,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#57534e", width: 1 }),
                    fill: new ol.style.Fill({ color: "rgba(87, 83, 78, 0.4)" }),
                });
                if (zoom >= 19.5) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "Jazigo",
                            font: "bold 11px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#1c1917" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 2,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        "rural-localidades": {
            z: 15,
            minZoom: 0,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "#57534e",
                        width: 2,
                        lineDash: [4, 4],
                    }),
                    fill: new ol.style.Fill({
                        color: "rgba(120, 113, 108, 0.2)",
                    }),
                });
                if (zoom >= 13) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "",
                            font: "bold 13px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#292524" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        "rural-propriedades": {
            z: 16,
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#f59e0b", width: 2 }),
                    fill: new ol.style.Fill({
                        color: "rgba(245, 158, 11, 0.2)",
                    }),
                });
                if (zoom >= 13) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "",
                            font: "bold 12px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#78350f" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        "rural-estradas": {
            z: 17,
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const pavimento = feature.get("tipo_pavimento");
                let strokeColor = "#78350f";
                let lineDash = [];
                if (pavimento === "Asfalto") strokeColor = "#374151";
                else if (pavimento === "Cascalho") lineDash = [4, 4];
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: strokeColor,
                        width: 4,
                        lineDash: lineDash,
                    }),
                });
                if (zoom >= 14) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "",
                            font: "bold 12px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: strokeColor }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            placement: "line",
                            textBaseline: "bottom",
                            offsetY: -5,
                        }),
                    );
                }
                return style;
            },
        },
        "rural-hidrografias": {
            z: 17,
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const geomType = feature.getGeometry().getType();
                let style;
                if (geomType === "Point" || geomType === "MultiPoint")
                    style = new ol.style.Style({
                        image: new ol.style.Circle({
                            radius: 6,
                            fill: new ol.style.Fill({ color: "#0ea5e9" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 2,
                            }),
                        }),
                    });
                else if (
                    geomType === "LineString" ||
                    geomType === "MultiLineString"
                )
                    style = new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: "#0ea5e9",
                            width: 3,
                        }),
                    });
                else
                    style = new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: "#0284c7",
                            width: 2,
                        }),
                        fill: new ol.style.Fill({
                            color: "rgba(14, 165, 233, 0.4)",
                        }),
                    });
                if (zoom >= 14 && feature.get("name")) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name").toString(),
                            font: "bold 12px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#0c4a6e" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            placement:
                                geomType === "LineString" ||
                                geomType === "MultiLineString"
                                    ? "line"
                                    : "point",
                            offsetY:
                                geomType === "Point" ||
                                geomType === "MultiPoint"
                                    ? -15
                                    : 0,
                        }),
                    );
                }
                return style;
            },
        },
        "rural-pontes": {
            z: 110,
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const estado = feature.get("estado_conservacao");
                let borderColor = "#f59e0b";
                if (estado === "Ruim") borderColor = "#ef4444";
                else if (estado === "Interditada") borderColor = "#000000";
                const style = new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 6,
                        fill: new ol.style.Fill({ color: "#78350f" }),
                        stroke: new ol.style.Stroke({
                            color: borderColor,
                            width: 2,
                        }),
                    }),
                });
                if (zoom >= 15) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "Ponte",
                            font: "bold 12px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#451a03" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            offsetY: -15,
                        }),
                    );
                }
                return style;
            },
        },
        "rural-pontos-interesse": {
            z: 120,
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const categoria = feature.get("categoria");
                let dotColor = "#14b8a6";
                if (categoria === "Escola") dotColor = "#3b82f6";
                else if (categoria === "Saúde") dotColor = "#ef4444";
                else if (categoria === "Igreja") dotColor = "#a855f7";
                else if (categoria === "Turismo") dotColor = "#f59e0b";
                else if (categoria === "Comércio") dotColor = "#84cc16";
                const style = new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 6,
                        fill: new ol.style.Fill({ color: dotColor }),
                        stroke: new ol.style.Stroke({
                            color: "#ffffff",
                            width: 2,
                        }),
                    }),
                });
                if (zoom >= 14) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "PoI",
                            font: "bold 12px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#1c1917" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            offsetY: -15,
                        }),
                    );
                }
                return style;
            },
        },

        setores_fiscais: {
            z: 22,
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "#f59e0b",
                        width: 3,
                        lineDash: [8, 8],
                    }),
                    fill: new ol.style.Fill({
                        color: "rgba(245, 158, 11, 0.15)",
                    }),
                });
                if (zoom >= 14) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "Setor Fiscal",
                            font: "bold 15px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#78350f" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },

        // ══ MOBILIDADE URBANA — 8 camadas SÓ LEITURA (D8, 2026-09-05), estilos da intranet ══
        mob_trechos: {
            z: 53,
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const principal = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: mobTrechoCor(feature), width: zoom >= 16 ? 5 : 3.5 }),
                });
                if (zoom >= 17.5) {
                    principal.setText(
                        new ol.style.Text({
                            text: "#" + (feature.get("sequential_id") ?? ""),
                            font: "bold 10px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#0c4a6e" }),
                            stroke: new ol.style.Stroke({ color: "#ffffff", width: 3 }),
                            placement: "line",
                            overflow: true,
                        }),
                    );
                }
                const estilos = [principal];
                if (zoom >= 16) estilos.push(...mobSetasDirecaoTrecho(feature, resolution));
                return estilos;
            },
        },
        mob_vias: {
            z: 54,
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const sentido = feature.get("sentido");
                const principal = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: mobViaCor(feature),
                        width: zoom >= 16 ? 5 : 3.5,
                        lineDash: sentido ? undefined : [6, 5],
                    }),
                });
                if (zoom >= 17.5 && feature.get("nome")) {
                    principal.setText(
                        new ol.style.Text({
                            text: String(feature.get("nome")),
                            font: "bold 10px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#1e3a8a" }),
                            stroke: new ol.style.Stroke({ color: "#ffffff", width: 3 }),
                            placement: "line",
                            overflow: true,
                        }),
                    );
                }
                const estilos = [principal];
                if ((sentido === "mao_unica" && zoom >= 15.5) || (sentido === "mao_dupla" && zoom >= 16.5)) {
                    estilos.push(...mobSetasSentido(feature, resolution, sentido === "mao_dupla"));
                }
                return estilos;
            },
        },
        mob_sinalizacoes: {
            z: 96,
            minZoom: 15,
            style: function (feature, resolution) {
                if (window.mobFiltrado("mob_sinalizacoes", feature.get("tipo_vh"))) return null;
                const zoom = view.getZoomForResolution(resolution);
                const cor = feature.get("cor") || "#9ca3af";
                const imagem = feature.get("tipo_vh") === "horizontal"
                    ? new ol.style.RegularShape({
                        points: 4,
                        radius: zoom >= 18 ? 8 : 6,
                        angle: Math.PI / 4,
                        fill: new ol.style.Fill({ color: cor }),
                        stroke: new ol.style.Stroke({ color: "#ffffff", width: 1.5 }),
                    })
                    : new ol.style.Circle({
                        radius: zoom >= 18 ? 7 : 5,
                        fill: new ol.style.Fill({ color: cor }),
                        stroke: new ol.style.Stroke({ color: "#ffffff", width: 1.5 }),
                    });
                const style = new ol.style.Style({ image: imagem });
                if (zoom >= 18.5) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name") || "",
                            font: "10px Arial, sans-serif",
                            offsetY: -14,
                            fill: new ol.style.Fill({ color: "#111827" }),
                            stroke: new ol.style.Stroke({ color: "#ffffff", width: 3 }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        mob_pontos_interesse: {
            z: 92,
            minZoom: 13,
            style: function (feature, resolution) {
                if (window.mobFiltrado("mob_pontos_interesse", feature.get("categoria"))) return null;
                const zoom = view.getZoomForResolution(resolution);
                const cor = MOB_POI_CORES[feature.get("categoria")] || "#64748b";
                const style = new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: zoom >= 16 ? 8 : 6,
                        fill: new ol.style.Fill({ color: cor }),
                        stroke: new ol.style.Stroke({ color: "#ffffff", width: 2 }),
                    }),
                });
                if (zoom >= 16.5) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name") || "",
                            font: "bold 10px Arial, sans-serif",
                            offsetY: -14,
                            fill: new ol.style.Fill({ color: "#1f2937" }),
                            stroke: new ol.style.Stroke({ color: "#ffffff", width: 3 }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        mob_cameras: {
            z: 97,
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const ativo = feature.get("ativo") !== false;
                const cor = ativo ? "#dc2626" : "#9ca3af";
                const estilos = [];
                const az = feature.get("azimute_visada");
                if (zoom >= 15 && az !== null && az !== undefined && az !== "") {
                    const c = feature.getGeometry().getCoordinates();
                    const r = 34 * resolution;
                    const a0 = (Number(az) * Math.PI) / 180;
                    const anel = [c];
                    for (let i = -25; i <= 25; i += 5) {
                        const t = a0 + (i * Math.PI) / 180;
                        anel.push([c[0] + r * Math.sin(t), c[1] + r * Math.cos(t)]);
                    }
                    anel.push(c);
                    estilos.push(
                        new ol.style.Style({
                            geometry: new ol.geom.Polygon([anel]),
                            fill: new ol.style.Fill({ color: ativo ? "rgba(220,38,38,0.18)" : "rgba(156,163,175,0.18)" }),
                            stroke: new ol.style.Stroke({ color: ativo ? "rgba(220,38,38,0.55)" : "rgba(156,163,175,0.55)", width: 1 }),
                            zIndex: 1,
                        }),
                    );
                }
                const principal = new ol.style.Style({
                    image: zoom >= 14.5 ? mobCameraIcone(cor, zoom >= 16 ? 1 : 0.8) : new ol.style.Circle({
                        radius: 6,
                        fill: new ol.style.Fill({ color: cor }),
                        stroke: new ol.style.Stroke({ color: "#ffffff", width: 2 }),
                    }),
                    zIndex: 2,
                });
                if (zoom >= 16) {
                    principal.setText(
                        new ol.style.Text({
                            text: feature.get("name") || "",
                            font: "bold 10px Arial, sans-serif",
                            offsetY: -20,
                            fill: new ol.style.Fill({ color: "#7f1d1d" }),
                            stroke: new ol.style.Stroke({ color: "#ffffff", width: 3 }),
                            overflow: true,
                        }),
                    );
                }
                estilos.push(principal);
                return estilos;
            },
        },
        mob_eixos: {
            z: 54,
            minZoom: 10,
            style: function (feature, resolution) {
                if (window.mobFiltrado("mob_eixos", feature.get("tipo"))) return null;
                const zoom = view.getZoomForResolution(resolution);
                const tipo = feature.get("tipo");
                const cor = MOB_EIXO_CORES[tipo] || "#0ea5e9";
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: cor,
                        width: tipo === "rodovia" ? 3 : 4.5,
                        lineDash: tipo === "ciclovia" ? [10, 6] : undefined,
                    }),
                });
                if (zoom >= 15) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name") || "",
                            font: "bold 10px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: cor }),
                            stroke: new ol.style.Stroke({ color: "#ffffff", width: 3 }),
                            placement: "line",
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        mob_zonas: {
            z: 28,
            minZoom: 9,
            style: function (feature, resolution) {
                if (window.mobFiltrado("mob_zonas", feature.get("tipo"))) return null;
                const zoom = view.getZoomForResolution(resolution);
                const tipo = feature.get("tipo");
                const cfg = MOB_ZONA_CORES[tipo] || MOB_ZONA_CORES.setor_censitario;
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: cfg.stroke,
                        width: tipo === "setor_censitario" ? 1 : 2,
                        lineDash: tipo === "setor_censitario" ? [4, 4] : undefined,
                    }),
                    fill: new ol.style.Fill({ color: cfg.fill }),
                });
                if (zoom >= 13 && tipo !== "setor_censitario") {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name") || "",
                            font: "bold 11px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: cfg.stroke }),
                            stroke: new ol.style.Stroke({ color: "#ffffff", width: 3 }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
        mob_fluxos: {
            z: 42,
            minZoom: 9,
            style: function (feature, resolution) {
                if (window.mobFiltrado("mob_fluxos", feature.get("destino_slug"))) return null;
                const zoom = view.getZoomForResolution(resolution);
                const valores = Number(feature.get("valores")) || 0;
                const cor = mobFluxoCor(feature);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: cor,
                        width: 1.5 + Math.min(9, Math.sqrt(valores) * 0.6),
                        lineCap: "round",
                    }),
                });
                const rotulo = mobFluxoRotulo(feature);
                if (zoom >= 13 && rotulo) {
                    style.setText(
                        new ol.style.Text({
                            text: rotulo,
                            font: "bold 11px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: cor }),
                            stroke: new ol.style.Stroke({ color: "#ffffff", width: 3 }),
                            placement: "line",
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },
    };

    // 5. INICIA O MAPA COM AS 3 CAMADAS BASE (Incluindo Ortofoto)
    const map = new ol.Map({
        target: "sigweb-map",
        layers: basemapLayers,
        view: view,
    });

    // ── ESCALA ATUAL DINÂMICA (PoC AC item 8) — espelho da intranet ─────────
    // Mesma convenção do irParaEscala (escala = resolution × 3780).
    const coordEscalaAtual = document.getElementById("coord-escala-atual");
    if (coordEscalaAtual) {
        const escalaFmt = new Intl.NumberFormat("pt-BR");
        const atualizarEscalaAtual = function () {
            const res = map.getView().getResolution();
            if (res) {
                coordEscalaAtual.textContent =
                    "Escala 1:" + escalaFmt.format(Math.round(res * 3780));
            }
        };
        map.getView().on("change:resolution", atualizarEscalaAtual);
        atualizarEscalaAtual();
    }

    // ── ZOOM EXTENSÃO + VISÃO ANTERIOR ──────────────────────────────
    // Guarda a view inicial diretamente do config do tenant
    const initialCenter = ol.proj.fromLonLat([config.mapLon, config.mapLat]);
    const initialZoom = config.mapZoom;

    // Histórico de navegação
    const viewHistory = [];
    let viewHistoryIndex = -1;
    let navegandoHistorico = false;

    map.getView().on("change:resolution", () => {
        if (navegandoHistorico) return;
        const v = map.getView();
        viewHistory.splice(viewHistoryIndex + 1);
        viewHistory.push({ center: v.getCenter().slice(), zoom: v.getZoom() });
        if (viewHistory.length > 50) viewHistory.shift();
        viewHistoryIndex = viewHistory.length - 1;
    });

    window.zoomExtensao = function () {
        map.getView().animate({
            center: initialCenter,
            zoom: initialZoom,
            duration: 600,
        });
    };

    window.visaoAnterior = function () {
        if (viewHistoryIndex <= 0) return;
        viewHistoryIndex--;
        navegandoHistorico = true;
        const v = viewHistory[viewHistoryIndex];
        map.getView().animate(
            { center: v.center, zoom: v.zoom, duration: 400 },
            () => {
                navegandoHistorico = false;
            },
        );
    };

    // Zoom in/out por botão (itens 24/25 do TR Internet)
    window.zoomMais = function () {
        const v = map.getView();
        v.animate({ zoom: (v.getZoom() || 0) + 1, duration: 250 });
    };
    window.zoomMenos = function () {
        const v = map.getView();
        v.animate({ zoom: (v.getZoom() || 0) - 1, duration: 250 });
    };
    // ────────────────────────────────────────────────────────────────

    const dblClickZoom = map
        .getInteractions()
        .getArray()
        .find((i) => i instanceof ol.interaction.DoubleClickZoom);
    if (dblClickZoom) map.removeInteraction(dblClickZoom);

    window.loadedLayers = {};

    // 6. SELETOR DE MAPA BASE — mesmo evento da intranet (Alpine despacha `switch-basemap`).
    //    Ortofoto liga o satélite por baixo para cobrir as bordas fora da área imageada.
    window.addEventListener("switch-basemap", (event) => {
        let selecionado = String(event.detail || "osm");
        if (!basemaps[selecionado]) {
            console.warn(`SIGWEB: Basemap "${selecionado}" não definido na engine pública.`);
            selecionado = "osm";
            window.dispatchEvent(new CustomEvent("sync-basemap-ui", { detail: "osm" }));
        }
        Object.keys(basemaps).forEach((key) => basemaps[key].setVisible(false));
        if (selecionado.startsWith("ortofoto")) {
            basemaps["esri_sat"].setVisible(true);
        }
        basemaps[selecionado].setVisible(true);
    });

    // 7. CARREGAMENTO DE CAMADAS (API AJAX ORIGINAL)
    const fetchAndDrawLayer = (layerName, checkboxElement) => {
        if (window.loadedLayers[layerName]) {
            window.loadedLayers[layerName].setVisible(true);
            return;
        }

        const textSpan =
            checkboxElement.nextElementSibling.querySelector(".layer-text");
        let originalText = "";
        if (textSpan) {
            originalText = textSpan.innerHTML;
            textSpan.innerHTML = "Carregando...";
            textSpan.classList.add("animate-pulse", "text-primary-500");
        }

        fetch(`/api/gis-data?tenant_id=${config.tenantId}&layer=${layerName}`)
            // 403 = módulo não contratado (defesa do backend); 404 = camada inexistente — nada a desenhar
            .then((response) => (response.ok ? response.json() : {}))
            .then((data) => {
                if (data && data.features && data.features.length > 0) {
                    const parsedFeatures = new ol.format.GeoJSON().readFeatures(
                        data,
                        { featureProjection: "EPSG:3857" },
                    );
                    parsedFeatures.forEach((f) => f.set("layer", layerName));

                    // Carrega as configurações de estilo exatas para aquela camada
                    const layerConf = layerConfigs[layerName];

                    const vectorLayer = new ol.layer.Vector({
                        source: new ol.source.Vector({
                            features: parsedFeatures,
                        }),
                        style: layerConf ? layerConf.style : null,
                        zIndex: layerConf ? layerConf.z : 10,
                        minZoom: layerConf ? layerConf.minZoom : 0,
                    });
                    map.addLayer(vectorLayer);
                    window.loadedLayers[layerName] = vectorLayer;
                    // Tema escolhido antes da camada carregar: monta a legenda agora
                    if (layerName === "mob_trechos" && window.mobTrechoTema) {
                        window.dispatchEvent(new CustomEvent("sigweb-mob-trecho-tema", { detail: { tema: window.mobTrechoTema } }));
                    }
                }
            })
            .catch((err) =>
                console.error(`Erro ao carregar ${layerName}:`, err),
            )
            .finally(() => {
                if (textSpan) {
                    textSpan.innerHTML = originalText;
                    textSpan.classList.remove(
                        "animate-pulse",
                        "text-primary-500",
                    );
                }
            });
    };

    document.querySelectorAll(".layer-toggle").forEach((checkbox) => {
        checkbox.addEventListener("change", function () {
            const layerName = this.getAttribute("data-layer");
            if (this.checked) fetchAndDrawLayer(layerName, this);
            else if (window.loadedLayers[layerName])
                window.loadedLayers[layerName].setVisible(false);
        });
        // Se já estiver marcado no HTML, carrega
        if (checkbox.checked)
            fetchAndDrawLayer(checkbox.getAttribute("data-layer"), checkbox);
    });

    document.querySelectorAll(".zona-toggle").forEach((checkbox) => {
        checkbox.addEventListener("change", function () {
            const sigla = this.getAttribute("data-zona-sigla");
            if (this.checked) {
                if (!zonasAtivas.includes(sigla)) zonasAtivas.push(sigla);
            } else {
                zonasAtivas = zonasAtivas.filter((s) => s !== sigla);
            }

            if (!window.loadedLayers["zonas"]) fetchAndDrawLayer("zonas", this);
            else {
                window.loadedLayers["zonas"].changed();
                window.loadedLayers["zonas"].setVisible(zonasAtivas.length > 0);
            }
        });
    });

    // ══ MOBILIDADE URBANA (só leitura) — sub-filtros, ficha e hover (D8, 2026-09-05) ══
    // Mini-checkboxes por tipo/categoria/destino: filtro de CLIENTE (mesmo mecanismo
    // .mob-sub-toggle da intranet). Ligar/desligar a camada-mãe sincroniza os filhos.
    document.addEventListener("change", (e) => {
        const el = e.target;
        if (!el.classList || !el.classList.contains("mob-sub-toggle")) return;
        const mobLayer = el.dataset.mobLayer;
        const valor = String(el.dataset.valor);
        if (!window._mobFiltros[mobLayer]) window._mobFiltros[mobLayer] = new Set();
        if (el.checked) window._mobFiltros[mobLayer].delete(valor);
        else window._mobFiltros[mobLayer].add(valor);
        if (window.loadedLayers[mobLayer]) window.loadedLayers[mobLayer].changed();
    });
    document.addEventListener("change", (e) => {
        const el = e.target;
        if (!el.classList || !el.classList.contains("layer-toggle")) return;
        const mobLayer = el.dataset.layer || "";
        if (!mobLayer.startsWith("mob_")) return;
        document
            .querySelectorAll('.mob-sub-toggle[data-mob-layer="' + mobLayer + '"]')
            .forEach((sub) => (sub.checked = el.checked));
        window._mobFiltros[mobLayer] = new Set();
        if (window.loadedLayers[mobLayer]) window.loadedLayers[mobLayer].changed();
        if (!el.checked) window.mobFichaFechar();
    });

    // "Colorir por" dos trechos: valor→cor a partir das feições carregadas + legenda
    // (#mob-trecho-legenda). Só visual, nada gravado — mesmo motor da intranet.
    window.addEventListener("sigweb-mob-trecho-tema", (e) => {
        const tema = e.detail && e.detail.tema ? e.detail.tema : null;
        window.mobTrechoTema = tema;
        window._mobTemaMap = {};
        const legenda = document.getElementById("mob-trecho-legenda");
        if (tema && window.loadedLayers["mob_trechos"]) {
            const valores = new Set();
            window.loadedLayers["mob_trechos"]
                .getSource()
                .getFeatures()
                .forEach((f) => valores.add(window.mobTrechoValorTema(f) ?? "—"));
            const ordenados = Array.from(valores).sort((a, b) => a.localeCompare(b, "pt-BR"));
            ordenados.forEach((v, i) => {
                window._mobTemaMap[v] = MOB_TEMA_PALETA[i % MOB_TEMA_PALETA.length];
            });
            if (legenda) {
                legenda.innerHTML = ordenados
                    .map(
                        (v) =>
                            '<span style="display:inline-flex;align-items:center;gap:4px;margin:2px 8px 2px 0;font-size:11px;">' +
                            '<i style="width:10px;height:10px;border-radius:2px;display:inline-block;background:' +
                            window._mobTemaMap[v] + ';"></i>' +
                            String(v).replace(/[<>&]/g, (c) => ({ "<": "&lt;", ">": "&gt;", "&": "&amp;" })[c]) +
                            "</span>",
                    )
                    .join("");
            }
        } else if (legenda) {
            legenda.innerHTML = "";
        }
        if (window.loadedLayers["mob_trechos"]) window.loadedLayers["mob_trechos"].changed();
    });

    const mobEsc = (s) => String(s ?? "").replace(/[<>&"]/g, (c) => ({ "<": "&lt;", ">": "&gt;", "&": "&amp;", '"': "&quot;" })[c]);
    const mobNum = (v, dec) =>
        v === null || v === undefined || v === "" || Number.isNaN(Number(v))
            ? null
            : Number(v).toLocaleString("pt-BR", { maximumFractionDigits: dec });
    const mobComUnidade = (v, dec, unidade) => {
        const n = mobNum(v, dec);
        return n === null ? null : n + " " + unidade;
    };
    const mobRotulo = (grupo, v) =>
        v === null || v === undefined || v === ""
            ? null
            : (MOB_ROTULOS[grupo] && MOB_ROTULOS[grupo][v]) || String(v).replace(/_/g, " ");
    const mobSentidoTexto = (s) =>
        s === "mao_unica" ? "Mão única (setas = sentido do fluxo)" : s === "mao_dupla" ? "Mão dupla" : "Sentido não classificado";
    const MOB_CAMADA_NOME = {
        mob_trechos: "Trecho viário (levantamento)",
        mob_vias: "Via urbana",
        mob_sinalizacoes: "Sinalização viária",
        mob_pontos_interesse: "Ponto de interesse",
        mob_eixos: "Eixo de mobilidade",
        mob_zonas: "Zona de estudo",
        mob_fluxos: "Fluxo origem → destino",
        mob_cameras: "Monitoramento em tempo real",
    };

    // Ficha SÓ LEITURA (painel #mob-ficha-publica do blade): linhas [rótulo, valor] por camada.
    // Fluxos: só percentuais (decisão 2026-09-04 — o volume absoluto fica na tela interna).
    window.mobFichaFechar = function () {
        const box = document.getElementById("mob-ficha-publica");
        if (box) box.style.display = "none";
    };
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") window.mobFichaFechar();
    });
    window.mobFichaPublica = function (feature) {
        const box = document.getElementById("mob-ficha-publica");
        if (!box) return;
        const layer = feature.get("layer");
        const g = (k) => feature.get(k);
        const linhas = [];
        const add = (rotulo, valor) => {
            if (valor !== null && valor !== undefined && valor !== "") linhas.push([rotulo, valor]);
        };
        let titulo = g("name") || "";

        if (layer === "mob_trechos") {
            add("Tipologia da via", g("tipologia_da_via"));
            add("Pavimentação", g("tipo_de_pavimentacao"));
            add("Estado da pavimentação", g("estado_conservacao_pavimentacao"));
            add("Classe (faixa de rodagem)", g("classe_faixa_rodagem"));
            add("Dimensionamento", g("dimensionamento_da_via"));
            add("Extensão", mobComUnidade(g("extensao_geo"), 1, "m"));
            // Campos do kit do município (calçadas, vegetação...) — rótulo = slug humanizado
            const custom = g("custom");
            if (custom && typeof custom === "object") {
                Object.keys(custom).forEach((k) => {
                    let v = custom[k];
                    if (Array.isArray(v)) v = v.join(", ");
                    if (v === null || v === undefined || v === "" || typeof v === "object") return;
                    add(k.replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase()), v);
                });
            }
            add("Observação", g("observacao"));
        } else if (layer === "mob_vias") {
            add("Sentido", mobSentidoTexto(g("sentido")));
            add("Extensão", mobComUnidade(g("extensao_geo"), 1, "m"));
        } else if (layer === "mob_sinalizacoes") {
            titulo = g("name") || "Sinalização";
            add("Posição", g("tipo_vh") === "horizontal" ? "Horizontal (no pavimento)" : "Vertical (placa)");
        } else if (layer === "mob_pontos_interesse") {
            add("Categoria", mobRotulo("poi", g("categoria")));
            add("Número", g("numero"));
        } else if (layer === "mob_eixos") {
            add("Tipo", mobRotulo("eixo", g("tipo")));
            add("Extensão", g("extensao_geo") ? mobComUnidade(g("extensao_geo") / 1000, 2, "km") : null);
        } else if (layer === "mob_zonas") {
            add("Tipo", mobRotulo("zona", g("tipo")));
            add("Código", g("codigo"));
            add("Situação", g("situacao"));
            add("Origens", mobComUnidade(g("origens"), 1, "% dos deslocamentos"));
            add("Destinos", mobComUnidade(g("destinos"), 1, "% dos deslocamentos"));
            add("Área", g("area_geo") ? mobComUnidade(g("area_geo") / 10000, 2, "ha") : null);
            add("População (Censo 2022)", mobNum(g("populacao"), 0));
            add("Densidade", mobComUnidade(g("densidade"), 2, "hab/ha"));
            add("Renda média", g("renda") ? "R$ " + mobNum(g("renda"), 2) : null);
        } else if (layer === "mob_fluxos") {
            add("Origem", g("origem_zona") || g("origem_regiao"));
            add("Destino", g("destino_zona") || "Sem zona");
            const pct = mobFluxoRotulo(feature);
            add("Participação", pct ? pct + " do total de deslocamentos" : "abaixo de 0,1% do total");
        }

        document.getElementById("mob-ficha-camada").textContent = MOB_CAMADA_NOME[layer] || "Mobilidade urbana";
        document.getElementById("mob-ficha-titulo").textContent = titulo || "—";
        const icone =
            layer === "mob_sinalizacoes" && g("icone")
                ? `<img src="${mobEsc(g("icone"))}" alt="" style="width:40px;height:40px;object-fit:contain;border-radius:8px;border:1px solid #e5e7eb;background:#fff;margin-bottom:8px;">`
                : "";
        // Grade rótulo | valor: o valor tem coluna própria e quebra por palavra (antes, na
        // caixa estreita, "Não possui" virava uma letra por linha)
        document.getElementById("mob-ficha-corpo").innerHTML =
            icone +
            (linhas.length
                ? linhas
                      .map(
                          ([r, v]) =>
                              `<div style="display:grid; grid-template-columns:minmax(170px, 38%) 1fr; gap:14px; padding:8px 0; border-top:1px solid #f3f4f6; align-items:start;"><span style="color:#6b7280;">${mobEsc(r)}</span><span style="font-weight:600; color:#111827; overflow-wrap:anywhere;">${mobEsc(v)}</span></div>`,
                      )
                      .join("")
                : '<p style="color:#9ca3af; margin:0;">Sem atributos cadastrados.</p>');
        box.style.display = "block";
    };

    // Hover das feições de mobilidade: destaque + texto do tooltip (espelho da intranet)
    function mobHoverInfo(feature) {
        const layer = feature.get("layer");
        const seq = feature.get("sequential_id");
        const titulo = mobEsc(feature.get("name") || (seq ? "#" + seq : ""));
        let detalhe = "";
        let estilo = null;
        const halo = (cor, w) => [
            new ol.style.Style({ stroke: new ol.style.Stroke({ color: "#ffffff", width: w + 3.5 }) }),
            new ol.style.Style({ stroke: new ol.style.Stroke({ color: cor, width: w }) }),
        ];
        const ponto = (cor, r) =>
            new ol.style.Style({
                image: new ol.style.Circle({
                    radius: r,
                    fill: new ol.style.Fill({ color: cor }),
                    stroke: new ol.style.Stroke({ color: "#f59e0b", width: 3 }),
                }),
            });
        const metros = (v) => (v ? " · " + Number(v).toLocaleString("pt-BR", { maximumFractionDigits: 1 }) + " m" : "");
        if (layer === "mob_trechos") {
            estilo = halo(mobTrechoCor(feature), 5.5);
            detalhe =
                "Trecho do levantamento" +
                (feature.get("tipo_de_pavimentacao") ? " · " + mobEsc(feature.get("tipo_de_pavimentacao")) : "") +
                metros(feature.get("extensao_geo"));
        } else if (layer === "mob_vias") {
            estilo = halo(mobViaCor(feature), 5.5);
            detalhe = mobSentidoTexto(feature.get("sentido")) + metros(feature.get("extensao_geo"));
        } else if (layer === "mob_eixos") {
            estilo = halo(MOB_EIXO_CORES[feature.get("tipo")] || "#0ea5e9", 6);
            const ext = feature.get("extensao_geo");
            detalhe =
                mobEsc(mobRotulo("eixo", feature.get("tipo")) || "") +
                (ext ? " · " + (ext / 1000).toLocaleString("pt-BR", { maximumFractionDigits: 2 }) + " km" : "");
        } else if (layer === "mob_fluxos") {
            const vol = Number(feature.get("valores")) || 0;
            const w = 4 + Math.min(9, Math.sqrt(vol) * 0.6);
            estilo = [
                new ol.style.Style({ stroke: new ol.style.Stroke({ color: "#ffffff", width: w + 3, lineCap: "round" }) }),
                new ol.style.Style({ stroke: new ol.style.Stroke({ color: mobFluxoCor(feature), width: w, lineCap: "round" }) }),
            ];
            const pct = mobFluxoRotulo(feature);
            detalhe = (pct ? "<strong>" + pct + "</strong> do total de deslocamentos" : "abaixo de 0,1% do total") + " · clique para a ficha";
        } else if (layer === "mob_sinalizacoes") {
            estilo = ponto(feature.get("cor") || "#9ca3af", 10);
            detalhe = feature.get("tipo_vh") === "horizontal" ? "Sinalização horizontal" : "Sinalização vertical";
        } else if (layer === "mob_pontos_interesse") {
            estilo = ponto(MOB_POI_CORES[feature.get("categoria")] || "#64748b", 11);
            detalhe = mobEsc(mobRotulo("poi", feature.get("categoria")) || "");
        } else if (layer === "mob_cameras") {
            estilo = ponto(feature.get("ativo") === false ? "#9ca3af" : "#dc2626", 13);
            detalhe =
                (feature.get("ativo") === false ? "Câmera inativa" : "&#128308; Ao vivo — clique para assistir") +
                (feature.get("provedor") ? " · " + mobEsc(feature.get("provedor")) : "");
        } else if (layer === "mob_zonas") {
            const cfg = MOB_ZONA_CORES[feature.get("tipo")] || MOB_ZONA_CORES.setor_censitario;
            estilo = new ol.style.Style({
                stroke: new ol.style.Stroke({ color: cfg.stroke, width: 3.5 }),
                fill: new ol.style.Fill({ color: cfg.fill.replace(/0\.\d+\)$/, "0.25)") }),
            });
            detalhe = mobEsc(mobRotulo("zona", feature.get("tipo")) || "") + " · clique para a ficha";
        }
        return { titulo, detalhe, estilo };
    }

    // 8. INTERFACE JANELA DE CAMADAS
    const panel = document.getElementById("layers-panel");
    const btnToggleLayers = document.getElementById("btn-toggle-layers");
    if (btnToggleLayers && panel) {
        btnToggleLayers.addEventListener("click", () =>
            panel.classList.toggle("hidden"),
        );
    }

    // 9. EVENTO DE BUSCA (VOAR PARA LOTE - CORRIGIDO!)
    // O seu Alpine.js já faz a busca na API e manda o resultado no 'detail.coords'
    window.addEventListener("voar-para-lote", (e) => {
        const data = e.detail;
        if (data && data.coords) {
            const targetCoords = ol.proj.fromLonLat([
                data.coords[0],
                data.coords[1],
            ]);
            view.animate({ center: targetCoords, zoom: 20, duration: 2000 });

            // Destaca o ponto da busca
            querySource.clear();
            const pointFeature = new ol.Feature(
                new ol.geom.Point(targetCoords),
            );
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
            stroke: new ol.style.Stroke({ color: "#f59e0b", width: 4 }), // Laranja forte
            fill: new ol.style.Fill({ color: "rgba(245, 158, 11, 0.4)" }),
            image: new ol.style.Circle({
                radius: 8,
                fill: new ol.style.Fill({ color: "#f59e0b" }),
                stroke: new ol.style.Stroke({ color: "#ffffff", width: 2 }),
            }),
        }),
        zIndex: 9999,
    });
    map.addLayer(queryLayer);

    // =========================================================================
    // T1.10c (itens 3-18/3-20/3-23): TEMATIZAÇÃO POR VALORES ÚNICOS (PÚBLICO)
    // Uma cor por valor distinto; legenda com cores editáveis (item 3-20).
    // =========================================================================
    window.vuColorMap = {};
    window.vuFiltroId = null;

    function vuHexToRgba(hex, alpha) {
        const h = hex.replace("#", "");
        const r = parseInt(h.substring(0, 2), 16);
        const g = parseInt(h.substring(2, 4), 16);
        const b = parseInt(h.substring(4, 6), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function vuCorAutomatica(indice) {
        const hue = (indice * 137.508) % 360;
        const h = hue / 360, s = 0.65, l = 0.52;
        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        const p = 2 * l - q;
        const cor = [h + 1 / 3, h, h - 1 / 3].map((t) => {
            if (t < 0) t += 1;
            if (t > 1) t -= 1;
            if (t < 1 / 6) return p + (q - p) * 6 * t;
            if (t < 1 / 2) return q;
            if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
            return p;
        });
        return "#" + cor.map((c) => Math.round(c * 255).toString(16).padStart(2, "0")).join("");
    }

    function vuEstiloDoValor(valor) {
        const cor = window.vuColorMap[valor] || "#9ca3af";
        return new ol.style.Style({
            fill: new ol.style.Fill({ color: vuHexToRgba(cor, 0.65) }),
            stroke: new ol.style.Stroke({ color: cor, width: 2 }),
            image: new ol.style.Circle({
                radius: 7,
                fill: new ol.style.Fill({ color: cor }),
                stroke: new ol.style.Stroke({ color: "#ffffff", width: 1.5 }),
            }),
        });
    }

    window.vuLimpar = function () {
        if (window.vuFiltroId) {
            querySource
                .getFeatures()
                .filter((f) => f.get("filtro_id") === window.vuFiltroId)
                .forEach((f) => querySource.removeFeature(f));
            window.filtrosAtivos = (window.filtrosAtivos || []).filter((fl) => fl.id !== window.vuFiltroId);
            if (window.atualizarPainelFiltros) window.atualizarPainelFiltros();
        }
        document.getElementById("legenda-valores-unicos")?.remove();
        window.vuColorMap = {};
        window.vuFiltroId = null;
    };

    function vuRenderLegenda(valores, atributoLabel) {
        let box = document.getElementById("legenda-valores-unicos");
        if (!box) {
            box = document.createElement("div");
            box.id = "legenda-valores-unicos";
            // position:FIXED — sempre visível na viewport
            box.style.cssText =
                "position:fixed;bottom:24px;right:12px;z-index:60;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.15);padding:10px 12px;max-height:46vh;overflow-y:auto;min-width:200px;max-width:280px;font-size:12px;";
            document.body.appendChild(box);
        }

        let html =
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">' +
            '<strong style="font-size:12px;">' + atributoLabel + "</strong>" +
            '<button type="button" onclick="window.vuLimpar()" style="border:none;background:none;color:#9ca3af;cursor:pointer;font-size:14px;" title="Fechar tematização">✕</button>' +
            "</div>";

        // Máx. 30 valores editáveis (atributo contínuo geraria milhares de linhas)
        const LIMITE_LEGENDA = 30;
        const visiveis = valores.slice(0, LIMITE_LEGENDA);
        const ocultos = valores.length - visiveis.length;

        visiveis.forEach((v) => {
            html +=
                '<div style="display:flex;align-items:center;gap:6px;margin:3px 0;">' +
                '<input type="color" value="' + window.vuColorMap[v.valor] + '" data-vu-valor="' + encodeURIComponent(v.valor) + '" ' +
                'style="width:22px;height:22px;border:none;padding:0;background:none;cursor:pointer;" />' +
                '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + v.valor + '">' + v.valor + "</span>" +
                '<span style="color:#6b7280;">' + v.quantidade + "</span>" +
                "</div>";
        });

        if (ocultos > 0) {
            html +=
                '<div style="margin-top:6px;padding-top:6px;border-top:1px solid #f3f4f6;color:#9ca3af;font-size:11px;">' +
                "+" + ocultos + " valores com cores automáticas.<br>" +
                "Para atributos numéricos, prefira a <strong>Tematização por Intervalo</strong>." +
                "</div>";
        }

        box.innerHTML = html;

        box.querySelectorAll("input[type=color]").forEach((inp) => {
            inp.addEventListener("input", function () {
                const valor = decodeURIComponent(this.getAttribute("data-vu-valor"));
                window.vuColorMap[valor] = this.value;
                querySource.getFeatures().forEach((f) => {
                    if (f.get("filtro_id") === window.vuFiltroId && f.get("valor_unico") === valor) {
                        f.setStyle(vuEstiloDoValor(valor));
                    }
                });
            });
        });
    }

    window.addEventListener("executar-tematizacao-valores-unicos", async (e) => {
        const dados = e.detail.dados || e.detail;
        const url = `/api/mapa/advanced-query?tenant_id=${config.tenantId}&tipo_filtro=valores_unicos&layer=${dados.vu_layer}&vu_attribute=${encodeURIComponent(dados.vu_attribute)}`;

        const resp = await fetch(url);
        const data = await resp.json();

        if (data.error) { alert("Erro: " + data.error); return; }

        // Blindagem: geometria vazia derruba o readFeatures do OL
        data.features = (data.features || []).filter(
            (f) => f.geometry && Array.isArray(f.geometry.coordinates) && f.geometry.coordinates.length,
        );

        if (data.features.length === 0) {
            alert("Nenhum dado encontrado para tematizar.");
            return;
        }

        window.vuLimpar();

        window.vuColorMap = {};
        (data.valores || []).forEach((v, i) => {
            window.vuColorMap[v.valor] = vuCorAutomatica(i);
        });

        const features = new ol.format.GeoJSON().readFeatures(data, {
            dataProjection: "EPSG:4326",
            featureProjection: "EPSG:3857",
        });

        window.vuFiltroId = "filtro_" + Date.now();
        features.forEach((f) => {
            f.setStyle(vuEstiloDoValor(f.get("valor_unico")));
            f.set("filtro_id", window.vuFiltroId);
        });
        querySource.addFeatures(features);

        vuRenderLegenda(data.valores || [], data.atributo_label || dados.vu_attribute);

        window.filtrosAtivos = window.filtrosAtivos || [];
        window.filtrosAtivos.push({
            id: window.vuFiltroId,
            descricao: `Valores Únicos: ${dados.vu_layer} por ${data.atributo_label || dados.vu_attribute}`,
            cor: Object.values(window.vuColorMap)[0] || "#9ca3af",
            total: features.length,
        });
        if (window.atualizarPainelFiltros) window.atualizarPainelFiltros();

        map.getView().fit(querySource.getExtent(), { padding: [50, 50, 50, 50], duration: 1000 });
    });

    // =========================================================================
    // FILTRO AVANÇADO — Atributo, Espacial e Desenho (com cor customizada)
    // =========================================================================
    window.addEventListener("executar-filtro-avancado", (e) => {
        const dados = e.detail[0] || e.detail.dados || e.detail;

        const queryParams = new URLSearchParams({ tenant_id: config.tenantId });

        if (dados.tipo_filtro === "espacial") {
            queryParams.set("tipo_filtro", "espacial");
            queryParams.set("spatial_target_layer", dados.spatial_target_layer);
            queryParams.set("spatial_operator", dados.spatial_operator);
            queryParams.set(
                "spatial_reference_layer",
                dados.spatial_reference_layer,
            );
            const ids = Array.isArray(dados.spatial_reference_id)
                ? dados.spatial_reference_id
                : [dados.spatial_reference_id];
            ids.forEach((id) =>
                queryParams.append("spatial_reference_ids[]", id),
            );
        } else {
            queryParams.set("tipo_filtro", "atributo");
            queryParams.set("layer", dados.layer);
            queryParams.set("field", dados.field);
            queryParams.set("operator", dados.operator);
            queryParams.set("value", dados.value);
        }

        fetch(`/api/mapa/advanced-query?${queryParams.toString()}`)
            .then(async (response) => {
                if (!response.ok) throw new Error(`Erro ${response.status}`);
                return response.json();
            })
            .then((data) => {
                if (data.error) {
                    alert("Erro: " + data.error);
                    return;
                }

                if (data.features && data.features.length > 0) {
                    const features = new ol.format.GeoJSON().readFeatures(
                        data,
                        {
                            dataProjection: "EPSG:4326",
                            featureProjection: "EPSG:3857",
                        },
                    );

                    const corHex = dados.cor_tematizacao || "#f59e0b";
                    const estiloCustomizado = new ol.style.Style({
                        fill: new ol.style.Fill({
                            color: hexToRgba(corHex, 0.4),
                        }),
                        stroke: new ol.style.Stroke({
                            color: corHex,
                            width: 4,
                        }),
                        image: new ol.style.Circle({
                            radius: 8,
                            fill: new ol.style.Fill({ color: corHex }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 2,
                            }),
                        }),
                    });

                    const filtroId = "filtro_" + Date.now();
                    features.forEach((f) => {
                        f.setStyle(estiloCustomizado);
                        f.set("estilo_customizado", estiloCustomizado);
                        f.set("filtro_id", filtroId);
                    });
                    querySource.addFeatures(features);

                    let descricao = "";
                    if (dados.tipo_filtro === "espacial") {
                        descricao = `${dados.spatial_target_layer} em ${dados.spatial_reference_layer}`;
                    } else {
                        descricao = `${dados.layer}: ${dados.field} ${dados.operator} "${dados.value}"`;
                    }

                    window.filtrosAtivos = window.filtrosAtivos || [];
                    window.filtrosAtivos.push({
                        id: filtroId,
                        descricao,
                        cor: corHex,
                        total: features.length,
                    });
                    window.atualizarPainelFiltros();

                    map.getView().fit(querySource.getExtent(), {
                        padding: [50, 50, 50, 50],
                        duration: 1000,
                        maxZoom: 19,
                    });
                } else {
                    alert("Nenhum artefato encontrado com esses critérios.");
                }
            })
            .catch((err) => {
                console.error("Erro no filtro:", err);
                alert("Falha ao executar o filtro.");
            });
    });

    // =========================================================================
    // DESENHO LIVRE
    // =========================================================================
    const drawFiltroSource = new ol.source.Vector();
    const drawFiltroLayer = new ol.layer.Vector({
        source: drawFiltroSource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({
                color: "#a855f7",
                width: 2,
                lineDash: [6, 4],
            }),
            fill: new ol.style.Fill({ color: "rgba(168, 85, 247, 0.15)" }),
        }),
        zIndex: 9998,
    });
    map.addLayer(drawFiltroLayer);
    let drawFiltroInteraction = null;

    window.addEventListener("iniciar-desenho-filtro", (e) => {
        const dados = e.detail[0] || e.detail.dados || e.detail;

        if (drawFiltroInteraction) map.removeInteraction(drawFiltroInteraction);
        drawFiltroSource.clear();

        const tipo = dados.draw_shape === "Box" ? "Circle" : "Polygon";
        const geometryFunction =
            dados.draw_shape === "Box"
                ? ol.interaction.Draw.createBox()
                : undefined;

        drawFiltroInteraction = new ol.interaction.Draw({
            source: drawFiltroSource,
            type: tipo,
            geometryFunction: geometryFunction,
            style: new ol.style.Style({
                stroke: new ol.style.Stroke({
                    color: "#a855f7",
                    width: 2,
                    lineDash: [6, 4],
                }),
                fill: new ol.style.Fill({ color: "rgba(168, 85, 247, 0.15)" }),
            }),
        });

        drawFiltroInteraction.on("drawend", (evt) => {
            map.removeInteraction(drawFiltroInteraction);
            drawFiltroInteraction = null;

            const geom = evt.feature
                .getGeometry()
                .clone()
                .transform("EPSG:3857", "EPSG:4326");
            const drawnGeoJson = new ol.format.GeoJSON().writeGeometryObject(
                geom,
            );
            const drawOperator = dados.draw_within
                ? "ST_Within"
                : "ST_Intersects";
            const targetLayer = dados.draw_target_layer;
            const corHex = dados.cor_tematizacao || "#f59e0b";

            const url = `/api/mapa/advanced-query?tenant_id=${config.tenantId}&tipo_filtro=desenho&draw_target_layer=${targetLayer}&draw_spatial_operator=${drawOperator}&drawn_geometry=${encodeURIComponent(JSON.stringify(drawnGeoJson))}`;

            fetch(url)
                .then((r) => r.json())
                .then((data) => {
                    if (data.error) {
                        alert("Erro: " + data.error);
                        return;
                    }
                    if (data.features && data.features.length > 0) {
                        const features = new ol.format.GeoJSON().readFeatures(
                            data,
                            {
                                dataProjection: "EPSG:4326",
                                featureProjection: "EPSG:3857",
                            },
                        );
                        const estiloCustomizado = new ol.style.Style({
                            fill: new ol.style.Fill({
                                color: hexToRgba(corHex, 0.4),
                            }),
                            stroke: new ol.style.Stroke({
                                color: corHex,
                                width: 4,
                            }),
                            image: new ol.style.Circle({
                                radius: 8,
                                fill: new ol.style.Fill({ color: corHex }),
                                stroke: new ol.style.Stroke({
                                    color: "#ffffff",
                                    width: 2,
                                }),
                            }),
                        });
                        const filtroId = "filtro_" + Date.now();
                        features.forEach((f) => {
                            f.setStyle(estiloCustomizado);
                            f.set("estilo_customizado", estiloCustomizado);
                            f.set("filtro_id", filtroId);
                        });
                        querySource.addFeatures(features);

                        window.filtrosAtivos = window.filtrosAtivos || [];
                        window.filtrosAtivos.push({
                            id: filtroId,
                            descricao: `${targetLayer} por área desenhada`,
                            cor: corHex,
                            total: features.length,
                        });
                        window.atualizarPainelFiltros();

                        map.getView().fit(querySource.getExtent(), {
                            padding: [50, 50, 50, 50],
                            duration: 1000,
                            maxZoom: 19,
                        });
                    } else {
                        alert(
                            "Nenhum artefato encontrado dentro da área desenhada.",
                        );
                    }
                })
                .catch((err) => {
                    console.error("Erro no desenho:", err);
                    alert("Falha ao buscar artefatos na área.");
                });
        });

        map.addInteraction(drawFiltroInteraction);
    });

    // =========================================================================
    // TEMATIZAÇÃO POR INTERVALO DE CLASSES (CHOROPLETH)
    // =========================================================================
    window.addEventListener("executar-tematizacao-intervalo", async (e) => {
        const dados = e.detail.dados || e.detail;
        const layer = dados.interval_layer;
        const attr = dados.interval_attribute;
        const nClasses = parseInt(dados.num_classes);

        const response = await fetch(
            `/api/mapa/advanced-query?tenant_id=${config.tenantId}&tipo_filtro=intervalo&layer=${layer}&interval_attribute=${attr}`,
        );
        const data = await response.json();

        if (!data.features || data.features.length === 0) {
            alert("Não há dados suficientes para criar o mapa temático.");
            return;
        }

        const valores = data.features
            .map((f) => {
                const v = parseFloat(f.properties[attr]);
                if (v && v > 0) return v;
                if (attr === "area_geo" && f.geometry?.type === "Polygon") {
                    const coords = f.geometry.coordinates[0];
                    let area = 0;
                    for (
                        let i = 0, j = coords.length - 1;
                        i < coords.length;
                        j = i++
                    ) {
                        area +=
                            (coords[j][0] + coords[i][0]) *
                            (coords[j][1] - coords[i][1]);
                    }
                    return Math.abs(area / 2) * 1e10;
                }
                return 0;
            })
            .filter((v) => v > 0);

        if (valores.length === 0) {
            alert(`Nenhum valor numérico encontrado no atributo "${attr}".`);
            return;
        }

        const min = Math.min(...valores);
        const max = Math.max(...valores);
        const range = (max - min) / nClasses;

        function hexToRgb(hex) {
            const h = hex.replace("#", "");
            return [
                parseInt(h.substring(0, 2), 16),
                parseInt(h.substring(2, 4), 16),
                parseInt(h.substring(4, 6), 16),
            ];
        }
        function rgbToHex(r, g, b) {
            return (
                "#" +
                [r, g, b]
                    .map((v) => Math.round(v).toString(16).padStart(2, "0"))
                    .join("")
            );
        }
        function gerarGradiente(hexInicio, hexFim, steps) {
            const [r1, g1, b1] = hexToRgb(hexInicio);
            const [r2, g2, b2] = hexToRgb(hexFim);
            return Array.from({ length: steps }, (_, i) => {
                const t = steps === 1 ? 0 : i / (steps - 1);
                return rgbToHex(
                    r1 + (r2 - r1) * t,
                    g1 + (g2 - g1) * t,
                    b1 + (b2 - b1) * t,
                );
            });
        }

        const corInicio = dados.cor_inicio || "#ffffb2";
        const corFim = dados.cor_fim || "#800026";
        const colors = gerarGradiente(corInicio, corFim, nClasses);

        const features = new ol.format.GeoJSON().readFeatures(data, {
            dataProjection: "EPSG:4326",
            featureProjection: "EPSG:3857",
        });

        const filtroId = "filtro_" + Date.now();
        features.forEach((f) => {
            let val = parseFloat(f.get(attr)) || 0;
            if (val === 0 && attr === "area_geo") {
                const geom = f.getGeometry();
                if (geom) val = ol.sphere.getArea(geom);
            }
            let idx = Math.floor((val - min) / range);
            if (idx >= nClasses) idx = nClasses - 1;
            if (idx < 0) idx = 0;

            const cor = colors[idx];
            const estilo = new ol.style.Style({
                fill: new ol.style.Fill({ color: hexToRgba(cor, 0.7) }),
                stroke: new ol.style.Stroke({ color: "#ffffff", width: 1 }),
            });
            f.setStyle(estilo);
            f.set("estilo_customizado", estilo);
            f.set("filtro_id", filtroId);
        });

        querySource.addFeatures(features);
        map.getView().fit(querySource.getExtent(), {
            padding: [50, 50, 50, 50],
            duration: 1000,
        });

        window.filtrosAtivos = window.filtrosAtivos || [];
        window.filtrosAtivos.push({
            id: filtroId,
            descricao: `Intervalo: ${layer} por ${attr} (${nClasses} faixas)`,
            cor: corInicio + "→" + corFim,
            total: features.length,
            gradiente: true,
            cores: colors,
        });
        window.atualizarPainelFiltros();
    });

    // =========================================================================
    // GERENCIADOR DE FILTROS ATIVOS + LIMPAR
    // =========================================================================
    window.filtrosAtivos = [];

    window.atualizarPainelFiltros = function () {
        const painel = document.getElementById("painel-filtros-ativos");
        if (!painel) return;
        if (!window.filtrosAtivos.length) {
            painel.style.display = "none";
            return;
        }
        painel.style.display = "block";
        const lista = document.getElementById("lista-filtros-ativos");
        lista.innerHTML = window.filtrosAtivos
            .map(
                (f) => `
            <div style="display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:8px;background:rgba(255,255,255,0.08);font-size:11px;color:#e5e7eb;">
                <span style="display:inline-block;width:24px;height:12px;border-radius:3px;flex-shrink:0;${f.gradiente ? `background:linear-gradient(to right,${f.cores.join(",")})` : `background:${f.cor}`};"></span>
                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${f.descricao}">${f.descricao} <span style="opacity:0.6">(${f.total})</span></span>
                <button onclick="window.removerFiltro('${f.id}')" style="background:none;border:none;cursor:pointer;padding:0;color:#f87171;font-size:14px;line-height:1;">✕</button>
            </div>
        `,
            )
            .join("");
    };

    window.removerFiltro = function (filtroId) {
        querySource
            .getFeatures()
            .filter((f) => f.get("filtro_id") === filtroId)
            .forEach((f) => querySource.removeFeature(f));
        window.filtrosAtivos = window.filtrosAtivos.filter(
            (f) => f.id !== filtroId,
        );
        window.atualizarPainelFiltros();
        if (!window.filtrosAtivos.length && window.Livewire)
            Livewire.dispatch("filtros-zerados");
    };

    window.addEventListener("limpar-filtro-avancado", () => {
        querySource.clear();
        drawFiltroSource.clear();
        window.filtrosAtivos = [];
        window.atualizarPainelFiltros();
        // T1.10c — limpa também a legenda de Valores Únicos
        document.getElementById("legenda-valores-unicos")?.remove();
        window.vuColorMap = {};
        window.vuFiltroId = null;
    });

    // 11. TOOLTIPS E HOVER (MÃOZINHA)
    const featureTooltip = document.getElementById("feature-tooltip");
    let hoveredFeature = null;

    map.on("pointermove", function (e) {
        if (currentMeasureInteraction) return; // Não interfere se estiver com a reguinha ligada

        // Limpa o destaque anterior
        if (hoveredFeature) {
            hoveredFeature.setStyle(undefined);
            hoveredFeature = null;
        }

        let hitFiltro = false;
        let featureNormal = null;

        // Detecta o que está debaixo do mouse
        map.forEachFeatureAtPixel(
            e.pixel,
            function (f) {
                if (f.get("titulo") && f.get("info")) {
                    hitFiltro = true; // É um resultado da pesquisa/filtro avançado
                    if (featureTooltip) {
                        featureTooltip.innerHTML = `<div style="font-size:14px; font-weight:900;">${f.get("titulo")}</div><div style="font-size:10px; color:#cbd5e1;">${f.get("info")}</div>`;
                    }
                } else {
                    featureNormal = f; // É um artefato normal (Rua, Lote, Bairro...)
                }
            },
            { hitTolerance: 5 },
        );

        // Lógica de exibição da caixinha
        if (hitFiltro) {
            featureTooltip.style.left = e.originalEvent.clientX + 15 + "px";
            featureTooltip.style.top = e.originalEvent.clientY + 15 + "px";
            featureTooltip.style.display = "block";
            map.getTargetElement().style.cursor = "pointer";
        } else if (featureNormal) {
            const layer = featureNormal.get("layer");
            const name =
                featureNormal.get("name") || featureNormal.get("titulo");

            // Define quais camadas mudam o cursor para "mãozinha"
            const hoverableLayers = [
                "lotes",
                "logradouros",
                "bairros",
                "quadras",
                "cemiterios",
                "rural-estradas",
                "pontos_panoramicos",
            ];
            const ehMob = !!(layer && layer.startsWith("mob_"));
            map.getTargetElement().style.cursor = hoverableLayers.includes(layer) || ehMob ? "pointer" : "";

            if (ehMob) {
                // ── MOBILIDADE URBANA (só leitura): destaque + tooltip, espelho da intranet ──
                hoveredFeature = featureNormal;
                const dica = mobHoverInfo(featureNormal);
                if (dica.estilo) featureNormal.setStyle(dica.estilo);
                if (featureTooltip) {
                    featureTooltip.innerHTML =
                        `<div style="font-size:12px; font-weight:bold;">${dica.titulo}</div>` +
                        (dica.detalhe ? `<div style="font-size:10px; color:#cbd5e1;">${dica.detalhe}</div>` : "");
                    featureTooltip.style.display = "block";
                    featureTooltip.style.left = e.originalEvent.clientX + 15 + "px";
                    featureTooltip.style.top = e.originalEvent.clientY + 15 + "px";
                }
            } else if (layer === "logradouros" || layer === "rural-estradas") {
                // Se for Logradouro (Rua), acende de azul e mostra a caixinha com o nome
                hoveredFeature = featureNormal;

                // Estilo de destaque (Rua mais grossa e azul claro)
                featureNormal.setStyle(
                    new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: "#38bdf8",
                            width: 6,
                        }),
                    }),
                );

                if (featureTooltip && name) {
                    featureTooltip.innerHTML = `<div style="font-size:12px; font-weight:bold;">${name}</div>`;
                    featureTooltip.style.display = "block";
                    featureTooltip.style.left =
                        e.originalEvent.clientX + 15 + "px";
                    featureTooltip.style.top =
                        e.originalEvent.clientY + 15 + "px";
                }
            } else {
                if (featureTooltip) featureTooltip.style.display = "none";
            }
        } else {
            map.getTargetElement().style.cursor = "";
            if (featureTooltip) featureTooltip.style.display = "none";
        }
    });

    // 12. FERRAMENTAS DE MEDIÇÃO
    const measureTooltipElement = document.getElementById("measure-tooltip");
    const measureOverlay = new ol.Overlay({
        element: measureTooltipElement,
        offset: [0, -15],
        positioning: "bottom-center",
    });
    map.addOverlay(measureOverlay);

    const drawSource = new ol.source.Vector();
    const drawLayer = new ol.layer.Vector({
        source: drawSource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({
                color: "#ef4444",
                width: 3,
                lineDash: [5, 5],
            }),
            fill: new ol.style.Fill({ color: "rgba(239, 68, 68, 0.2)" }),
        }),
        zIndex: 9999,
    });
    map.addLayer(drawLayer);

    let currentMeasureInteraction = null;

    // ÍMÃ (SNAP) NAS MEDIÇÕES — espelho do enableUniversalSnap da intranet
    // (item Internet 15): gruda em vértice/aresta (fim) e no MEIO de cada
    // segmento das camadas visíveis. Ligado só enquanto a régua está ativa.
    let medicaoSnaps = [];
    const medicaoMidpointSource = new ol.source.Vector();

    function medicaoSegmentMidpoints(geometry) {
        const mids = [];
        const t = geometry.getType();
        const ring = (coords) => {
            for (let i = 0; i < coords.length - 1; i++) {
                const a = coords[i],
                    b = coords[i + 1];
                if (a && b && a.length >= 2 && b.length >= 2) {
                    mids.push([(a[0] + b[0]) / 2, (a[1] + b[1]) / 2]);
                }
            }
        };
        if (t === "LineString") ring(geometry.getCoordinates());
        else if (t === "MultiLineString" || t === "Polygon")
            geometry.getCoordinates().forEach(ring);
        else if (t === "MultiPolygon")
            geometry.getCoordinates().forEach((poly) => poly.forEach(ring));
        return mids;
    }

    function desligarSnapMedicao() {
        medicaoSnaps.forEach((snap) => map.removeInteraction(snap));
        medicaoSnaps = [];
        medicaoMidpointSource.clear();
    }

    function ligarSnapMedicao() {
        desligarSnapMedicao();

        Object.keys(window.loadedLayers).forEach((layerName) => {
            const layer = window.loadedLayers[layerName];
            if (!layer || !layer.getVisible() || !layer.getSource) return;

            const source = layer.getSource();
            if (!(source instanceof ol.source.Vector)) return;

            const snap = new ol.interaction.Snap({
                source: source,
                pixelTolerance: 12,
            });
            map.addInteraction(snap);
            medicaoSnaps.push(snap);

            // Meio de segmento — guarda de performance p/ camadas gigantes
            // (o snap de vértice/aresta acima continua valendo).
            const feats = source.getFeatures();
            if (feats.length <= 3000) {
                feats.forEach((f) => {
                    const geom = f.getGeometry();
                    if (!geom) return;
                    medicaoSegmentMidpoints(geom).forEach((mid) => {
                        medicaoMidpointSource.addFeature(
                            new ol.Feature(new ol.geom.Point(mid)),
                        );
                    });
                });
            }
        });

        if (medicaoMidpointSource.getFeatures().length > 0) {
            const midSnap = new ol.interaction.Snap({
                source: medicaoMidpointSource,
                pixelTolerance: 12,
            });
            map.addInteraction(midSnap);
            medicaoSnaps.push(midSnap);
        }
    }

    const resetToPan = () => {
        if (currentMeasureInteraction)
            map.removeInteraction(currentMeasureInteraction);
        desligarSnapMedicao();
        drawSource.clear();
        measureTooltipElement.style.display = "none";
        map.getTargetElement().style.cursor = "";

        ["btn-measure-line", "btn-measure-area"].forEach((id) => {
            const btn = document.getElementById(id);
            if (btn) btn.classList.remove("bg-primary-100", "text-primary-600");
        });
    };

    document.getElementById("btn-pan")?.addEventListener("click", resetToPan);

    const enableMeasure = (type, btn) => {
        resetToPan();
        btn.classList.add("bg-primary-100", "text-primary-600");
        map.getTargetElement().style.cursor = "crosshair";

        currentMeasureInteraction = new ol.interaction.Draw({
            source: drawSource,
            type: type === "line" ? "LineString" : "Polygon",
            style: new ol.style.Style({
                stroke: new ol.style.Stroke({
                    color: "#fff",
                    width: 2,
                    lineDash: [5, 5],
                }),
            }),
        });

        currentMeasureInteraction.on("drawstart", () => {
            drawSource.clear();
            measureTooltipElement.style.display = "block";
        });
        currentMeasureInteraction.on("drawend", (e) => {
            const geom = e.feature.getGeometry();
            const output =
                type === "line"
                    ? ol.sphere.getLength(geom).toFixed(2) + " m"
                    : ol.sphere.getArea(geom).toFixed(2) + " m²";
            measureTooltipElement.innerHTML = output;
            measureOverlay.setPosition(
                type === "line"
                    ? geom.getLastCoordinate()
                    : geom.getInteriorPoint().getCoordinates(),
            );
            map.removeInteraction(currentMeasureInteraction);
            desligarSnapMedicao();
            map.getTargetElement().style.cursor = "";
        });

        map.addInteraction(currentMeasureInteraction);

        // 🧲 Snap DEPOIS do Draw (ordem importa p/ o OpenLayers interceptar o clique)
        ligarSnapMedicao();
    };

    document
        .getElementById("btn-measure-line")
        ?.addEventListener("click", function () {
            enableMeasure("line", this);
        });
    document
        .getElementById("btn-measure-area")
        ?.addEventListener("click", function () {
            enableMeasure("area", this);
        });

    // ------------------------------------------------------------------------
    // CLIQUE NO LOTE OU PONTO PANORÂMICO (ABRIR FICHA/MODAL DO CIDADÃO)
    // ------------------------------------------------------------------------
    map.on("singleclick", function (e) {
        if (currentMeasureInteraction) return; // Não clica se estiver usando a reguinha

        // 🛑 TRAVA GERAL DE DESENHO: ferramenta de desenho ativa inibe o clique padrão
        let desenhoAtivoPub = false;
        map.getInteractions().forEach((i) => {
            if (i instanceof ol.interaction.Draw && i.getActive()) desenhoAtivoPub = true;
        });
        if (desenhoAtivoPub) return;

        // T1.6 (item 3-11): coleta TODAS as feições no pixel e escolhe por prioridade —
        // lote > ponto panorâmico > zona. Assim a zona vira clicável sem roubar o clique
        // de um lote desenhado por cima dela.
        const feats = [];
        map.forEachFeatureAtPixel(e.pixel, (f) => { feats.push(f); }, {
            hitTolerance: 5,
        });

        const pick = (layerName) => feats.find((f) => f.get("layer") === layerName);
        // T2.3 — logradouro clicável: a LINHA ganha da zona (polígono cobre tudo),
        // mas perde para lote/ponto (alvos mais específicos do clique).
        // D8 (público): mobilidade entra na prioridade — pontos antes de linhas, linhas antes de polígonos
        // Edificações do lote (camada auxiliar desenhada por cima): ficha só leitura (2026-09-05)
        const feature =
            pick("edificacoes") || pick("lotes") || pick("pontos_panoramicos") ||
            pick("mob_cameras") || pick("mob_sinalizacoes") || pick("mob_pontos_interesse") ||
            pick("logradouros") || pick("mob_vias") || pick("mob_trechos") || pick("mob_eixos") || pick("mob_fluxos") ||
            pick("zonas") || pick("mob_zonas") || feats[0];

        if (feature) {
            // Mudamos o nome da variável para evitar o erro "layer is not defined"
            const featureLayer = feature.get("layer");
            const featureId = feature.get("id");

            if (featureLayer === "edificacoes") {
                // Ficha SÓ LEITURA da edificação (área, campos do município) — modal Livewire
                Livewire.dispatch("abrirFichaEdificacaoPublica", { id: featureId });
            } else if (featureLayer === "lotes") {
                const loteNome =
                    feature.get("titulo") || feature.get("name") || "S/N";
                // Avisa o Livewire para abrir a ficha do lote
                Livewire.dispatch("abrirFichaImovel", {
                    loteId: featureId,
                    loteNome: loteNome,
                });
            } else if (featureLayer === "pontos_panoramicos") {
                // Avisa o Livewire para abrir a modal de visualização 360º
                Livewire.dispatch("abrirVisualizadorPublico360", {
                    id: featureId,
                });
            } else if (featureLayer === "logradouros") {
                // T2.3 — ficha pública do logradouro (dados + seções + fotos)
                Livewire.dispatch("abrirFichaLogradouro", { logradouroId: featureId });
            } else if (featureLayer === "zonas") {
                // T1.6 — ficha pública da zona (parâmetros + usos)
                Livewire.dispatch("abrirFichaZona", { zonaId: featureId });
            } else if (featureLayer === "mob_cameras") {
                // D8 — player público da câmera (modal Livewire, sem formulário)
                Livewire.dispatch("abrirCameraPublica", { id: featureId });
            } else if (featureLayer && featureLayer.startsWith("mob_")) {
                // D8 — ficha SÓ LEITURA da feição de mobilidade (painel JS, sem Livewire)
                window.mobFichaPublica(feature);
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
            stroke: new ol.style.Stroke({ color: "#d97706", width: 2 }), // Laranja/Amber
            fill: new ol.style.Fill({ color: "rgba(217, 119, 6, 0.4)" }),
        }),
        zIndex: 150,
    });
    map.addLayer(edificacoesLayer);

    window.addEventListener("mostrar-edificacoes-lote", (event) => {
        const loteId = event.detail.id || event.detail[0]?.id;
        edificacoesSource.clear();

        if (loteId) {
            fetch(
                `/api/mapa/advanced-query?tenant_id=${config.tenantId}&layer=edificacoes&field=lote_id&operator=%3D&value=${loteId}`,
            )
                .then((response) => response.json())
                .then((data) => {
                    if (data.features && data.features.length > 0) {
                        const features = new ol.format.GeoJSON().readFeatures(
                            data,
                            {
                                dataProjection: "EPSG:4326",
                                featureProjection: "EPSG:3857",
                            },
                        );
                        edificacoesSource.addFeatures(features);
                    }
                })
                .catch((err) =>
                    console.error("Erro ao carregar edificações:", err),
                );
        }
    });

    window.addEventListener("esconder-edificacoes-lote", () => {
        edificacoesSource.clear();
    });

    // Utilitário de cor (necessário para tematização)
    function hexToRgba(hex, alpha) {
        const h = hex.replace("#", "");
        const r = parseInt(h.substring(0, 2), 16);
        const g = parseInt(h.substring(2, 4), 16);
        const b = parseInt(h.substring(4, 6), 16);
        return `rgba(${r},${g},${b},${alpha})`;
    }

    // =========================================================================
    // MOTOR DE IMPRESSÃO PÚBLICO — A4/A3 (TR Tangará Internet #16 e #17)
    // Replica fielmente a lógica do mapa-engine.js, restrita aos formatos
    // exigidos para o ambiente cidadão.
    // =========================================================================
    const dimensoesPapelCidadao = {
        a3: [420, 297],
        a4: [297, 210],
    };

    window.addEventListener("gerar-pdf-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        const format = (data.size || "a4").toLowerCase();
        const orientation = data.orientation || "portrait";

        if (!dimensoesPapelCidadao[format]) {
            alert("Formato não suportado no mapa público.");
            return;
        }

        const overlay = document.getElementById("print-loading-overlay");
        if (overlay) overlay.style.display = "flex";

        const dim = dimensoesPapelCidadao[format];
        const widthMm = orientation === "landscape" ? dim[0] : dim[1];
        const heightMm = orientation === "landscape" ? dim[1] : dim[0];

        const dpi = 150;
        const widthPx = Math.round((widthMm * dpi) / 25.4);
        const heightPx = Math.round((heightMm * dpi) / 25.4);

        const originalSize = map.getSize();
        const originalResolution = view.getResolution();

        map.setSize([widthPx, heightPx]);
        const scaling = Math.min(widthPx / originalSize[0], heightPx / originalSize[1]);
        view.setResolution(originalResolution / scaling);

        map.once("rendercomplete", function () {
            const mapCanvas = document.createElement("canvas");
            mapCanvas.width = widthPx;
            mapCanvas.height = heightPx;
            const mapContext = mapCanvas.getContext("2d");

            mapContext.fillStyle = "white";
            mapContext.fillRect(0, 0, widthPx, heightPx);

            document.querySelectorAll(".ol-layer canvas").forEach((canvas) => {
                if (canvas.width > 0) {
                    const opacity = canvas.parentNode.style.opacity || 1;
                    mapContext.globalAlpha = Number(opacity);

                    const transform = canvas.style.transform;
                    if (transform) {
                        const matrix = transform
                            .match(/^matrix\(([^]*)\)$/)[1]
                            .split(",")
                            .map(Number);
                        CanvasRenderingContext2D.prototype.setTransform.apply(mapContext, matrix);
                    }
                    mapContext.drawImage(canvas, 0, 0);
                }
            });

            mapContext.globalAlpha = 1;
            mapContext.setTransform(1, 0, 0, 1, 0, 0);

            try {
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({
                    orientation: orientation,
                    unit: "mm",
                    format: format,
                });

                pdf.addImage(
                    mapCanvas.toDataURL("image/jpeg", 0.85),
                    "JPEG",
                    0, 0,
                    widthMm, heightMm,
                );

                pdf.setFontSize(10);
                pdf.setTextColor(50, 50, 50);
                pdf.text(
                    `Mapa Público — Gerado em ${new Date().toLocaleDateString("pt-BR")}`,
                    10,
                    heightMm - 6,
                );

                pdf.save(`Mapa_Publico_${format.toUpperCase()}_${orientation}.pdf`);
            } catch (err) {
                console.error("Erro na compilação do PDF público:", err);
                alert("❌ Falha ao gerar o PDF. Tente um formato menor.");
            } finally {
                map.setSize(originalSize);
                view.setResolution(originalResolution);
                if (overlay) overlay.style.display = "none";
            }
        });

        map.renderSync();
    });
}); // <-- Fim do DOMContentLoaded
