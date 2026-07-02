<?php

namespace App\Filament\Concerns;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Fluxo de cadastro "prefeitura primeiro" para as telas de registro (cidadão e prefeitura).
 *
 * Passo 1 (URL sem prefeitura): mostra apenas a seleção da prefeitura → ao continuar,
 * redireciona para a MESMA rota com `?prefeitura={slug}`.
 * Passo 2 (URL já com `?prefeitura={slug}`): mostra os dados do usuário com a prefeitura
 * fixada — assim a prefeitura tem uma URL válida e compartilhável (ex.: site oficial).
 *
 * A página que usa este trait deve montar o schema condicionalmente com
 * `prefeituraDefinida()`, `faixaPrefeituraSelecionada()` e `passoSelecaoPrefeitura()`,
 * e usar `$this->tenantSelecionadoId` no `handleRegistration`.
 */
trait HasTenantFirstRegistration
{
    /** Slug da prefeitura vinda da URL (?prefeitura=slug). */
    public ?string $prefeitura = null;

    /** Id da tenant resolvida a partir da slug (fixada no passo 2). */
    public ?int $tenantSelecionadoId = null;

    public function mount(): void
    {
        // Resolve a prefeitura da URL ANTES do parent::mount(), pois é ele que constrói
        // o formulário (getForms()) — precisa já saber se é passo 1 (seleção) ou passo 2 (dados).
        $slug = request()->query('prefeitura');
        if (filled($slug) && ($tenant = Tenant::where('slug', $slug)->first())) {
            $this->prefeitura = $tenant->slug;
            $this->tenantSelecionadoId = $tenant->id;
        }

        parent::mount();
    }

    protected function prefeituraDefinida(): bool
    {
        return $this->tenantSelecionadoId !== null;
    }

    protected function getTenantSelecionado(): ?Tenant
    {
        return $this->tenantSelecionadoId ? Tenant::find($this->tenantSelecionadoId) : null;
    }

    /**
     * URL da própria tela de registro (do painel atual), opcionalmente com a prefeitura.
     * NÃO usar url()->current() aqui: dentro de uma ação Livewire ela retorna
     * /livewire/update (POST) e quebra o redirect.
     */
    protected function urlRegistro(?string $slug = null): string
    {
        return filament()->getCurrentPanel()
            ->getRegistrationUrl($slug ? ['prefeitura' => $slug] : []);
    }

    /** Passo 1 — seleção da prefeitura. */
    protected function passoSelecaoPrefeitura(): array
    {
        return [
            Select::make('tenant_escolhido')
                ->label('Selecione sua Cidade / Prefeitura')
                ->options(Tenant::orderBy('name')->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->native(false)
                ->helperText('Escolha a prefeitura para continuar. Seus dados serão preenchidos no próximo passo.'),
        ];
    }

    /** Faixa read-only no passo 2 mostrando a prefeitura escolhida, com link para trocar. */
    protected function faixaPrefeituraSelecionada(): Placeholder
    {
        $tenant = $this->getTenantSelecionado();

        return Placeholder::make('prefeitura_atual')
            ->label('Prefeitura')
            ->content(new HtmlString(
                '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">'
                . '<span style="font-weight:600;">' . e($tenant?->name ?? '—') . '</span>'
                . '<a href="' . e($this->urlRegistro()) . '" style="font-size:12px;color:#2563eb;text-decoration:underline;">Trocar</a>'
                . '</div>'
            ));
    }

    public function register(): ?RegistrationResponse
    {
        // Passo 1: sem prefeitura na URL → valida a escolha e redireciona carregando a slug.
        if (! $this->prefeituraDefinida()) {
            $data = $this->form->getState(); // valida o Select obrigatório
            $tenant = Tenant::find($data['tenant_escolhido'] ?? null);

            if ($tenant) {
                $this->redirect($this->urlRegistro($tenant->slug));
            }

            return null;
        }

        // Passo 2: fluxo normal de cadastro (handleRegistration usa $this->tenantSelecionadoId).
        return parent::register();
    }

    public function getRegisterFormAction(): Action
    {
        return parent::getRegisterFormAction()
            ->label($this->prefeituraDefinida() ? 'Cadastrar' : 'Continuar');
    }

    public function getSubheading(): string | Htmlable | null
    {
        if ($this->prefeituraDefinida()) {
            return new HtmlString('Cadastro para <strong>' . e($this->getTenantSelecionado()?->name) . '</strong>');
        }

        return 'Primeiro, selecione a sua prefeitura.';
    }
}
