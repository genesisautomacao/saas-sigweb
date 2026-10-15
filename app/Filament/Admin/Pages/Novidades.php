<?php

namespace App\Filament\Admin\Pages;

use App\Support\Novidades as NovidadesRelease;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * /admin → Novidades: as páginas de novidades de cada release (public/novidades/),
 * da mais nova para a mais antiga. Lista montada pelos metadados de cada página.
 */
class Novidades extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Novidades';

    protected static ?string $title = 'Novidades das releases';

    protected static ?string $slug = 'novidades';

    protected static ?int $navigationSort = -1;

    protected static string $view = 'filament.admin.pages.novidades';

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->isAdminSaas() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'O que entrou em cada release do SIGWEB. "Visualizar" abre a página para mandar à equipe.';
    }

    protected function getViewData(): array
    {
        return ['releases' => NovidadesRelease::lista()];
    }
}
