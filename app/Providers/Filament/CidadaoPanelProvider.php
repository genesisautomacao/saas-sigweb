<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CidadaoPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('cidadao')
            ->path('cidadao') // A rota será seudominio.com/cidadao
            ->login() // Terá ecrã de login
            ->registration(\App\Filament\Cidadao\Pages\Auth\RegisterCidadao::class)
            ->passwordReset() // Recuperação de senha
            ->profile() // Editar o próprio perfil (nome, email, etc)
            ->colors([
                'primary' => Color::Blue, // Uma cor diferente do painel admin para fácil distinção
            ])

            // 2. ATIVA O SININHO DE NOTIFICAÇÕES NA NAVBAR
            ->databaseNotifications()

            // 3. FAVICON (O ícone da aba do navegador)
            ->favicon(asset('assets/images/favicon.png'))

            // 4. LOGO DA NAVBAR (Substitui o texto "Laravel")
            ->brandLogo(fn() => view('filament.components.logo'))
            
            ->discoverResources(in: app_path('Filament/Cidadao/Resources'), for: 'App\\Filament\\Cidadao\\Resources')
            ->discoverPages(in: app_path('Filament/Cidadao/Pages'), for: 'App\\Filament\\Cidadao\\Pages')
            ->pages([
                Pages\Dashboard::class, // Depois trocaremos por um Dashboard costumizado
            ])
            ->discoverWidgets(in: app_path('Filament/Cidadao/Widgets'), for: 'App\\Filament\\Cidadao\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}