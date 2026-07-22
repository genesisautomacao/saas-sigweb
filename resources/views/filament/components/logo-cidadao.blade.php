@php
    /**
     * Logo do painel Cidadão com a marca do MUNICÍPIO (PT-1).
     * Resolve o tenant: usuário logado → 1ª prefeitura vinculada; telas de auth
     * (login/cadastro vindos do Portal) → ?prefeitura={slug} da URL.
     * Sem tenant resolvível, cai na logo padrão do SIGWEB.
     */
    $tenantBrasao = auth()->user()?->tenants?->first();

    if (! $tenantBrasao && ($slug = request()->query('prefeitura'))) {
        $tenantBrasao = \App\Models\Tenant::where('slug', $slug)->where('is_active', true)->first();
    }

    $brasaoUrl = $tenantBrasao?->getFilamentAvatarUrl();
    $ehTelaAuth = request()->routeIs('filament.cidadao.auth.*');
    $altura = $ehTelaAuth ? '90px' : '40px';
@endphp

@if ($brasaoUrl)
    <style>
        .fi-logo {
            height: auto !important;
            display: flex !important;
            justify-content: center !important;
        }
    </style>
    <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
        <img src="{{ $brasaoUrl }}" alt="Brasão — {{ $tenantBrasao->name }}"
             style="height: {{ $altura }} !important; max-height: none !important; margin: 0 auto !important; object-fit: contain;">
        @if ($ehTelaAuth)
            <span style="font-size:14px; font-weight:600;">{{ $tenantBrasao->name }}</span>
        @endif
    </div>
@else
    {{-- Fallback: logo padrão do sistema (claro/escuro) --}}
    @include('filament.components.logo')
@endif
