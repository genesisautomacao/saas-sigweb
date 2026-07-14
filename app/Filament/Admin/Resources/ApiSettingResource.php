<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ApiSettingResource\Pages;
use App\Models\ApiSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Configurações globais de APIs de terceiros (super usuário).
 *
 * Cada registro é um par nome → credenciais (KeyValue). O AppServiceProvider lê estes
 * valores no boot() e injeta a config correspondente para TODAS as tenants.
 *
 * Ex.: name = "Resend" · data = {
 *   RESEND_API_KEY: "re_xxx", MAIL_FROM_ADDRESS: "nao-responda@lider...", MAIL_FROM_NAME: "SIG WEB"
 * }
 */
class ApiSettingResource extends Resource
{
    protected static ?string $model = ApiSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $modelLabel = 'Configuração de API';
    protected static ?string $pluralModelLabel = 'Configurações de APIs';
    protected static ?string $navigationGroup = 'Configurações Globais';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalhes da Integração')
                    ->description('Credenciais globais usadas por todas as prefeituras. Para o e-mail transacional (Resend) crie um registro com o nome exato "Resend" e as chaves RESEND_API_KEY, MAIL_FROM_ADDRESS e MAIL_FROM_NAME.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome da API')
                            ->placeholder('Ex: Resend, AWS S3, WhatsApp')
                            ->helperText('Para ativar o disparo de e-mails, use exatamente "Resend".')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        // KeyValue = construtor de chaves e valores (mesmo padrão da base Gatefyx).
                        Forms\Components\KeyValue::make('data')
                            ->label('Credenciais / Variáveis')
                            ->keyLabel('Nome do Campo (ex: RESEND_API_KEY)')
                            ->valueLabel('Valor (ex: re_xxxxxxxx)')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome da API')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Atualização')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiSettings::route('/'),
            'create' => Pages\CreateApiSetting::route('/create'),
            'edit' => Pages\EditApiSetting::route('/{record}/edit'),
        ];
    }
}
