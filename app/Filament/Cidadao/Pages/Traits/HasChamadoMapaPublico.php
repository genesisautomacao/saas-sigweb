<?php

namespace App\Filament\Cidadao\Pages\Traits;

use App\Models\ChamadoMapa;
use App\Services\ChamadosMapa\ChamadoMapaService;
use App\Services\Fiscal\ProprietarioService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * R80-1 — "Fale conosco" do mapa público: o cidadão (sem login) abre um chamado com
 * foto opcional e, se quiser, marca o local no mapa (clique ou GPS).
 *
 * Fluxo com local: o form valida e guarda os dados em `$chamadoPendente` (#[Locked] —
 * o navegador não consegue injetar dados que pulem a validação) → o engine entra no modo
 * "marcar ponto" → `confirmarPontoChamado(lon, lat)` grava. Sem local: grava na hora.
 *
 * Anti-spam (decisão 2026-10-08): campo-armadilha + tempo mínimo + 10/h por IP;
 * Cloudflare Turnstile só se o ApiSetting "Cloudflare Turnstile" existir.
 */
trait HasChamadoMapaPublico
{
    #[Locked]
    public ?array $chamadoPendente = null;

    #[Locked]
    public ?int $faleConoscoAbertoEm = null;

    /** Onde a foto do envio atual ficou (midia | local) — definido no upload. */
    #[Locked]
    public ?string $fotoDiscoUpload = null;

    public function chamadoMapaAtivo(): bool
    {
        return in_array('chamados_mapa', $this->modulos, true) && $this->tenantId > 0;
    }

    public function faleConoscoAction(): Action
    {
        return Action::make('faleConosco')
            ->modalHeading('Fale com a prefeitura')
            ->modalDescription('Envie uma solicitação, sugestão ou reclamação. Você recebe um número de protocolo para acompanhar.')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Enviar chamado')
            ->visible(fn () => $this->chamadoMapaAtivo())
            ->mountUsing(function (Forms\Form $form) {
                $this->faleConoscoAbertoEm = time();
                $form->fill(['mostrar_no_mapa' => false]);
            })
            ->form(fn () => $this->camposFaleConosco())
            ->action(fn (array $data, Action $action) => $this->receberFaleConosco($data, $action));
    }

    private function camposFaleConosco(): array
    {
        $campos = [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('nome')->label('Seu nome')->required()->maxLength(150)->columnSpanFull(),
                Forms\Components\TextInput::make('celular')->label('Celular')->tel()
                    ->mask('(99) 99999-9999')->maxLength(20)
                    ->requiredWithout('email')
                    ->validationMessages(['required_without' => 'Informe o celular ou o e-mail para a prefeitura conseguir responder.']),
                Forms\Components\TextInput::make('email')->label('E-mail')->email()->maxLength(150)
                    ->requiredWithout('celular')
                    ->validationMessages(['required_without' => 'Informe o e-mail ou o celular para a prefeitura conseguir responder.'])
                    ->helperText('Com e-mail você recebe as atualizações do chamado.'),
                Forms\Components\TextInput::make('cpf')->label('CPF (opcional)')
                    ->mask('999.999.999-99')
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                        if (filled($value) && ! ProprietarioService::cpfValido($value)) {
                            $fail('CPF inválido.');
                        }
                    }),
                Forms\Components\Select::make('assunto')->label('Assunto')->required()->native(false)
                    ->options(ChamadoMapa::ASSUNTOS),
            ]),
            Forms\Components\TextInput::make('titulo')->label('Título')->required()->maxLength(150),
            Forms\Components\Textarea::make('descricao')->label('Descrição do chamado')->required()->rows(4)->maxLength(3000),
            Forms\Components\FileUpload::make('foto')->label('Foto (opcional)')
                ->image()->maxSize(10240)
                ->disk('local')
                // Bucket privado "midia" primeiro; recusou (credencial/rede) ⇒ disco local
                // privado. Sem isto o Filament gravava o caminho de um arquivo que não subiu.
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => $this->guardarFotoChamado($file)),
            Forms\Components\Toggle::make('mostrar_no_mapa')->label('Mostrar no mapa')
                ->helperText('Depois de enviar, você marca no mapa o local do chamado (ou usa a localização do celular).'),
            // Campo-armadilha: invisível para pessoas; robô que preenche tudo é descartado.
            Forms\Components\TextInput::make('site')->label('Site')->autocomplete('off')
                ->extraFieldWrapperAttributes(['style' => 'position:absolute; left:-10000px; width:1px; height:1px; overflow:hidden;', 'aria-hidden' => 'true'])
                ->extraInputAttributes(['tabindex' => '-1']),
        ];

        if (ChamadoMapaService::turnstileAtivo()) {
            $campos[] = Forms\Components\Hidden::make('turnstile_token');
            $campos[] = Forms\Components\View::make('filament.cidadao.components.turnstile')
                ->viewData(['siteKey' => ChamadoMapaService::turnstileSiteKey()]);
        }

        return $campos;
    }

    private function receberFaleConosco(array $data, Action $action): void
    {
        if (! $this->chamadoMapaAtivo()) {
            return;
        }

        // Robô preencheu o campo-armadilha: finge sucesso e descarta.
        if (filled($data['site'] ?? null)) {
            $this->apagarFoto($data['foto'] ?? null, $this->fotoDiscoUpload);
            Notification::make()->title('Chamado enviado!')->success()->send();

            return;
        }

        // Pessoa não preenche em menos de 3 s. Modal continua aberto (nada se perde).
        if ($this->faleConoscoAbertoEm && (time() - $this->faleConoscoAbertoEm) < ChamadoMapaService::TEMPO_MINIMO) {
            Notification::make()->title('Envio muito rápido')->body('Confira os dados e envie novamente.')->warning()->send();
            $action->halt();
        }

        if (RateLimiter::tooManyAttempts($this->chaveLimite(), ChamadoMapaService::LIMITE_POR_HORA)) {
            $this->apagarFoto($data['foto'] ?? null, $this->fotoDiscoUpload);
            Notification::make()->title('Muitos envios')
                ->body('Você já enviou vários chamados na última hora. Tente novamente mais tarde.')
                ->danger()->send();

            return;
        }

        if (! ChamadoMapaService::turnstileValido($data['turnstile_token'] ?? null, request()->ip())) {
            Notification::make()->title('Verificação de segurança')->body('Confirme que você não é um robô e envie de novo.')->danger()->send();
            $action->halt();
        }

        $dados = collect($data)->only(['nome', 'celular', 'email', 'cpf', 'assunto', 'titulo', 'descricao', 'foto'])->all();
        $dados['foto_disco'] = filled($dados['foto'] ?? null) ? ($this->fotoDiscoUpload ?? 'local') : null;

        if (! empty($data['mostrar_no_mapa'])) {
            $this->chamadoPendente = $dados;
            $this->dispatch('chamado-mapa-escolher-ponto');

            return;
        }

        $this->gravarChamado($dados, null, null);
    }

    /** Engine: o cidadão confirmou o ponto (clique no mapa ou GPS). */
    #[On('confirmarPontoChamado')]
    public function confirmarPontoChamado($lon = null, $lat = null): void
    {
        if (! $this->chamadoPendente || ! $this->chamadoMapaAtivo()) {
            return;
        }

        $lon = is_numeric($lon) ? (float) $lon : null;
        $lat = is_numeric($lat) ? (float) $lat : null;
        if ($lon === null || $lat === null || abs($lon) > 180 || abs($lat) > 90) {
            Notification::make()->title('Local inválido')->body('Marque o ponto novamente no mapa.')->warning()->send();
            $this->dispatch('chamado-mapa-escolher-ponto');

            return;
        }

        $this->gravarChamado($this->chamadoPendente, $lon, $lat);
    }

    /** Engine: "Enviar sem marcar o local". */
    #[On('enviarChamadoSemPonto')]
    public function enviarChamadoSemPonto(): void
    {
        if ($this->chamadoPendente && $this->chamadoMapaAtivo()) {
            $this->gravarChamado($this->chamadoPendente, null, null);
        }
    }

    /** Engine: o cidadão desistiu na etapa do ponto. */
    #[On('cancelarChamadoPendente')]
    public function cancelarChamadoPendente(): void
    {
        $this->apagarFoto($this->chamadoPendente['foto'] ?? null, $this->chamadoPendente['foto_disco'] ?? null);
        $this->chamadoPendente = null;
        $this->dispatch('chamado-mapa-concluido');
        Notification::make()->title('Chamado cancelado')->body('Nada foi enviado.')->send();
    }

    private function gravarChamado(array $dados, ?float $lon, ?float $lat): void
    {
        if (RateLimiter::tooManyAttempts($this->chaveLimite(), ChamadoMapaService::LIMITE_POR_HORA)) {
            $this->apagarFoto($dados['foto'] ?? null, $dados['foto_disco'] ?? null);
            $this->chamadoPendente = null;
            $this->dispatch('chamado-mapa-concluido');
            Notification::make()->title('Muitos envios')
                ->body('Você já enviou vários chamados na última hora. Tente novamente mais tarde.')
                ->danger()->send();

            return;
        }

        $chamado = app(ChamadoMapaService::class)->registrar(
            $this->tenantId, $dados, $lon, $lat, request()->ip(), request()->userAgent(),
        );
        RateLimiter::hit($this->chaveLimite(), 3600);

        $this->chamadoPendente = null;
        $this->faleConoscoAbertoEm = null;
        $this->fotoDiscoUpload = null;
        $this->dispatch('chamado-mapa-concluido');

        Notification::make()
            ->title('Chamado enviado! Protocolo '.$chamado->protocolo)
            ->body(filled($chamado->email)
                ? 'Guarde este número. Você receberá as atualizações no e-mail '.$chamado->email.'.'
                : 'Guarde este número para acompanhar o seu chamado com a prefeitura.')
            ->success()
            ->persistent()
            ->send();
    }

    private function guardarFotoChamado(TemporaryUploadedFile $file): ?string
    {
        $dir = $this->tenantSlug.'/chamados_mapa';
        $nome = Str::ulid().'.'.(strtolower($file->getClientOriginalExtension()) ?: 'jpg');

        if (ChamadoMapa::discoFotoPadrao() === 'midia') {
            try {
                if (Storage::disk('midia')->putFileAs($dir, $file, $nome)) {
                    $this->fotoDiscoUpload = 'midia';

                    return $dir.'/'.$nome;
                }
            } catch (\Throwable $e) {
                // cai no disco local abaixo
            }
            Log::warning("[ChamadoMapa] bucket midia recusou a foto de {$this->tenantSlug}; gravada no disco local.");
        }

        if (Storage::disk('local')->putFileAs($dir, $file, $nome)) {
            $this->fotoDiscoUpload = 'local';

            return $dir.'/'.$nome;
        }

        $this->fotoDiscoUpload = null;

        return null;
    }

    private function chaveLimite(): string
    {
        return 'chamado-mapa:'.$this->tenantId.':'.request()->ip();
    }

    private function apagarFoto(?string $caminho, ?string $disco = null): void
    {
        if (blank($caminho)) {
            return;
        }

        try {
            Storage::disk($disco ?: 'local')->delete($caminho);
        } catch (\Throwable) {
            // best-effort
        }
    }
}
