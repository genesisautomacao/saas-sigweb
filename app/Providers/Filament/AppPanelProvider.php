<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SyncSpatieTenant;
use App\Models\Tenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
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
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;

use Filament\Navigation\NavigationItem;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->passwordReset()
            ->profile()
            ->colors([
                'primary' => Color::Indigo,
            ])

            //configs adicionais do grupo
            /* ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Configurações')
                    ->collapsed(),
            ]) */

            /* Link externo na sidebar */
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('Suporte Técnico')
                    ->url('https://api.whatsapp.com/send?phone=5516982281632', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->group('Ajuda')
                    ->sort(10),
            ])

            // 🛑 GATILHO PARA A BARRA DE BUSCA NO MENU LATERAL
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn () => view('filament.components.sidebar-search')
            )

            // 1. SIDEBAR RETRÁTIL (Cria o ícone do menu hamburger e encolhe a barra)
            ->sidebarCollapsibleOnDesktop()

            // 2. ATIVA O SININHO DE NOTIFICAÇÕES NA NAVBAR
            ->databaseNotifications()

            // 3. FAVICON (O ícone da aba do navegador)
            ->favicon(asset('assets/images/favicon.png'))

            // 4. LOGO DA NAVBAR (Substitui o texto "Laravel")
            ->brandLogo(fn() => view('filament.components.logo'))

            //->brandLogo(asset('assets/images/logo.png'))
            //->darkModeBrandLogo(asset('assets/images/logo-light.png'))
            //->brandLogoHeight('2.5rem') // Ajuste a altura para não ficar gigante


            ->tenant(Tenant::class, slugAttribute: 'slug')
            // --- CORREÇÃO AQUI: Acionando o Middleware do Spatie a cada requisição ---
            // --- A MÁGICA ESTÁ NO isPersistent: true ---
            ->tenantMiddleware([
                SyncSpatieTenant::class,
            ], isPersistent: true)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')

            ->pages([
                \App\Filament\Pages\Dashboard::class, // <-- Agora ele usa o seu Dashboard customizado!
            ])
            
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                //Widgets\AccountWidget::class,
                //Widgets\FilamentInfoWidget::class,
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