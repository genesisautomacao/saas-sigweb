<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ArquivoGeradoAdminResource\Pages;
use App\Filament\Concerns\TabelaArquivosGerados;
use App\Models\ArquivoGerado;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * R84-4 — "Arquivos Gerados" do /admin (exportações pedidas no painel do SaaS):
 * Master vê todos; Operador só os seus.
 */
class ArquivoGeradoAdminResource extends Resource
{
    use TabelaArquivosGerados;

    protected static ?string $model = ArquivoGerado::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Configurações Globais';

    protected static ?string $navigationLabel = 'Arquivos Gerados';

    protected static ?string $modelLabel = 'Arquivo Gerado';

    protected static ?string $pluralModelLabel = 'Arquivos Gerados';

    protected static ?string $slug = 'arquivos-gerados';

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->isAdminSaas() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        return $user
            ? parent::getEloquentQuery()->with(['user', 'tenant'])->visiveisNoAdmin($user)
            : parent::getEloquentQuery()->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return static::tabelaArquivos($table)
            ->pushColumns([
                Tables\Columns\TextColumn::make('tenant.name')->label('Prefeitura')->toggleable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArquivosGeradosAdmin::route('/'),
        ];
    }
}
