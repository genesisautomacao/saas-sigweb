<?php

namespace App\Filament\Pages\Traits;

use App\Models\PatrimonioPublico;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait HasPatrimonioPublicoActions
{
    public ?int $patrimonioPublicoAtivoId = null;

    public function criarPatrimonioPublicoAction(): Action
    {
        return Action::make('criarPatrimonioPublico')
            ->model(PatrimonioPublico::class)
            ->modalHeading('Cadastrar Patrimônio Público')
            ->modalSubmitActionLabel('Salvar Patrimônio')
            ->modalWidth('3xl')
            ->form($this->getPatrimonioPublicoFormSchema())
            ->action(function (array $data) {
                $patrimonio = PatrimonioPublico::create([
                    'tenant_id' => $this->tenantId,
                    'name' => $data['name'],
                    'tipo_patrimonio_id' => $data['tipo_patrimonio_id'],
                    'address' => $data['address'] ?? null,
                    'description' => $data['description'] ?? null,
                    'geo' => $this->geometriaRascunho,
                ]);

                Notification::make()->title('Patrimônio Cadastrado com Sucesso!')->success()->send();

                $this->dispatch('adicionar-patrimonio_publico-mapa', [
                    'id' => $patrimonio->id,
                    'name' => $patrimonio->name,
                    'geo' => $this->geometriaRascunho,
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesPatrimonioPublicoAction(): Action
    {
        return Action::make('opcoesPatrimonioPublico')
            ->hiddenLabel()
            ->modalHeading(function () {
                $p = PatrimonioPublico::find($this->patrimonioPublicoAtivoId);

                return 'Patrimônio Público'.($p ? ' — '.$p->name : '');
            })
            ->modalDescription('Selecione a operação que deseja realizar:')
            ->modalWidth('sm')
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->form(function () {
                return [
                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('editar_ficha')
                            ->label('Ver / Editar Ficha')
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->action(fn () => $this->replaceMountedAction('editarPatrimonioPublico')),
                    ])->fullWidth(),

                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('documentos')
                            ->label('Documentos')
                            ->icon('heroicon-o-paper-clip')
                            ->color('gray')
                            ->action(fn () => $this->replaceMountedAction('documentosPatrimonioPublico')),
                    ])->fullWidth(),

                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('geometria')
                            ->label('Alterar Geometria')
                            ->icon('heroicon-o-map')
                            ->color('warning')
                            ->action(function () {
                                $this->dispatch('iniciar-edicao-geometria-patrimonio_publico', id: $this->patrimonioPublicoAtivoId);
                                $this->dispatch('fechar-modal-filament');
                            }),
                    ])->fullWidth(),

                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('excluir')
                            ->label('Excluir Patrimônio')
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(function () {
                                PatrimonioPublico::where('id', $this->patrimonioPublicoAtivoId)->delete();
                                Notification::make()->title('Patrimônio Excluído!')->success()->send();
                                $this->dispatch('remover-patrimonio_publico-mapa', ['id' => $this->patrimonioPublicoAtivoId]);
                                $this->dispatch('fechar-modal-filament');
                            }),
                    ])->fullWidth(),
                ];
            });
    }

    public function editarPatrimonioPublicoAction(): Action
    {
        return Action::make('editarPatrimonioPublico')
            ->model(PatrimonioPublico::class)
            ->hiddenLabel()
            ->modalHeading(function () {
                $p = PatrimonioPublico::find($this->patrimonioPublicoAtivoId);

                return 'Editar Patrimônio'.($p ? ' — '.$p->name : '');
            })
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $p = PatrimonioPublico::find($this->patrimonioPublicoAtivoId);
                if (! $p) {
                    return [];
                }

                return [
                    'name' => $p->name,
                    'tipo_patrimonio_id' => $p->tipo_patrimonio_id,
                    'address' => $p->address,
                    'description' => $p->description,
                ];
            })
            ->form($this->getPatrimonioPublicoFormSchema())
            ->action(function (array $data) {
                $p = PatrimonioPublico::find($this->patrimonioPublicoAtivoId);
                if ($p) {
                    $p->update([
                        'name' => $data['name'],
                        'tipo_patrimonio_id' => $data['tipo_patrimonio_id'],
                        'address' => $data['address'] ?? null,
                        'description' => $data['description'] ?? null,
                    ]);

                    Notification::make()->title('Patrimônio Atualizado!')->success()->send();

                    $this->dispatch('atualizar-label-patrimonio_publico', [
                        'id' => $p->id,
                        'name' => $p->name,
                    ]);
                }
            });
    }

    /**
     * Ver e anexar documentos digitalizados do patrimônio direto pelo mapa (item 030).
     * Espelha o DocumentosRelationManager (mesma relação polimórfica, mesmo diretório/disco).
     */
    public function documentosPatrimonioPublicoAction(): Action
    {
        return Action::make('documentosPatrimonioPublico')
            ->hiddenLabel()
            ->modalHeading(function () {
                $p = PatrimonioPublico::find($this->patrimonioPublicoAtivoId);

                return 'Documentos'.($p ? ' — '.$p->name : '');
            })
            ->modalDescription('Veja os documentos anexados e anexe novos (item 030).')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Anexar documentos')
            ->form(function () {
                $p = PatrimonioPublico::find($this->patrimonioPublicoAtivoId);
                $docs = $p ? $p->documentos()->latest()->get() : collect();

                $lista = $docs->isEmpty()
                    ? '<p style="color:#6b7280;font-size:.875rem;margin:0;">Nenhum documento anexado ainda.</p>'
                    : '<ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;">'
                        .$docs->map(fn ($d) => '<li style="display:flex;align-items:center;gap:8px;">'
                            .'<a href="'.e(asset('storage/'.$d->path)).'" target="_blank" rel="noopener" '
                            .'style="color:#2563eb;text-decoration:underline;font-size:.9rem;">📄 '.e($d->name).'</a>'
                            .'<span style="color:#9ca3af;font-size:.75rem;">'.e(optional($d->created_at)->format('d/m/Y')).'</span>'
                            .'</li>')->implode('')
                        .'</ul>';

                return [
                    \Filament\Forms\Components\Section::make('Documentos anexados')
                        ->schema([
                            \Filament\Forms\Components\Placeholder::make('lista_documentos')
                                ->hiddenLabel()
                                ->content(new \Illuminate\Support\HtmlString($lista)),
                        ]),

                    \Filament\Forms\Components\Section::make('Anexar novos documentos')
                        ->schema([
                            \Filament\Forms\Components\Repeater::make('novos_documentos')
                                ->hiddenLabel()
                                ->addActionLabel('Adicionar documento')
                                ->reorderable(false)
                                ->defaultItems(0)
                                ->schema([
                                    \Filament\Forms\Components\TextInput::make('name')
                                        ->label('Nome do Documento (Ex: Escritura, Planta, Laudo)')
                                        ->required()
                                        ->maxLength(255),
                                    \Filament\Forms\Components\FileUpload::make('path')
                                        ->label('Arquivo')
                                        ->directory('documentos/patrimonios')
                                        ->preserveFilenames()
                                        ->maxSize(10240)
                                        ->openable()
                                        ->downloadable()
                                        ->required(),
                                ]),
                        ]),
                ];
            })
            ->action(function (array $data) {
                $p = PatrimonioPublico::find($this->patrimonioPublicoAtivoId);
                if (! $p) {
                    return;
                }

                $count = 0;
                foreach ($data['novos_documentos'] ?? [] as $doc) {
                    $path = $doc['path'] ?? null;
                    if (is_array($path)) {
                        $path = $path[0] ?? null;
                    }
                    if (empty($path)) {
                        continue;
                    }
                    $p->documentos()->create([
                        'tenant_id' => $this->tenantId,
                        'name' => $doc['name'] ?? 'Documento',
                        'path' => $path,
                    ]);
                    $count++;
                }

                if ($count > 0) {
                    Notification::make()->title("{$count} documento(s) anexado(s) com sucesso!")->success()->send();
                } else {
                    Notification::make()->title('Nenhum documento novo informado.')->info()->send();
                }
            });
    }

    protected function getPatrimonioPublicoFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Section::make('Identificação')->schema([
                \Filament\Forms\Components\TextInput::make('name')
                    ->label('Nome do Patrimônio')
                    ->placeholder('Ex: Praça Matriz, Ponte Histórica...')
                    ->required()
                    ->maxLength(255),

                \Filament\Forms\Components\Select::make('tipo_patrimonio_id')
                    ->label('Tipo de Patrimônio')
                    ->relationship('tipo', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                \Filament\Forms\Components\TextInput::make('address')
                    ->label('Endereço / Localização')
                    ->maxLength(255),
            ])->columns(3),

            \Filament\Forms\Components\Textarea::make('description')
                ->label('Descrição')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }
}
