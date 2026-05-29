/**
 * SIGWEB - Engine Cartográfica (OpenLayers 8.2)
 * Arquivo isolado para não poluir o Blade.
 * Não requer Vite, apenas carregue via asset() no Laravel.
 */

document.addEventListener("DOMContentLoaded", function () {
    // 1. CARREGA AS CONFIGURAÇÕES INJETADAS PELO PHP
    const config = window.mapConfig || {};
    let zonasAtivas = [];

    // VARIÁVEIS DE ESTADO SOCIAL (Filtros do Mapa de Calor)
    let filtroRiscoAtivo = false;
    let filtroBeneficioAtivo = false;
    let filtroPcdAtivo = false;

    //variáveis do modo de consulta de unificação
    window.modoUnificacao = false;
    window.lotesParaUnificar = [];
    window.featuresUnificacao = [];

    // 2. CONFIGURA A CÂMERA DO MAPA
    const view = new ol.View({
        center: ol.proj.fromLonLat([config.mapLon, config.mapLat]),
        zoom: config.mapZoom,
        maxZoom: 22,
    });

    // 3. DEFINIÇÃO DOS MAPAS BASE (BASEMAPS)
    const azureKey = config.azureMapsKey || "";

    const basemaps = {
        osm: new ol.layer.Tile({
            source: new ol.source.OSM(),
            visible: true,
            zIndex: 0,
        }),
        esri_sat: new ol.layer.Tile({
            source: new ol.source.XYZ({
                url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
                maxZoom: 18,
                crossOrigin: "anonymous",
            }),
            visible: false,
            zIndex: 1,
        }),
        azure_road: new ol.layer.Tile({
            source: new ol.source.XYZ({
                url: `https://atlas.microsoft.com/map/tile?api-version=2.0&tilesetId=microsoft.base.road&zoom={z}&x={x}&y={y}&subscription-key=${azureKey}`,
                crossOrigin: "anonymous",
            }),
            visible: false,
            zIndex: 1,
        }),
        azure_sat: new ol.layer.Tile({
            source: new ol.source.XYZ({
                url: `https://atlas.microsoft.com/map/tile?api-version=2.0&tilesetId=microsoft.imagery&zoom={z}&x={x}&y={y}&subscription-key=${azureKey}`,
                crossOrigin: "anonymous",
            }),
            visible: false,
            zIndex: 1,
        }),
        ortofoto_2025: new ol.layer.Tile({
            source: new ol.source.XYZ({
                url: `/mapas/${config.tenantSlug}/{z}/{x}/{y}.png`,
                minZoom: 12,
                maxZoom: 22,
                crossOrigin: "anonymous",
            }),
            visible: false,
            zIndex: 2, // 🛑 CORREÇÃO: Elevado para 2 para ficar por cima do Esri Satélite (1)
        }),
    };

    // Adiciona todas ao mapa (apenas a OSM inicia visível)
    const basemapLayers = Object.values(basemaps);

    // 4. ESTILOS DAS CAMADAS VETORIAIS
    const layerConfigs = {
        perimetros: {
            z: 10,
            minZoom: 0,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#ef4444", width: 3 }),
                    fill: new ol.style.Fill({
                        color: "rgba(239, 68, 68, 0.05)",
                    }),
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

                // Aparece num zoom mais aberto (14) para não sumir a cidade inteira
                if (zoom >= 14) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "",
                            font: "bold 16px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#1e3a8a" }), // Azul muito escuro
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
            z: 35, // Fica acima dos Bairros (30) e abaixo das Quadras (40)
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "#2563eb",
                        width: 3,
                        lineDash: [8, 4],
                    }), // Azul (Blue 600) tracejado
                    fill: new ol.style.Fill({
                        color: "rgba(37, 99, 235, 0.1)",
                    }), // Fundo azul bem clarinho
                });

                if (zoom >= 14) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "Loteamento",
                            font: "bold 15px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#1e3a8a" }), // Azul escuro
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

                // Aparece num zoom intermediário (16)
                if (zoom >= 16) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? "Q " + feature.get("name").toString()
                                : "",
                            font: "bold 14px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#9a3412" }), // Laranja escuro
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
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#3675ce", width: 3 }),
                });
                if (zoom >= 16) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "",
                            font: "bold 11px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#1e3a8a" }),
                            stroke: new ol.style.Stroke({
                                color: "#ffffff",
                                width: 3,
                            }),
                            placement: "line",
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },

        postes: {
            z: 100,
            minZoom: 14,
            style: function (feature) {
                const condition = feature.get("structural_condition");
                const temChamado = feature.get("tem_chamado"); // 🟢 LÊ DO BANCO

                let fillColor = "#eab308"; // Amarelo (Padrão)
                if (condition === "Bom") fillColor = "#22c55e"; // Verde
                if (condition === "Ruim") fillColor = "#ef4444"; // Vermelho

                // 🛑 SOBRESCREVE SE ESTIVER EM MANUTENÇÃO (Roxo brilhante)
                if (temChamado) fillColor = "#d946ef";

                return new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: temChamado ? 8 : 6, // 🟢 Fica maior se tiver chamado
                        fill: new ol.style.Fill({ color: fillColor }),
                        stroke: new ol.style.Stroke({
                            color: temChamado ? "#000000" : "#ffffff",
                            width: temChamado ? 3 : 2,
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
                const temChamado = feature.get("tem_chamado"); // 🟢 LÊ DO BANCO

                let radius = 6;
                if (size === "pequeno") radius = 6;
                if (size === "grande") radius = 8;
                if (temChamado) radius += 2; // 🟢 Cresce mais um pouco

                let fillColor = "#22c55e"; // Verde padrão
                if (condition === "Regular") fillColor = "#eab308";
                if (condition === "Ruim") fillColor = "#ef4444";
                if (condition === "Morta") fillColor = "#6b7280";

                // 🛑 SOBRESCREVE SE ESTIVER EM MANUTENÇÃO (Roxo brilhante)
                if (temChamado) fillColor = "#d946ef";

                return new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: radius,
                        fill: new ol.style.Fill({ color: fillColor }),
                        stroke: new ol.style.Stroke({
                            color: temChamado ? "#000000" : "#ffffff",
                            width: temChamado ? 3 : 2,
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

                // --- 🛑 A MÁGICA SOCIAL COMEÇA AQUI ---
                // Verifica se a API mandou as variáveis e se o botão do painel está ligado!
                const isRisco = feature.get("social_risco") && filtroRiscoAtivo;
                const isBeneficio =
                    feature.get("social_beneficio") && filtroBeneficioAtivo;
                const isPcd = feature.get("social_pcd") && filtroPcdAtivo;

                // Definimos as cores. A prioridade máxima é a Área de Risco (Vermelho).
                let strokeColor = "#10b981"; // Verde Padrão (Emerald)
                let fillColor = "rgba(16, 185, 129, 0.15)";

                if (isRisco) {
                    strokeColor = "#e11d48"; // Vermelho (Rose)
                    fillColor = "rgba(225, 29, 72, 0.6)"; // Vermelho forte
                } else if (isPcd) {
                    strokeColor = "#9333ea"; // Roxo (Purple)
                    fillColor = "rgba(147, 51, 234, 0.5)";
                } else if (isBeneficio) {
                    strokeColor = "#f59e0b"; // Amarelo (Amber)
                    fillColor = "rgba(245, 158, 11, 0.5)";
                }
                // --- 🛑 FIM DA MÁGICA ---

                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: strokeColor,
                        width: isRisco ? 2 : 1,
                    }), // Borda mais grossa se for risco
                    fill: new ol.style.Fill({ color: fillColor }),
                });

                if (zoom >= 18) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name")
                                ? feature.get("name").toString()
                                : "",
                            font: "bold 12px Arial, sans-serif",
                            fill: new ol.style.Fill({
                                color: isRisco ? "#ffffff" : "#064e3b",
                            }), // Se for risco, texto branco para dar contraste
                            stroke: new ol.style.Stroke({
                                color: isRisco ? "#9f1239" : "#ffffff",
                                width: 3,
                            }),
                            overflow: true,
                        }),
                    );
                }
                return style;
            },
        },

        edificacoes: {
            z: 70,
            minZoom: 16,
            style: new ol.style.Style({
                stroke: new ol.style.Stroke({ color: "#b45309", width: 1 }),
                fill: new ol.style.Fill({ color: "rgba(180, 83, 9, 0.5)" }),
            }),
        },

        pontos_panoramicos: {
            style: new ol.style.Style({
                image: new ol.style.Icon({
                    src: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="%233b82f6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>',
                    scale: 1.0,
                    anchor: [0.5, 0.5],
                }),
            }),
            z: 100, // Câmeras sempre por cima das quadras e lotes!
            minZoom: 14, // Só aparece quando der um certo zoom
        },

        cemiterios: {
            z: 25, // Fica entre a Zona e o Bairro
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#9333ea", width: 2 }), // Roxo
                    fill: new ol.style.Fill({
                        color: "rgba(147, 51, 234, 0.2)",
                    }), // Roxo transparente
                });
                // Mostra o nome do cemitério a partir do zoom 15
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
            z: 26, // Z-index maior que o cemitério (25) para a quadra ficar por cima e ser clicável
            minZoom: 16,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "#6366f1",
                        width: 2,
                        lineDash: [4, 4],
                    }), // Borda Indigo tracejada
                    fill: new ol.style.Fill({
                        color: "rgba(99, 102, 241, 0.3)",
                    }), // Fundo Indigo transparente
                });

                // Texto só aparece com zoom bem próximo
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
            minZoom: 18, // Só aparece bem de perto para não poluir
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#57534e", width: 1 }), // Stone 600
                    fill: new ol.style.Fill({ color: "rgba(87, 83, 78, 0.4)" }),
                });

                if (zoom >= 19.5) {
                    // Texto só no ultra-zoom
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

        setores_fiscais: {
            z: 22, // Fica abaixo dos lotes mas acima dos bairros
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "#f59e0b",
                        width: 3,
                        lineDash: [8, 8],
                    }), // Laranja tracejado forte
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

        // Dentro de const layerConfigs = { ... }
        "rural-localidades": {
            z: 15, // Acima do mapa base, abaixo dos lotes urbanos
            minZoom: 0,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "#57534e", // Stone 600
                        width: 2,
                        lineDash: [4, 4],
                    }),
                    fill: new ol.style.Fill({
                        color: "rgba(120, 113, 108, 0.2)", // Stone 500 translúcido
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
            z: 16, // Fica uma camada ACIMA das localidades para ser clicável
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const style = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: "#f59e0b", width: 2 }), // Amber 500
                    fill: new ol.style.Fill({
                        color: "rgba(245, 158, 11, 0.2)",
                    }), // Fundo transparente
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
            z: 17, // Acima das localidades, abaixo dos lotes
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const pavimento = feature.get("tipo_pavimento");

                let strokeColor = "#78350f"; // Marrom (Terra)
                let lineDash = [];

                if (pavimento === "Asfalto")
                    strokeColor = "#374151"; // Cinza Escuro (Asfalto)
                else if (pavimento === "Cascalho") lineDash = [4, 4]; // Tracejado

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
                            placement: "line", // 🛑 MÁGICA: O texto faz a curva junto com a estrada!
                            textBaseline: "bottom",
                            offsetY: -5,
                        }),
                    );
                }
                return style;
            },
        },

        "rural-hidrografias": {
            z: 17, // Abaixo das localidades e lotes, para a água ficar por baixo
            minZoom: 12,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const geomType = feature.getGeometry().getType();
                let style;

                if (geomType === "Point" || geomType === "MultiPoint") {
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
                } else if (
                    geomType === "LineString" ||
                    geomType === "MultiLineString"
                ) {
                    style = new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: "#0ea5e9",
                            width: 3,
                        }),
                    });
                } else {
                    // Polygon
                    style = new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: "#0284c7",
                            width: 2,
                        }),
                        fill: new ol.style.Fill({
                            color: "rgba(14, 165, 233, 0.4)",
                        }),
                    });
                }

                if (zoom >= 14 && feature.get("name")) {
                    style.setText(
                        new ol.style.Text({
                            text: feature.get("name").toString(),
                            font: "bold 12px Arial, sans-serif",
                            fill: new ol.style.Fill({ color: "#0c4a6e" }), // Azul muito escuro
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
            z: 110, // Super alto para ficar por cima da água e da estrada
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const estado = feature.get("estado_conservacao");

                // Muda a cor da borda se estiver interditada ou ruim!
                let borderColor = "#f59e0b"; // Amber (Padrão)
                if (estado === "Ruim")
                    borderColor = "#ef4444"; // Vermelho
                else if (estado === "Interditada") borderColor = "#000000"; // Preto

                const style = new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 6,
                        fill: new ol.style.Fill({ color: "#78350f" }), // Marrom Madeira Escuro
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
                            fill: new ol.style.Fill({ color: "#451a03" }), // Marrom Quase Preto
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
            z: 120, // O mais alto de todos os pontos rurais
            minZoom: 13,
            style: function (feature, resolution) {
                const zoom = view.getZoomForResolution(resolution);
                const categoria = feature.get("categoria");

                console.log(categoria);

                let dotColor = "#14b8a6"; // Teal (Padrão/Outros)
                if (categoria === "Escola")
                    dotColor = "#3b82f6"; // Azul
                else if (categoria === "Saúde")
                    dotColor = "#ef4444"; // Vermelho
                else if (categoria === "Igreja")
                    dotColor = "#a855f7"; // Roxo
                else if (categoria === "Turismo")
                    dotColor = "#f59e0b"; // Laranja
                else if (categoria === "Comércio") dotColor = "#84cc16"; // Verde Lima

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

        toponimias: {
            z: 200, // Acima de tudo — é texto anotado pelo usuário
            minZoom: 0,
            style: function (feature) {
                const texto = feature.get("texto") || "";
                const estilo = feature.get("estilo") || {};
                const tam = parseInt(estilo.tamanho || "16", 10);
                const cor = estilo.cor || "#1f2937";
                return new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 5,
                        fill: new ol.style.Fill({ color: cor }),
                        stroke: new ol.style.Stroke({
                            color: "#ffffff",
                            width: 1.5,
                        }),
                    }),
                    text: new ol.style.Text({
                        text: texto,
                        font: "bold " + tam + "px Arial, sans-serif",
                        fill: new ol.style.Fill({ color: cor }),
                        stroke: new ol.style.Stroke({
                            color: "#ffffff",
                            width: 3,
                        }),
                        overflow: true,
                        offsetY: -14,
                        textBaseline: "bottom",
                    }),
                });
            },
        },
    };

    // 5. INICIA O MAPA
    const map = new ol.Map({
        target: "sigweb-map",
        layers: [
            ...Object.values(basemaps), // Adiciona todas as bases (apenas OSM começa visible: true)
        ],
        view: view,
        controls: [],
    });

    // ── #12 — COORDENADA DO CURSOR EM TEMPO REAL ────────────────────
    const coordDisplay = document.getElementById("coord-display");
    if (coordDisplay) {
        map.on("pointermove", function (evt) {
            if (evt.dragging) return;
            const lonLat = ol.proj.toLonLat(evt.coordinate);
            coordDisplay.textContent =
                "Lat: " +
                lonLat[1].toFixed(6) +
                "  Lon: " +
                lonLat[0].toFixed(6);
        });
        map.getTargetElement().addEventListener("mouseleave", function () {
            coordDisplay.textContent = "Lat: —  Lon: —";
        });
    }

    // ── PERMISSÕES DE CAMADAS E TOOLBAR ────────────────────────────
    if (config.permissionsUrl) {
        fetch(config.permissionsUrl, { credentials: "same-origin" })
            .then(function (r) {
                return r.ok ? r.json() : null;
            })
            .then(function (perms) {
                if (!perms || perms.bypass) return;

                if (Array.isArray(perms.layers)) {
                    const allowed = new Set(perms.layers);

                    // Sub-camadas que herdam a permissão de uma camada-pai.
                    // Quem tem ver_camada_cemiterios vê automaticamente quadras_cemiterio,
                    // logradouros_cemiterio e jazigos — sem precisar de permissões separadas.
                    const LAYER_PERMISSION_ALIASES = {
                        quadras_cemiterio:     'cemiterios',
                        logradouros_cemiterio: 'cemiterios',
                        jazigos:               'cemiterios',
                    };

                    document
                        .querySelectorAll("input.layer-toggle[data-layer]")
                        .forEach(function (chk) {
                            const rawKey = chk.dataset.layer.replace(
                                /-/g,
                                "_",
                            );
                            const layerKey = LAYER_PERMISSION_ALIASES[rawKey] || rawKey;
                            if (!allowed.has(layerKey)) {
                                // A label é o pai direto do input para todos os tipos de camada.
                                // Para camadas com controles de rótulo, a label fica dentro de um
                                // div.justify-between — nesse caso, escondemos o div inteiro.
                                // Para camadas simples (rural, infra, cemitério), escondemos só a label.
                                const label = chk.closest("label");
                                if (!label) return;
                                const parent = label.parentElement;
                                const wrapper =
                                    parent &&
                                    parent.tagName === "DIV" &&
                                    parent.classList.contains("justify-between")
                                        ? parent
                                        : label;
                                wrapper.style.display = "none";
                            }
                        });
                }

                // Esconde GRUPOS inteiros marcados com data-permission-group="layer:X" ou "toolbar:X".
                // Útil para containers que não têm input.layer-toggle direto (ex: Inteligência Social,
                // WMS Externo) ou onde os checkboxes internos somem mas o cabeçalho permanece (Zoneamento).
                document.querySelectorAll('[data-permission-group]').forEach(function (el) {
                    const spec = el.dataset.permissionGroup || '';
                    const idx = spec.indexOf(':');
                    if (idx < 0) return;
                    const type = spec.substring(0, idx);
                    const key  = spec.substring(idx + 1);
                    let allowed = true;
                    if (type === 'layer') {
                        if (Array.isArray(perms.layers)) {
                            allowed = perms.layers.includes(key);
                        }
                    } else if (type === 'toolbar') {
                        if (perms.toolbar && perms.toolbar[key] === false) {
                            allowed = false;
                        }
                    }
                    if (!allowed) el.style.display = 'none';
                });

                if (perms.toolbar) {
                    if (perms.toolbar.criar_artefatos === false) {
                        const el = document.getElementById(
                            "toolbar-criar-artefatos",
                        );
                        if (el) el.style.display = "none";
                    }
                    if (perms.toolbar.ferramentas === false) {
                        const el = document.getElementById(
                            "toolbar-ferramentas",
                        );
                        if (el) el.style.display = "none";
                    }
                    if (perms.toolbar.filtros === false) {
                        const el = document.getElementById("toolbar-filtros");
                        if (el) el.style.display = "none";
                    }
                }
            })
            .catch(function () {}); // falha silenciosa — exibe tudo em vez de bloquear
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
    // ────────────────────────────────────────────────────────────────

    // 4. LÓGICA DE TROCA DE MAPA BASE
    window.addEventListener("switch-basemap", (event) => {
        let selectedType = event.detail;

        // 🛑 VALIDAÇÃO 1: Faltando chave do Azure Maps
        if (selectedType.startsWith("azure") && !azureKey) {
            alert(
                "⚠️ A chave da API do Azure Maps não foi configurada no servidor (.env).\nO mapa será revertido para o padrão aberto (OpenStreetMap).",
            );
            selectedType = "osm";
            // Dispara o evento de volta para corrigir a interface (Alpine.js)
            window.dispatchEvent(
                new CustomEvent("sync-basemap-ui", { detail: "osm" }),
            );
        }

        console.log(`SIGWEB: Alterando mapa base para -> ${selectedType}`);

        // Desliga a visibilidade de todos os basemaps
        Object.keys(basemaps).forEach((key) => {
            basemaps[key].setVisible(false);
        });

        // 🛑 VALIDAÇÃO 2: Se for Ortofoto, liga também o Satélite por baixo para cobrir as bordas!
        if (selectedType.startsWith("ortofoto")) {
            if (basemaps["esri_sat"]) basemaps["esri_sat"].setVisible(true); // O Chão
            if (basemaps[selectedType]) basemaps[selectedType].setVisible(true); // A Ortofoto por cima
        }
        // Comportamento normal para os demais mapas
        else if (basemaps[selectedType]) {
            basemaps[selectedType].setVisible(true);
        } else {
            console.warn(
                `SIGWEB: Basemap "${selectedType}" não definido na engine.`,
            );
            basemaps["osm"].setVisible(true); // Fallback para segurança
        }
    });

    // Desativa o zoom de duplo clique para facilitar edições
    const dblClickZoom = map
        .getInteractions()
        .getArray()
        .find((i) => i instanceof ol.interaction.DoubleClickZoom);
    if (dblClickZoom) map.removeInteraction(dblClickZoom);

    window.window.loadedLayers = {};

    // 6. EVENTOS DE SATÉLITE
    let showSat = false;
    const btnSatelite = document.getElementById("btn-satelite");
    const sateliteText = document.getElementById("satelite-text");
    if (btnSatelite) {
        btnSatelite.addEventListener("click", () => {
            showSat = !showSat;
            osmLayer.setVisible(!showSat);
            esriLayer.setVisible(showSat);
            ortofotoLayer.setVisible(showSat);
            if (showSat) {
                btnSatelite.classList.add(
                    "bg-primary-50",
                    "text-primary-600",
                    "dark:bg-primary-900/20",
                    "dark:text-primary-400",
                );
                btnSatelite.classList.remove(
                    "text-gray-600",
                    "dark:text-gray-300",
                );
                if (sateliteText) sateliteText.innerText = "Mapa Open";
            } else {
                btnSatelite.classList.remove(
                    "bg-primary-50",
                    "text-primary-600",
                    "dark:bg-primary-900/20",
                    "dark:text-primary-400",
                );
                btnSatelite.classList.add(
                    "text-gray-600",
                    "dark:text-gray-300",
                );
                if (sateliteText) sateliteText.innerText = "Satélite";
            }
        });
    }

    // 7. CARREGAMENTO DE CAMADAS (API AJAX)
    window.loadingLayers = window.loadingLayers || {}; // 🛑 TRAVA ANTI-FANTASMA

    const fetchAndDrawLayer = (layerName, checkboxElement) => {
        // Se a camada já está desenhada OU está no meio do processo de download da API, cancela a nova requisição!
        if (window.loadedLayers[layerName] || window.loadingLayers[layerName]) {
            if (window.loadedLayers[layerName])
                window.loadedLayers[layerName].setVisible(true);
            return;
        }

        window.loadingLayers[layerName] = true; // Tranca a porta!

        const textSpan =
            checkboxElement.nextElementSibling.querySelector(".layer-text");
        let originalText = "";
        if (textSpan) {
            originalText = textSpan.innerHTML;
            textSpan.innerHTML = "Carregando...";
            textSpan.classList.add("animate-pulse", "text-primary-500");
        }

        // Lê o ID do tenant vindo da variável PHP
        fetch(`/api/gis-data?tenant_id=${config.tenantId}&layer=${layerName}`)
            .then((response) => response.json())
            .then((data) => {
                if (data && data.features && data.features.length > 0) {
                    const parsedFeatures = new ol.format.GeoJSON().readFeatures(
                        data,
                        { featureProjection: "EPSG:3857" },
                    );

                    // CARIMBO OBRIGATÓRIO
                    parsedFeatures.forEach((f) => f.set("layer", layerName));

                    const vectorSource = new ol.source.Vector({
                        features: parsedFeatures,
                    });
                    const vectorLayer = new ol.layer.Vector({
                        source: vectorSource,
                        style: layerConfigs[layerName].style,
                        zIndex: layerConfigs[layerName].z,
                        minZoom: layerConfigs[layerName].minZoom,
                    });
                    map.addLayer(vectorLayer);
                    window.loadedLayers[layerName] = vectorLayer;
                }
            })
            .catch((err) =>
                console.error(`Erro ao carregar ${layerName}:`, err),
            )
            .finally(() => {
                window.loadingLayers[layerName] = false; // Destranca a porta ao finalizar!
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
            else if (window.window.loadedLayers[layerName])
                window.window.loadedLayers[layerName].setVisible(false);
        });
        if (checkbox.checked)
            fetchAndDrawLayer(checkbox.getAttribute("data-layer"), checkbox);
    });

    // 🛑 Delegação de Eventos: Ouve até os checkboxes que forem criados depois!
    document.addEventListener("change", function (e) {
        if (e.target && e.target.classList.contains("zona-toggle")) {
            const checkbox = e.target;
            const sigla = checkbox.getAttribute("data-zona-sigla");

            if (checkbox.checked) {
                if (!zonasAtivas.includes(sigla)) zonasAtivas.push(sigla);
            } else {
                zonasAtivas = zonasAtivas.filter((s) => s !== sigla);
            }

            if (!window.loadedLayers["zonas"]) {
                fetchAndDrawLayer("zonas", checkbox);
            } else {
                window.loadedLayers["zonas"].changed();
                window.loadedLayers["zonas"].setVisible(zonasAtivas.length > 0);
            }
        }
    });

    // 8. INTERFACE E ARRASTO DO PAINEL DE CAMADAS
    const panel = document.getElementById("layers-panel");
    const btnToggleLayers = document.getElementById("btn-toggle-layers");
    btnToggleLayers.addEventListener("click", () =>
        panel.classList.toggle("hidden"),
    );

    function dragElement(elmnt) {
        let pos1 = 0,
            pos2 = 0,
            pos3 = 0,
            pos4 = 0;
        const header = document.getElementById(elmnt.id + "-header");
        header.onmousedown = dragMouseDown;

        function dragMouseDown(e) {
            e.preventDefault();
            pos3 = e.clientX;
            pos4 = e.clientY;
            document.onmouseup = closeDragElement;
            document.onmousemove = elementDrag;
            elmnt.classList.add("dragging-now");
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
                if (newTop > window.innerHeight - elmnt.clientHeight - 10)
                    newTop = window.innerHeight - elmnt.clientHeight - 10;
                if (newLeft > window.innerWidth - elmnt.clientWidth - 10)
                    newLeft = window.innerWidth - elmnt.clientWidth - 10;
                elmnt.style.top = newTop + "px";
                elmnt.style.left = newLeft + "px";
            });
        }

        function closeDragElement() {
            document.onmouseup = null;
            document.onmousemove = null;
            elmnt.classList.remove("dragging-now");
        }
    }
    dragElement(panel);

    // 9. VOO DA BUSCA E CLIQUE NO MAPA
    window.addEventListener("voar-para-lote", (e) => {
        const data = e.detail;
        if (data && data.coords) {
            const targetCoords = ol.proj.fromLonLat([
                data.coords[0],
                data.coords[1],
            ]);
            view.animate({ center: targetCoords, zoom: 20, duration: 2000 });

            // Marcador laranja pulsante no local encontrado
            searchPinSource.clear();
            const pinFeature = new ol.Feature(new ol.geom.Point(targetCoords));
            pinFeature.set("tipo", "search-pin");
            searchPinSource.addFeature(pinFeature);

            // Remove o marcador após 6 segundos
            if (window._searchPinTimer) clearTimeout(window._searchPinTimer);
            window._searchPinTimer = setTimeout(
                () => searchPinSource.clear(),
                6000,
            );
        }
    });

    const featureTooltip = document.getElementById("feature-tooltip");
    let hoveredFeature = null;

    map.on("pointermove", function (e) {
        // 📐 CRUZAMENTO ORTOGONAL DINÂMICO (Estilo AutoCAD)
        if (window.isOrtogonalActive) {
            if (window.ortogonalLastFix) {
                window.atualizarGuiasOrtogonais(window.ortogonalLastFix); // Trava no último clique
            } else {
                window.atualizarGuiasOrtogonais(e.coordinate); // Segue o mouse livremente
            }
        }

        // 🛑 A TRAVA MESTRA: Se estiver editando geometria, desliga o hover na hora!
        if (featureEmEdicao) {
            if (hoveredFeature) {
                hoveredFeature.setStyle(
                    hoveredFeature.get("estilo_customizado") || undefined,
                ); // Limpa o hover atual
                hoveredFeature = null;
            }
            if (featureTooltip) featureTooltip.style.display = "none"; // Esconde o texto
            return; // 🛑 Cancela a execução do resto do evento de hover!
        }

        // 1. Limpa o efeito do último elemento que passamos o mouse
        if (hoveredFeature) {
            hoveredFeature.setStyle(
                hoveredFeature.get("estilo_customizado") || undefined,
            );
            hoveredFeature = null;
        }

        const feature = map.forEachFeatureAtPixel(
            e.pixel,
            (feature) => feature,
            { hitTolerance: 5 },
        );

        // 2. Define quais camadas ganham a "mãozinha" (pointer) ao passar o mouse
        const hoverableLayers = [
            "lotes",
            "edificacao_ativa",
            "pontos_panoramicos",
            "logradouros",
            "zonas",
            "bairros",
            "loteamentos",
            "quadras",
            "postes",
            "arvores",
            "cemiterios",
            "quadras_cemiterio",
            "logradouros_cemiterio",
            "setores_fiscais",
            "rural-localidades",
            "rural-propriedades",
            "rural-estradas",
            "rural-hidrografias",
            "rural-pontes",
            "rural-pontos-interesse",
        ];
        const isHoverable =
            feature && hoverableLayers.includes(feature.get("layer"));
        map.getTargetElement().style.cursor = isHoverable ? "pointer" : "";

        // 3. Aplica o efeito de Hover
        if (feature) {
            const layer = feature.get("layer");
            const name = feature.get("name")
                ? feature.get("name").toString()
                : "";
            const zoom = view.getZoom(); // Pega o zoom atual para respeitar as regras de texto

            if (hoverableLayers.includes(layer)) {
                hoveredFeature = feature;
            }

            if (layer === "logradouros" || layer === "logradouros_cemiterio") {
                feature.setStyle(
                    new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color:
                                layer === "logradouros" ? "#38bdf8" : "#94a3b8",
                            width: 6,
                        }),
                    }),
                );

                if (featureTooltip) {
                    featureTooltip.innerHTML = name || "Rua Interna sem nome";
                    featureTooltip.style.display = "block";
                    featureTooltip.style.left = e.originalEvent.clientX + "px";
                    featureTooltip.style.top = e.originalEvent.clientY + "px";
                }
            } else {
                // Esconde o tooltip de rua se estivermos em outra coisa
                if (featureTooltip) featureTooltip.style.display = "none";

                // EFEITO DE "ACENDER" (Mais opacidade no fundo e Texto Branco)
                if (layer === "bairros") {
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#3b82f6",
                                width: 3,
                            }),
                            fill: new ol.style.Fill({
                                color: "rgba(59, 130, 246, 0.4)",
                            }), // Fundo mais forte
                            text:
                                zoom >= 14
                                    ? new ol.style.Text({
                                          text: name,
                                          font: "bold 16px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }), // Texto Branco
                                          stroke: new ol.style.Stroke({
                                              color: "#1e3a8a",
                                              width: 4,
                                          }), // Borda azul escura
                                          overflow: true,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "quadras") {
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#f97316",
                                width: 2,
                            }),
                            fill: new ol.style.Fill({
                                color: "rgba(249, 115, 22, 0.5)",
                            }), // Fundo mais forte
                            text:
                                zoom >= 16
                                    ? new ol.style.Text({
                                          text: name ? "Q " + name : "",
                                          font: "bold 14px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }), // Texto Branco
                                          stroke: new ol.style.Stroke({
                                              color: "#9a3412",
                                              width: 3,
                                          }),
                                          overflow: true,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "lotes") {
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#0ea5e9",
                                width: 2,
                            }),
                            fill: new ol.style.Fill({
                                color: "rgba(14, 165, 233, 0.4)",
                            }), // Fundo mais forte
                            text:
                                zoom >= 18
                                    ? new ol.style.Text({
                                          text: name,
                                          font: "bold 12px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }), // Texto Branco
                                          stroke: new ol.style.Stroke({
                                              color: "#0369a1",
                                              width: 3,
                                          }),
                                          overflow: true,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "postes") {
                    const condition = feature.get("structural_condition");
                    const seqId = feature.get("sequential_id");
                    const temChamado = feature.get("tem_chamado"); // 🟢

                    const textoBase = seqId ? `Poste #${seqId}` : "Poste S/N";
                    const textoHover = temChamado
                        ? `🛠️ ${textoBase} (Em Manutenção)`
                        : textoBase;

                    let fillColor = "#eab308";
                    if (condition === "Bom") fillColor = "#22c55e";
                    if (condition === "Ruim") fillColor = "#ef4444";
                    if (temChamado) fillColor = "#d946ef"; // 🛑

                    feature.setStyle(
                        new ol.style.Style({
                            image: new ol.style.Circle({
                                radius: temChamado ? 10 : 9, // Cresce no hover
                                fill: new ol.style.Fill({ color: fillColor }),
                                stroke: new ol.style.Stroke({
                                    color: temChamado ? "#000000" : "#ffffff",
                                    width: 3,
                                }),
                            }),
                            text:
                                zoom >= 18
                                    ? new ol.style.Text({
                                          text: textoHover,
                                          offsetY: -16,
                                          font: "bold 12px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }),
                                          stroke: new ol.style.Stroke({
                                              color: "#000000",
                                              width: 3,
                                          }),
                                          overflow: true,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "arvores") {
                    const condition = feature.get("phytosanitary_condition");
                    const seqId = feature.get("sequential_id");
                    const nameStr = feature.get("name");
                    const temChamado = feature.get("tem_chamado"); // 🟢

                    const especie =
                        nameStr && nameStr !== "S/N"
                            ? nameStr
                            : "Não Identificada";
                    const textoBase = seqId
                        ? `Árvore #${seqId} - ${especie}`
                        : `Árvore - ${especie}`;
                    const textoHover = temChamado
                        ? `🛠️ ${textoBase} (Em Manutenção)`
                        : textoBase;

                    let fillColor = "#22c55e";
                    if (condition === "Regular") fillColor = "#eab308";
                    if (condition === "Ruim") fillColor = "#ef4444";
                    if (condition === "Morta") fillColor = "#6b7280";
                    if (temChamado) fillColor = "#d946ef"; // 🛑

                    feature.setStyle(
                        new ol.style.Style({
                            image: new ol.style.Circle({
                                radius: temChamado ? 11 : 10,
                                fill: new ol.style.Fill({ color: fillColor }),
                                stroke: new ol.style.Stroke({
                                    color: temChamado ? "#000000" : "#ffffff",
                                    width: 3,
                                }),
                            }),
                            text:
                                zoom >= 18
                                    ? new ol.style.Text({
                                          text: textoHover,
                                          offsetY: -16,
                                          font: "bold 12px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }),
                                          stroke: new ol.style.Stroke({
                                              color: "#000000",
                                              width: 3,
                                          }),
                                          overflow: true,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "cemiterios") {
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#a855f7",
                                width: 3,
                            }), // Roxo mais brilhante
                            fill: new ol.style.Fill({
                                color: "rgba(147, 51, 234, 0.4)",
                            }), // Fundo mais escuro
                            text:
                                zoom >= 15
                                    ? new ol.style.Text({
                                          text: name,
                                          font: "bold 15px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }), // Letra branca
                                          stroke: new ol.style.Stroke({
                                              color: "#581c87",
                                              width: 4,
                                          }),
                                          overflow: true,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "quadras_cemiterio") {
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#818cf8",
                                width: 3,
                            }),
                            fill: new ol.style.Fill({
                                color: "rgba(99, 102, 241, 0.5)",
                            }),
                            text:
                                zoom >= 17
                                    ? new ol.style.Text({
                                          text: name,
                                          font: "bold 14px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }),
                                          stroke: new ol.style.Stroke({
                                              color: "#3730a3",
                                              width: 3,
                                          }),
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "jazigos") {
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#78716c",
                                width: 2,
                            }),
                            fill: new ol.style.Fill({
                                color: "rgba(87, 83, 78, 0.7)",
                            }),
                            text:
                                zoom >= 19.5
                                    ? new ol.style.Text({
                                          text: name,
                                          font: "bold 12px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }),
                                          stroke: new ol.style.Stroke({
                                              color: "#292524",
                                              width: 3,
                                          }),
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "rural-localidades") {
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#57534e",
                                width: 4,
                            }), // Stone 600 mais grosso
                            fill: new ol.style.Fill({
                                color: "rgba(120, 113, 108, 0.5)",
                            }), // Fundo mais opaco
                            text:
                                zoom >= 13
                                    ? new ol.style.Text({
                                          text: name,
                                          font: "bold 14px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }),
                                          stroke: new ol.style.Stroke({
                                              color: "#292524",
                                              width: 3,
                                          }),
                                          overflow: true,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "rural-propriedades") {
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#d97706",
                                width: 3,
                            }), // Borda mais forte
                            fill: new ol.style.Fill({
                                color: "rgba(245, 158, 11, 0.5)",
                            }), // Fundo aceso
                            text:
                                zoom >= 14
                                    ? new ol.style.Text({
                                          text: name,
                                          font: "bold 14px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }), // Texto branco
                                          stroke: new ol.style.Stroke({
                                              color: "#92400e",
                                              width: 3,
                                          }),
                                          overflow: true,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "rural-estradas") {
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#f59e0b",
                                width: 6,
                            }), // Acende Laranja Forte
                            text:
                                zoom >= 14
                                    ? new ol.style.Text({
                                          text: name,
                                          font: "bold 14px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#b45309",
                                          }),
                                          stroke: new ol.style.Stroke({
                                              color: "#ffffff",
                                              width: 3,
                                          }),
                                          placement: "line",
                                          textBaseline: "bottom",
                                          offsetY: -5,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "rural-hidrografias") {
                    const geomType = feature.getGeometry().getType();
                    let hoverStyle;
                    if (geomType === "Point" || geomType === "MultiPoint") {
                        hoverStyle = new ol.style.Style({
                            image: new ol.style.Circle({
                                radius: 9,
                                fill: new ol.style.Fill({ color: "#38bdf8" }),
                                stroke: new ol.style.Stroke({
                                    color: "#ffffff",
                                    width: 3,
                                }),
                            }),
                        });
                    } else if (
                        geomType === "LineString" ||
                        geomType === "MultiLineString"
                    ) {
                        hoverStyle = new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#38bdf8",
                                width: 5,
                            }),
                        });
                    } else {
                        hoverStyle = new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#0284c7",
                                width: 3,
                            }),
                            fill: new ol.style.Fill({
                                color: "rgba(56, 189, 248, 0.6)",
                            }),
                        });
                    }

                    if (zoom >= 14 && name) {
                        hoverStyle.setText(
                            new ol.style.Text({
                                text: name,
                                font: "bold 14px Arial, sans-serif",
                                fill: new ol.style.Fill({ color: "#082f49" }),
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
                                        ? -18
                                        : 0,
                            }),
                        );
                    }
                    feature.setStyle(hoverStyle);
                } else if (layer === "rural-pontes") {
                    feature.setStyle(
                        new ol.style.Style({
                            image: new ol.style.Circle({
                                radius: 9, // Cresce a bolinha
                                fill: new ol.style.Fill({ color: "#b45309" }), // Marrom mais claro/vivo
                                stroke: new ol.style.Stroke({
                                    color: "#fbbf24",
                                    width: 3,
                                }), // Borda Amarela
                            }),
                            text:
                                zoom >= 14
                                    ? new ol.style.Text({
                                          text: name,
                                          font: "bold 14px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#78350f",
                                          }),
                                          stroke: new ol.style.Stroke({
                                              color: "#ffffff",
                                              width: 3,
                                          }),
                                          offsetY: -18,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "rural-pontos-interesse") {
                    const cat = feature.get("categoria");
                    let hoverColor = "#0f766e"; // Teal escuro padrão
                    if (cat === "Escola") hoverColor = "#1d4ed8";
                    else if (cat === "Saúde") hoverColor = "#b91c1c";
                    else if (cat === "Igreja") hoverColor = "#7e22ce";
                    else if (cat === "Turismo") hoverColor = "#b45309";
                    else if (cat === "Comércio") hoverColor = "#4d7c0f";

                    feature.setStyle(
                        new ol.style.Style({
                            image: new ol.style.Circle({
                                radius: 10,
                                fill: new ol.style.Fill({ color: hoverColor }),
                                stroke: new ol.style.Stroke({
                                    color: "#ffffff",
                                    width: 3,
                                }),
                            }),
                            text:
                                zoom >= 13
                                    ? new ol.style.Text({
                                          text: `${name} (${cat})`,
                                          font: "bold 14px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: hoverColor,
                                          }),
                                          stroke: new ol.style.Stroke({
                                              color: "#ffffff",
                                              width: 3,
                                          }),
                                          offsetY: -18,
                                      })
                                    : null,
                        }),
                    );
                } else if (layer === "loteamentos") {
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#1d4ed8",
                                width: 4,
                                lineDash: [8, 4],
                            }), // Azul mais forte
                            fill: new ol.style.Fill({
                                color: "rgba(37, 99, 235, 0.3)",
                            }), // Fundo mais visível
                            text:
                                zoom >= 14
                                    ? new ol.style.Text({
                                          text: name,
                                          font: "bold 16px Arial, sans-serif",
                                          fill: new ol.style.Fill({
                                              color: "#ffffff",
                                          }), // Texto Branco
                                          stroke: new ol.style.Stroke({
                                              color: "#1e40af",
                                              width: 4,
                                          }), // Borda azul escura
                                          overflow: true,
                                      })
                                    : null,
                        }),
                    );
                }
            }
        } else {
            // Limpa tudo se clicar fora
            if (featureTooltip) featureTooltip.style.display = "none";
        }

        // 🔍 TOOLTIP DO FILTRO AVANÇADO (Hover nos itens Laranjas)
        const tooltip = document.getElementById("feature-tooltip");
        if (tooltip) {
            let hitFiltro = false;

            // Só ativa o hover se a ferramenta do mouse estiver livre (pan)
            if (activeTool === "pan" || activeTool === "wait") {
                map.forEachFeatureAtPixel(
                    e.pixel,
                    function (feature, layer) {
                        // Verifica se a feature tem a propriedade 'titulo' (que vem lá do nosso novo PHP)
                        if (feature.get("titulo")) {
                            hitFiltro = true;

                            // Monta o visual do Tooltip
                            tooltip.innerHTML = `
                            <div style="font-size: 14px; font-weight: 900; color: #ffffff;">${feature.get("titulo")}</div>
                            <div style="font-size: 10px; color: #cbd5e1; margin-top: 2px;">${feature.get("info")}</div>
                        `;
                        }
                    },
                    { hitTolerance: 5 },
                ); // hitTolerance ajuda a pegar linhas finas (ruas) com facilidade
            }

            // Exibe e persegue o mouse
            if (hitFiltro) {
                tooltip.style.left = e.originalEvent.clientX + 15 + "px";
                tooltip.style.top = e.originalEvent.clientY + 15 + "px";
                tooltip.style.display = "block";
                map.getTargetElement().style.cursor = "pointer";
            } else {
                // Esconde se tirar o mouse de cima
                tooltip.style.display = "none";
                if (!window.isOrtogonalActive && activeTool === "pan") {
                    map.getTargetElement().style.cursor = ""; // Volta a seta do mouse ao normal
                }
            }
        }
    });

    map.on("singleclick", function (evt) {
        //trava para modo de consulta de unificação
        if (window.modoUnificacao) return;

        // 🛑 TRAVA MESTRA DE EDIÇÃO: Se estiver editando geometria, ignora cliques em outros artefatos!
        if (featureEmEdicao) {
            return; // Encerra o clique aqui, impedindo que abra qualquer ficha ou modal
        }

        // 🛑 INTERCEPTADOR DA NUMERAÇÃO PREDIAL (NOVO FLUXO: DESENHAR TRAJETO)
        if (activeTool.startsWith("numeracao")) {
            if (activeTool === "numeracao_step1") {
                const features = map.getFeaturesAtPixel(evt.pixel, {
                    hitTolerance: 5,
                });

                const clickedLogradouro = features
                    ? features.find((f) => f.get("layer") === "logradouros")
                    : null;
                if (clickedLogradouro) {
                    ruaSelecionadaNumeracao = clickedLogradouro;
                    activeTool = "numeracao_step2";

                    alert(
                        `✅ Rua "${clickedLogradouro.get("name")}" selecionada!\n\n2️⃣ PASSO 2: Agora DESENHE O TRAJETO da numeração.\nClique no ponto inicial e vá clicando para contornar a rua.\nDê DOIS CLIQUES Rápidos para finalizar o percurso.`,
                    );

                    map.getTargetElement().style.cursor = "crosshair";

                    // 🛑 Liga a ferramenta de desenhar Linha na tela!
                    currentDrawInteraction = new ol.interaction.Draw({
                        source: drawSource,
                        type: "LineString",
                        style: new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#eab308",
                                width: 5,
                                lineDash: [4, 4],
                            }), // Linha amarela tracejada
                        }),
                    });

                    currentDrawInteraction.on("drawend", function (e) {
                        // Quando terminar os dois cliques, pega o GeoJSON do trajeto
                        const drawnGeoJson = formatGeoJSON.writeGeometryObject(
                            e.feature.getGeometry(),
                        );

                        setTimeout(() => drawSource.clear(), 500);
                        map.removeInteraction(currentDrawInteraction);
                        window.resetToPan(); // Devolve a mãozinha azul

                        // Dispara para o PHP mandando a linha inteira desenhada!
                        Livewire.dispatch("abrirModalNumeracao", {
                            logradouro_id: ruaSelecionadaNumeracao.get("id"),
                            logradouro_nome:
                                ruaSelecionadaNumeracao.get("name"),
                            drawn_line: drawnGeoJson,
                        });
                    });

                    map.addInteraction(currentDrawInteraction);
                } else {
                    alert(
                        "❌ Você não clicou em uma rua. Aproxime o zoom e clique na linha colorida do logradouro.",
                    );
                }
            }
            return; // Impede que faça outras coisas no mapa
        }

        // 🛑 INTERCEPTADOR DE UNIFICAÇÃO (PASSO 1 E 2)
        if (activeTool.startsWith("unificar")) {
            const features = map.getFeaturesAtPixel(evt.pixel, {
                hitTolerance: 5,
            });
            const clickedLote = features
                ? features.find((f) => f.get("layer") === "lotes")
                : null;

            if (clickedLote) {
                const id = clickedLote.get("id");
                const highlightFeature = clickedLote.clone(); // Clona o desenho para pintar de roxo

                if (activeTool === "unificar_step1") {
                    lotePrincipalId = id;
                    unificacaoSource.addFeature(highlightFeature);
                    activeTool = "unificar_step2";
                    alert(
                        "✅ Lote Principal selecionado!\n\nPASSO 2: Agora clique no LOTE VIZINHO que será anexado/absorvido.",
                    );
                } else if (activeTool === "unificar_step2") {
                    if (id === lotePrincipalId) {
                        alert(
                            "⚠️ Você clicou no mesmo lote! Clique no lote VIZINHO.",
                        );
                        return;
                    }

                    loteSecundarioId = id;
                    unificacaoSource.addFeature(highlightFeature);

                    // Limpa a tela e volta o mouse ao normal
                    setTimeout(() => unificacaoSource.clear(), 1000);
                    window.resetToPan();

                    // Dispara a Mágica no Livewire!
                    Livewire.dispatch("processarUnificacaoLotes", {
                        lotePrincipalId: lotePrincipalId,
                        loteSecundarioId: loteSecundarioId,
                    });
                }
            } else {
                alert("❌ Clique dentro de um Lote válido.");
            }
            return; // Impede a ficha de abrir ou outro clique processar
        }

        // 🛑 INTERCEPTADOR DO CAD (CLONAR) - AGORA SOLTO E NO LUGAR CERTO!
        if (activeTool === "cad_clonar") {
            const features = map.getFeaturesAtPixel(evt.pixel, {
                hitTolerance: 5,
            });
            if (features && features.length > 0) {
                const featureToClone = features[0];
                const layerName = featureToClone.get("layer");

                // Evita clonar o próprio rascunho ou o mapa base
                if (layerName && layerName !== "cad_draft") {
                    const clone = featureToClone.clone();
                    clone.set("id", "clone_temp");
                    clone.set("layer", "cad_draft"); // Identificador da Mesa de Desenho
                    featureCloneOriginalLayer = layerName; // Guarda a origem (ex: 'lotes')

                    cadSource.clear();
                    cadSource.addFeature(clone);

                    // Transforma o clone no "featureEmEdicao" usando a nossa Mestra!
                    activeTool = "pan"; // Devolve o mouse pro normal
                    window.ativarModoEdicaoAvancado(clone, "#4f46e5");
                }
            }
            return; // Impede que abra a ficha lateral
        }

        // 🛑 INTERCEPTADOR DO CAD (BUFFER)
        if (activeTool === "cad_buffer") {
            const features = map.getFeaturesAtPixel(evt.pixel, {
                hitTolerance: 5,
            });
            if (features && features.length > 0) {
                const featureToBuffer = features[0];
                const layerName = featureToBuffer.get("layer");

                // Impede de dar buffer no próprio rascunho
                if (layerName && layerName !== "cad_draft") {
                    // 1. Pega o valor digitado e garante a conversão de vírgula para ponto
                    const inputElement =
                        document.getElementById("input-cad-buffer");
                    let valorDigitado = inputElement
                        ? inputElement.value.replace(",", ".")
                        : "5";
                    let distMetros = parseFloat(valorDigitado);

                    if (isNaN(distMetros) || distMetros <= 0) {
                        alert(
                            "⚠️ Digite uma distância válida maior que zero no campinho da barra inferior.",
                        );
                        return;
                    }

                    try {
                        // 2. Transforma o artefato original em GeoJSON
                        const geojsonOriginal =
                            formatGeoJSON.writeFeatureObject(featureToBuffer);

                        // 3. 🪄 A MÁGICA: O Turf infla a geometria instantaneamente!
                        // Adicionado "steps: 1" para diminuir a curvatura das pontas e deixar mais "chanfrado/reto"
                        const bufferedGeojson = turf.buffer(
                            geojsonOriginal,
                            distMetros,
                            { units: "meters" },
                        );

                        // 4. Converte de volta para Feature do OpenLayers EPSG:3857
                        const featureBuffer =
                            formatGeoJSON.readFeature(bufferedGeojson);

                        // 5. Carimba como "Mesa de Desenho"
                        featureBuffer.set("id", "clone_temp");
                        featureBuffer.set("layer", "cad_draft");
                        featureCloneOriginalLayer = layerName; // Guarda a origem

                        cadSource.clear();
                        cadSource.addFeature(featureBuffer);

                        // 6. Joga o resultado pro topo com as ferramentas ativas
                        activeTool = "pan"; // Solta a ferramenta
                        window.ativarModoEdicaoAvancado(
                            featureBuffer,
                            "#4f46e5",
                        );

                        // 7. 🛑 AVISA O HTML PARA ESCONDER A CAIXINHA DE METROS
                        window.dispatchEvent(new Event("fechar-submenus-cad"));
                    } catch (error) {
                        console.error("Erro no motor Turf.js:", error);
                        alert(
                            "⚠️ Não foi possível calcular o Buffer desta geometria.",
                        );
                    }
                }
            }
            return; // Impede que a ficha abra
        }

        // 🛑 INTERCEPTADOR DO CAD (UNIR GENÉRICO - SOMA BOOLEANA)
        if (activeTool.startsWith("cad_unir")) {
            const features = map.getFeaturesAtPixel(evt.pixel, {
                hitTolerance: 5,
            });

            if (features && features.length > 0) {
                // Procura o primeiro polígono clicado (ignora linhas/pontos e ignora o próprio rascunho)
                const clickedFeature = features.find(
                    (f) =>
                        f.get("layer") &&
                        f.get("layer") !== "cad_draft" &&
                        f.getGeometry().getType().includes("Polygon"),
                );

                if (clickedFeature) {
                    if (activeTool === "cad_unir_step1") {
                        window.cadFeatureToUnite = clickedFeature;

                        // Joga uma cópia na mesa de rascunho só para ficar roxo/azul e mostrar que foi selecionado
                        const cloneHighlight = clickedFeature.clone();
                        cadSource.clear();
                        cadSource.addFeature(cloneHighlight);

                        activeTool = "cad_unir_step2";
                        alert(
                            "✅ Primeiro polígono selecionado!\n\nPASSO 2: Clique no SEGUNDO polígono para processar a união geométrica.",
                        );
                    } else if (activeTool === "cad_unir_step2") {
                        if (
                            clickedFeature.get("id") ===
                            window.cadFeatureToUnite.get("id")
                        ) {
                            alert(
                                "⚠️ Você clicou no mesmo polígono! Clique no polígono vizinho para unir.",
                            );
                            return;
                        }

                        if (
                            clickedFeature.get("layer") !==
                            window.cadFeatureToUnite.get("layer")
                        ) {
                            alert(
                                "⚠️ Operação Inválida: Você só pode unir artefatos da mesma camada (ex: Setor Fiscal com Setor Fiscal).",
                            );
                            return;
                        }

                        try {
                            // 1. Extrai as duas geometrias para o padrão GeoJSON do Turf
                            const geo1 = formatGeoJSON.writeFeatureObject(
                                window.cadFeatureToUnite,
                            );
                            const geo2 =
                                formatGeoJSON.writeFeatureObject(
                                    clickedFeature,
                                );

                            // 2. 🪄 MÁGICA: O Turf.js faz a soma booleana das duas formas
                            const unionGeo = turf.union(geo1, geo2);

                            if (!unionGeo) {
                                alert(
                                    "❌ Ocorreu um erro matemático ao tentar unir essas geometrias.",
                                );
                                return;
                            }

                            // 3. Converte de volta para o OpenLayers
                            const featureUnida =
                                formatGeoJSON.readFeature(unionGeo);

                            // 4. Carimba como Rascunho para abrir a modal de criação ao salvar
                            featureUnida.set("id", "clone_temp");
                            featureUnida.set("layer", "cad_draft");
                            featureCloneOriginalLayer =
                                clickedFeature.get("layer"); // Salva de onde veio (ex: 'setores_fiscais')

                            cadSource.clear();
                            cadSource.addFeature(featureUnida);

                            // 5. Joga pra barra de edição do topo!
                            activeTool = "pan";
                            window.ativarModoEdicaoAvancado(
                                featureUnida,
                                "#4f46e5",
                            );
                            window.dispatchEvent(
                                new Event("fechar-submenus-cad"),
                            );
                        } catch (error) {
                            console.error(
                                "Erro no motor Turf.js (Union):",
                                error,
                            );
                            alert(
                                "⚠️ Não foi possível unir. Verifique se os polígonos possuem erros topológicos.",
                            );
                        }
                    }
                } else {
                    alert("❌ Clique num polígono válido.");
                }
            }
            return; // Impede abrir ficha
        }

        // 🛑 INTERCEPTADOR DO CAD (CORTAR GENÉRICO)
        if (activeTool.startsWith("cad_cortar")) {
            // PASSO 1: Selecionar o polígono
            if (activeTool === "cad_cortar_step1") {
                const features = map.getFeaturesAtPixel(evt.pixel, {
                    hitTolerance: 5,
                });
                const clickedFeature = features
                    ? features.find(
                          (f) =>
                              f.get("layer") &&
                              f.get("layer") !== "cad_draft" &&
                              f.getGeometry().getType().includes("Polygon"),
                      )
                    : null;

                if (clickedFeature) {
                    window.cadFeatureToCut = clickedFeature;

                    // Joga o polígono destacado na mesa
                    const cloneHighlight = clickedFeature.clone();
                    cadSource.clear();
                    cadSource.addFeature(cloneHighlight);

                    activeTool = "cad_cortar_step2";
                    alert(
                        "✅ Polígono selecionado!\n\nPASSO 2: Agora DESENHE UMA LINHA cruzando o polígono de fora a fora. Dê DOIS CLIQUES para finalizar a linha.",
                    );

                    // Liga a ferramenta de desenho de Linha (aproveitando nosso Motor Ortogonal!)
                    const drawOptionsCorte = {
                        source: cadSource,
                        type: "LineString",
                        style: new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#ea580c",
                                width: 4,
                                lineDash: [5, 5],
                            }),
                        }),
                    };
                    drawOptionsCorte.geometryFunction =
                        window.getOrtogonalGeometryFunction("LineString");
                    currentDrawInteraction = new ol.interaction.Draw(
                        drawOptionsCorte,
                    );

                    currentDrawInteraction.on("drawend", function (e) {
                        window.ortogonalLastFix = null;
                        const linhaGeoJson = formatGeoJSON.writeFeatureObject(
                            e.feature,
                        );
                        const polyGeoJson = formatGeoJSON.writeFeatureObject(
                            window.cadFeatureToCut,
                        );
                        const layerOrigem = window.cadFeatureToCut.get("layer");

                        map.removeInteraction(currentDrawInteraction);
                        currentDrawInteraction = null;
                        activeTool = "wait"; // Trava o mapa enquanto o servidor pensa
                        document.body.style.cursor = "wait";

                        // Manda pro Livewire cortar
                        Livewire.dispatch("processarCorteGenerico", {
                            polygonGeoJson: polyGeoJson.geometry,
                            lineGeoJson: linhaGeoJson.geometry,
                            layerOrigem: layerOrigem,
                        });
                    });

                    map.addInteraction(currentDrawInteraction);
                } else {
                    alert("❌ Clique num polígono válido.");
                }
            }
            // PASSO 3: O usuário escolhe a fatia na "Vitrine"
            else if (activeTool === "cad_cortar_step3") {
                const features = map.getFeaturesAtPixel(evt.pixel, {
                    hitTolerance: 5,
                });
                const clickedFatia = features
                    ? features.find((f) => f.get("is_fatia") === true)
                    : null;

                if (clickedFatia) {
                    cadSource.clear(); // Limpa as fatias rejeitadas

                    // Configura a fatia vencedora como rascunho
                    clickedFatia.set("id", "clone_temp");
                    clickedFatia.set("layer", "cad_draft");
                    clickedFatia.unset("is_fatia");

                    cadSource.addFeature(clickedFatia);

                    activeTool = "pan"; // Libera o mouse
                    window.ativarModoEdicaoAvancado(clickedFatia, "#4f46e5");
                    window.dispatchEvent(new Event("fechar-submenus-cad"));
                } else {
                    alert(
                        "❌ Clique DENTRO de uma das fatias pontilhadas para escolher.",
                    );
                }
            }
            return;
        }

        // 🛑 INTERCEPTADOR DO CAD (COTAR / GABARITO)
        if (activeTool === "cad_cotar") {
            const features = map.getFeaturesAtPixel(evt.pixel, {
                hitTolerance: 5,
            });
            const clickedFeature = features
                ? features.find(
                      (f) => f.get("layer") && f.get("layer") !== "cad_draft",
                  )
                : null;

            if (clickedFeature) {
                const geom = clickedFeature.getGeometry();
                const geomType = geom.getType();

                // Ignora Pontos (Árvores, Postes), pois não têm área nem lados
                if (geomType.includes("Point")) {
                    alert(
                        "⚠️ Esta ferramenta só funciona em Polígonos (Lotes, Quadras) ou Linhas (Ruas).",
                    );
                    return;
                }

                cadSource.clear(); // Limpa a cota do artefato anterior
                const labels = [];

                // 1. Destaca o artefato selecionado com uma cor suave
                const clone = clickedFeature.clone();
                clone.setStyle(
                    new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: "#ea580c",
                            width: 2,
                        }), // Laranja
                        fill: new ol.style.Fill({
                            color: "rgba(234, 88, 12, 0.1)",
                        }),
                    }),
                );
                cadSource.addFeature(clone);

                // Função auxiliar que "carimba" os textos no mapa
                const criarEtiqueta = (coords, texto, isCenter = false) => {
                    const feat = new ol.Feature(new ol.geom.Point(coords));
                    feat.setStyle(
                        new ol.style.Style({
                            text: new ol.style.Text({
                                text: texto,
                                font: isCenter
                                    ? "bold 13px Arial"
                                    : "bold 11px Arial",
                                fill: new ol.style.Fill({
                                    color: isCenter ? "#ffffff" : "#ea580c",
                                }),
                                backgroundFill: new ol.style.Fill({
                                    color: isCenter ? "#ea580c" : "#ffffff",
                                }),
                                backgroundStroke: new ol.style.Stroke({
                                    color: "#ea580c",
                                    width: 1,
                                }),
                                padding: [2, 4, 2, 4],
                            }),
                            zIndex: 10010,
                        }),
                    );
                    return feat;
                };

                // 2. Se for Polígono/MultiPolígono, calcula a Área e as Arestas
                if (geomType.includes("Polygon")) {
                    // 🛡️ BLINDAGEM: Descobre se é MultiPolygon e pega a forma principal
                    const basePoly =
                        geomType === "MultiPolygon" ? geom.getPolygon(0) : geom;

                    // Área Central (Calcula a área total de tudo, mas pega o centro só da forma principal)
                    const area = ol.sphere.getArea(geom);
                    const center = basePoly.getInteriorPoint().getCoordinates();
                    labels.push(
                        criarEtiqueta(center, area.toFixed(2) + " m²", true),
                    );

                    // Lados (Itera sobre cada linha do anel externo)
                    const anelExterno = basePoly.getCoordinates()[0];
                    for (let i = 0; i < anelExterno.length - 1; i++) {
                        const p1 = anelExterno[i];
                        const p2 = anelExterno[i + 1];

                        const segmento = new ol.geom.LineString([p1, p2]);
                        const distancia = ol.sphere.getLength(segmento);
                        const meio = [(p1[0] + p2[0]) / 2, (p1[1] + p2[1]) / 2];

                        // Só plota a etiqueta se a linha tiver mais de 0.5 metros (evita poluição em nós muito juntos)
                        if (distancia > 0.5) {
                            labels.push(
                                criarEtiqueta(
                                    meio,
                                    distancia.toFixed(2) + " m",
                                    false,
                                ),
                            );
                        }
                    }
                }
                // 3. Se for Linha/MultiLinha (Logradouro), calcula apenas o comprimento total
                else if (geomType.includes("LineString")) {
                    const comp = ol.sphere.getLength(geom);
                    // 🛡️ BLINDAGEM: Descobre se é MultiLineString e pega a linha principal
                    const baseLine =
                        geomType === "MultiLineString"
                            ? geom.getLineString(0)
                            : geom;
                    const meio = baseLine.getCoordinateAt(0.5); // Pega exatamente o ponto médio

                    labels.push(
                        criarEtiqueta(meio, comp.toFixed(2) + " m", true),
                    );
                }

                // Joga todas as etiquetas visuais na Mesa de Desenho
                cadSource.addFeatures(labels);
            } else {
                alert("❌ Clique num artefato válido para cotar.");
            }
            return; // Impede que abra a ficha
        }

        if (activeTool !== "pan") return;
        const features = map.getFeaturesAtPixel(evt.pixel, { hitTolerance: 5 });

        if (features && features.length > 0) {
            // 🛑 HIERARQUIA INTELIGENTE DE CLIQUES: Quem estiver mais no topo da lista "rouba" o clique!
            const clickPriority = [
                "edificacao_ativa", // Modo edição ganha de tudo
                "postes",
                "arvores",
                "toponimias",
                "rural-pontos-interesse",
                "rural-pontes", // PONTOS (Maior prioridade)
                "logradouros",
                "logradouros_cemiterio",
                "rural-estradas",
                "pontos_panoramicos", // LINHAS
                "jazigos", // Polígono Micro
                "rural-hidrografias", // Pode ser misto, prioridade alta
                "lotes",
                "rural-propriedades", // Polígonos Pequenos
                "quadras_cemiterio",
                "quadras", // Polígonos Médios
                "cemiterios",
                "setores_fiscais",
                "zonas", // Polígonos Grandes
                "bairros",
                "loteamentos",
                "rural-localidades", // Polígonos Gigantes (Menor prioridade)
            ];

            let clickedFeature = null;
            let clickedLayer = null;

            // Varre as entidades sob o mouse na ordem de prioridade definida acima
            for (const layerName of clickPriority) {
                const found = features.find(
                    (f) => f.get("layer") === layerName,
                );
                if (found) {
                    clickedFeature = found;
                    clickedLayer = layerName;
                    break; // Achou o mais prioritário? PARA A BUSCA NA HORA!
                }
            }

            if (clickedFeature) {
                const id = clickedFeature.get("id");

                // Envia a ação dependendo da camada que ganhou a prioridade
                switch (clickedLayer) {
                    case "edificacao_ativa":
                        Livewire.dispatch("abrirOpcoesEdificacao", { id: id });
                        break;
                    case "postes":
                        Livewire.dispatch("abrirOpcoesPoste", { id: id });
                        break;
                    case "arvores":
                        Livewire.dispatch("abrirOpcoesArvore", { id: id });
                        break;
                    case "rural-pontos-interesse":
                        Livewire.dispatch("abrirOpcoesRuralPontoInteresse", {
                            id: id,
                        });
                        break;
                    case "rural-pontes":
                        Livewire.dispatch("abrirOpcoesRuralPonte", { id: id });
                        break;
                    case "logradouros":
                        Livewire.dispatch("abrirOpcoesLogradouro", { id: id });
                        break;
                    case "logradouros_cemiterio":
                        Livewire.dispatch("abrirOpcoesLogradouroCemiterio", {
                            id: id,
                        });
                        break;
                    case "rural-estradas":
                        Livewire.dispatch("abrirOpcoesRuralEstrada", {
                            id: id,
                        });
                        break;
                    case "jazigos":
                        Livewire.dispatch("abrirOpcoesJazigo", { id: id });
                        break;
                    case "rural-hidrografias":
                        Livewire.dispatch("abrirOpcoesRuralHidrografia", {
                            id: id,
                        });
                        break;
                    case "rural-propriedades":
                        Livewire.dispatch("abrirOpcoesRuralPropriedade", {
                            id: id,
                        });
                        break;
                    case "quadras_cemiterio":
                        Livewire.dispatch("abrirOpcoesQuadraCemiterio", {
                            id: id,
                        });
                        break;
                    case "quadras":
                        Livewire.dispatch("abrirOpcoesQuadra", { id: id });
                        break;
                    case "cemiterios":
                        Livewire.dispatch("abrirOpcoesCemiterio", { id: id });
                        break;
                    case "setores_fiscais":
                        Livewire.dispatch("abrirOpcoesSetorFiscal", { id: id });
                        break;
                    case "zonas":
                        Livewire.dispatch("abrirOpcoesZona", { id: id });
                        break; // 👈 ADICIONE ESTA LINHA
                    case "bairros":
                        Livewire.dispatch("abrirOpcoesBairro", { id: id });
                        break;
                    case "loteamentos":
                        Livewire.dispatch("abrirOpcoesLoteamento", { id: id });
                        break;
                    case "rural-localidades":
                        Livewire.dispatch("abrirOpcoesRuralLocalidade", {
                            id: id,
                        });
                        break;

                    case "pontos_panoramicos":
                        Livewire.dispatch("abrirOpcoesPontoPanoramico", {
                            id: id,
                        });
                        break;

                    case "lotes":
                        // 🟢 MODIFICADO: Agora ele busca o 'name' padrão ou o 'titulo' gerado pelo filtro avançado
                        const loteNome =
                            clickedFeature.get("name") ||
                            clickedFeature.get("titulo") ||
                            "S/N";
                        Livewire.dispatch("abrirFichaImovel", {
                            loteId: id,
                            loteNome: loteNome,
                        });
                        break;

                    case "toponimias":
                        Livewire.dispatch("abrirOpcoesToponimiia", { id: id });
                        break;
                }

                return; // 🛑 FUNDAMENTAL: Encerra o evento aqui para não abrir modais empilhadas!
            }
        } else {
            if (featureEmEdicao) window.cancelarEdicaoGeometria();
        }
    }); // Fim do map.on('singleclick')

    // 10. MEDIÇÕES E RASCUNHOS
    // 10. MEDIÇÕES E RASCUNHOS
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
    let currentDrawInteraction = null;
    let currentTranslateInteraction = null; // NOVO: Para arrastar polígonos inteiros
    let currentSnapInteraction = null; // BUGFIX: Mantido para não quebrar as ferramentas de medição antigas
    let activeTool = "pan";

    // NOVO: GERENCIADOR DE ÍMÃ UNIVERSAL (SNAP)
    let activeSnaps = [];

    window.enableUniversalSnap = function () {
        window.disableUniversalSnap(); // Limpa resquícios anteriores

        // Varre todas as camadas carregadas no objeto global
        Object.keys(window.loadedLayers).forEach((layerName) => {
            const layer = window.loadedLayers[layerName];

            // Aplica o ímã APENAS nas camadas que o usuário ligou no menu lateral
            if (layer && layer.getVisible()) {
                const snap = new ol.interaction.Snap({
                    source: layer.getSource(),
                    pixelTolerance: 12, // Força do ímã (aumentei um pouco para facilitar)
                });
                map.addInteraction(snap);
                activeSnaps.push(snap);
            }
        });
    };

    window.disableUniversalSnap = function () {
        activeSnaps.forEach((snap) => map.removeInteraction(snap));
        activeSnaps = [];
    };

    // =========================================================================
    // 📐 MOTOR ORTOGONAL UNIVERSAL (AutoCAD Style Ortho)
    // =========================================================================
    window.isOrtogonalActive = false;
    window.ortogonalLastFix = null; // Guarda o último ponto fixado na tela

    // Camada visual para as linhas guias "infinitas"
    window.ortogonalGuideSource = new ol.source.Vector();
    const ortogonalGuideLayer = new ol.layer.Vector({
        source: window.ortogonalGuideSource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({
                color: "rgba(16, 185, 129, 0.8)",
                width: 1.5,
                lineDash: [5, 5],
            }), // Verde esmeralda tracejado mais visível
        }),
        zIndex: 10006, // Topo absoluto
    });
    map.addLayer(ortogonalGuideLayer);

    window.atualizarGuiasOrtogonais = function (centerCoord) {
        if (!window.isOrtogonalActive || !centerCoord) {
            window.ortogonalGuideSource.clear();
            return;
        }
        const ext = map.getView().calculateExtent(map.getSize());
        const maxDist = (ext[2] - ext[0]) * 2;

        const guideX = new ol.Feature(
            new ol.geom.LineString([
                [centerCoord[0] - maxDist, centerCoord[1]],
                [centerCoord[0] + maxDist, centerCoord[1]],
            ]),
        );
        const guideY = new ol.Feature(
            new ol.geom.LineString([
                [centerCoord[0], centerCoord[1] - maxDist],
                [centerCoord[0], centerCoord[1] + maxDist],
            ]),
        );

        window.ortogonalGuideSource.clear();
        window.ortogonalGuideSource.addFeatures([guideX, guideY]);
    };

    window.toggleOrtogonal = function (isActive) {
        window.isOrtogonalActive = isActive;
        if (!isActive) {
            window.ortogonalGuideSource.clear();
            window.ortogonalLastFix = null;
        }
    };

    window.getOrtogonalGeometryFunction = function (type) {
        return function (coordinates, geometry) {
            if (!geometry) {
                geometry =
                    type === "Polygon"
                        ? new ol.geom.Polygon(coordinates)
                        : new ol.geom.LineString(coordinates);
            }

            if (window.isOrtogonalActive) {
                let pts = type === "Polygon" ? coordinates[0] : coordinates;

                if (pts && pts.length >= 2) {
                    // O Segredo: No Polígono, o mouse é o penúltimo ponto. O último é a âncora de fechamento (intocável!)
                    let mouseIdx =
                        type === "Polygon" ? pts.length - 2 : pts.length - 1;
                    let lastFixIdx =
                        type === "Polygon" ? pts.length - 3 : pts.length - 2;

                    if (lastFixIdx >= 0 && pts[lastFixIdx]) {
                        let lastFix = pts[lastFixIdx];
                        let mouse = pts[mouseIdx];

                        // Trava o eixo ortogonal matematicamente APENAS no nó do mouse!
                        if (
                            Math.abs(mouse[0] - lastFix[0]) >
                            Math.abs(mouse[1] - lastFix[1])
                        ) {
                            mouse[1] = lastFix[1]; // Trava Y (Linha Horizontal)
                        } else {
                            mouse[0] = lastFix[0]; // Trava X (Linha Vertical)
                        }

                        // 🛑 BLINDAGEM: Retiramos a sobrescrita do nó de fechamento.
                        // O OpenLayers cuida disso sozinho, garantindo que você consiga
                        // clicar quantas vezes quiser até fechar o lote!

                        // Guarda a referência do último clique para a Linha Guia verde seguir
                        window.ortogonalLastFix = [lastFix[0], lastFix[1]];
                    }
                }
            }

            geometry.setCoordinates(coordinates);
            return geometry;
        };
    };

    // 11. DESENHO DE ARTEFATOS (FUNÇÃO GLOBAL PARA O HTML)
    let currentDrawEntity = null;
    const formatGeoJSON = new ol.format.GeoJSON({
        featureProjection: "EPSG:3857",
        dataProjection: "EPSG:4326",
    });

    window.enableDrawing = function (entityType) {
        // 1. Limpa qualquer régua ou mãozinha ativa
        if (typeof window.resetToPan === "function") window.resetToPan();

        activeTool = "draw";
        currentDrawEntity = entityType;

        // 2. Apaga a cor azul da mãozinha visualmente
        const btnPan = document.getElementById("btn-pan");
        if (btnPan) {
            btnPan.classList.remove(
                "bg-primary-100",
                "text-primary-600",
                "dark:bg-primary-900/30",
                "dark:text-primary-400",
            );
            btnPan.classList.add(
                "hover:bg-gray-100",
                "text-gray-600",
                "dark:hover:bg-gray-700",
                "dark:text-gray-300",
            );
        }

        // 3. Limpa interações antigas
        if (currentMeasureInteraction)
            map.removeInteraction(currentMeasureInteraction);
        if (currentDrawInteraction)
            map.removeInteraction(currentDrawInteraction);
        if (currentSnapInteraction)
            map.removeInteraction(currentSnapInteraction);

        drawSource.clear();
        measureTooltipElement.style.display = "none";

        let geometryType = "Polygon";

        //point
        //point
        if (
            [
                "arvore",
                "poste",
                "rural_hidro_ponto",
                "rural_ponte",
                "rural_ponto_interesse",
                "ponto_panoramico",
            ].includes(entityType)
        )
            geometryType = "Point";

        //linestring
        if (
            [
                "logradouro",
                "logradouro_cemiterio",
                "rural_estrada",
                "rural_hidro_linha",
            ].includes(entityType)
        )
            geometryType = "LineString";
        // Se for 'rural_hidro_poligono', ele cai no padrão Polygon!

        map.getTargetElement().style.cursor = "crosshair";

        const drawOptions = {
            source: drawSource,
            type: geometryType,
            style: new ol.style.Style({
                stroke: new ol.style.Stroke({
                    color: "#3b82f6",
                    width: 3,
                    lineDash: [5, 5],
                }),
                fill: new ol.style.Fill({ color: "rgba(59, 130, 246, 0.2)" }),
                image: new ol.style.Circle({
                    radius: 6,
                    fill: new ol.style.Fill({ color: "#3b82f6" }),
                }),
            }),
        };

        // 📐 MÁGICA: Injeta o limitador ortogonal se a entidade for polígono ou linha
        if (geometryType === "Polygon" || geometryType === "LineString") {
            drawOptions.geometryFunction =
                window.getOrtogonalGeometryFunction(geometryType);
        }

        currentDrawInteraction = new ol.interaction.Draw(drawOptions);

        currentDrawInteraction.on("drawend", function (e) {
            window.ortogonalLastFix = null; // 🧹 SOLTA A LINHA GUIA

            const geoJson = formatGeoJSON.writeFeatureObject(e.feature);
            setTimeout(() => drawSource.clear(), 500);
            map.getTargetElement().style.cursor = "";
            map.removeInteraction(currentDrawInteraction);
            if (currentSnapInteraction)
                map.removeInteraction(currentSnapInteraction);

            // Devolve a cor azul pra mãozinha ao terminar
            activeTool = "pan";
            if (btnPan) {
                btnPan.classList.add(
                    "bg-primary-100",
                    "text-primary-600",
                    "dark:bg-primary-900/30",
                    "dark:text-primary-400",
                );
                btnPan.classList.remove(
                    "hover:bg-gray-100",
                    "text-gray-600",
                    "dark:hover:bg-gray-700",
                    "dark:text-gray-300",
                );
            }

            Livewire.dispatch("abrirModalCriacao", {
                entityType: currentDrawEntity,
                geoJson: geoJson.geometry,
            });
        });

        map.addInteraction(currentDrawInteraction);

        // 🧲 LIGA O ÍMÃ UNIVERSAL PARA O DESENHO
        window.enableUniversalSnap();
    };

    window.addEventListener("limpar-rascunho-mapa", () => {
        if (drawSource) drawSource.clear();
    });

    // 12. ATUALIZAÇÕES VISUAIS DO LIVEWIRE
    window.addEventListener("adicionar-lote-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        if (drawSource) drawSource.clear();
        const checkbox = document.querySelector('input[data-layer="lotes"]');
        if (checkbox && checkbox.checked && window.loadedLayers["lotes"]) {
            const feature = new ol.Feature({
                geometry: new ol.format.GeoJSON().readGeometry(data.geo, {
                    dataProjection: "EPSG:4326",
                    featureProjection: "EPSG:3857",
                }),
                id: data.id,
                name: data.numero_lote,
                layer: "lotes",
            });
            window.loadedLayers["lotes"].getSource().addFeature(feature);
        }
    });

    window.addEventListener("atualizar-label-lote", (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers["lotes"]) {
            const feature = window.loadedLayers["lotes"]
                .getSource()
                .getFeatures()
                .find((f) => f.get("id") == data.id);
            if (feature) {
                feature.set("name", data.numero_lote);
                feature.changed();
            }
        }
    });

    window.addEventListener("remover-lote-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers["lotes"]) {
            const source = window.loadedLayers["lotes"].getSource();
            const feature = source
                .getFeatures()
                .find((f) => f.get("id") == data.id);
            if (feature) source.removeFeature(feature);
        }
    });

    // ── CIRURGIA EM MEMÓRIA: LOGRADOUROS ──────────────────────────────
    window.addEventListener("adicionar-logradouro-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        const checkbox = document.querySelector(
            'input[data-layer="logradouros"]',
        );
        if (
            checkbox &&
            checkbox.checked &&
            window.loadedLayers["logradouros"]
        ) {
            const feature = new ol.Feature({
                geometry: new ol.format.GeoJSON().readGeometry(data.geo, {
                    dataProjection: "EPSG:4326",
                    featureProjection: "EPSG:3857",
                }),
                id: data.id,
                name: data.name,
                layer: "logradouros",
            });
            window.loadedLayers["logradouros"].getSource().addFeature(feature);
        }
    });

    window.addEventListener("atualizar-label-logradouro", (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers["logradouros"]) {
            const feature = window.loadedLayers["logradouros"]
                .getSource()
                .getFeatures()
                .find((f) => f.get("id") == data.id);
            if (feature) {
                feature.set("name", data.name);
                feature.changed();
            }
        }
    });

    window.addEventListener("remover-logradouro-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers["logradouros"]) {
            const source = window.loadedLayers["logradouros"].getSource();
            const feature = source
                .getFeatures()
                .find((f) => f.get("id") == data.id);
            if (feature) source.removeFeature(feature);
        }
    });

    // ── CIRURGIA EM MEMÓRIA: TOPONÍMIAS ──────────────────────────────
    window.addEventListener("adicionar-toponimia-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        const checkbox = document.querySelector(
            'input[data-layer="toponimias"]',
        );
        if (checkbox && checkbox.checked && window.loadedLayers["toponimias"]) {
            const feature = new ol.Feature({
                geometry: new ol.geom.Point(
                    ol.proj.fromLonLat([data.lon, data.lat]),
                ),
                id: data.id,
                texto: data.texto,
                estilo: data.estilo,
                layer: "toponimias",
            });
            window.loadedLayers["toponimias"].getSource().addFeature(feature);
        }
    });

    window.addEventListener("atualizar-label-toponimia", (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers["toponimias"]) {
            const feature = window.loadedLayers["toponimias"]
                .getSource()
                .getFeatures()
                .find((f) => f.get("id") == data.id);
            if (feature) {
                feature.set("texto", data.texto);
                feature.set("estilo", data.estilo);
                feature.changed();
            }
        }
    });

    window.addEventListener("remover-toponimia-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers["toponimias"]) {
            const source = window.loadedLayers["toponimias"].getSource();
            const feature = source
                .getFeatures()
                .find((f) => f.get("id") == data.id);
            if (feature) source.removeFeature(feature);
        }
    });

    // 13. EDIÇÃO DE GEOMETRIA (MODIFICAR + MOVER)
    let currentModifyInteraction = null;
    let featureEmEdicao = null;
    let geometriaOriginal = null;

    // 🛠️ NOVA FUNÇÃO MESTRA DE EDIÇÃO (Mover + Redimensionar + Ímã)
    window.ativarModoEdicaoAvancado = function (feature, corHex) {
        geometriaOriginal = feature.getGeometry().clone();
        featureEmEdicao = feature;

        const collection = new ol.Collection([feature]);

        // 1. INTERAÇÃO DE MODIFICAR (Puxar os cantos/nós)
        currentModifyInteraction = new ol.interaction.Modify({
            features: collection,
            style: new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 7,
                    fill: new ol.style.Fill({ color: corHex }),
                    stroke: new ol.style.Stroke({ color: "#ffffff", width: 2 }),
                }),
            }),
        });

        // 2. INTERAÇÃO DE MOVER (Arrastar o polígono inteiro)
        currentTranslateInteraction = new ol.interaction.Translate({
            features: collection,
        });

        // 🛑 INICIA POR PADRÃO APENAS COM O ARRASTAR ATIVO
        map.addInteraction(currentTranslateInteraction);

        // 3. LIGA O ÍMÃ UNIVERSAL PARA A EDIÇÃO
        window.enableUniversalSnap();

        window.dispatchEvent(
            new CustomEvent("iniciar-edicao", {
                detail: { id: feature.get("id") },
            }),
        );
    };

    // 🔄 NOVA FUNÇÃO: ALTERNAR ENTRE ARRASTAR E REDIMENSIONAR (Chamada pelo Blade)
    // Variável para guardar o estado exato do polígono antes de começar a girar
    let geometriaParaRotacaoGeoJSON = null;

    // 🔄 NOVA FUNÇÃO: ALTERNAR FERRAMENTAS
    window.alternarFerramentaEdicao = function (modo) {
        if (!featureEmEdicao) return;

        // Remove as interações para não dar briga
        map.removeInteraction(currentModifyInteraction);
        map.removeInteraction(currentTranslateInteraction);

        if (modo === "mover") {
            map.addInteraction(currentTranslateInteraction);
        } else if (modo === "redimensionar") {
            map.addInteraction(currentModifyInteraction);
        } else if (modo === "girar") {
            // Quando entra no modo girar, "tira uma foto" da geometria atual em EPSG:4326 (padrão do Turf)
            geometriaParaRotacaoGeoJSON =
                formatGeoJSON.writeFeatureObject(featureEmEdicao);
        }

        // 🧲 Sempre reativa o ímã
        window.enableUniversalSnap();
    };

    // 📐 MÁGICA DO TURF.JS: GIRA O POLÍGONO EM TEMPO REAL
    window.aplicarRotacao = function (graus) {
        if (!featureEmEdicao || !geometriaParaRotacaoGeoJSON || !window.turf)
            return;

        const angulo = parseFloat(graus) || 0;

        // 1. Acha o centro (pivô) da geometria original
        const centro = turf.centroid(geometriaParaRotacaoGeoJSON);

        // 2. Manda o Turf girar usando o centro como eixo
        const featureRotacionada = turf.transformRotate(
            geometriaParaRotacaoGeoJSON,
            angulo,
            {
                pivot: centro.geometry.coordinates,
            },
        );

        // 3. Converte de volta pro formato do OpenLayers (EPSG:3857) e atualiza a tela instantaneamente
        const novaGeometriaOL = formatGeoJSON.readGeometry(
            featureRotacionada.geometry,
        );
        featureEmEdicao.setGeometry(novaGeometriaOL);
    };

    // OUVINTES
    // 🗂️ DICIONÁRIO DE EDIÇÃO (Mapeia o evento do Livewire para a Camada e a Cor do Nó)
    const configsEdicao = [
        { evento: "iniciar-edicao-geometria", layer: "lotes", cor: "#10b981" },
        {
            evento: "iniciar-edicao-geometria-edificacao",
            layer: "edificacao_ativa",
            cor: "#ea580c",
        },
        {
            evento: "iniciar-edicao-geometria-logradouro",
            layer: "logradouros",
            cor: "#38bdf8",
        },
        {
            evento: "iniciar-edicao-geometria-poste",
            layer: "postes",
            cor: "#eab308",
        },
        {
            evento: "iniciar-edicao-geometria-arvore",
            layer: "arvores",
            cor: "#22c55e",
        },
        {
            evento: "iniciar-edicao-geometria-bairro",
            layer: "bairros",
            cor: "#3b82f6",
        },
        {
            evento: "iniciar-edicao-geometria-loteamento",
            layer: "loteamentos",
            cor: "#2563eb",
        },
        {
            evento: "iniciar-edicao-geometria-quadra",
            layer: "quadras",
            cor: "#f97316",
        },
        {
            evento: "iniciar-edicao-geometria-cemiterio",
            layer: "cemiterios",
            cor: "#9333ea",
        },
        {
            evento: "iniciar-edicao-geometria-quadra_cemiterio",
            layer: "quadras_cemiterio",
            cor: "#6366f1",
        },
        {
            evento: "iniciar-edicao-geometria-logradouro_cemiterio",
            layer: "logradouros_cemiterio",
            cor: "#64748b",
        },
        {
            evento: "iniciar-edicao-geometria-jazigo",
            layer: "jazigos",
            cor: "#57534e",
        },
        {
            evento: "iniciar-edicao-geometria-setor_fiscal",
            layer: "setores_fiscais",
            cor: "#f59e0b",
        },

        {
            evento: "iniciar-edicao-geometria-zona",
            layer: "zonas",
            cor: "#ec4899",
        },
        {
            evento: "iniciar-edicao-geometria-ponto_panoramico",
            layer: "pontos_panoramicos",
            cor: "#3b82f6",
        },

        {
            evento: "iniciar-edicao-geometria-rural_localidade",
            layer: "rural-localidades",
            cor: "#57534e",
        },
        {
            evento: "iniciar-edicao-geometria-rural_propriedade",
            layer: "rural-propriedades",
            cor: "#57534e",
        },
        {
            evento: "iniciar-edicao-geometria-rural_estrada",
            layer: "rural-estradas",
            cor: "#78350f",
        },
        {
            evento: "iniciar-edicao-geometria-rural_hidrografia",
            layer: "rural-hidrografias",
            cor: "#0ea5e9",
        },
        {
            evento: "iniciar-edicao-geometria-rural_ponte",
            layer: "rural-pontes",
            cor: "#f59e0b",
        },
        {
            evento: "iniciar-edicao-geometria-rural_ponto_interesse",
            layer: "rural-pontos-interesse",
            cor: "#14b8a6",
        },
    ];

    // 🔄 REGISTRA TODOS OS OUVINTES DE UMA VEZ SÓ
    configsEdicao.forEach((config) => {
        window.addEventListener(config.evento, (e) => {
            const data = e.detail[0] || e.detail;
            let featureAlvo = null;

            // Tratamento especial para edificação (que vive em uma camada temporária separada)
            if (config.layer === "edificacao_ativa") {
                if (typeof edifAtivasSource !== "undefined") {
                    featureAlvo = edifAtivasSource
                        .getFeatures()
                        .find((f) => f.get("id") == data.id);
                }
            } else {
                // Busca o polígono/linha/ponto no cache de camadas do mapa
                if (window.loadedLayers[config.layer]) {
                    featureAlvo = window.loadedLayers[config.layer]
                        .getSource()
                        .getFeatures()
                        .find((f) => f.get("id") == data.id);
                }
            }

            // Se achou o desenho, liga os motores!
            if (featureAlvo) {
                window.ativarModoEdicaoAvancado(featureAlvo, config.cor);
            }
        });
    });

    // SALVAR GEOMETRIA PARA TODOS
    window.salvarEdicaoGeometria = function () {
        if (featureEmEdicao) {
            const geoJson = formatGeoJSON.writeGeometryObject(
                featureEmEdicao.getGeometry(),
            );
            const id = featureEmEdicao.get("id");
            const layerName = featureEmEdicao.get("layer");

            // 🛑 MÁGICA DO CAD: Se for o rascunho, ABRE A MODAL DE CRIAR em vez de dar Update!
            if (layerName === "cad_draft") {
                // Dicionário de tradução do plural (camada) para o singular (entidade)
                const mapSingular = {
                    lotes: "lote",
                    quadras: "quadra",
                    bairros: "bairro",
                    loteamentos: "loteamento",
                    edificacao_ativa: "edificacao",
                    logradouros: "logradouro",
                    postes: "poste",
                    arvores: "arvore",
                    cemiterios: "cemiterio",
                    quadras_cemiterio: "quadra_cemiterio",
                    logradouros_cemiterio: "logradouro_cemiterio",
                    jazigos: "jazigo",
                    setores_fiscais: "setor_fiscal",
                    "rural-localidades": "rural_localidade",
                    "rural-propriedades": "rural_propriedade",
                    "rural-estradas": "rural_estrada",
                    "rural-hidrografias": "rural_hidrografia",
                    "rural-pontes": "rural_ponte",
                    "rural-pontos-interesse": "rural_ponto_interesse",
                };

                const entityToCreate = mapSingular[featureCloneOriginalLayer];

                if (entityToCreate) {
                    Livewire.dispatch("abrirModalCriacao", {
                        entityType: entityToCreate,
                        geoJson: geoJson,
                    });
                    cadSource.clear();
                }
                encerrarModoEdicao();
                return; // Encerra o salvamento aqui!
            }

            window._featureBackup = featureEmEdicao;
            window._geometriaBackup = geometriaOriginal;

            if (layerName === "lotes")
                Livewire.dispatch("salvarNovaGeometria", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "edificacao_ativa")
                Livewire.dispatch("salvarNovaGeometriaEdificacao", {
                    id: id,
                    geoJson: geoJson,
                });
            // Adicionado o despacho para salvar o Logradouro:
            else if (layerName === "logradouros")
                Livewire.dispatch("salvarNovaGeometriaLogradouro", {
                    id: id,
                    geoJson: geoJson,
                });
            // Disparo para salvar a nova posição do Poste
            else if (layerName === "postes")
                Livewire.dispatch("salvarNovaGeometriaPoste", {
                    id: id,
                    geoJson: geoJson,
                });
            // Disparo para salvar a nova posição da Árvore
            else if (layerName === "arvores")
                Livewire.dispatch("salvarNovaGeometriaArvore", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "bairros")
                Livewire.dispatch("salvarNovaGeometriaBairro", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "loteamentos")
                Livewire.dispatch("salvarNovaGeometriaLoteamento", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "quadras")
                Livewire.dispatch("salvarNovaGeometriaQuadra", {
                    id: id,
                    geoJson: geoJson,
                });
            // 🛑 INJEÇÃO 3: Disparo para salvar o Cemitério
            else if (layerName === "cemiterios")
                Livewire.dispatch("salvarNovaGeometriaCemiterio", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "quadras_cemiterio")
                Livewire.dispatch("salvarNovaGeometriaQuadraCemiterio", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "logradouros_cemiterio")
                Livewire.dispatch("salvarNovaGeometriaLogradouroCemiterio", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "jazigos")
                Livewire.dispatch("salvarNovaGeometriaJazigo", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "setores_fiscais")
                Livewire.dispatch("salvarNovaGeometriaSetorFiscal", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "rural-localidades")
                Livewire.dispatch("salvarNovaGeometriaRuralLocalidade", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "rural-propriedades")
                Livewire.dispatch("salvarNovaGeometriaRuralPropriedade", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "rural-estradas")
                Livewire.dispatch("salvarNovaGeometriaRuralEstrada", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "rural-hidrografias")
                Livewire.dispatch("salvarNovaGeometriaRuralHidrografia", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "rural-pontes")
                Livewire.dispatch("salvarNovaGeometriaRuralPonte", {
                    id: id,
                    geoJson: geoJson,
                });
            else if (layerName === "rural-pontos-interesse")
                Livewire.dispatch("salvarNovaGeometriaRuralPontoInteresse", {
                    id: id,
                    geoJson: geoJson,
                });

            encerrarModoEdicao();
        }
    };

    window.cancelarEdicaoGeometria = function () {
        if (featureEmEdicao) {
            // Se for o rascunho, só apaga da tela
            if (featureEmEdicao.get("layer") === "cad_draft") {
                cadSource.clear();
            } else if (geometriaOriginal) {
                // Se for edição normal, devolve a geometria original
                featureEmEdicao.setGeometry(geometriaOriginal);
                featureEmEdicao.changed();
            }
        }
        encerrarModoEdicao();
    };

    window.addEventListener("desfazer-edicao-geometria", () => {
        if (window._featureBackup && window._geometriaBackup) {
            window._featureBackup.setGeometry(window._geometriaBackup);
            window._featureBackup.changed();
            window._featureBackup = null;
            window._geometriaBackup = null;
        }
    });

    window.addEventListener("fechar-modal-filament", () => {
        // O delay de 150ms é o segredo! Ele simula um clique humano logo após o Livewire processar a ação,
        // garantindo que a animação do Alpine.js termine em paz e leve o fundo escuro embora.
        setTimeout(() => {
            const closeBtn = document.querySelector(".fi-modal-close-btn");
            if (closeBtn) {
                closeBtn.click();
            }
        }, 150);
    });

    function encerrarModoEdicao() {
        if (currentModifyInteraction)
            map.removeInteraction(currentModifyInteraction);
        if (currentTranslateInteraction)
            map.removeInteraction(currentTranslateInteraction);
        window.disableUniversalSnap(); // Desliga o ímã universal

        currentModifyInteraction = null;
        currentTranslateInteraction = null;
        featureEmEdicao = null;
        geometriaOriginal = null;

        window.dispatchEvent(new Event("encerrar-edicao"));
    }

    // 🔪 OUVINTE DA TESOURA: Quando o PHP devolve as fatias cortadas
    window.addEventListener("mostrar-fatias-corte", (e) => {
        const data = e.detail[0] || e.detail;
        const fatias = data.fatias;
        featureCloneOriginalLayer = data.layerOrigem; // Grava a entidade original (ex: setor_fiscal)

        cadSource.clear(); // Limpa a linha de desenho
        document.body.style.cursor = "default";

        // Desenha as fatias na tela como uma "Vitrine"
        fatias.forEach((fatia, index) => {
            const feature = formatGeoJSON.readFeature(fatia.geojson, {
                dataProjection: "EPSG:4326",
                featureProjection: "EPSG:3857",
            });
            feature.set("is_fatia", true); // Etiqueta identificadora

            // Pinta a fatia maior de verde, a fatia menor de azul
            feature.setStyle(
                new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: index === 0 ? "#10b981" : "#3b82f6",
                        width: 3,
                        lineDash: [5, 5],
                    }),
                    fill: new ol.style.Fill({
                        color:
                            index === 0
                                ? "rgba(16, 185, 129, 0.4)"
                                : "rgba(59, 130, 246, 0.4)",
                    }),
                }),
            );
            cadSource.addFeature(feature);
        });

        activeTool = "cad_cortar_step3"; // Muda o estado
        alert(
            "✂️ CORTE REALIZADO!\n\nPASSO 3: Clique na fatia que você deseja EXTRAIR E MANTER. A outra será descartada.",
        );
    });

    // Se o corte der errado, destrava o mapa
    window.addEventListener("cancelar-corte-generico", () => {
        cadSource.clear();
        window.resetToPan();
        document.body.style.cursor = "default";
        window.dispatchEvent(new Event("fechar-submenus-cad"));
    });

    // 14. CAMADA TEMPORÁRIA DE EDIFICAÇÕES (Laranja)
    const edifAtivasSource = new ol.source.Vector();
    const edifAtivasLayer = new ol.layer.Vector({
        source: edifAtivasSource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({ color: "#ea580c", width: 3 }),
            fill: new ol.style.Fill({ color: "rgba(234, 88, 12, 0.5)" }),
        }),
        zIndex: 9999,
    });
    map.addLayer(edifAtivasLayer);

    window.addEventListener("mostrar-edificacoes-lote", (e) => {
        const edificacoes =
            e.detail && e.detail.edificacoes
                ? e.detail.edificacoes
                : e.detail[0] || e.detail;
        edifAtivasSource.clear();
        if (
            edificacoes &&
            Array.isArray(edificacoes) &&
            edificacoes.length > 0
        ) {
            const features = [];
            edificacoes.forEach((edif) => {
                if (edif.geo) {
                    try {
                        const feature = new ol.Feature({
                            geometry: new ol.format.GeoJSON().readGeometry(
                                edif.geo,
                                {
                                    dataProjection: "EPSG:4326",
                                    featureProjection: "EPSG:3857",
                                },
                            ),
                            id: edif.id,
                            layer: "edificacao_ativa",
                        });
                        features.push(feature);
                    } catch (err) {
                        console.error("Erro pintar edificação", err);
                    }
                }
            });
            edifAtivasSource.addFeatures(features);
        }
    });

    window.addEventListener("esconder-edificacoes-lote", () =>
        edifAtivasSource.clear(),
    );

    // 15. FERRAMENTAS DE MEDIÇÃO E NAVEGAÇÃO
    const btnPan = document.getElementById("btn-pan");
    const btnMeasureLine = document.getElementById("btn-measure-line");
    const btnMeasureArea = document.getElementById("btn-measure-area");

    const btnToolNumeracao = document.getElementById("btn-tool-numeracao");
    let ruaSelecionadaNumeracao = null;

    if (btnToolNumeracao) {
        btnToolNumeracao.addEventListener("click", function () {
            window.resetToPan();
            activeTool = "numeracao_step1";
            ruaSelecionadaNumeracao = null;
            map.getTargetElement().style.cursor = "help";
            alert("1️⃣ PASSO 1: Clique na RUA (Linha) que você deseja numerar.");
        });
    }

    // CAMADA VISUAL PARA A UNIFICAÇÃO (Roxa)
    const unificacaoSource = new ol.source.Vector();
    const unificacaoLayer = new ol.layer.Vector({
        source: unificacaoSource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({ color: "#9333ea", width: 4 }), // Roxo brilhante
            fill: new ol.style.Fill({ color: "rgba(147, 51, 234, 0.4)" }),
        }),
        zIndex: 10001,
    });
    map.addLayer(unificacaoLayer);

    let lotePrincipalId = null;
    let loteSecundarioId = null;

    const btnToolUnificar = document.getElementById("btn-tool-unificar");
    if (btnToolUnificar) {
        btnToolUnificar.addEventListener("click", function () {
            window.resetToPan();
            activeTool = "unificar_step1";
            lotePrincipalId = null;
            loteSecundarioId = null;
            map.getTargetElement().style.cursor = "crosshair";
            alert(
                "🔗 MODO UNIFICAÇÃO ATIVADO\n\nPASSO 1: Clique no LOTE PRINCIPAL (Este é o lote que vai 'absorver' o vizinho e herdar as edificações).",
            );
        });
    }

    window.resetToPan = function () {
        if (currentMeasureInteraction)
            map.removeInteraction(currentMeasureInteraction);
        if (currentDrawInteraction)
            map.removeInteraction(currentDrawInteraction);
        if (currentSnapInteraction)
            map.removeInteraction(currentSnapInteraction);
        if (currentTranslateInteraction)
            map.removeInteraction(currentTranslateInteraction); // Limpa o arrastar

        // 🧹 Limpa as guias SÓ SE a ferramenta tiver sido desligada
        if (!window.isOrtogonalActive && window.ortogonalGuideSource)
            window.ortogonalGuideSource.clear();
        window.ortogonalLastFix = null; // Solta o clique

        window.disableUniversalSnap(); // 🧲 DESLIGA O ÍMÃ UNIVERSAL

        unificacaoSource.clear();

        drawSource.clear();
        measureTooltipElement.style.display = "none";
        activeTool = "pan";
        map.getTargetElement().style.cursor = ""; // Volta para a mãozinha

        if (btnPan) {
            btnPan.classList.add(
                "bg-primary-100",
                "text-primary-600",
                "dark:bg-primary-900/30",
                "dark:text-primary-400",
            );
            btnPan.classList.remove(
                "hover:bg-gray-100",
                "text-gray-600",
                "dark:hover:bg-gray-700",
                "dark:text-gray-300",
            );
        }
        if (btnMeasureLine) {
            btnMeasureLine.classList.remove(
                "bg-primary-100",
                "text-primary-600",
                "dark:bg-primary-900/30",
                "dark:text-primary-400",
            );
            btnMeasureLine.classList.add(
                "hover:bg-gray-100",
                "text-gray-600",
                "dark:hover:bg-gray-700",
                "dark:text-gray-300",
            );
        }
        if (btnMeasureArea) {
            btnMeasureArea.classList.remove(
                "bg-primary-100",
                "text-primary-600",
                "dark:bg-primary-900/30",
                "dark:text-primary-400",
            );
            btnMeasureArea.classList.add(
                "hover:bg-gray-100",
                "text-gray-600",
                "dark:hover:bg-gray-700",
                "dark:text-gray-300",
            );
        }
    };

    if (btnPan) btnPan.addEventListener("click", window.resetToPan);

    // 🛑 FERRAMENTA DE PERFIL ALTIMÉTRICO (GOOGLE ELEVATION)
    const btnToolAltimetria = document.getElementById("btn-tool-altimetria");
    if (btnToolAltimetria) {
        btnToolAltimetria.addEventListener("click", function () {
            window.resetToPan();
            activeTool = "altimetria";

            map.getTargetElement().style.cursor = "crosshair";
            alert(
                "📈 MODO ALTIMETRIA: Desenhe o trajeto no mapa.\nClique para fazer curvas ao longo da rua e dê DOIS CLIQUES RÁPIDOS para finalizar e gerar o gráfico.",
            );

            currentDrawInteraction = new ol.interaction.Draw({
                source: drawSource,
                type: "LineString",
                style: new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "#10b981",
                        width: 4,
                        lineDash: [4, 4],
                    }), // Linha Verde Tracejada
                }),
            });

            currentDrawInteraction.on("drawend", function (e) {
                const geometry = e.feature.getGeometry();

                // Converte as coordenadas do formato do Mapa (3857) para GPS padrão do Google (4326)
                const coords4326 = geometry
                    .clone()
                    .transform("EPSG:3857", "EPSG:4326")
                    .getCoordinates();

                setTimeout(() => drawSource.clear(), 500);
                map.removeInteraction(currentDrawInteraction);
                window.resetToPan();

                // Dispara o evento pro Livewire mandando o array de coordenadas
                Livewire.dispatch("gerarPerfilAltimetrico", {
                    coords: coords4326,
                });
            });

            map.addInteraction(currentDrawInteraction);
        });
    }

    function enableMeasurement(type, buttonElement) {
        window.resetToPan();
        activeTool = type;

        if (btnPan) {
            btnPan.classList.remove(
                "bg-primary-100",
                "text-primary-600",
                "dark:bg-primary-900/30",
                "dark:text-primary-400",
            );
            btnPan.classList.add(
                "hover:bg-gray-100",
                "text-gray-600",
                "dark:hover:bg-gray-700",
                "dark:text-gray-300",
            );
        }
        buttonElement.classList.add(
            "bg-primary-100",
            "text-primary-600",
            "dark:bg-primary-900/30",
            "dark:text-primary-400",
        );
        buttonElement.classList.remove(
            "hover:bg-gray-100",
            "text-gray-600",
            "dark:hover:bg-gray-700",
            "dark:text-gray-300",
        );

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

        currentMeasureInteraction.on("drawstart", function () {
            drawSource.clear();
            measureTooltipElement.style.display = "block";
        });
        currentMeasureInteraction.on("drawend", function (e) {
            const geom = e.feature.getGeometry();
            const output =
                type === "line"
                    ? ol.sphere.getLength(geom).toFixed(2) + " m"
                    : ol.sphere.getArea(geom).toFixed(2) + " m²";
            measureTooltipElement.innerHTML = output;
            const position =
                type === "line"
                    ? geom.getLastCoordinate()
                    : geom.getInteriorPoint().getCoordinates();
            measureOverlay.setPosition(position);
            map.removeInteraction(currentMeasureInteraction);
        });

        map.addInteraction(currentMeasureInteraction);

        // 🧲 LIGA O ÍMÃ UNIVERSAL PARA A MEDIÇÃO AQUI!
        window.enableUniversalSnap();
    }

    if (btnMeasureLine)
        btnMeasureLine.addEventListener("click", function () {
            enableMeasurement("line", this);
        });
    if (btnMeasureArea)
        btnMeasureArea.addEventListener("click", function () {
            enableMeasurement("area", this);
        });

    // =========================================================================
    // CIRURGIA EM MEMÓRIA: CEMITÉRIOS
    // =========================================================================

    // Adiciona apenas o novo Cemitério ao terminar de desenhar
    window.addEventListener("adicionar-cemiterio-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        if (drawSource) drawSource.clear();
        const checkbox = document.querySelector(
            'input[data-layer="cemiterios"]',
        );

        if (checkbox && checkbox.checked && window.loadedLayers["cemiterios"]) {
            const feature = new ol.Feature({
                geometry: new ol.format.GeoJSON().readGeometry(data.geo, {
                    dataProjection: "EPSG:4326",
                    featureProjection: "EPSG:3857",
                }),
                id: data.id,
                name: data.name,
                layer: "cemiterios", // Essencial para o clique e hover funcionarem
            });
            window.loadedLayers["cemiterios"].getSource().addFeature(feature);
        }
    });

    // Atualiza só o texto do hover se alterar o nome no banco
    window.addEventListener("atualizar-label-cemiterio", (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers["cemiterios"]) {
            const feature = window.loadedLayers["cemiterios"]
                .getSource()
                .getFeatures()
                .find((f) => f.get("id") == data.id);
            if (feature) {
                feature.set("name", data.name);
                feature.changed(); // Força a re-renderização visual do polígono
            }
        }
    });

    // Arranca o polígono do mapa sem precisar baixar os outros do banco
    window.addEventListener("remover-cemiterio-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers["cemiterios"]) {
            const source = window.loadedLayers["cemiterios"].getSource();
            const feature = source
                .getFeatures()
                .find((f) => f.get("id") == data.id);
            if (feature) source.removeFeature(feature);
        }
    });

    // =========================================================================
    // CIRURGIA EM MEMÓRIA: LOGRADOUROS, POSTES E ÁRVORES ETC...
    // =========================================================================

    const entidadesSurgical = [
        { layer: "logradouros", singular: "logradouro" },
        { layer: "postes", singular: "poste" },
        { layer: "arvores", singular: "arvore" },

        { layer: "bairros", singular: "bairro" },
        { layer: "loteamentos", singular: "loteamento" },
        { layer: "quadras_cemiterio", singular: "quadra_cemiterio" },
        { layer: "logradouros_cemiterio", singular: "logradouro_cemiterio" },
        { layer: "jazigos", singular: "jazigo" },
        { layer: "setores_fiscais", singular: "setor_fiscal" },
        { layer: "rural-localidades", singular: "rural_localidade" },
        { layer: "rural-propriedades", singular: "rural_propriedade" },
        { layer: "rural-estradas", singular: "rural_estrada" },
        { layer: "rural-hidrografias", singular: "rural_hidrografia" },
        { layer: "rural-pontes", singular: "rural_ponte" },
        { layer: "rural-pontos-interesse", singular: "rural_ponto_interesse" },
        { layer: "quadras", singular: "quadra" },

        { layer: "pontos_panoramicos", singular: "ponto_panoramico" },
    ];

    entidadesSurgical.forEach((entidade) => {
        // 1. Adicionar no Mapa (Após Criar)
        window.addEventListener(`adicionar-${entidade.singular}-mapa`, (e) => {
            const data = e.detail[0] || e.detail;
            if (drawSource) drawSource.clear();
            const checkbox = document.querySelector(
                `input[data-layer="${entidade.layer}"]`,
            );

            if (
                checkbox &&
                checkbox.checked &&
                window.loadedLayers[entidade.layer]
            ) {
                const feature = new ol.Feature({
                    geometry: new ol.format.GeoJSON().readGeometry(data.geo, {
                        dataProjection: "EPSG:4326",
                        featureProjection: "EPSG:3857",
                    }),
                    id: data.id,
                    name: data.name || "",
                    layer: entidade.layer,
                });
                window.loadedLayers[entidade.layer]
                    .getSource()
                    .addFeature(feature);
            }
        });

        // 2. Atualizar Label (Após Editar Nome)
        window.addEventListener(`atualizar-label-${entidade.singular}`, (e) => {
            const data = e.detail[0] || e.detail;
            if (window.loadedLayers[entidade.layer]) {
                const feature = window.loadedLayers[entidade.layer]
                    .getSource()
                    .getFeatures()
                    .find((f) => f.get("id") == data.id);
                if (feature) {
                    feature.set("name", data.name);
                    feature.changed();
                }
            }
        });

        // 3. Remover do Mapa (Após Excluir)
        window.addEventListener(`remover-${entidade.singular}-mapa`, (e) => {
            const data = e.detail[0] || e.detail;
            if (window.loadedLayers[entidade.layer]) {
                const source = window.loadedLayers[entidade.layer].getSource();
                const feature = source
                    .getFeatures()
                    .find((f) => f.get("id") == data.id);
                if (feature) source.removeFeature(feature);
            }
        });

        // 4. Atualizar Status de Manutenção (Ficar Roxo no Mapa)
        window.addEventListener(
            `atualizar-manutencao-${entidade.singular}`,
            (e) => {
                const data = e.detail[0] || e.detail;
                if (window.loadedLayers[entidade.layer]) {
                    const feature = window.loadedLayers[entidade.layer]
                        .getSource()
                        .getFeatures()
                        .find((f) => f.get("id") == data.id);
                    if (feature) {
                        // Injeta a propriedade "tem_chamado" no cache do OpenLayers e força redesenhar
                        feature.set("tem_chamado", data.tem_chamado);
                        feature.changed();
                    }
                }
            },
        );
    });

    // =========================================================================
    // CIRURGIA EM MEMÓRIA EXCLUSIVA: ZONAS (Precisa de RGB e Sigla)
    // =========================================================================
    window.addEventListener("adicionar-zona-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        if (drawSource) drawSource.clear();

        // 1. Ativa a sigla na memória para o mapa não esconder
        if (!zonasAtivas.includes(data.sigla)) {
            zonasAtivas.push(data.sigla);
        }

        // 2. Força o Checkbox a ligar (Atraso de 150ms pro Alpine ter tempo de renderizar o HTML da nova zona)
        setTimeout(() => {
            const checkbox = document.querySelector(
                `input[data-zona-sigla="${data.sigla}"]`,
            );
            if (checkbox && !checkbox.checked) checkbox.checked = true;
        }, 150);

        // 3. Injeta no mapa com RGB e Sigla!
        if (window.loadedLayers["zonas"]) {
            const feature = new ol.Feature({
                geometry: new ol.format.GeoJSON().readGeometry(data.geo, {
                    dataProjection: "EPSG:4326",
                    featureProjection: "EPSG:3857",
                }),
                id: data.id,
                name: data.name,
                sigla: data.sigla,
                rgb: data.rgb,
                layer: "zonas",
            });
            window.loadedLayers["zonas"].getSource().addFeature(feature);
            window.loadedLayers["zonas"].changed();
            window.loadedLayers["zonas"].setVisible(true);
        } else {
            // Se a camada inteira não existe ainda, pede pra carregar
            const tempCheckbox = document.querySelector(
                `input[data-layer="zonas"]`,
            );
            if (tempCheckbox) fetchAndDrawLayer("zonas", tempCheckbox);
        }
    });

    window.addEventListener("atualizar-label-zona", (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers["zonas"]) {
            const feature = window.loadedLayers["zonas"]
                .getSource()
                .getFeatures()
                .find((f) => f.get("id") == data.id);
            if (feature) {
                feature.set("name", data.name);
                feature.set("sigla", data.sigla);
                feature.set("rgb", data.rgb);
                feature.changed(); // Força pintar com a cor nova
            }
        }
    });

    window.addEventListener("remover-zona-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        if (window.loadedLayers["zonas"]) {
            const source = window.loadedLayers["zonas"].getSource();
            const feature = source
                .getFeatures()
                .find((f) => f.get("id") == data.id);
            if (feature) source.removeFeature(feature);
        }

        // 🛑 MÁGICA: Ao invés de remover a sigla cegamente da lista visual,
        // nós lemos o DOM para ver quais siglas AINDA estão marcadas no menu lateral.
        zonasAtivas = [];
        document.querySelectorAll(".zona-toggle:checked").forEach((cb) => {
            const sigla = cb.getAttribute("data-zona-sigla");
            if (sigla && !zonasAtivas.includes(sigla)) zonasAtivas.push(sigla);
        });

        if (window.loadedLayers["zonas"])
            window.loadedLayers["zonas"].changed();
    });

    // =========================================================================
    // 17. INTERCEPTADOR DE CRIAÇÃO GEOGRÁFICA (PONTOS E DESENHOS VIA URL)
    // =========================================================================
    const urlParams = new URLSearchParams(window.location.search);
    const actionParams = urlParams.get("action");
    const layerParams = urlParams.get("layer");

    // 🛑 1. MODO DE PONTOS (Árvores, Postes e Ponto de Nascente - Redireciona com Lat/Lon)
    if (
        actionParams === "create" &&
        (layerParams === "postes" ||
            layerParams === "arvores" ||
            layerParams === "rural_hidro_ponto")
    ) {
        const entityName = layerParams === "postes" ? "Poste" : "Árvore";

        console.log(`Modo de inserção de ${entityName} ativado!`);
        alert(
            `📍 Clique no mapa exatamente onde o(a) novo(a) ${entityName} está localizado(a).`,
        );

        const drawPoint = new ol.interaction.Draw({ type: "Point" });
        map.addInteraction(drawPoint);
        map.getTargetElement().style.cursor = "crosshair";

        drawPoint.on("drawend", function (event) {
            map.removeInteraction(drawPoint);
            map.getTargetElement().style.cursor = "";

            const geometry = event.feature.getGeometry();
            const coords4326 = ol.proj.transform(
                geometry.getCoordinates(),
                "EPSG:3857",
                "EPSG:4326",
            );

            const lon = coords4326[0].toFixed(8);
            const lat = coords4326[1].toFixed(8);

            window.location.href = `/app/${config.tenantSlug}/${layerParams}/create?lat=${lat}&lon=${lon}`;
        });
    }

    // 🛑 2. MODO DE DESENHO (Polígonos e Linhas - Abre modal nativa do mapa)
    // 🛑 2. MODO DE DESENHO (Polígonos e Linhas - Abre modal nativa do mapa)
    else if (actionParams === "create") {
        let drawKey = layerParams;

        // 🛑 TRUQUE CAMALEÃO: A hidrografia pergunta qual é a forma geométrica antes!
        if (layerParams === "rural-hidrografias") {
            const opcao = prompt(
                "🌊 Múltiplas formas detectadas para Hidrografia.\nO que você deseja desenhar no mapa?\n\n1 - Rio / Córrego (Linha)\n2 - Lago / Represa (Polígono)\n3 - Nascente (Ponto)\n\nDigite o número da opção (1, 2 ou 3):",
            );

            if (opcao === "1") drawKey = "rural_hidro_linha";
            else if (opcao === "2") drawKey = "rural_hidro_poligono";
            else if (opcao === "3") drawKey = "rural_hidro_ponto";
            else return; // Se o usuário cancelar ou digitar errado, aborta a ação!
        }

        // Dicionário inteligente de entidades que desenham na tela
        const drawableEntities = {
            lotes: { label: "do novo Lote", func: "lote" },
            logradouros: {
                label: "da linha do novo Logradouro",
                func: "logradouro",
            },
            zonas: { label: "da nova Zona de Uso", func: "zona" },
            bairros: { label: "do novo Bairro", func: "bairro" },
            loteamentos: { label: "do novo Loteamento", func: "loteamento" },
            quadras: { label: "da nova Quadra", func: "quadra" },

            edificacoes: { label: "da nova Edificação", func: "edificacao" },
            cemiterios: { label: "do novo Cemitério", func: "cemiterio" },
            quadras_cemiterio: {
                label: "da nova Quadra",
                func: "quadra_cemiterio",
            },
            logradouros_cemiterio: {
                label: "da nova Rua Interna",
                func: "logradouro_cemiterio",
            },
            jazigos: { label: "do novo Jazigo", func: "jazigo" },
            setores_fiscais: {
                label: "do novo Setor Fiscal",
                func: "setor_fiscal",
            },
            "rural-localidades": {
                label: "da nova Localidade Rural",
                func: "rural_localidade",
            },
            "rural-propriedades": {
                label: "da nova Propriedade Rural",
                func: "rural_propriedade",
            },
            "rural-estradas": {
                label: "da nova Estrada Rural",
                func: "rural_estrada",
            },
            "rural-pontes": { label: "da nova Ponte", func: "rural_ponte" },
            "rural-pontos-interesse": {
                label: "do novo Ponto de Interesse",
                func: "rural_ponto_interesse",
            },

            // 👇 As 3 formas da Hidrografia mapeadas aqui para a ferramenta certa
            rural_hidro_linha: {
                label: "do novo Rio/Córrego (Linha)",
                func: "rural_hidro_linha",
            },
            rural_hidro_poligono: {
                label: "do novo Lago/Represa (Polígono)",
                func: "rural_hidro_poligono",
            },
            rural_hidro_ponto: {
                label: "da nova Nascente (Ponto)",
                func: "rural_hidro_ponto",
            },

            //'pontos_panoramicos': { label: 'da localização da Câmera 360º', func: 'ponto_panoramico', type: 'Point' },
        };

        if (drawableEntities[drawKey]) {
            let labelEnt = drawableEntities[drawKey].label;
            let funcEnt = drawableEntities[drawKey].func;

            console.log(
                `Modo de inserção de ${funcEnt} ativado via Backoffice!`,
            );

            setTimeout(() => {
                // Removemos o alert para não encher a tela de popups se já usou o Prompt
                if (layerParams !== "rural-hidrografias") {
                    alert(`🗺️ Desenhe a geometria ${labelEnt} no mapa.`);
                }

                if (layerParams !== "zonas") {
                    // 🛑 MÁGICA: Ignora as zonas porque elas ligam por SIGLA e não geral!
                    const checkbox = document.querySelector(
                        `input[data-layer="${layerParams}"]`,
                    );
                    if (checkbox && !checkbox.checked) {
                        checkbox.checked = true;
                        checkbox.dispatchEvent(new Event("change"));
                    }
                }

                // Usa o layerParams original para achar o checkbox e ligar a camada azulzinha!
                const checkbox = document.querySelector(
                    `input[data-layer="${layerParams}"]`,
                );
                if (checkbox && !checkbox.checked) {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new Event("change"));
                }

                // Dispara a ferramenta de desenho!
                if (typeof window.enableDrawing === "function") {
                    window.enableDrawing(funcEnt);
                }
            }, 800);
        }
    }

    // =========================================================================
    // 19. VOO DIRETO VIA URL (VER NO MAPA)
    // =========================================================================
    const focusLat = urlParams.get("focus_lat");
    const focusLon = urlParams.get("focus_lon");
    const targetLayer = urlParams.get("layer");

    const focusZoom = urlParams.get("zoom")
        ? parseInt(urlParams.get("zoom"))
        : 20;

    if (focusLat && focusLon) {
        // 1. Liga a camada no menu lateral automaticamente se ela existir
        if (targetLayer) {
            // 👉 INÍCIO DA SEPARAÇÃO: Zonas vs Outros 👈

            if (targetLayer === "zonas") {
                // LÓGICA EXCLUSIVA DAS ZONAS
                const targetSigla = urlParams.get("sigla");
                if (targetSigla) {
                    // Aguarda 500ms para o Alpine renderizar a sanfona
                    setTimeout(() => {
                        const checkboxes = document.querySelectorAll(
                            `input[data-zona-sigla="${targetSigla}"]`,
                        );
                        checkboxes.forEach((cb) => {
                            if (!cb.checked) {
                                cb.checked = true;
                                cb.dispatchEvent(new Event("change"));
                            }
                        });
                    }, 500);
                }
            } else {
                // LÓGICA GENÉRICA (Postes, Lotes, Bairros, etc)
                const checkbox = document.querySelector(
                    `input[data-layer="${targetLayer}"]`,
                );
                if (checkbox && !checkbox.checked) {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new Event("change")); // Força o carregamento no banco

                    // Abre a sanfona correspondente (Mantido o seu código dos postes!)
                    if (targetLayer === "postes") {
                        const infraButton = document.querySelector(
                            'button[x-on\\:click*="infra"]',
                        );
                        if (infraButton) infraButton.click();
                    }
                }
            }
            // 👉 FIM DA SEPARAÇÃO 👈
        }

        // 2. Faz o voo cinematográfico para o local exato
        setTimeout(() => {
            const coords = ol.proj.fromLonLat([
                parseFloat(focusLon),
                parseFloat(focusLat),
            ]);
            view.animate({
                center: coords,
                zoom: focusZoom, // Zoom bem fechado para ver a rua e o poste de perto
                duration: 2500, // 2.5 segundos de animação suave
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
                    fill: new ol.style.Fill({ color: "#2563eb" }), // Azul Blue-600
                    stroke: new ol.style.Stroke({ color: "#ffffff", width: 2 }),
                }),
                text: new ol.style.Text({
                    text: feature.get("numero").toString(),
                    font: "bold 12px Arial, sans-serif",
                    fill: new ol.style.Fill({ color: "#ffffff" }),
                    offsetY: 0,
                }),
            });
        },
        zIndex: 10000, // Fica acima de TUDO no mapa
    });
    map.addLayer(previewNumLayer);

    window.addEventListener("mostrar-preview-numeracao", (e) => {
        const lotes = e.detail[0] || e.detail.dados || e.detail;
        previewNumSource.clear();

        if (lotes && Array.isArray(lotes)) {
            const features = [];
            lotes.forEach((lote) => {
                if (lote.geo) {
                    try {
                        const feature = new ol.Feature({
                            geometry: new ol.format.GeoJSON().readGeometry(
                                lote.geo,
                                {
                                    dataProjection: "EPSG:4326",
                                    featureProjection: "EPSG:3857",
                                },
                            ),
                            numero: lote.novo_numero,
                        });
                        features.push(feature);
                    } catch (err) {
                        console.error("Erro no JSON da numeração", err);
                    }
                }
            });
            previewNumSource.addFeatures(features);
        }
    });

    window.addEventListener("limpar-preview-numeracao", () => {
        previewNumSource.clear();
    });

    // =========================================================================
    // 🛠️ MESA DE DESENHO CAD (RASCUNHOS AVANÇADOS)
    // =========================================================================
    const cadSource = new ol.source.Vector();
    const cadLayer = new ol.layer.Vector({
        source: cadSource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({
                color: "#4f46e5",
                width: 3,
                lineDash: [8, 4],
            }), // Indigo-600 tracejado
            fill: new ol.style.Fill({ color: "rgba(79, 70, 229, 0.3)" }), // Fundo translúcido
        }),
        zIndex: 10005, // Acima de quase tudo no mapa
    });
    map.addLayer(cadLayer);

    let featureCloneOriginalLayer = null;

    window.setFerramentaCAD = function (ferramenta) {
        window.resetToPan(); // Limpa as ferramentas antigas

        if (!ferramenta) {
            activeTool = "pan";
            cadSource.clear();
            return;
        }

        activeTool = "cad_" + ferramenta; // Ex: 'cad_clonar'

        if (ferramenta === "clonar") {
            alert(
                "📝 MODO CLONE ATIVADO\n\nClique no artefato (lote, quadra, poste, etc) que deseja copiar.",
            );
            map.getTargetElement().style.cursor = "copy";
        } else if (ferramenta === "buffer") {
            alert(
                "📏 MODO BUFFER ATIVADO\n\nDefina os metros na caixinha inferior e clique no artefato para inflá-lo.",
            );
            map.getTargetElement().style.cursor = "crosshair";

            // 👇 INICIA O BLOCO DO UNIR GENÉRICO AQUI 👇
        } else if (ferramenta === "unir") {
            activeTool = "cad_unir_step1";
            window.cadFeatureToUnite = null; // Variável para guardar o primeiro polígono
            alert(
                "🔗 MODO UNIR (SOMA BOOLEANA)\n\nPASSO 1: Clique no PRIMEIRO polígono que deseja fundir.",
            );
            map.getTargetElement().style.cursor = "crosshair";

            // 👇 INICIA O BLOCO DA TESOURA AQUI 👇
        } else if (ferramenta === "desmembrar") {
            activeTool = "cad_cortar_step1";
            window.cadFeatureToCut = null;
            alert(
                "✂️ MODO CORTAR (TESOURA)\n\nPASSO 1: Clique no polígono que deseja fatiar.",
            );
            map.getTargetElement().style.cursor = "crosshair";

            // 👇 INICIA O BLOCO COTAR AQUI 👇
        } else if (ferramenta === "cotar") {
            activeTool = "cad_cotar";
            alert(
                "📏 MODO COTAR ATIVADO\n\nClique em um Lote, Bairro ou Rua para extrair a área e a medida de todos os lados instantaneamente.",
            );
            map.getTargetElement().style.cursor = "help"; // Cursor de dúvida/informação
        }
    };

    // =========================================================================
    // FERRAMENTA DE DESMEMBRAMENTO DE LOTES (A TESOURA)
    // =========================================================================
    let loteParaDesmembrarId = null;

    window.ativarFerramentaCorteLote = function (loteId) {
        window.resetToPan(); // Limpa qualquer outra ferramenta ativa
        activeTool = "cortar_lote";
        loteParaDesmembrarId = loteId;

        // Fecha a ficha lateral suavemente para dar espaço na tela
        const livewireComponent = Livewire.find(
            document.querySelector("[wire\\:id]").getAttribute("wire:id"),
        );
        if (livewireComponent) livewireComponent.fecharFicha();

        alert(
            "✂️ MODO DESMEMBRAMENTO ATIVADO\n\n1. Clique fora do lote para iniciar a linha de corte.\n2. Atravesse o lote.\n3. Dê dois cliques fora do lote do outro lado para finalizar o corte.",
        );

        map.getTargetElement().style.cursor = "crosshair";

        // Cria a interação de desenhar Linha (LineString) cor Laranja
        currentDrawInteraction = new ol.interaction.Draw({
            source: drawSource,
            type: "LineString",
            style: new ol.style.Style({
                stroke: new ol.style.Stroke({
                    color: "#ea580c",
                    width: 4,
                    lineDash: [5, 5],
                }), // Laranja tracejado
            }),
        });

        currentDrawInteraction.on("drawend", function (e) {
            window.ortogonalLastFix = null; // 🧹 SOLTA A LINHA GUIA

            const linhaDeCorteGeoJson = formatGeoJSON.writeGeometryObject(
                e.feature.getGeometry(),
            );

            // Limpa o mapa e devolve a mãozinha
            setTimeout(() => drawSource.clear(), 500);
            map.removeInteraction(currentDrawInteraction);
            window.resetToPan();

            // Manda a linha desenhada para a "Mágica do PostGIS" no Livewire
            Livewire.dispatch("processarDesmembramentoLote", {
                loteId: loteParaDesmembrarId,
                linhaCorte: linhaDeCorteGeoJson,
            });
        });

        map.addInteraction(currentDrawInteraction);
    };

    // =========================================================================
    // LISTENERS DO MAPA DE CALOR SOCIAL + HEATMAP
    // =========================================================================

    // Camadas de Heatmap — uma por tipo social
    const heatmapRiscoSource = new ol.source.Vector();
    const heatmapBeneficioSource = new ol.source.Vector();
    const heatmapPcdSource = new ol.source.Vector();

    const heatmapRiscoLayer = new ol.layer.Heatmap({
        source: heatmapRiscoSource,
        blur: 30,
        radius: 20,
        gradient: ["#fef9c3", "#fca5a5", "#ef4444", "#991b1b"],
        zIndex: 9000,
        visible: false,
    });

    const heatmapBeneficioLayer = new ol.layer.Heatmap({
        source: heatmapBeneficioSource,
        blur: 28,
        radius: 18,
        gradient: ["#fef9c3", "#fde68a", "#f59e0b", "#92400e"],
        zIndex: 9001,
        visible: false,
    });

    const heatmapPcdLayer = new ol.layer.Heatmap({
        source: heatmapPcdSource,
        blur: 28,
        radius: 18,
        gradient: ["#f3e8ff", "#d8b4fe", "#9333ea", "#581c87"],
        zIndex: 9002,
        visible: false,
    });

    map.addLayer(heatmapRiscoLayer);
    map.addLayer(heatmapBeneficioLayer);
    map.addLayer(heatmapPcdLayer);

    let modoHeatmapAtivo = false;

    // Popula as fontes de heatmap com os centroides dos lotes filtrados
    function popularHeatmaps() {
        heatmapRiscoSource.clear();
        heatmapBeneficioSource.clear();
        heatmapPcdSource.clear();

        if (!window.loadedLayers["lotes"]) return;

        const features = window.loadedLayers["lotes"].getSource().getFeatures();
        features.forEach((f) => {
            const geom = f.getGeometry();
            if (!geom) return;

            // Pega o centroide do polígono do lote
            let point;
            if (
                geom.getType() === "Polygon" ||
                geom.getType() === "MultiPolygon"
            ) {
                const extent = geom.getExtent();
                point = new ol.geom.Point(ol.extent.getCenter(extent));
            } else {
                point = new ol.geom.Point(geom.getFirstCoordinate());
            }

            if (f.get("social_risco"))
                heatmapRiscoSource.addFeature(new ol.Feature(point.clone()));
            if (f.get("social_beneficio"))
                heatmapBeneficioSource.addFeature(
                    new ol.Feature(point.clone()),
                );
            if (f.get("social_pcd"))
                heatmapPcdSource.addFeature(new ol.Feature(point.clone()));
        });
    }

    // Atualiza visibilidade das camadas heatmap conforme filtros e modo ativo
    function sincronizarHeatmaps() {
        heatmapRiscoLayer.setVisible(modoHeatmapAtivo && filtroRiscoAtivo);
        heatmapBeneficioLayer.setVisible(
            modoHeatmapAtivo && filtroBeneficioAtivo,
        );
        heatmapPcdLayer.setVisible(modoHeatmapAtivo && filtroPcdAtivo);
    }

    // Função utilitária para re-aplicar cores nos lotes (modo normal)
    function atualizarCoresDosLotes() {
        if (window.loadedLayers["lotes"]) {
            window.loadedLayers["lotes"].changed();
        } else {
            const checkboxLotes = document.querySelector(
                'input[data-layer="lotes"]',
            );
            if (checkboxLotes && !checkboxLotes.checked) {
                checkboxLotes.checked = true;
                checkboxLotes.dispatchEvent(new Event("change"));
            }
        }
        if (modoHeatmapAtivo) popularHeatmaps();
        sincronizarHeatmaps();
    }

    // Toggle Heatmap
    const checkHeatmap = document.getElementById("toggle-modo-heatmap");
    if (checkHeatmap) {
        checkHeatmap.addEventListener("change", function () {
            modoHeatmapAtivo = this.checked;
            if (modoHeatmapAtivo) {
                // Garante que os lotes estão carregados para popular o heatmap
                const checkboxLotes = document.querySelector(
                    'input[data-layer="lotes"]',
                );
                if (checkboxLotes && !checkboxLotes.checked) {
                    checkboxLotes.checked = true;
                    checkboxLotes.dispatchEvent(new Event("change"));
                    // Aguarda o fetch terminar antes de popular
                    setTimeout(() => {
                        popularHeatmaps();
                        sincronizarHeatmaps();
                    }, 1500);
                } else {
                    popularHeatmaps();
                    sincronizarHeatmaps();
                }
            } else {
                sincronizarHeatmaps(); // Esconde todos os heatmaps
            }
            atualizarCoresDosLotes();
        });
    }

    const checkRisco = document.getElementById("filtro-social-risco");
    if (checkRisco) {
        checkRisco.addEventListener("change", function () {
            filtroRiscoAtivo = this.checked;
            atualizarCoresDosLotes();
        });
    }

    const checkBeneficio = document.getElementById("filtro-social-beneficio");
    if (checkBeneficio) {
        checkBeneficio.addEventListener("change", function () {
            filtroBeneficioAtivo = this.checked;
            atualizarCoresDosLotes();
        });
    }

    const checkPcd = document.getElementById("filtro-social-pcd");
    if (checkPcd) {
        checkPcd.addEventListener("change", function () {
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
                    text: feature.get("valor_formatado"),
                    font: "bold 13px Arial, sans-serif",
                    fill: new ol.style.Fill({ color: "#ffffff" }),
                    backgroundFill: new ol.style.Fill({ color: "#10b981" }), // Emerald/Verde Dinheiro
                    backgroundStroke: new ol.style.Stroke({
                        color: "#047857",
                        width: 2,
                    }),
                    padding: [4, 6, 4, 6],
                    offsetY: -15,
                }),
            });
        },
        zIndex: 10002, // Acima da numeração predial
    });
    map.addLayer(previewPgvLayer);

    window.addEventListener("mostrar-preview-pgv", (e) => {
        const lotes = e.detail[0] || e.detail.dados || e.detail;
        previewPgvSource.clear();

        if (lotes && Array.isArray(lotes)) {
            const features = [];
            lotes.forEach((lote) => {
                if (lote.geo) {
                    try {
                        const feature = new ol.Feature({
                            geometry: new ol.format.GeoJSON().readGeometry(
                                lote.geo,
                                {
                                    dataProjection: "EPSG:4326",
                                    featureProjection: "EPSG:3857",
                                },
                            ),
                            valor_formatado: lote.valor_formatado,
                        });
                        features.push(feature);
                    } catch (err) {
                        console.error("Erro no JSON da PGV", err);
                    }
                }
            });
            previewPgvSource.addFeatures(features);
        }
    });

    window.addEventListener("limpar-preview-pgv", () =>
        previewPgvSource.clear(),
    );

    // ── CAMADA DO MARCADOR DE BUSCA (PIN LARANJA) ──────────────────
    const searchPinSource = new ol.source.Vector();
    let searchPinAnimFrame = null;
    const searchPinLayer = new ol.layer.Vector({
        source: searchPinSource,
        style: function (feature, resolution) {
            const now = Date.now();
            const pulse = 0.5 + 0.5 * Math.sin(now / 200); // oscila entre 0.5 e 1.0
            const radius = 10 + 6 * pulse;
            const opacity = 0.5 + 0.5 * pulse;
            return [
                // Anel pulsante externo
                new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: radius,
                        fill: new ol.style.Fill({
                            color: `rgba(249, 115, 22, ${opacity * 0.4})`,
                        }),
                        stroke: new ol.style.Stroke({
                            color: `rgba(249, 115, 22, ${opacity})`,
                            width: 2,
                        }),
                    }),
                }),
                // Ponto central sólido
                new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 7,
                        fill: new ol.style.Fill({ color: "#f97316" }),
                        stroke: new ol.style.Stroke({
                            color: "#ffffff",
                            width: 2.5,
                        }),
                    }),
                }),
            ];
        },
        zIndex: 99999,
    });
    map.addLayer(searchPinLayer);

    // Força re-render do pin a cada frame para animar o pulso
    const animateSearchPin = () => {
        if (searchPinSource.getFeatures().length > 0) {
            searchPinSource.changed();
        }
        searchPinAnimFrame = requestAnimationFrame(animateSearchPin);
    };
    animateSearchPin();
    // ───────────────────────────────────────────────────────────────

    // --- CAMADA DE RESULTADOS DO FILTRO AVANÇADO ---
    const querySource = new ol.source.Vector();
    const queryLayer = new ol.layer.Vector({
        source: querySource,
        style: new ol.style.Style({
            stroke: new ol.style.Stroke({ color: "#f59e0b", width: 4 }), // Âmbar forte
            fill: new ol.style.Fill({ color: "rgba(245, 158, 11, 0.4)" }),
            image: new ol.style.Circle({
                radius: 8,
                fill: new ol.style.Fill({ color: "#f59e0b" }),
                stroke: new ol.style.Stroke({ color: "#ffffff", width: 2 }),
            }),
        }),
        zIndex: 9999, // Fica por cima de tudo
    });
    map.addLayer(queryLayer);

    // --- ESCUTADOR DO FILTRO ---
    window.addEventListener("executar-filtro-avancado", (e) => {
        const dados = e.detail[0] || e.detail.dados || e.detail;
        console.log("🎯 Filtro Avançado Disparado! Dados recebidos:", dados);

        // 1. Construtor Dinâmico de Parâmetros
        let queryParams = new URLSearchParams({
            tenant_id: config.tenantId,
            tipo_filtro: dados.tipo_filtro || "atributo",
        });

        // 2. Preenche os parâmetros baseado no que o usuário escolheu
        if (dados.tipo_filtro === "espacial") {
            queryParams.append(
                "spatial_target_layer",
                dados.spatial_target_layer,
            );
            queryParams.append("spatial_operator", dados.spatial_operator);
            queryParams.append(
                "spatial_reference_layer",
                dados.spatial_reference_layer,
            );

            // 👈 Ajuste para tratar se o Select enviar um array (multiple)
            if (Array.isArray(dados.spatial_reference_id)) {
                dados.spatial_reference_id.forEach((id) =>
                    queryParams.append("spatial_reference_ids[]", id),
                );
            } else {
                queryParams.append(
                    "spatial_reference_id",
                    dados.spatial_reference_id,
                );
            }
        } else {
            queryParams.append("layer", dados.layer);
            queryParams.append("field", dados.field);
            queryParams.append("operator", dados.operator);
            queryParams.append("value", dados.value);
        }

        // 3. Monta a URL
        // ⚠️ ATENÇÃO: Usei a rota correspondente ao nome do seu método no Controller.
        // Se no seu arquivo routes/api.php a rota estiver apenas como "advanced-query",
        // basta apagar o "-spatial" da string abaixo.
        const url = `/api/mapa/advanced-query?${queryParams.toString()}`;
        console.log("🌐 Buscando na URL:", url);

        fetch(url)
            .then(async (response) => {
                const contentType = response.headers.get("content-type");

                // Se o servidor retornar erro 500 ou 404
                if (!response.ok) {
                    const text = await response.text();
                    console.error(
                        "❌ Erro HTTP do Servidor:",
                        response.status,
                        text,
                    );
                    throw new Error(
                        `Servidor retornou erro ${response.status}`,
                    );
                }

                // Verifica se realmente é um JSON
                if (
                    contentType &&
                    contentType.indexOf("application/json") !== -1
                ) {
                    return response.json();
                } else {
                    const text = await response.text();
                    console.error(
                        "❌ A resposta não é um JSON. Retorno do servidor:",
                        text,
                    );
                    throw new Error(
                        "A API não retornou um JSON válido. A rota pode estar errada (404).",
                    );
                }
            })
            .then((data) => {
                console.log("✅ Retorno da API:", data);

                // Se o Controller do Laravel cuspir um erro tratado
                if (data.error) {
                    alert("Erro do Banco de Dados: " + data.error);
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

                    // 🪄 LÊ AS OPÇÕES DE ESTILO (com fallback para os defaults antigos)
                    const corFundo   = dados.cor_tematizacao || "#f59e0b";
                    const corBorda   = dados.cor_borda || corFundo;
                    const opacidade  = dados.transparencia_fundo !== undefined && dados.transparencia_fundo !== null && dados.transparencia_fundo !== ''
                        ? Math.max(0, Math.min(100, parseFloat(dados.transparencia_fundo))) / 100
                        : 0.4;
                    const espessura  = dados.espessura_borda !== undefined && dados.espessura_borda !== null && dados.espessura_borda !== ''
                        ? Math.max(1, Math.min(20, parseInt(dados.espessura_borda)))
                        : 4;

                    const estiloCustomizado = new ol.style.Style({
                        fill: new ol.style.Fill({
                            color: hexToRgba(corFundo, opacidade),
                        }),
                        stroke: new ol.style.Stroke({
                            color: corBorda,
                            width: espessura,
                        }),
                        image: new ol.style.Circle({
                            radius: 8,
                            fill: new ol.style.Fill({ color: corFundo }),
                            stroke: new ol.style.Stroke({
                                color: corBorda,
                                width: Math.max(2, Math.min(espessura, 4)),
                            }),
                        }),
                    });

                    // Gera um ID único para este filtro e marca cada feature
                    const filtroId = "filtro_" + Date.now();
                    features.forEach((f) => {
                        f.setStyle(estiloCustomizado);
                        f.set("estilo_customizado", estiloCustomizado);
                        f.set("filtro_id", filtroId); // 👈 Marca a qual filtro esta feature pertence
                    });
                    querySource.addFeatures(features);

                    // Monta descrição legível do filtro
                    let descricao = "";
                    if (dados.tipo_filtro === "atributo") {
                        descricao = `${dados.layer}: ${dados.field} ${dados.operator} "${dados.value}"`;
                    } else if (dados.tipo_filtro === "espacial") {
                        descricao = `${dados.spatial_target_layer} dentro de ${dados.spatial_reference_layer}`;
                    } else if (dados.tipo_filtro === "desenho") {
                        descricao = `${dados.draw_target_layer} por área desenhada`;
                    }

                    // Registra o filtro no painel
                    window.filtrosAtivos = window.filtrosAtivos || [];
                    window.filtrosAtivos.push({
                        id: filtroId,
                        descricao,
                        cor: corFundo,
                        total: features.length,
                    });
                    window.atualizarPainelFiltros();

                    // Zoom automático
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
                console.error("❌ Erro fatal na requisição:", err);
                alert(
                    "Falha ao executar o filtro. Aperte F12 e olhe a aba Console para ver o erro exato.",
                );
            });
    });

    // =========================================================================
    // NOVO: TEMATIZAÇÃO POR INTERVALO DE CLASSES (CHOROPLETH)
    // =========================================================================
    window.addEventListener("executar-tematizacao-intervalo", async (e) => {
        const dados = e.detail.dados || e.detail;
        const layer = dados.interval_layer;
        const attr = dados.interval_attribute;
        const nClasses = parseInt(dados.num_classes);

        // 🛑 AQUI ESTÁ A CORREÇÃO: Usando a rota oficial /api/gis-data
        const response = await fetch(
            `/api/mapa/advanced-query?tenant_id=${config.tenantId}&tipo_filtro=intervalo&layer=${layer}&interval_attribute=${attr}`,
        );
        const data = await response.json();

        if (!data.features || data.features.length === 0) {
            alert("Não há dados suficientes para criar o mapa temático.");
            return;
        }

        // 2. Cálculo dos Intervalos (Acha o Min e Max da Área/Testada)
        const valores = data.features
            .map((f) => {
                const v = parseFloat(f.properties[attr]);
                if (v && v > 0) return v;
                // Fallback: calcula área aproximada da geometria em m² (projeção plana simples)
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
                    return Math.abs(area / 2) * 1e10; // graus → valor relativo comparável
                }
                return 0;
            })
            .filter((v) => v > 0);

        if (valores.length === 0) {
            alert(
                `Nenhum valor numérico encontrado no atributo "${attr}" para calcular as cores.`,
            );
            return;
        }

        const min = Math.min(...valores);
        const max = Math.max(...valores);
        const range = (max - min) / nClasses;

        // 3. Paleta de Cores — degradê personalizado entre cor_inicio e cor_fim
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

        // 4. Pintura por "Degrau"
        const filtroIdIntervalo = "filtro_" + Date.now();
        features.forEach((f) => {
            let val = parseFloat(f.get(attr)) || 0;
            if (val === 0 && attr === "area_geo") {
                const geom = f.getGeometry();
                if (geom) val = ol.sphere.getArea(geom); // área real em m² usando OL
            }

            let classIdx = Math.floor((val - min) / range);
            if (classIdx >= nClasses) classIdx = nClasses - 1;
            if (classIdx < 0) classIdx = 0;

            const cor = colors[classIdx];
            const estilo = new ol.style.Style({
                fill: new ol.style.Fill({ color: hexToRgba(cor, 0.7) }),
                stroke: new ol.style.Stroke({ color: "#ffffff", width: 1 }),
            });

            f.setStyle(estilo);
            f.set("estilo_customizado", estilo);
            f.set("filtro_id", filtroIdIntervalo);
        });

        querySource.addFeatures(features);
        map.getView().fit(querySource.getExtent(), {
            padding: [50, 50, 50, 50],
            duration: 1000,
        });

        // Registra no painel
        window.filtrosAtivos = window.filtrosAtivos || [];
        window.filtrosAtivos.push({
            id: filtroIdIntervalo,
            descricao: `Intervalo: ${layer} por ${attr} (${nClasses} faixas)`,
            cor: corInicio + "→" + corFim,
            total: features.length,
            gradiente: true,
            cores: colors,
        });
        window.atualizarPainelFiltros();
    });

    // =========================================================================
    // GERENCIADOR DE FILTROS ATIVOS — deve ficar ANTES de todos os listeners
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

    // --- ESCUTADOR PARA LIMPAR O FILTRO ---
    window.addEventListener("limpar-filtro-avancado", () => {
        if (typeof querySource !== "undefined") {
            querySource.clear();
            window.filtrosAtivos = [];
            window.atualizarPainelFiltros();
            console.log("🧹 Todos os filtros removidos.");
        }
    });

    // ========================================================================
    // FERRAMENTA DE DESENHO PARA FILTRO ESPACIAL (EXIGÊNCIA DO EDITAL)
    // ========================================================================

    // 1. Cria uma camada invisível apenas para segurar a "caneta" enquanto o usuário desenha
    const drawFiltroSource = new ol.source.Vector();
    const drawFiltroLayer = new ol.layer.Vector({
        source: drawFiltroSource,
        style: new ol.style.Style({
            fill: new ol.style.Fill({ color: "rgba(255, 255, 255, 0.2)" }),
            stroke: new ol.style.Stroke({ color: "#ffcc33", width: 2 }),
            image: new ol.style.Circle({
                radius: 7,
                fill: new ol.style.Fill({ color: "#ffcc33" }),
            }),
        }),
        zIndex: 9999, // Fica por cima de tudo
    });
    map.addLayer(drawFiltroLayer);

    let drawFiltroInteraction; // Variável global para ligar/desligar a caneta

    window.addEventListener("iniciar-desenho-filtro", (e) => {
        const dados = e.detail[0] || e.detail.dados || e.detail;
        console.log("✏️ Modo de Desenho Ativo! Alvo:", dados.draw_target_layer);

        // Limpa desenhos e consultas anteriores
        drawFiltroSource.clear();

        // Se já tinha uma caneta ligada, desliga para não bugar
        if (drawFiltroInteraction) {
            map.removeInteraction(drawFiltroInteraction);
        }

        // 2. Configura o formato da caneta (Polígono Livre ou Caixa)
        let drawType = "Polygon";
        let geometryFunction = undefined;

        if (dados.draw_shape === "Box") {
            drawType = "Circle"; // No OpenLayers, um retângulo é desenhado como um círculo com função de caixa
            geometryFunction = ol.interaction.Draw.createBox();
        }

        drawFiltroInteraction = new ol.interaction.Draw({
            source: drawFiltroSource,
            type: drawType,
            geometryFunction: geometryFunction,
        });

        map.addInteraction(drawFiltroInteraction);

        // 3. O que acontece quando o usuário termina de desenhar (Solta o clique ou dá dois cliques)
        drawFiltroInteraction.on("drawend", function (evt) {
            // Tira a caneta da mão do usuário imediatamente
            map.removeInteraction(drawFiltroInteraction);
            console.log("✅ Desenho concluído, processando área...");

            // Extrai a geometria desenhada e converte para GeoJSON (EPSG:4326 para o PostGIS entender)
            const formatoGeoJSON = new ol.format.GeoJSON();
            const drawnGeometry = formatoGeoJSON.writeGeometry(
                evt.feature.getGeometry(),
                {
                    dataProjection: "EPSG:4326",
                    featureProjection: "EPSG:3857",
                },
            );

            // 4. Monta a URL e dispara o Fetch para a nossa nova Rota 3 na API
            let queryParams = new URLSearchParams({
                tenant_id: config.tenantId,
                tipo_filtro: "desenho",
                draw_target_layer: dados.draw_target_layer,
                drawn_geometry: drawnGeometry, // A String do GeoJSON
                draw_spatial_operator: dados.draw_within
                    ? "ST_Within"
                    : "ST_Intersects",
            });

            const url = `/api/mapa/advanced-query?${queryParams.toString()}`;
            console.log("🌐 Buscando cruzamento do desenho na URL:", url);

            // Fetch idêntico ao que estabilizamos no passo anterior
            fetch(url)
                .then(async (response) => {
                    const contentType = response.headers.get("content-type");
                    if (!response.ok) {
                        const text = await response.text();
                        console.error(
                            "❌ Erro HTTP do Servidor:",
                            response.status,
                            text,
                        );
                        throw new Error(
                            `Servidor retornou erro ${response.status}`,
                        );
                    }
                    if (
                        contentType &&
                        contentType.indexOf("application/json") !== -1
                    ) {
                        return response.json();
                    } else {
                        throw new Error("A API não retornou um JSON válido.");
                    }
                })
                .then((data) => {
                    if (data.error) {
                        alert("Erro do Banco de Dados: " + data.error);
                        return;
                    }

                    // Limpa a linha amarela que o usuário usou para desenhar (já cumpriu o papel dela)
                    drawFiltroSource.clear();

                    if (data.features && data.features.length > 0) {
                        const features = new ol.format.GeoJSON().readFeatures(
                            data,
                            {
                                dataProjection: "EPSG:4326",
                                featureProjection: "EPSG:3857",
                            },
                        );

                        // Pinta os Lotes/Postes encontrados de Laranja!
                        // 🪄 LÊ A COR DO DESENHO E APLICA
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

                        const filtroIdDesenho = "filtro_" + Date.now();
                        features.forEach((f) => {
                            f.setStyle(estiloCustomizado);
                            f.set("estilo_customizado", estiloCustomizado);
                            f.set("filtro_id", filtroIdDesenho);
                        });
                        querySource.addFeatures(features);

                        window.filtrosAtivos = window.filtrosAtivos || [];
                        window.filtrosAtivos.push({
                            id: filtroIdDesenho,
                            descricao: `${dados.draw_target_layer} por área desenhada`,
                            cor: corHex,
                            total: features.length,
                        });
                        window.atualizarPainelFiltros();

                        // Dá o Zoom elegante nos resultados
                        map.getView().fit(querySource.getExtent(), {
                            padding: [50, 50, 50, 50],
                            duration: 1000,
                            maxZoom: 19,
                        });
                    } else {
                        alert(
                            "Nenhum artefato foi encontrado dentro da área que você desenhou.",
                        );
                    }
                })
                .catch((err) => {
                    console.error(
                        "❌ Erro fatal na requisição do desenho:",
                        err,
                    );
                    alert(
                        "Falha ao analisar a área desenhada. Veja o console.",
                    );
                });
        });
    });

    /* =========================================================
        O MODO "LASER" DE SELEÇÃO DE UNIFICAÇÃO NO MAPA
    ========================================================= */
    window.addEventListener("iniciar-selecao-unificacao", (e) => {
        const data = e.detail[0] || e.detail;
        window.modoUnificacao = true;
        window.lotesParaUnificar = [data.lote_id];
        window.featuresUnificacao = [];

        // Destaque inicial do lote âncora
        if (window.loadedLayers && window.loadedLayers["lotes"]) {
            const source = window.loadedLayers["lotes"].getSource();
            const feat = source
                .getFeatures()
                .find((f) => f.get("id") == data.lote_id);
            if (feat) {
                feat.setStyle(
                    new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: "#10b981",
                            width: 4,
                        }),
                        fill: new ol.style.Fill({
                            color: "rgba(16, 185, 129, 0.4)",
                        }),
                    }),
                );
                window.featuresUnificacao.push(feat);
            }
        }

        // Cria a barra (se não existir)
        if (!document.getElementById("painel-unificacao")) {
            const painel = document.createElement("div");
            painel.id = "painel-unificacao";
            painel.style =
                "position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: white; padding: 12px 25px; border-radius: 50px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 9999; display: flex; align-items: center; gap: 20px; border: 2px solid #3b82f6;";
            painel.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-weight: bold; color: #1f2937;">🔗 Unificação</span>
                <span id="cont-unif" style="background: #eff6ff; color: #1d4ed8; padding: 2px 10px; border-radius: 10px; font-size: 12px;">1 Lote</span>
            </div>
            <button onclick="window.cancelarUnificacao()" style="background: #f3f4f6; border: none; padding: 5px 12px; border-radius: 15px; cursor: pointer;">Sair</button>
            <button onclick="window.concluirUnificacao()" style="background: #3b82f6; color: white; border: none; padding: 6px 15px; border-radius: 15px; cursor: pointer; font-weight: bold;">Gerar PDF</button>
        `;
            document.body.appendChild(painel);
        }
    });

    // Listener de clique específico para unificação
    map.on("click", function (evt) {
        if (!window.modoUnificacao) return;

        map.forEachFeatureAtPixel(evt.pixel, function (feature, layer) {
            if (layer === window.loadedLayers["lotes"]) {
                const id = feature.get("id");
                if (id && !window.lotesParaUnificar.includes(id)) {
                    window.lotesParaUnificar.push(id);
                    feature.setStyle(
                        new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: "#3b82f6",
                                width: 3,
                            }),
                            fill: new ol.style.Fill({
                                color: "rgba(59, 130, 246, 0.4)",
                            }),
                        }),
                    );
                    window.featuresUnificacao.push(feature);
                    document.getElementById("cont-unif").innerText =
                        window.lotesParaUnificar.length + " Lotes";
                }
            }
        });
    });

    window.cancelarUnificacao = function () {
        window.modoUnificacao = false;
        document.getElementById("painel-unificacao")?.remove();
        window.featuresUnificacao.forEach((f) =>
            f.setStyle(f.get("estilo_customizado") || undefined),
        );
    };

    window.concluirUnificacao = function () {
        const btn = document.querySelector(
            "#painel-unificacao button:last-child",
        );
        btn.innerText = "📸 Processando...";
        btn.disabled = true;

        setTimeout(() => {
            // Captura do Canvas (Sua lógica padrão)
            const mapCanvas = document.createElement("canvas");
            const mapaElement = document.getElementById("sigweb-map");
            mapCanvas.width = mapaElement.clientWidth;
            mapCanvas.height = mapaElement.clientHeight;
            const mapContext = mapCanvas.getContext("2d");
            mapContext.fillStyle = "#ffffff";
            mapContext.fillRect(0, 0, mapCanvas.width, mapCanvas.height);

            document.querySelectorAll(".ol-layer canvas").forEach((canvas) => {
                if (canvas.width > 0) {
                    const opacity = canvas.parentNode.style.opacity || 1;
                    mapContext.globalAlpha = Number(opacity);
                    const matrix = new DOMMatrix(canvas.style.transform);
                    mapContext.setTransform(
                        matrix.a,
                        matrix.b,
                        matrix.c,
                        matrix.d,
                        matrix.e,
                        matrix.f,
                    );
                    mapContext.drawImage(canvas, 0, 0);
                }
            });

            const dataURL = mapCanvas.toDataURL("image/jpeg", 0.8);

            Livewire.find(
                document.querySelector("[wire\\:id]").getAttribute("wire:id"),
            ).imprimirUnificacao(dataURL, window.lotesParaUnificar);

            window.cancelarUnificacao();
        }, 1500);
    };

    // =========================================================================
    // 20. INTEROPERABILIDADE OGC: CONSUMO DE WMS EXTERNO
    // =========================================================================
    const btnAddWms = document.getElementById("btn-add-wms");
    const wmsUrlInput = document.getElementById("wms-url");
    const wmsLayerInput = document.getElementById("wms-layer");
    const wmsLayersList = document.getElementById("wms-layers-list");
    let externalWmsCount = 0;

    if (btnAddWms) {
        btnAddWms.addEventListener("click", () => {
            const url = wmsUrlInput.value.trim();
            const layerName = wmsLayerInput.value.trim();

            if (!url || !layerName) {
                alert(
                    "⚠️ Por favor, preencha a URL do serviço e o Nome da Camada (Layer) para estabelecer a conexão.",
                );
                return;
            }

            // Altera botão para estado de carregamento
            const originalText = btnAddWms.innerHTML;
            btnAddWms.innerHTML = "Conectando...";
            btnAddWms.disabled = true;

            externalWmsCount++;
            const wmsId = `wms_ext_${externalWmsCount}`;

            try {
                // Cria a nova camada Raster usando o padrão OGC WMS do OpenLayers
                const newWmsLayer = new ol.layer.Tile({
                    source: new ol.source.TileWMS({
                        url: url,
                        params: {
                            LAYERS: layerName,
                            TILED: true,
                            FORMAT: "image/png",
                            TRANSPARENT: true,
                            VERSION: "1.1.1", // 🛑 A MÁGICA: Força a versão 1.1.1 para evitar erros de inversão de eixo em servidores do Governo BR
                        },
                        serverType: "geoserver",
                        crossOrigin: "anonymous",
                    }),
                    zIndex: 21,
                    visible: true,
                });

                // Carimba a camada para gestão
                newWmsLayer.set("id", wmsId);
                newWmsLayer.set("is_external_wms", true);

                // Adiciona ao mapa
                map.addLayer(newWmsLayer);

                // Constrói o item na interface para o usuário controlar a camada
                const uiItem = document.createElement("div");
                uiItem.className =
                    "flex items-center justify-between bg-white dark:bg-gray-800 p-2 rounded shadow-sm border border-gray-200 dark:border-gray-700";
                uiItem.innerHTML = `
                    <label class="flex items-center space-x-2 cursor-pointer w-full overflow-hidden">
                        <input type="checkbox" checked class="wms-toggle rounded border-gray-400 text-emerald-600 focus:ring-emerald-500 w-3.5 h-3.5 flex-shrink-0" data-wms-id="${wmsId}">
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300 truncate" style="margin-left:10px;" title="${layerName}">${layerName}</span>
                    </label>
                    <button class="btn-remove-wms text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30 p-1 rounded transition-colors" data-wms-id="${wmsId}" title="Desconectar WMS">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                `;

                wmsLayersList.appendChild(uiItem);

                // Lógica do Checkbox (Ligar/Desligar Visibilidade)
                const toggleBtn = uiItem.querySelector(".wms-toggle");
                toggleBtn.addEventListener("change", function () {
                    newWmsLayer.setVisible(this.checked);
                });

                // Lógica da Lixeira (Destruir Camada)
                const removeBtn = uiItem.querySelector(".btn-remove-wms");
                removeBtn.addEventListener("click", function () {
                    map.removeLayer(newWmsLayer);
                    uiItem.remove();
                });

                // Limpa o formulário após o sucesso
                wmsUrlInput.value = "";
                wmsLayerInput.value = "";
            } catch (error) {
                console.error("Erro ao conectar WMS: ", error);
                alert(
                    "❌ Erro ao processar a camada WMS. Verifique se a URL suporta o protocolo OGC WMS.",
                );
            } finally {
                // Restaura o botão
                btnAddWms.innerHTML = originalText;
                btnAddWms.disabled = false;
            }
        });
    }

    // =========================================================================
    // 21. EXPORTAÇÃO PARA SHAPEFILE (SHP) VIA CLOUD OGR2OGR
    // =========================================================================
    window.addEventListener("exportar-camada-shp", (e) => {
        const data = e.detail[0] || e.detail;
        const layerName = data.layer;

        if (!window.loadedLayers[layerName]) {
            alert(
                `⚠️ A camada ${layerName.toUpperCase()} não está carregada no mapa. Ligue-a no menu lateral antes de exportar.`,
            );
            return;
        }

        const features = window.loadedLayers[layerName]
            .getSource()
            .getFeatures();
        if (features.length === 0) {
            alert(
                `⚠️ A camada ${layerName} está vazia ou sem dados no momento.`,
            );
            return;
        }

        // 1. Extrai tudo para GeoJSON e reverte a projeção do Mapa (3857) para o padrão Mundial (4326)
        const format = new ol.format.GeoJSON();
        const geojsonStr = format.writeFeatures(features, {
            featureProjection: "EPSG:3857",
            dataProjection: "EPSG:4326",
        });

        // 2. Cria um formulário fantasma para acionar a API Pública do OGRE (OGR2OGR)
        const form = document.createElement("form");
        form.method = "POST";
        form.action = "https://ogre.adc4gis.com/convertJson";

        // Target _blank faz o download iniciar em segundo plano sem tirar o usuário do SIGWEB
        form.target = "_blank";

        const inputJson = document.createElement("input");
        inputJson.type = "hidden";
        inputJson.name = "json";
        inputJson.value = geojsonStr;

        const inputName = document.createElement("input");
        inputName.type = "hidden";
        inputName.name = "outputName";
        inputName.value = "SIGWEB_Exportacao_" + layerName.toUpperCase();

        form.appendChild(inputJson);
        form.appendChild(inputName);
        document.body.appendChild(form);

        // 3. Dispara o POST. O Servidor converte e devolve o .ZIP automaticamente!
        form.submit();

        // 4. Limpa a sujeira do HTML após 1 segundo
        setTimeout(() => document.body.removeChild(form), 1000);
    });

    // =========================================================================
    // 22. MOTOR DE IMPRESSÃO DE ALTA RESOLUÇÃO (A4 ao A0)
    // =========================================================================
    const dimensoesPapel = {
        a0: [1189, 841],
        a1: [841, 594],
        a2: [594, 420],
        a3: [420, 297],
        a4: [297, 210],
    };

    window.addEventListener("gerar-pdf-mapa", (e) => {
        const data = e.detail[0] || e.detail;
        const format = data.size.toLowerCase();
        const orientation = data.orientation;

        const overlay = document.getElementById("print-loading-overlay");
        if (overlay) overlay.style.display = "flex"; // Mostra a tela de carregamento

        // 1. Define Largura e Altura com base na Orientação
        const dim = dimensoesPapel[format];
        const widthMm = orientation === "landscape" ? dim[0] : dim[1];
        const heightMm = orientation === "landscape" ? dim[1] : dim[0];

        // 2. Define o DPI de Impressão (A0 e A1 usam 120 para não estourar a RAM do navegador)
        const dpi = format === "a0" || format === "a1" ? 120 : 150;
        const widthPx = Math.round((widthMm * dpi) / 25.4);
        const heightPx = Math.round((heightMm * dpi) / 25.4);

        // 3. Salva a geometria atual da tela para devolver depois
        const originalSize = map.getSize();
        const originalResolution = view.getResolution();

        // 4. ESTICA o mapa matematicamente (Invisível para o usuário)
        map.setSize([widthPx, heightPx]);
        const scaling = Math.min(
            widthPx / originalSize[0],
            heightPx / originalSize[1],
        );
        view.setResolution(originalResolution / scaling);

        // 5. Escuta APENAS UMA VEZ o evento de quando o OpenLayers terminar de desenhar o mapa gigante
        map.once("rendercomplete", function () {
            // Cria um Canvas gigante na memória
            const mapCanvas = document.createElement("canvas");
            mapCanvas.width = widthPx;
            mapCanvas.height = heightPx;
            const mapContext = mapCanvas.getContext("2d");

            // Fundo Branco (Previne PDFs pretos quando se usa mapas transparentes)
            mapContext.fillStyle = "white";
            mapContext.fillRect(0, 0, widthPx, heightPx);

            // Copia a tinta do mapa do OL para o nosso Canvas gigante
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
                        CanvasRenderingContext2D.prototype.setTransform.apply(
                            mapContext,
                            matrix,
                        );
                    }
                    mapContext.drawImage(canvas, 0, 0);
                }
            });

            mapContext.globalAlpha = 1;
            mapContext.setTransform(1, 0, 0, 1, 0, 0);

            try {
                // 6. Monta o PDF oficial
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({
                    orientation: orientation,
                    unit: "mm",
                    format: format,
                });

                // Injeta a foto capturada
                pdf.addImage(
                    mapCanvas.toDataURL("image/jpeg", 0.8),
                    "JPEG",
                    0,
                    0,
                    widthMm,
                    heightMm,
                );

                // Injeta a "Assinatura" do SIGWEB no canto do mapa
                pdf.setFontSize(10);
                pdf.setTextColor(50, 50, 50);
                pdf.text(
                    `SIGWEB - Gerado em ${new Date().toLocaleDateString("pt-BR")}`,
                    10,
                    heightMm - 10,
                );

                // Faz o Download!
                pdf.save(`SIGWEB_Mapa_${format.toUpperCase()}.pdf`);
            } catch (err) {
                console.error("Erro na compilação do PDF:", err);
                alert(
                    "❌ O formato escolhido gerou uma imagem muito grande para a memória RAM deste navegador. Tente um formato menor.",
                );
            } finally {
                // 7. MÁGICA FINAL: Devolve o mapa ao tamanho normal da tela
                map.setSize(originalSize);
                view.setResolution(originalResolution);
                if (overlay) overlay.style.display = "none"; // Apaga o loading
            }
        });

        // Dispara o comando para o mapa se redesenhar no tamanho gigante
        map.renderSync();
    });

    // =========================================================================
    // ESTATÍSTICAS POR ÁREA DE INTERESSE
    // =========================================================================
    window.estatOverlays = [];

    window.limparOverlaysEstat = function () {
        window.estatOverlays.forEach((o) => map.removeOverlay(o));
        window.estatOverlays = [];
    };

    window.addEventListener("executar-estatisticas", async (e) => {
        const dados = e.detail.dados || e.detail;
        const {
            area_type,
            area_id,
            target_layer,
            group_field,
            chart_type,
            show_on_map,
        } = dados;

        const url = `/api/mapa/estatisticas?tenant_id=${config.tenantId}&area_type=${area_type}&area_id=${area_id}&target_layer=${target_layer}&group_field=${group_field}`;

        try {
            const resp = await fetch(url);
            const json = await resp.json();

            if (json.error) {
                alert("Erro: " + json.error);
                return;
            }
            if (!json.areas || !json.areas.length) {
                alert("Nenhum dado encontrado para essa seleção.");
                return;
            }

            // ---- Painel lateral ----
            const painel = document.getElementById("painel-estatisticas");
            const titulo = document.getElementById("stat-titulo");
            const resumo = document.getElementById("stat-resumo");
            const tabela = document.getElementById("stat-tabela");
            const canvas = document.getElementById("stat-chart");

            // Agrega todos os grupos de todas as áreas para o gráfico do painel
            const agregado = {};
            let totalGeral = 0;
            json.areas.forEach((a) => {
                totalGeral += a.total;
                a.grupos.forEach((g) => {
                    agregado[g.valor] = (agregado[g.valor] || 0) + g.quantidade;
                });
            });

            const labels = Object.keys(agregado);
            const valores = Object.values(agregado);
            const cores = labels.map(
                (_, i) => `hsl(${(i * 47) % 360}, 65%, 55%)`,
            );

            titulo.textContent = `${json.areas[0]?.layer_label || target_layer} — ${json.areas[0]?.group_label || group_field}`;
            resumo.textContent = `Total geral: ${totalGeral} | ${json.areas.length} área(s)`;

            // Destrói chart anterior se existir
            if (window._estatChartInstance)
                window._estatChartInstance.destroy();
            window._estatChartInstance = new Chart(canvas.getContext("2d"), {
                type: chart_type || "bar",
                data: {
                    labels,
                    datasets: [
                        {
                            label: "Quantidade",
                            data: valores,
                            backgroundColor: cores,
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: chart_type === "pie" } },
                    scales:
                        chart_type === "pie"
                            ? {}
                            : {
                                  y: {
                                      ticks: { color: "#9ca3af" },
                                      grid: { color: "rgba(255,255,255,0.05)" },
                                  },
                                  x: { ticks: { color: "#9ca3af" } },
                              },
                },
            });

            // Tabela resumo
            tabela.innerHTML = `
                <table style="width:100%;border-collapse:collapse;font-size:11px;color:#e5e7eb;">
                    <thead>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.1);">
                            <th style="text-align:left;padding:4px 6px;color:#9ca3af;">Valor</th>
                            <th style="text-align:right;padding:4px 6px;color:#9ca3af;">Qtd</th>
                            <th style="text-align:right;padding:4px 6px;color:#9ca3af;">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${labels
                            .map(
                                (l, i) => `
                            <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                                <td style="padding:4px 6px;display:flex;align-items:center;gap:6px;">
                                    <span style="width:8px;height:8px;border-radius:2px;background:${cores[i]};display:inline-block;"></span>${l}
                                </td>
                                <td style="text-align:right;padding:4px 6px;">${valores[i]}</td>
                                <td style="text-align:right;padding:4px 6px;">${totalGeral > 0 ? ((valores[i] / totalGeral) * 100).toFixed(1) : 0}%</td>
                            </tr>
                        `,
                            )
                            .join("")}
                    </tbody>
                </table>`;

            painel.style.display = "block";

            // ---- Overlays no mapa ----
            window.limparOverlaysEstat();

            if (show_on_map) {
                json.areas.forEach((area) => {
                    if (!area.centroide || !area.grupos.length) return;

                    const [lng, lat] = area.centroide;
                    const coord = ol.proj.fromLonLat([lng, lat]);

                    // Cria canvas do mini-gráfico
                    const miniCanvas = document.createElement("canvas");
                    miniCanvas.width = 100;
                    miniCanvas.height = 100;
                    miniCanvas.style.cssText =
                        "border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.5);cursor:pointer;";

                    const miniLabels = area.grupos.map((g) => g.valor);
                    const miniValores = area.grupos.map((g) => g.quantidade);
                    const miniCores = miniLabels.map(
                        (_, i) => `hsl(${(i * 47) % 360}, 65%, 55%)`,
                    );

                    new Chart(miniCanvas.getContext("2d"), {
                        type: "pie",
                        data: {
                            labels: miniLabels,
                            datasets: [
                                {
                                    data: miniValores,
                                    backgroundColor: miniCores,
                                },
                            ],
                        },
                        options: {
                            responsive: false,
                            animation: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: { enabled: false },
                            },
                        },
                    });

                    // Label com nome da área + total
                    const wrapper = document.createElement("div");
                    wrapper.style.cssText =
                        "display:flex;flex-direction:column;align-items:center;gap:3px;";
                    const label = document.createElement("div");
                    label.style.cssText =
                        "font-size:10px;font-weight:600;color:#fff;background:rgba(0,0,0,0.6);padding:2px 6px;border-radius:4px;white-space:nowrap;";
                    label.textContent = `${area.area_label} (${area.total})`;
                    wrapper.appendChild(miniCanvas);
                    wrapper.appendChild(label);

                    const overlay = new ol.Overlay({
                        position: coord,
                        positioning: "center-center",
                        element: wrapper,
                        stopEvent: false,
                    });

                    map.addOverlay(overlay);
                    window.estatOverlays.push(overlay);
                });
            }
        } catch (err) {
            console.error("Erro nas estatísticas:", err);
            alert("Falha ao carregar estatísticas.");
        }
    });

    // 🎨 FUNÇÃO AUXILIAR: Converte HEX do Filament para RGBA do OpenLayers
    function hexToRgba(hex, alpha) {
        if (!hex) return `rgba(245, 158, 11, ${alpha})`; // Fallback para laranja
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    // =========================================================================
    // B2 — LOCALIZAÇÃO POR COORDENADA DIGITADA + ESCALA + MARCADOR PISCANTE
    // =========================================================================

    /**
     * Pisca um marcador vermelho temporário na coordenada (lat, lon) por ~4 segundos.
     * Usado após irParaCoordenada e poderia ser usado também em outras buscas no mapa.
     */
    window.piscarMarcadorTemporario = function (lat, lon) {
        const latF = parseFloat(lat);
        const lonF = parseFloat(lon);
        if (isNaN(latF) || isNaN(lonF)) return;

        const coord = ol.proj.fromLonLat([lonF, latF]);
        const feature = new ol.Feature({ geometry: new ol.geom.Point(coord) });

        const styleOn = new ol.style.Style({
            image: new ol.style.Circle({
                radius: 14,
                fill: new ol.style.Fill({ color: "rgba(239,68,68,0.55)" }),
                stroke: new ol.style.Stroke({ color: "#dc2626", width: 3 }),
            }),
        });
        const styleOff = new ol.style.Style({
            image: new ol.style.Circle({
                radius: 14,
                fill: new ol.style.Fill({ color: "rgba(239,68,68,0)" }),
                stroke: new ol.style.Stroke({ color: "rgba(220,38,38,0.25)", width: 1 }),
            }),
        });
        feature.setStyle(styleOn);

        const source = new ol.source.Vector({ features: [feature] });
        const layer = new ol.layer.Vector({ source: source, zIndex: 9999 });
        map.addLayer(layer);

        let visible = true;
        let count = 0;
        const interval = setInterval(function () {
            visible = !visible;
            feature.setStyle(visible ? styleOn : styleOff);
            count++;
            if (count >= 8) {
                clearInterval(interval);
                map.removeLayer(layer);
            }
        }, 500);
    };

    window.irParaCoordenada = function (lat, lon, zoom) {
        const latF = parseFloat(lat);
        const lonF = parseFloat(lon);
        if (isNaN(latF) || isNaN(lonF)) {
            alert(
                "Coordenadas inválidas. Informe latitude e longitude numéricas.",
            );
            return;
        }
        map.getView().animate(
            {
                center: ol.proj.fromLonLat([lonF, latF]),
                zoom: zoom || 18,
                duration: 1500,
            },
            function () {
                // Após o voo terminar, pisca o marcador temporário no destino
                window.piscarMarcadorTemporario(latF, lonF);
            },
        );
    };

    /**
     * Ajusta o zoom do mapa para corresponder a uma escala 1:X.
     * Fórmula: resolution = scale / (DPI * inches_per_meter)
     * Com DPI=96 e inches_per_meter=39.3701 → divisor ≈ 3780.
     */
    window.irParaEscala = function (escala) {
        const escalaF = parseFloat(escala);
        if (isNaN(escalaF) || escalaF <= 0) {
            alert(
                "Escala inválida. Informe um número positivo (ex: 1000 para 1:1000).",
            );
            return;
        }
        const resolution = escalaF / 3780;
        map.getView().animate({
            resolution: resolution,
            duration: 800,
        });
    };

    // =========================================================================
    // B3 — TOGGLE DE RÓTULOS POR CAMADA
    // Guarda as style functions originais e alterna entre com/sem texto
    // =========================================================================
    window.sigwebLabelsEnabled = {};
    window.sigwebOriginalStyles = {};

    window.sigwebLabelField = {};

    window.toggleLayerLabels = function (layerName, enabled, attributeField) {
        window.sigwebLabelsEnabled[layerName] = enabled;

        if (attributeField) {
            window.sigwebLabelField[layerName] = attributeField;
        }

        const layer = window.loadedLayers[layerName];
        if (!layer) return;

        if (!window.sigwebOriginalStyles[layerName]) {
            window.sigwebOriginalStyles[layerName] = layer.getStyleFunction();
        }

        if (enabled) {
            const field = window.sigwebLabelField[layerName];
            if (field && field !== "__default__") {
                layer.setStyle(function (feature, resolution) {
                    const originalFn = window.sigwebOriginalStyles[layerName];
                    const styles = originalFn
                        ? originalFn(feature, resolution)
                        : [];
                    const arr = Array.isArray(styles)
                        ? styles
                        : styles
                          ? [styles]
                          : [];
                    return arr.map((s) => {
                        const clone = s.clone();
                        const val = feature.get(field);
                        clone.setText(
                            new ol.style.Text({
                                text:
                                    val !== undefined && val !== null
                                        ? String(val)
                                        : "",
                                font: "bold 11px Arial, sans-serif",
                                fill: new ol.style.Fill({ color: "#1f2937" }),
                                stroke: new ol.style.Stroke({
                                    color: "#ffffff",
                                    width: 3,
                                }),
                                overflow: true,
                            }),
                        );
                        return clone;
                    });
                });
            } else {
                layer.setStyle(window.sigwebOriginalStyles[layerName]);
            }
        } else {
            layer.setStyle(function (feature, resolution) {
                const originalFn = window.sigwebOriginalStyles[layerName];
                const styles = originalFn
                    ? originalFn(feature, resolution)
                    : [];
                const arr = Array.isArray(styles)
                    ? styles
                    : styles
                      ? [styles]
                      : [];
                return arr.map((s) => {
                    const clone = s.clone();
                    clone.setText(null);
                    return clone;
                });
            });
        }
    };

    // Listener para evento disparado pelo blade
    window.addEventListener("sigweb-toggle-labels", function (e) {
        window.toggleLayerLabels(
            e.detail.layer,
            e.detail.enabled,
            e.detail.field || null,
        );
    });

    // =========================================================================
    // STATUS DE COLETA — colore os lotes por status_cadastro (Antônio Carlos PoC)
    // Verde=coletado · Amarelo=pendente · Vermelho=inconformidade · Cinza=não visitado
    // =========================================================================
    window.sigwebStatusColorEnabled = {};
    window.sigwebStatusColorBaseStyle = {};

    window.toggleLotesStatusColor = function (enabled) {
        const layerName = "lotes";
        window.sigwebStatusColorEnabled[layerName] = enabled;

        const layer = window.loadedLayers[layerName];
        if (!layer) return;

        if (!window.sigwebStatusColorBaseStyle[layerName]) {
            window.sigwebStatusColorBaseStyle[layerName] =
                layer.getStyleFunction();
        }

        const STATUS_COLORS = {
            nao_visitado: { fill: "rgba(156,163,175,0.35)", stroke: "#6B7280" },
            coletado: { fill: "rgba(16,185,129,0.40)", stroke: "#059669" },
            pendente: { fill: "rgba(245,158,11,0.40)", stroke: "#D97706" },
            inconformidade: { fill: "rgba(239,68,68,0.40)", stroke: "#DC2626" },
        };

        if (enabled) {
            layer.setStyle(function (feature, resolution) {
                const status = feature.get("status_cadastro") || "nao_visitado";
                const c = STATUS_COLORS[status] || STATUS_COLORS.nao_visitado;
                return new ol.style.Style({
                    fill: new ol.style.Fill({ color: c.fill }),
                    stroke: new ol.style.Stroke({ color: c.stroke, width: 2 }),
                });
            });
        } else {
            layer.setStyle(window.sigwebStatusColorBaseStyle[layerName]);
        }
    };

    window.addEventListener("sigweb-toggle-status-color", function (e) {
        window.toggleLotesStatusColor(e.detail.enabled);
    });

    // =========================================================================
    // #9 — FERRAMENTA DE TOPONÍMIA (texto livre no mapa)
    // =========================================================================
    let modoToponimia = false;

    window.ativarFerramentaToponimiia = function (ativo) {
        modoToponimia = ativo;
        const mapEl = document.getElementById("sigweb-map");
        if (mapEl) mapEl.style.cursor = ativo ? "crosshair" : "";
    };

    map.on("click", function (evt) {
        if (!modoToponimia) return;

        const lonLat = ol.proj.toLonLat(evt.coordinate);
        const lat = parseFloat(lonLat[1].toFixed(7));
        const lon = parseFloat(lonLat[0].toFixed(7));

        // Desativa o modo após o clique para não acionar duas vezes
        modoToponimia = false;
        document.getElementById("sigweb-map").style.cursor = "";

        // Notifica o Livewire para abrir o modal de texto
        Livewire.dispatch("abrirModalToponimia", { lat, lon });
    });

    // Recarrega uma camada vetorial já carregada (ex: após salvar uma toponímia)
    window.addEventListener("sigweb-recarregar-camada", function (e) {
        const layerName = e.detail?.layer;
        if (!layerName || !window.loadedLayers?.[layerName]) return;
        const source = window.loadedLayers[layerName].getSource();
        if (source && source.refresh) source.refresh();
    });

    // Livewire dispatch 'recarregarCamada' → CustomEvent 'sigweb-recarregar-camada'
    document.addEventListener("livewire:initialized", function () {
        Livewire.on("recarregarCamada", function ({ layer }) {
            window.dispatchEvent(
                new CustomEvent("sigweb-recarregar-camada", {
                    detail: { layer },
                }),
            );
        });
    });

    // =========================================================================
    // B4 — CAPTURAR ENQUADRAMENTO ATUAL (para salvar como padrão)
    // =========================================================================
    window.getEnquadramentoAtual = function () {
        const center = ol.proj.toLonLat(map.getView().getCenter());
        return {
            lon: parseFloat(center[0].toFixed(7)),
            lat: parseFloat(center[1].toFixed(7)),
            zoom: Math.round(map.getView().getZoom()),
        };
    };
}); // <-- Fim do DOMContentLoaded
