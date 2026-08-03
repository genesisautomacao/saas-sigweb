<?php

namespace App\Providers;

use App\Models\ApiSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // "Esqueci minha senha" em PT-BR, com a marca do município e envio SÍNCRONO.
        // O Filament resolve a notification via container (RequestPasswordReset::request()
        // faz app(ResetPassword::class, ['token' => ...])) — este bind troca a original
        // (ShouldQueue — presa na fila sem worker) pela nossa versão inline via Resend.
        $this->app->bind(
            \Filament\Notifications\Auth\ResetPassword::class,
            \App\Notifications\ResetSenhaNotification::class,
        );
    }

    public function boot(): void
    {
        // O Bypass Definitivo (God Mode)
        Gate::before(function ($user, $ability) {

            // Fazemos uma consulta direta (RAW) no banco de dados.
            // Isso ignora completamente o cache do Spatie e a perda de contexto do Livewire.
            $hasSuperPower = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', get_class($user))
                ->whereIn('roles.name', ['Master', 'Manager'])
                ->exists();

            if ($hasSuperPower) {
                return true;
            }

            return null;
        });

        \Filament\Support\Facades\FilamentIcon::register([
            'panels::sidebar.collapse-button' => 'heroicon-o-bars-3',
            'panels::sidebar.expand-button' => 'heroicon-o-bars-3',
        ]);

        // Injeção dinâmica das credenciais de APIs de terceiros (ApiSettingResource, /admin).
        // Todas as linhas de api_settings são carregadas numa consulta só (com cache — o
        // model invalida no saved/deleted) e injetadas em config(). O .env é o fallback.
        // Sem linha cadastrada, nada muda — e NENHUM runtime deve chamar env() direto:
        // com config:cache ativo, env() fora dos arquivos de config retorna null (E1.5).
        try {
            if (Schema::hasTable('api_settings')) {
                $settings = \Illuminate\Support\Facades\Cache::rememberForever(
                    'api_settings.all',
                    fn () => ApiSetting::query()->get()->keyBy('name')
                );

                // Resend (e-mail transacional) — vale para TODAS as tenants.
                $resend = $settings->get('Resend');
                if ($resend && ! empty($resend->data)) {
                    $data = $resend->data;

                    Config::set('mail.default', 'resend');
                    Config::set('mail.mailers.resend.api_key', $data['RESEND_API_KEY'] ?? env('RESEND_API_KEY'));
                    // Chave exigida pelo pacote resend/resend-laravel.
                    Config::set('resend.api_key', $data['RESEND_API_KEY'] ?? env('RESEND_API_KEY'));

                    Config::set('mail.from.address', $data['MAIL_FROM_ADDRESS'] ?? env('MAIL_FROM_ADDRESS'));
                    Config::set('mail.from.name', $data['MAIL_FROM_NAME'] ?? env('MAIL_FROM_NAME'));
                }

                // Azure Maps (basemaps Road/Satélite do mapa — item 1-4 da PoC Tangará).
                $azure = $settings->get('Azure Maps');
                if ($azure && ! empty($azure->data['AZURE_MAPS_KEY'])) {
                    Config::set('services.azure_maps.key', $azure->data['AZURE_MAPS_KEY']);
                }

                // Google Maps (Street View / visualizador 360 + perfil altimétrico).
                $google = $settings->get('Google Maps');
                if ($google && ! empty($google->data['GOOGLE_MAPS_API_KEY'])) {
                    Config::set('services.google_maps.key', $google->data['GOOGLE_MAPS_API_KEY']);
                }
            }
        } catch (\Throwable $e) {
            // Banco fora do ar / migration ainda não rodada: ignora silenciosamente.
        }
    }
}
