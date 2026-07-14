<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\ApiSetting;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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

        // Injeção dinâmica das credenciais do Resend (e-mail transacional).
        // As credenciais são globais (super usuário → ApiSettingResource, name = "Resend") e
        // valem para TODAS as tenants. Sem essa linha configurada, o mailer default (log/env)
        // continua valendo — nada quebra.
        try {
            if (Schema::hasTable('api_settings')) {
                $resend = ApiSetting::query()->where('name', 'Resend')->first();

                if ($resend && !empty($resend->data)) {
                    $data = $resend->data;

                    Config::set('mail.default', 'resend');
                    Config::set('mail.mailers.resend.api_key', $data['RESEND_API_KEY'] ?? env('RESEND_API_KEY'));
                    // Chave exigida pelo pacote resend/resend-laravel.
                    Config::set('resend.api_key', $data['RESEND_API_KEY'] ?? env('RESEND_API_KEY'));

                    Config::set('mail.from.address', $data['MAIL_FROM_ADDRESS'] ?? env('MAIL_FROM_ADDRESS'));
                    Config::set('mail.from.name', $data['MAIL_FROM_NAME'] ?? env('MAIL_FROM_NAME'));
                }
            }
        } catch (\Throwable $e) {
            // Banco fora do ar / migration ainda não rodada: ignora silenciosamente.
        }
    }
}