<?php

namespace App\Filament\Resources\ColetaAtribuicaoResource\Pages\Concerns;

use App\Models\ColetaAtribuicao;
use App\Models\Quadra;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * R67-4 — trava de servidor: uma quadra não pode estar com dois cadastradores no mesmo
 * período. O mapa já bloqueia visualmente; isto protege contra edição concorrente
 * (dois gestores salvando ao mesmo tempo) e contra payload manipulado.
 */
trait ValidaConflitoRegiao
{
    protected function validarConflitoRegiao(array $data, ?int $ignorarId = null): void
    {
        $quadraIds = array_map('intval', $data['quadra_ids'] ?? []);

        if (empty($quadraIds)) {
            return;
        }

        $inicio = $data['data_inicio'] ?? now()->toDateString();
        $fim = $data['data_fim'] ?? null;

        $conflitantes = ColetaAtribuicao::withoutGlobalScopes()
            ->with('user:id,name')
            ->where('tenant_id', Filament::getTenant()->id)
            ->where('ativo', true)
            ->whereNull('deleted_at')
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->where(fn ($q) => $q->whereNull('data_fim')->orWhereDate('data_fim', '>=', $inicio))
            ->when($fim, fn ($q) => $q->whereDate('data_inicio', '<=', $fim))
            ->get();

        $conflitos = [];

        foreach ($conflitantes as $outra) {
            $comuns = array_intersect($quadraIds, array_map('intval', $outra->quadra_ids ?? []));

            foreach ($comuns as $quadraId) {
                $conflitos[$quadraId] = $outra->user?->name ?? 'outro cadastrador';
            }
        }

        if (empty($conflitos)) {
            return;
        }

        $nomes = Quadra::withoutGlobalScopes()->whereIn('id', array_keys($conflitos))->pluck('name', 'id');
        $lista = collect($conflitos)
            ->map(fn ($cadastrador, $id) => ($nomes[$id] ?? "#{$id}").' → '.$cadastrador)
            ->implode(' · ');

        Notification::make()
            ->danger()
            ->title('Quadras já atribuídas neste período')
            ->body($lista)
            ->persistent()
            ->send();

        throw ValidationException::withMessages([
            'data.quadra_ids' => 'Estas quadras já pertencem a outro cadastrador no período: '.$lista,
        ]);
    }
}
