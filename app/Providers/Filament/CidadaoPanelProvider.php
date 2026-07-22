<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AuthenticateCidadaoComMapaPublico;
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
            // PT-1 — login sem o link "registre-se" (o Portal /portal/{slug} é a porta de entrada;
            // o cadastro continua ativo em /cidadao/register?prefeitura={slug})
            ->login(\App\Filament\Cidadao\Pages\Auth\LoginCidadao::class)
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

            // 4. LOGO — brasão do MUNICÍPIO (usuário logado ou ?prefeitura= na URL de
            //    login/cadastro vindas do Portal); fallback = logo padrão do SIGWEB (PT-1)
            ->brandLogo(fn () => view('filament.components.logo-cidadao'))

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
                AuthenticateCidadaoComMapaPublico::class,
            ]);
        // PT-1 — removidos os render hooks "Acessar mapa sem cadastro" do login/registro:
        // eles levavam ao seletor de município (/mapa-publico), o que confunde agora que o
        // Portal (/portal/{slug}) já entrega o mapa do município certo sem cadastro.
    }
}
