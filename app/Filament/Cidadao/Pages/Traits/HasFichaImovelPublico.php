<?php

namespace App\Filament\Cidadao\Pages\Traits;

use App\Models\Lote;
use App\Models\Edificacao;
use App\Models\UnidadeImobiliaria;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Filament\Notifications\Notification;

trait HasFichaImovelPublico
{
    // Propriedades da interface
    public bool $showFicha = false;
    public ?int $loteAtivoId = null;
    public ?string $loteAtivoNome = null;
    public ?string $loteSequentialId = null;
    public float $loteAreaGeo = 0.0;
    public float $loteAreaConstruida = 0.0;
    public float $loteFacePrincipal = 0.0;
    public bool $mostrarEdificacoesLoteAtivo = false;

    // T1.4 (item 3-9) — imagem frontal do imóvel na ficha pública
    public ?string $loteFotoFrontal = null;

    #[On('abrirFichaImovel')]
    public function abrirFichaImovel($loteId, $loteNome = 'S/N')
    {
        if ($this->loteAtivoId !== null && $this->loteAtivoId != $loteId) {
            $this->mostrarEdificacoesLoteAtivo = false;
            $this->dispatch('esconder-edificacoes-lote');
        }

        $this->loteAtivoId = $loteId;
        $this->loteAtivoNome = $loteNome;

        $lote = Lote::find($loteId);
        $this->loteAreaGeo = $lote ? (float) $lote->area_geo : 0.0;
        $this->loteFacePrincipal = $lote ? (float) $lote->main_facade_length : 0.0;
        $this->loteAreaConstruida = (float) Edificacao::where('lote_id', $loteId)->sum('area_geo');
        $this->loteSequentialId = $lote ? $lote->sequential_id : 'S/N';
        $this->loteFotoFrontal = $lote?->foto_frontal ? asset('storage/'.$lote->foto_frontal) : null;

        $this->showFicha = true;
    }

    // ------------------------------------------------------------------------
    // T2.3 (item 3-10) — LOGRADOURO CLICÁVEL: dados + seções + fotos (T1.7)
    // ------------------------------------------------------------------------
    #[On('abrirFichaLogradouro')]
    public function abrirFichaLogradouro($logradouroId)
    {
        $this->mountAction('fichaLogradouro', ['logradouroId' => (int) $logradouroId]);
    }

    public function fichaLogradouroAction(): Action
    {
        return Action::make('fichaLogradouro')
            ->modalHeading(function (array $arguments): string {
                $logradouro = \App\Models\Logradouro::find($arguments['logradouroId'] ?? null);

                return $logradouro?->name ?? 'Logradouro';
            })
            ->modalWidth('2xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function (array $arguments) {
                $logradouro = \App\Models\Logradouro::with(['secoes' => fn ($q) => $q->with('fotos')->orderBy('codigo')])
                    ->find($arguments['logradouroId'] ?? null);

                if (! $logradouro) {
                    return new HtmlString('<p class="text-sm text-gray-500">Logradouro não encontrado.</p>');
                }

                return view('filament.cidadao.components.ficha-logradouro-publica', [
                    'logradouro' => $logradouro,
                ]);
            });
    }

    // ------------------------------------------------------------------------
    // T2.4 (item 3-12) — VIABILIDADE DE PARCELAMENTO/DESMEMBRAMENTO no público.
    // Mesmo motor da intranet (ViabilidadeService::analisarParcelamento) + PDF
    // oficial com protocolo /v/{protocolo}.
    // ------------------------------------------------------------------------
    public function consultarParcelamentoAction(): Action
    {
        return Action::make('consultarParcelamento')
            ->modalHeading('Viabilidade de Parcelamento / Desmembramento')
            ->modalDescription('Simule a divisão deste imóvel e receba o parecer conforme os parâmetros da zona.')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Analisar')
            ->form([
                \Filament\Forms\Components\TextInput::make('qtd_lotes')
                    ->label('Dividir em quantos lotes?')
                    ->numeric()
                    ->minValue(2)
                    ->maxValue(50)
                    ->default(2)
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->replaceMountedAction('resultadoParcelamentoPublico', ['qtd_lotes' => (int) $data['qtd_lotes']]);
            });
    }

    public function resultadoParcelamentoPublicoAction(): Action
    {
        return Action::make('resultadoParcelamentoPublico')
            ->modalHeading('Resultado da Análise — Parcelamento')
            ->modalWidth('3xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->form(function (array $arguments) {
                $service = new \App\Services\Viabilidade\ViabilidadeService;
                $resultado = $service->analisarParcelamento($this->loteAtivoId, $arguments['qtd_lotes'] ?? 2);

                $cor = match ($resultado['status_final'] ?? 'proibido') {
                    'permitido' => 'success',
                    'permissivel' => 'warning',
                    default => 'danger',
                };
                $textoStatus = strtoupper($resultado['status_final'] ?? 'PROIBIDO');
                $htmlParecer = implode('<br><br>', $resultado['parecer_tecnico'] ?? ['Nenhum parecer gerado.']);

                return [
                    \Filament\Forms\Components\Section::make('Parecer Técnico')
                        ->schema([
                            \Filament\Forms\Components\Placeholder::make('status')
                                ->label('Veredito Final')
                                ->content(new HtmlString("<span class='text-{$cor}-600 font-bold text-lg' style='color: var(--{$cor}-600);'>{$textoStatus}</span>")),

                            \Filament\Forms\Components\Placeholder::make('parecer')
                                ->label('Análise Geométrica e Matemática')
                                ->content(new HtmlString("<div class='text-gray-700 dark:text-gray-300' style='font-size: 1rem; line-height: 1.5;'>{$htmlParecer}</div>")),
                        ]),
                ];
            })
            ->extraModalFooterActions(function (array $arguments) {
                $qtd = $arguments['qtd_lotes'] ?? 2;

                return [
                    Action::make('imprimir_parcelamento_publico')
                        ->label('Gerar PDF Oficial')
                        ->color('success')
                        ->icon('heroicon-o-printer')
                        ->extraAttributes([
                            'type' => 'button',
                            'x-on:click.prevent' => "capturarMapaEImprimirParcelamento({$this->loteAtivoId}, {$qtd})",
                        ])
                        ->action(function () { /* o JS captura o croqui e chama imprimirParcelamento */
                        }),
                ];
            });
    }

    /** Recebe o print do mapa do JS e devolve o PDF oficial (com protocolo /v/). */
    public function imprimirParcelamento($mapImageBase64, $qtdLotes, $loteId)
    {
        $service = app(\App\Services\Viabilidade\ViabilidadeService::class);
        $dadosAnalise = $service->analisarParcelamento($loteId, (int) $qtdLotes);

        if (isset($dadosAnalise['error'])) {
            Notification::make()->danger()->title('Erro')->body($dadosAnalise['error'])->send();

            return;
        }

        $dadosAnalise['numero_lote'] = $this->loteAtivoNome ?? Lote::query()->find($loteId)?->numero_lote ?? 'S/N';

        return app(\App\Services\Viabilidade\ViabilidadePdfService::class)
            ->generateParcelamentoPdf($dadosAnalise, $mapImageBase64);
    }

    // ------------------------------------------------------------------------
    // T1.6 (item 3-11) — ZONA CLICÁVEL: parâmetros urbanísticos + usos da zona
    // ------------------------------------------------------------------------
    #[On('abrirFichaZona')]
    public function abrirFichaZona($zonaId)
    {
        $this->mountAction('fichaZona', ['zonaId' => (int) $zonaId]);
    }

    public function fichaZonaAction(): Action
    {
        return Action::make('fichaZona')
            ->modalHeading(function (array $arguments): string {
                $zona = \App\Models\Zona::find($arguments['zonaId'] ?? null);

                return $zona ? "{$zona->sigla} — {$zona->name}" : 'Zona';
            })
            ->modalWidth('2xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function (array $arguments) {
                $zona = \App\Models\Zona::find($arguments['zonaId'] ?? null);

                if (! $zona) {
                    return new HtmlString('<p class="text-sm text-gray-500">Zona não encontrada.</p>');
                }

                $parametro = \App\Models\ParametroUrbano::where('zona_id', $zona->id)->first();

                $regras = \App\Models\ZoneamentoRegra::query()
                    ->where('zona_sigla', $zona->sigla)
                    ->orderBy('classificacao')
                    ->get()
                    ->groupBy('status');

                return view('filament.cidadao.components.ficha-zona-publica', [
                    'zona' => $zona,
                    'parametro' => $parametro,
                    'regras' => $regras,
                ]);
            });
    }

    public function fecharFicha()
    {
        $this->showFicha = false;
        $this->mostrarEdificacoesLoteAtivo = false;
        $this->dispatch('esconder-edificacoes-lote');
    }

    // ------------------------------------------------------------------------
    // 1. UNIDADES IMOBILIÁRIAS (SOMENTE LEITURA)
    // ------------------------------------------------------------------------
    public function verUnidadesAction(): Action
    {
        return Action::make('verUnidades')
            ->hiddenLabel()
            ->modalHeading(fn() => 'Unidades Imobiliárias - Lote #' . $this->loteAtivoNome)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalWidth('3xl')
            ->modalContent(function () {
                $unidades = UnidadeImobiliaria::where('lote_id', $this->loteAtivoId)->get();

                $bladeView = <<<'BLADE'
                    <div>
                        @if($unidades->isEmpty())
                            <div class="text-center py-8 text-gray-500 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">
                                <x-heroicon-o-home-modern class="w-10 h-10 mx-auto text-gray-400 mb-2 opacity-50" />
                                Nenhuma unidade cadastrada neste lote.
                            </div>
                        @else
                            <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                                <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                    <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700">
                                        <tr>
                                            <th class="px-4 py-3">Inscrição Imobiliária</th>
                                            <th class="px-4 py-3">Código Tributário</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($unidades as $u)
                                            <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">{{ $u->inscricao_imobiliaria ?? 'S/N' }}</td>
                                                <td class="px-4 py-3">{{ $u->codigo_imovel_tributario ?? 'S/N' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                BLADE;
                return new HtmlString(Blade::render($bladeView, ['unidades' => $unidades]));
            });
    }

    public function toggleEdificacoesLote()
    {
        $this->mostrarEdificacoesLoteAtivo = !$this->mostrarEdificacoesLoteAtivo;
        if ($this->mostrarEdificacoesLoteAtivo) {
            $this->dispatch('mostrar-edificacoes-lote', id: $this->loteAtivoId);
        } else {
            $this->dispatch('esconder-edificacoes-lote');
        }
    }

    // ------------------------------------------------------------------------
    // 2. CONSULTA DE VIABILIDADE
    // ------------------------------------------------------------------------
    public function consultarViabilidadeAction(): Action
    {
        return Action::make('consultarViabilidadeAction')
            ->modalHeading('Consulta de Viabilidade')
            ->modalDescription('Selecione as atividades desejadas para analisar a viabilidade neste lote.')
            ->modalSubmitActionLabel('Consultar Viabilidade')
            ->modalWidth('lg')
            ->closeModalByClickingAway(false)
            ->form([
                Select::make('cnaes')
                    ->label('Atividades Econômicas (CNAE)')
                    ->multiple()
                    ->searchable()
                    ->placeholder('Digite o código ou nome da atividade...')
                    ->getSearchResultsUsing(function (string $search) {
                        $searchClean = preg_replace('/[^0-9a-zA-Z\s]/', '', $search);
                        return \App\Models\Cnae::where('tenant_id', $this->tenantId)
                            ->where(function ($q) use ($search, $searchClean) {
                                $q->where('codigo', 'like', "%{$search}%")
                                    ->orWhereRaw("REGEXP_REPLACE(codigo, '[^0-9]', '') like ?", ["%{$searchClean}%"])
                                    ->orWhere('descricao', 'ilike', "%{$search}%");
                            })
                            ->limit(30)->get()
                            ->mapWithKeys(fn($cnae) => [$cnae->codigo => $cnae->codigo . ' - ' . $cnae->descricao])->toArray();
                    })
                    ->getOptionLabelsUsing(function (array $values) {
                        return \App\Models\Cnae::whereIn('codigo', $values)->get()
                            ->mapWithKeys(fn($cnae) => [$cnae->codigo => $cnae->codigo . ' - ' . $cnae->descricao])->toArray();
                    })->required()
            ])
            ->action(function (array $data) {
                $this->replaceMountedAction('resultadoViabilidadeAction', ['cnaes' => $data['cnaes']]);
            });
    }

    public function resultadoViabilidadeAction(): Action
    {
        return Action::make('resultadoViabilidadeAction')
            ->modalHeading('Resultado da Análise')
            ->modalWidth('5xl')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(false)
            ->modalContent(function (array $arguments) {
                $cnaes = $arguments['cnaes'] ?? [];
                $service = app(\App\Services\Viabilidade\ViabilidadeService::class);
                $analise = $service->analisar($this->loteAtivoId, $cnaes);

                if (isset($analise['error'])) {
                    return new HtmlString("<div class='text-red-500 font-bold p-4 bg-red-50 rounded-lg'>Erro: {$analise['error']}</div>");
                }

                $bladeView = <<<'BLADE'
                    <div class="space-y-4 font-sans">
                        <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                            <h3 class="font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider text-sm">Parecer Técnico</h3>
                            <x-filament::badge color="info" size="lg">
                                Zona: {{ $analise['zona']['sigla'] }} - {{ $analise['zona']['nome'] }}
                            </x-filament::badge>
                        </div>
                        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                <thead class="bg-gray-100 dark:bg-gray-800/80 text-xs uppercase text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="px-4 py-4">CNAE</th>
                                        <th class="px-4 py-4 w-1/2">Descrição da Atividade</th>
                                        <th class="px-4 py-4">Classificações</th>
                                        <th class="px-4 py-4 text-center">Viabilidade</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                    @foreach($analise['analises'] as $item)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                            <td class="px-4 py-4 font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ $item['cnae'] }}</td>
                                            <td class="px-4 py-4 text-xs leading-relaxed">{{ $item['descricao'] }}</td>
                                            <td class="px-4 py-4 text-xs">
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($item['classificacoes_detalhe'] as $detalhe)
                                                        @php
                                                            $color = match($detalhe['status']) { 'permitido' => 'success', 'permissivel' => 'warning', 'proibido' => 'danger', default => 'gray' };
                                                        @endphp
                                                        <x-filament::badge :color="$color" size="sm" title="{{ ucfirst($detalhe['status']) }}">{{ $detalhe['classificacao'] }}</x-filament::badge>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-center">
                                                @php
                                                    $badge = match($item['status_final']) { 'permitido' => 'success', 'permissivel' => 'warning', 'proibido' => 'danger', default => 'gray' };
                                                @endphp
                                                <x-filament::badge :color="$badge" size="lg" class="font-black uppercase tracking-wider">{{ $item['status_final'] }}</x-filament::badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                BLADE;
                return new HtmlString(Blade::render($bladeView, ['analise' => $analise]));
            })
            ->extraModalFooterActions(function (array $arguments) {
                $cnaesString = implode(',', $arguments['cnaes'] ?? []);
                return [
                    Action::make('voltar')->label('Nova Consulta')->color('gray')->action(fn() => $this->replaceMountedAction('consultarViabilidadeAction')),
                    Action::make('imprimir_pdf')->label('Imprimir Relatório')->color('success')->icon('heroicon-o-printer')
                        ->extraAttributes([
                            'id' => 'btn-imprimir-viab',
                            'type' => 'button',
                            'data-cnaes' => $cnaesString,
                            'x-on:click.prevent' => "capturarMapaEImprimir({$this->loteAtivoId}, \$el.dataset.cnaes)"
                        ])->action(function () {})
                ];
            });
    }

    public function imprimirViabilidade($mapImageBase64, $cnaes, $loteId)
    {
        if (is_string($cnaes)) {
            $decodificado = json_decode($cnaes, true);
            $cnaes = (json_last_error() === JSON_ERROR_NONE && is_array($decodificado)) ? $decodificado : explode(',', $cnaes);
        }
        if (!is_array($cnaes)) $cnaes = [];

        $service = app(\App\Services\Viabilidade\ViabilidadeService::class);
        $dadosAnalise = $service->analisar($loteId, $cnaes);

        if (isset($dadosAnalise['error'])) {
            Notification::make()->danger()->title('Erro')->body($dadosAnalise['error'])->send();
            return;
        }
        $dadosAnalise['numero_lote'] = $this->loteAtivoNome ?? 'S/N';
        $pdfService = app(\App\Services\Viabilidade\ViabilidadePdfService::class);
        return $pdfService->generatePdf($dadosAnalise, $mapImageBase64);
    }

    // ------------------------------------------------------------------------
    // 3. MEMORIAL DESCRITIVO
    // ------------------------------------------------------------------------
    public function gerarMemorialAction(): Action
    {
        return Action::make('gerarMemorialAction')
            ->requiresConfirmation()
            ->modalHeading('Gerar Memorial Descritivo')
            ->modalDescription('Deseja gerar o documento legal com a descrição do perímetro e confrontantes para este lote?')
            ->modalSubmitActionLabel('Gerar PDF')
            ->icon('heroicon-o-document-text')
            ->color('success')
            ->action(function () {
                $lote = \App\Models\Lote::find($this->loteAtivoId);
                if (!$lote) {
                    Notification::make()->danger()->title('Erro')->body('Lote não encontrado.')->send();
                    return;
                }
                $service = app(\App\Services\Gis\MemorialDescritivoService::class);
                $textoMemorial = $service->gerarTextoMemorial($lote->id);
                $segmentos = $service->gerarDadosPerimetro($lote->id);

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.memorial_descritivo', [
                    'lote' => $lote,
                    'textoMemorial' => $textoMemorial,
                    'segmentos' => $segmentos,
                    'dataExtenso' => now()->translatedFormat('d \d\e F \d\e Y'),
                    'tenantNome' => \Filament\Facades\Filament::getTenant()->name ?? 'Município',
                ]);
                return response()->streamDownload(function () use ($pdf) {
                    echo $pdf->output();
                }, 'memorial_lote_' . ($lote->numero_lote ?? $lote->id) . '.pdf');
            });
    }

    // ------------------------------------------------------------------------
    // 4. CROQUI E EXPORTAÇÃO (PDF, DWG, SHP)
    // ------------------------------------------------------------------------
    public function exportarCroquiAction(): Action
    {
        return Action::make('exportarCroqui')
            ->modalHeading('Exportar Lote / Croqui')
            ->modalDescription(fn() => 'Selecione o formato de exportação desejado para o Lote ' . $this->loteAtivoNome)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth('3xl')
            ->extraModalFooterActions([
                Action::make('export_pdf')->label('Gerar PDF')->color('danger')->icon('heroicon-o-document')
                    ->extraAttributes(['onclick' => "capturarMapaEImprimirCroqui({$this->loteAtivoId})", 'x-on:click' => 'close()'])
                    ->action(function () {}),

                Action::make('export_shp')->label('Gerar Shapefile')->color('success')->icon('heroicon-o-map')
                    ->action(function () {
                        $lote = \App\Models\Lote::find($this->loteAtivoId);
                        $geoJsonQuery = \Illuminate\Support\Facades\DB::selectOne("SELECT ST_AsGeoJSON(geo) as geojson FROM lotes WHERE id = ?", [$lote->id]);
                        $featureCollection = ['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'properties' => ['id' => $lote->id, 'numero' => $lote->numero_lote, 'area_m2' => $lote->area_geo, 'testada' => $lote->main_facade_length], 'geometry' => json_decode($geoJsonQuery->geojson)]]];
                        $jsonContent = json_encode($featureCollection);
                        try {
                            $tempDir = storage_path('app/temp_shp_' . uniqid());
                            if (!is_dir($tempDir)) mkdir($tempDir);
                            $geoJsonPath = $tempDir . '/lote.geojson';
                            file_put_contents($geoJsonPath, $jsonContent);
                            $shpPath = $tempDir . '/Lote_' . $lote->numero_lote . '.shp';
                            $process = \Illuminate\Support\Facades\Process::run("ogr2ogr -f \"ESRI Shapefile\" {$shpPath} {$geoJsonPath}");
                            if ($process->successful() && file_exists($shpPath)) {
                                $zipPath = $tempDir . '/Lote_' . $lote->numero_lote . '.zip';
                                $zip = new \ZipArchive();
                                $zip->open($zipPath, \ZipArchive::CREATE);
                                $zip->addFile($tempDir . '/Lote_' . $lote->numero_lote . '.shp', 'Lote_' . $lote->numero_lote . '.shp');
                                $zip->addFile($tempDir . '/Lote_' . $lote->numero_lote . '.shx', 'Lote_' . $lote->numero_lote . '.shx');
                                $zip->addFile($tempDir . '/Lote_' . $lote->numero_lote . '.dbf', 'Lote_' . $lote->numero_lote . '.dbf');
                                $zip->addFile($tempDir . '/Lote_' . $lote->numero_lote . '.prj', 'Lote_' . $lote->numero_lote . '.prj');
                                $zip->close();
                                return response()->download($zipPath)->deleteFileAfterSend(true);
                            }
                        } catch (\Exception $e) {
                        }
                        Notification::make()->title('Exportado como GeoJSON')->body('O conversor GDAL (ogr2ogr) não foi detectado no seu ambiente. O arquivo foi exportado no formato universal GeoJSON, compatível nativamente com QGIS e ArcGIS.')->warning()->send();
                        return response()->streamDownload(function () use ($jsonContent) {
                            echo $jsonContent;
                        }, 'Lote_' . ($lote->numero_lote ?? $lote->id) . '.geojson');
                    }),
            ]);
    }

    public function imprimirCroqui($loteId, $mapImageBase64)
    {
        $lote = \App\Models\Lote::with(['quadra.bairro', 'zona'])->find($loteId);
        if (!$lote) {
            Notification::make()->title('Erro')->body('Lote não encontrado.')->danger()->send();
            return;
        }
        $service = app(\App\Services\Gis\CroquiPdfService::class);
        return $service->generatePdf($lote, $mapImageBase64);
    }

    // ------------------------------------------------------------------------
    // 5. GOOGLE STREET VIEW
    // ------------------------------------------------------------------------
    public function abrirStreetViewAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('abrirStreetViewAction')
            ->modalHeading(fn() => 'Google Street View - Frente do Lote')
            ->modalWidth('5xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function () {
                $lote = \App\Models\Lote::find($this->loteAtivoId);
                $centro = \Illuminate\Support\Facades\DB::selectOne("SELECT ST_Y(ST_Centroid(geo::geometry)) as lat, ST_X(ST_Centroid(geo::geometry)) as lng FROM lotes WHERE id = ?", [$lote->id]);
                $lat = $centro->lat ?? 0;
                $lng = $centro->lng ?? 0;

                $bladeView = <<<'BLADE'
                    <div x-data="{
                            init() { setTimeout(() => this.loadStreetView(), 200); },
                            loadStreetView() {
                                const panoDiv = document.getElementById('street-view-modal-pano');
                                if (!panoDiv || typeof google === 'undefined' || !google.maps) return;
                                const svService = new google.maps.StreetViewService();
                                const centroDoLote = new google.maps.LatLng({{ $lat }}, {{ $lng }});
                                svService.getPanorama({ location: centroDoLote, radius: 50 }, (data, status) => {
                                    if (status === 'OK') {
                                        panoDiv.style.opacity = '1';
                                        const panorama = new google.maps.StreetViewPanorama(panoDiv, {
                                            position: data.location.latLng, zoom: 0, panControl: true, zoomControl: true, linksControl: true, clickToGo: true
                                        });
                                        const anguloParaOLote = google.maps.geometry.spherical.computeHeading(data.location.latLng, centroDoLote);
                                        panorama.setPov({ heading: anguloParaOLote, pitch: 0 });
                                    }
                                });
                            }
                        }"
                        wire:ignore style="height: 500px; width: 100%; position: relative; border-radius: 0.75rem; overflow: hidden; border: 1px solid #e5e7eb; background-color: #f3f4f6;" class="dark:border-gray-600 dark:bg-gray-800">
                        <div id="street-view-modal-pano" style="position: absolute; inset: 0; width: 100%; height: 100%; z-index: 10; opacity: 0; transition: opacity 0.5s;"></div>
                        <div id="street-view-error" style="position: absolute; inset: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 0;">
                            <x-heroicon-o-video-camera-slash style="width: 3rem; height: 3rem; opacity: 0.3; margin-bottom: 0.5rem; color: #6b7280;" />
                            <span style="font-size: 14px; font-weight: bold; text-transform: uppercase; color: #6b7280; opacity: 0.6;">Sem Cobertura do Street View</span>
                        </div>
                    </div>
                BLADE;
                return new HtmlString(Blade::render($bladeView, ['lat' => $lat, 'lng' => $lng]));
            });
    }
}
