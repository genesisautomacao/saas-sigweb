<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// e-SUS diário às 02:00 DESLIGADO (decisão do usuário, 2026-10-15): na VPS nova não havia
// agendador e o sync não rodava; ao ligar o agendador (R84-4) ele voltaria sozinho. O sync
// continua disponível sob demanda (/admin → Prefeitura → "Sincronizar e-SUS" ou
// `php artisan sigweb:sync-esus`). Para religar, descomente a linha abaixo.
// Schedule::command('sigweb:sync-esus')->dailyAt('02:00')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// R84-4 — Arquivos Gerados vencidos (7 dias) saem do bucket e da lista
Schedule::command('arquivos:limpar-expirados')->dailyAt('04:30')->withoutOverlapping();
