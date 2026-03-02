@php
    $isLogin = request()->routeIs('filament.app.auth.login');

    // CONTROLE DE TAMANHO: Altere os números abaixo conforme o seu gosto!
    $alturaLogin = '90px'; // Tente 120px ou 150px se quiser ainda maior
    $alturaNavbar = '40px'; // Tamanho ideal para a barra superior
@endphp

{{-- CSS Puro para garantir que a troca de temas funcione 100% --}}
<style>
    .logo-tema-claro {
        display: block;
    }

    .logo-tema-escuro {
        display: none;
    }

    /* Quando o Filament ativar o modo Escuro na página, ele inverte as logos */
    :is(.dark .logo-tema-claro) {
        display: none !important;
    }

    :is(.dark .logo-tema-escuro) {
        display: block !important;
    }
</style>

@if($isLogin)

    {{-- ========================================== --}}
    {{-- LOGOS DA PÁGINA DE LOGIN --}}
    {{-- ========================================== --}}

    <style>
        /* Destranca a altura da caixa original do Filament e empurra o texto para baixo */
        .fi-logo {
            height: auto !important;
            margin-bottom: 0 !important;
            /* Respiro entre a logo e o texto */
            display: flex !important;
            justify-content: center !important;
        }
    </style>



    <img src="{{ asset('assets/images/logo-login.png') }}" alt="Logo Login" class="logo-tema-claro"
        style="height: {{ $alturaLogin }} !important; max-height: none !important; margin: 0 auto !important; object-fit: contain;">

    <img src="{{ asset('assets/images/logo-login-light.png') }}" alt="Logo Login Dark Mode" class="logo-tema-escuro"
        style="height: {{ $alturaLogin }} !important; max-height: none !important; margin: 0 auto !important; object-fit: contain;">

@else

    {{-- ========================================== --}}
    {{-- LOGOS DA NAVBAR / DASHBOARD --}}
    {{-- ========================================== --}}

    <img src="{{ asset('assets/images/logo.png') }}" alt="Logo Sistema" class="logo-tema-claro"
        style="height: {{ $alturaNavbar }} !important; max-height: none !important;">

    <img src="{{ asset('assets/images/logo-light.png') }}" alt="Logo Sistema Dark Mode" class="logo-tema-escuro"
        style="height: {{ $alturaNavbar }} !important;">

@endif