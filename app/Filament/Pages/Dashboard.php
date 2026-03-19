<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\MaxWidth;

class Dashboard extends BaseDashboard
{
    // Sumindo com o título padrão "Painel de Controle"
    protected ?string $heading = ' '; 
    
    // O jeito correto no Filament v3 (sem a palavra static)
    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }
}