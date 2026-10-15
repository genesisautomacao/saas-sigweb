<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TabelaArquivosGerados;
use App\Filament\Resources\ArquivoGeradoResource\Pages;
use App\Models\ArquivoGerado;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * R84-4 — "Arquivos Gerados" da prefeitura: cada usuário vê os seus; o Manager vê
 * todos da prefeitura (escopo em ArquivoGerado::scopeVisiveisNoApp).
 */
class ArquivoGeradoResource extends Resource
{
    use TabelaArquivosGerados;

    protected static ?string $model = ArquivoGerado::class;

    /** Escopo próprio (autor/Manager) — não o da tenancy do Filament. */
    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Arquivos Gerados';

    protected static ?string $modelLabel = 'Arquivo Gerado';

    protected static ?string $pluralModelLabel = 'Arquivos Gerados';

    protected static ?string $slug = 'arquivos-gerados';

    protected static ?int $navigationSort = 90;

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        if (! $tenant || ! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->with('user')->visiveisNoApp($user, $tenant->id);
    }

    public static function table(Table $table): Table
    {
        return static::tabelaArquivos($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArquivosGerados::route('/'),
        ];
    }
}
