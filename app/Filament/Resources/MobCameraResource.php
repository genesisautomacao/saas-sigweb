<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\TemCoordenadaMob;
use App\Filament\Resources\MobCameraResource\Pages;
use App\Models\MobCamera;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Câmeras de monitoramento em tempo real (docs/piuma.txt, Onda 5). */
class MobCameraResource extends Resource
{
    use HasTenantModule;
    use TemCoordenadaMob;

    protected static ?string $tenantModule = 'mob_infra';

    protected static ?string $model = MobCamera::class;

    protected static ?string $tenantRelationshipName = 'mobCameras';

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationGroup = 'Mobilidade Urbana';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'Câmera de Monitoramento';

    protected static ?string $pluralModelLabel = 'Monitoramento em Tempo Real';

    /** Texto de ajuda do campo URL conforme o tipo da fonte (usado também no modal do mapa). */
    public static function ajudaUrl(?string $tipo): string
    {
        return match ($tipo) {
            'youtube' => 'Link do vídeo/transmissão ao vivo do YouTube (watch, youtu.be ou live).',
            'hls' => 'Endereço do stream .m3u8 (ex.: MediaMTX / servidor de mídia da prefeitura).',
            'imagem' => 'URL da imagem JPEG que a câmera atualiza; o player recarrega a cada 5 s.',
            default => 'Endereço do player embutível (iframe), ex.: FullCam ou o provedor da prefeitura.',
        };
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome / local')->required()->maxLength(255)->columnSpan(2),
            Forms\Components\Toggle::make('ativo')->label('Ativa')->default(true)->inline(false),
            Forms\Components\Select::make('tipo')
                ->label('Tipo de fonte')
                ->options(MobCamera::TIPOS)
                ->default('embed')
                ->required()
                ->live(),
            Forms\Components\TextInput::make('provedor')->label('Provedor / operadora')->maxLength(100),
            Forms\Components\TextInput::make('azimute_visada')
                ->label('Azimute da visada (graus)')
                ->numeric()->minValue(0)->maxValue(360)
                ->helperText('Para onde a câmera aponta (0 = norte). Desenha o cone no mapa.'),
            Forms\Components\TextInput::make('url')
                ->label('URL do vídeo')
                ->required()->maxLength(2000)
                ->helperText(fn (Get $get) => static::ajudaUrl($get('tipo')))
                ->columnSpanFull(),
            Forms\Components\TextInput::make('url_snapshot')->label('URL de miniatura (opcional)')->maxLength(2000)->columnSpanFull(),
            Forms\Components\Textarea::make('descricao')->label('Descrição')->rows(2)->columnSpanFull(),
            static::campoCoordenada('mob_cameras'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::comCoordenada($query, 'mob_cameras'))
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('nome')->label('Nome / local')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Fonte')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'youtube' => 'YouTube',
                        'hls' => 'HLS',
                        'imagem' => 'Snapshot',
                        default => 'Embed',
                    }),
                Tables\Columns\TextColumn::make('provedor')->label('Provedor')->placeholder('-'),
                Tables\Columns\IconColumn::make('ativo')->label('Ativa')->boolean(),
                static::colunaCoordenada(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->label('Fonte')->options(MobCamera::TIPOS),
                Tables\Filters\TernaryFilter::make('ativo')->label('Ativa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('sequential_id');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMobCameras::route('/')];
    }
}
