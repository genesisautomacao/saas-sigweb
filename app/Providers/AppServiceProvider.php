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
            // D7 (docs/Modulos_Permissoes.txt): o papel é de sistema pela FLAG
            // roles.papel_sistema, não pelo nome — renomear "Manager" não derruba nada.
            // A9: filtra pela prefeitura da sessão (SyncSpatieTenant seta o team id) —
            // Manager em A não vira Manager em B. Sem team id (console/API) mantém o
            // comportamento antigo (qualquer papel de sistema vale).
            $teamId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
            $hasSuperPower = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', get_class($user))
                ->whereIn('roles.papel_sistema', ['master', 'manager'])
                ->when($teamId, fn ($q) => $q->where(fn ($w) => $w->whereNull('roles.tenant_id')->orWhere('roles.tenant_id', $teamId)))
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

                // Cloudflare R2 (disk "midia" — bucket único de mídias pesadas:
                // panorâmicas 360 etc.). Sem a linha cadastrada, o disk fica com
                // o fallback do .env (ou inoperante) — nada quebra.
                $r2 = $settings->get('Cloudflare R2');
                if ($r2 && ! empty($r2->data['R2_ACCESS_KEY_ID'])) {
                    $d = $r2->data;

                    Config::set('filesystems.disks.midia.key', $d['R2_ACCESS_KEY_ID']);
                    Config::set('filesystems.disks.midia.secret', $d['R2_SECRET_ACCESS_KEY'] ?? null);
                    Config::set('filesystems.disks.midia.bucket', $d['R2_BUCKET'] ?? 'sigweb-midia');
                    Config::set('filesystems.disks.midia.endpoint', $d['R2_ENDPOINT'] ?? null);
                }
            }
        } catch (\Throwable $e) {
            // Banco fora do ar / migration ainda não rodada: ignora silenciosamente.
        }
    }
}
