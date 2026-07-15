<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BpmnFluxoResource\Pages;
use App\Models\BpmnFluxo;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BpmnFluxoResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'processos'; // Um novo módulo!

    protected static ?string $model = BpmnFluxo::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $modelLabel = 'Fluxo de Processo (BPMN)';

    protected static ?string $pluralModelLabel = 'Fluxos BPMN';

    protected static ?string $navigationGroup = 'Processos Digitais';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação do Fluxo')
                    ->schema([
                        Forms\Components\TextInput::make('nome')
                            ->label('Nome do Processo (Ex: Aprovação REURB)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Toggle::make('ativo')
                            ->label('Fluxo Ativo')
                            ->default(true),

                        // Cor deste fluxo no mapa (camada "Processos Digitais")
                        Forms\Components\ColorPicker::make('cor')
                            ->label('Cor no mapa')
                            ->default('#3b82f6')
                            ->helperText('Cor com que os lotes deste fluxo são marcados na camada de Processos do mapa.'),

                        // Decisão #5 / item 2: modo de seleção de imóvel (processosConceito.md §9.5)
                        Forms\Components\Select::make('modo_imovel')
                            ->label('Seleção de imóvel')
                            ->options([
                                'nenhum' => 'Sem necessidade de imóvel',
                                'mapa' => 'Mostrar mapa para localização do imóvel',
                                'busca' => 'Seleção de imóvel por busca (nº do lote / código tributário)',
                            ])
                            ->default('mapa')
                            ->required()
                            ->native(false)
                            ->helperText('Define se e como o cidadão informa o imóvel ao abrir o processo (itens 130/221).'),

                        Forms\Components\Textarea::make('descricao')
                            ->label('Descrição do Fluxo')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                // PD-1 — requerimento assinado antes da análise
                Forms\Components\Section::make('Requerimento Assinado')
                    ->description('Se ativado, o cidadão gera o requerimento em PDF (a partir do template abaixo), assina e anexa o PDF assinado — só então o processo segue. Por padrão isso acontece na abertura (1ª etapa); para exigir em outro momento, marque "Exigir requerimento assinado" na etapa do solicitante desejada (aba Etapas abaixo) — útil quando as variáveis do template são preenchidas depois da abertura.')
                    ->schema([
                        Forms\Components\Toggle::make('exige_requerimento')
                            ->label('Exigir requerimento assinado antes da análise')
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('template_requerimento')
                            ->label('Template do Requerimento')
                            ->visible(fn (Forms\Get $get) => (bool) $get('exige_requerimento'))
                            ->required(fn (Forms\Get $get) => (bool) $get('exige_requerimento'))
                            ->disableToolbarButtons(['attachFiles'])
                            ->helperText('Variáveis do solicitante: {{nome}} {{cpf}} {{rg}} {{telefone}} {{email}} {{endereco}} · do processo: {{protocolo}} {{fluxo}} {{data}} {{data_extenso}} {{municipio}} · do imóvel: {{lote}} {{quadra}} {{loteamento}} {{endereco_imovel}} {{inscricao}} · dos formulários das etapas: {{campo:slug-do-campo}} (ver lista abaixo — a variável só sai preenchida se a etapa já tiver sido respondida quando o requerimento for gerado). Trecho condicional: {{#se campo:estado-civil = Casado}} ...texto com {{campo:nome-do-conjuge}}... {{/se}} — o trecho só entra no PDF se a condição valer (operadores: =, != e contem; sem aninhar blocos). Variável desconhecida sai literal no PDF.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('placeholders_formulario')
                            ->label('Variáveis disponíveis dos formulários das etapas')
                            ->visible(fn (Forms\Get $get, ?BpmnFluxo $record) => (bool) $get('exige_requerimento') && $record !== null)
                            ->content(function (?BpmnFluxo $record) {
                                $etapas = $record?->etapas()->orderBy('ordem')->orderBy('id')->get() ?? collect();

                                $blocos = $etapas->map(function ($etapa) {
                                    $itens = collect($etapa->campos_formulario ?? [])
                                        ->filter(fn ($c) => ! in_array($c['type'] ?? '', ['arquivo', 'mapa']))
                                        ->map(function ($c) {
                                            $label = $c['data']['label_campo'] ?? 'Campo';

                                            return '<li><code>{{campo:'.e(\Illuminate\Support\Str::slug($label)).'}}</code> — '.e($label).'</li>';
                                        });

                                    if ($itens->isEmpty()) {
                                        return null;
                                    }

                                    return '<div class="mb-2"><span class="font-medium">'.e($etapa->nome)
                                        .($etapa->exige_requerimento ? ' <span class="text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-800">requerimento exigido aqui</span>' : '')
                                        .'</span><ul class="text-sm space-y-0.5 pl-4">'.$itens->implode('').'</ul></div>';
                                })->filter();

                                return new \Illuminate\Support\HtmlString($blocos->isEmpty()
                                    ? '<span class="text-gray-500 italic">As etapas do fluxo ainda não têm campos de texto/seleção.</span>'
                                    : $blocos->implode(''));
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn (?BpmnFluxo $record) => ! $record?->exige_requerimento),

                Forms\Components\Section::make('Editor Visual BPMN')
                    ->description('Desenhe o fluxo arrastando os elementos. Cada tarefa (caixa) representará uma etapa do processo digital.')
                    // Item 3 — recolhido por padrão para não atrapalhar a rolagem; abre ao clicar.
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        // O NOSSO COMPONENTE CUSTOMIZADO SENDO CHAMADO AQUI:
                        Forms\Components\ViewField::make('xml_diagrama')
                            ->hiddenLabel()
                            ->view('filament.forms.components.bpmn-modeler')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\BpmnFluxoResource\RelationManagers\EtapasRelationManager::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\ColorColumn::make('cor')
                    ->label('Cor'),

                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome do Fluxo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\ToggleColumn::make('ativo')
                    ->label('Ativo')
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip('Ativa/desativa o fluxo. Fluxos inativos não aparecem na abertura de novos processos.')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Alteração')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBpmnFluxos::route('/'),
            'create' => Pages\CreateBpmnFluxo::route('/create'),
            'edit' => Pages\EditBpmnFluxo::route('/{record}/edit'),
        ];
    }
}
