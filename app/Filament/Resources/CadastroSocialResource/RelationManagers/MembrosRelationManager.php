<?php

namespace App\Filament\Resources\CadastroSocialResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembrosRelationManager extends RelationManager
{
    protected static string $relationship = 'membros';
    protected static ?string $title = 'Membros da Família';
    protected static ?string $modelLabel = 'Membro';
    protected static ?string $pluralModelLabel = 'Membros';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('pessoa_id')
                    ->label('Cidadão (Membro)')
                    ->relationship('pessoa', 'name', fn (Builder $query) => $query->where('tenant_id', \Filament\Facades\Filament::getTenant()->id))
                    ->searchable()
                    ->preload()
                    ->required()
                    // 🛑 Regra 1: Evita o cara ser dependente em duas famílias
                    ->unique(table: 'membro_familias', column: 'pessoa_id', ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Atenção: Este cidadão já está cadastrado como dependente em outra família.',
                    ])
                    // 🛑 Regras Customizadas e Complexas
                    ->rule(function ($livewire) {
                        return function (string $attribute, $value, \Closure $fail) use ($livewire) {
                            
                            // Regra 2: Evita colocar o próprio Responsável Familiar como dependente dele mesmo
                            if ($value == $livewire->ownerRecord->pessoa_id) {
                                $fail('Você não pode adicionar o Responsável Familiar como dependente dele mesmo.');
                            }

                            // Regra 3: Evita colocar um Responsável de OUTRA família como dependente desta
                            $isRfEmOutraFamilia = \App\Models\CadastroSocial::where('pessoa_id', $value)->exists();
                            if ($isRfEmOutraFamilia) {
                                $fail('Atenção: Esta pessoa já é o Responsável Familiar de uma família. Ela não pode ser dependente.');
                            }
                        };
                    })
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->label('Nome Completo')->required(),
                        Forms\Components\TextInput::make('cpf')->label('CPF')->mask('999.999.999-99'),
                        Forms\Components\Hidden::make('type')->default('fisica'),
                        Forms\Components\Hidden::make('tenant_id')->default(fn () => \Filament\Facades\Filament::getTenant()->id),
                        Forms\Components\Hidden::make('code')->default(fn () => (string) \Illuminate\Support\Str::uuid()),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Select::make('parentesco')
                    ->label('Grau de Parentesco com o RF')
                    ->options([
                        'conjuge' => 'Cônjuge / Companheiro(a)',
                        'filho_a' => 'Filho(a)',
                        'enteado_a' => 'Enteado(a)',
                        'pai_mae' => 'Pai / Mãe',
                        'avo_a' => 'Avô / Avó',
                        'neto_a' => 'Neto(a)',
                        'outro' => 'Outro Parentesco',
                    ])
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('representante_familiar')
                    ->label('Representante familiar')
                    ->helperText('Membro que representa a família junto à assistência social (item 095).')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pessoa.name')
            ->columns([
                Tables\Columns\TextColumn::make('pessoa.name')
                    ->label('Nome do Membro')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('pessoa.cpf')
                    ->label('CPF')
                    ->searchable(),

                Tables\Columns\TextColumn::make('parentesco')
                    ->label('Parentesco')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'conjuge' => 'Cônjuge',
                        'filho_a' => 'Filho(a)',
                        'enteado_a' => 'Enteado(a)',
                        'pai_mae' => 'Pai / Mãe',
                        'avo_a' => 'Avô / Avó',
                        'neto_a' => 'Neto(a)',
                        'outro' => 'Outro',
                        default => $state,
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('representante_familiar')
                    ->label('Representante')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar Membro')
                    ->icon('heroicon-o-user-plus'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}