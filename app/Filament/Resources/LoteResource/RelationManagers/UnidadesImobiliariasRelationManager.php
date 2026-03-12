<?php

namespace App\Filament\Resources\LoteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\File;
use Filament\Notifications\Notification;
use App\Models\Pessoa;

class UnidadesImobiliariasRelationManager extends RelationManager
{
    protected static string $relationship = 'unidadesImobiliarias';
    protected static ?string $title = 'Unidades Imobiliárias (Cadastros Fiscais)';
    protected static ?string $icon = 'heroicon-o-document-currency-dollar';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('codigo_imovel_tributario')
                    ->label('Código Imóvel (Tributário)'),
                Forms\Components\TextInput::make('inscricao_imobiliaria')
                    ->label('Inscrição Imobiliária (BIC)'),
                Forms\Components\Select::make('proprietario_id')
                    ->label('Proprietário Principal')
                    ->options(fn() => Pessoa::pluck('name', 'id'))
                    ->searchable(),
                
                // 🛑 NOVO: Campo de leitura do JSON
                Forms\Components\Textarea::make('dados_tributarios')
                    ->label('Dados Sincronizados (API Prefeitura)')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Ainda não sincronizado.')
                    ->disabled() // Impede a edição manual do JSON
                    ->rows(15)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('codigo_imovel_tributario')
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID'),
                Tables\Columns\TextColumn::make('codigo_imovel_tributario')->label('Cód Tributário')->weight('bold'),
                Tables\Columns\TextColumn::make('inscricao_imobiliaria')->label('Inscrição Imobiliária'),
                
                // Exibe o nome do JSON de forma inteligente, mesmo se não tiver proprietário_id linkado no banco
                Tables\Columns\TextColumn::make('dados_tributarios.proprietario_name')
                    ->label('Contribuinte (API Prefeitura)')
                    ->default('Não Sincronizado'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nova Unidade'),
            ])
            ->actions([
                // 🛑 AÇÃO MÁGICA: Sincronizar com a "API" (JSON MOCK)
                // 🛑 AÇÃO MÁGICA EM 1 CLIQUE
                Tables\Actions\Action::make('sincronizar_api')
                    ->label('Sincronizar')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sincronizar com a Prefeitura')
                    ->modalDescription(fn ($record) => "Deseja buscar os dados fiscais do imóvel de código: {$record->codigo_imovel_tributario}?")
                    ->modalSubmitActionLabel('Sim, buscar dados')
                    ->action(function ($record, \App\Services\ApiTools\IntegraPrefeituraService $apiService) {
                        
                        if (!$record->codigo_imovel_tributario) {
                            Notification::make()->title('Código Ausente')->body('Preencha o Código Imóvel Tributário antes de sincronizar.')->warning()->send();
                            return;
                        }

                        try {
                            // O Filament injeta o Service automaticamente! Chamamos apenas a função:
                            $dadosPrefeitura = $apiService->buscarImovelPorCodigo($record->codigo_imovel_tributario);

                            if ($dadosPrefeitura) {
                                $record->update([
                                    'inscricao_imobiliaria' => $dadosPrefeitura['inscricao_imobiliaria'],
                                    'dados_tributarios' => $dadosPrefeitura 
                                ]);
                                Notification::make()->title('Sincronizado com Sucesso!')->success()->send();
                            } else {
                                Notification::make()->title('Não Encontrado')->body('O código '.$record->codigo_imovel_tributario.' não foi localizado na prefeitura.')->danger()->send();
                            }
                            
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro na Integração')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    // 🛑 NOVA AÇÃO EM MASSA (BULK ACTION)
                    Tables\Actions\BulkAction::make('sincronizar_selecionados')
                        ->label('Sincronizar Selecionados')
                        ->icon('heroicon-o-cloud-arrow-down')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, \App\Services\ApiTools\IntegraPrefeituraService $apiService) {
                            $sucesso = 0;
                            $falha = 0;

                            foreach ($records as $record) {
                                if (!$record->codigo_imovel_tributario) {
                                    $falha++;
                                    continue;
                                }

                                try {
                                    $dados = $apiService->buscarImovelPorCodigo($record->codigo_imovel_tributario);
                                    if ($dados) {
                                        $record->update([
                                            'inscricao_imobiliaria' => $dados['inscricao_imobiliaria'],
                                            'dados_tributarios' => $dados 
                                        ]);
                                        $sucesso++;
                                    } else {
                                        $falha++;
                                    }
                                } catch (\Exception $e) {
                                    $falha++;
                                }
                            }

                            Notification::make()
                                ->title('Sincronização em Massa Concluída')
                                ->body("{$sucesso} atualizados com sucesso. {$falha} falharam ou não encontrados.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}