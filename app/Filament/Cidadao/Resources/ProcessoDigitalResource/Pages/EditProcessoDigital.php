<?php

namespace App\Filament\Cidadao\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Cidadao\Resources\ProcessoDigitalResource;
use App\Models\ProcessoAnexo;
use App\Models\ProcessoDigital;
use App\Services\Processo\ProcessoFormService;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProcessoDigital extends EditRecord
{
    protected static string $resource = ProcessoDigitalResource::class;

    /** PD-1 — path do upload do requerimento assinado (campo virtual, fora do model). */
    protected ?string $requerimentoAssinadoPath = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /** PD-1 — o upload do requerimento assinado não é atributo do model: guarda e remove do $data. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $upload = $data['requerimento_assinado_upload'] ?? null;
        $this->requerimentoAssinadoPath = is_array($upload) ? (array_values($upload)[0] ?? null) : $upload;
        unset($data['requerimento_assinado_upload']);

        // ⚠️ O Form::getState() do Filament parte do validate() e devolve SÓ os caminhos que têm
        // componente na tela — que pode ser até um SUBCONJUNTO da etapa atual (correção focada
        // nos reprovados). Merge em DOIS níveis: preserva as outras etapas E, dentro da etapa,
        // preserva os campos que não estavam na tela (substituir a etapa inteira os apagaria).
        $existente = $this->record->dados_formulario ?? [];

        foreach (($data['dados_formulario'] ?? []) as $chaveEtapa => $valores) {
            $existente[$chaveEtapa] = is_array($valores)
                ? array_replace($existente[$chaveEtapa] ?? [], $valores)
                : $valores;
        }

        $data['dados_formulario'] = $existente;

        return $data;
    }

    protected function afterSave(): void
    {
        $processo = $this->record;
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();

        // Guarda defensiva: fora dos estados editáveis, nenhum POST tramita o processo.
        if (! ProcessoDigitalResource::podeEditar($processo)) {
            return;
        }

        // PD-1 — anexa o requerimento assinado (nova versão na cadeia, sem sobrescrever)
        if ($this->requerimentoAssinadoPath) {
            $this->anexarRequerimentoAssinado($processo, $user->id, $this->requerimentoAssinadoPath);
        }

        // Grava a resposta da etapa atual (+ ProcessoAnexo dos uploads nomeados)
        ProcessoFormService::salvarRespostaEtapa($processo, $processo->etapaAtual, $user->id);

        // PD-1 — gate: sem requerimento assinado válido, o processo NÃO segue para análise.
        // (Fase 1 → 2: os dados acabaram de ser salvos; a seção do requerimento aparece agora.)
        if ($processo->refresh()->precisaRequerimentoAssinado()) {
            Notification::make()
                ->info()
                ->title('Dados enviados — falta o requerimento assinado')
                ->body('Agora gere o requerimento (já preenchido com os dados enviados), assine e anexe o PDF assinado para concluir o envio.')
                ->persistent()
                ->send();

            return;
        }

        // Auto-avança: o cidadão respondeu/corrigiu → segue para a próxima etapa (item 7)
        ProcessoFormService::avancarProximaEtapa($processo, $user->id);
    }

    /** Cria o ProcessoAnexo `requerimento_assinado` versionado (padrão versao/anexo_origem_id). */
    protected function anexarRequerimentoAssinado(ProcessoDigital $processo, int $usuarioId, string $caminho): void
    {
        $anterior = ProcessoAnexo::withoutGlobalScopes()
            ->where('processo_digital_id', $processo->id)
            ->where('tipo_anexo', 'requerimento_assinado')
            ->orderByDesc('id')
            ->first();

        // Reenvio sem trocar o arquivo (mesmo path) → não duplica o anexo
        if ($anterior && $anterior->caminho_arquivo === $caminho) {
            return;
        }

        ProcessoAnexo::create([
            'tenant_id' => $processo->tenant_id,
            'processo_digital_id' => $processo->id,
            'etapa_id' => $processo->etapa_atual_id,
            'usuario_id' => $usuarioId,
            'nome_arquivo' => 'Requerimento assinado — '.($processo->codigo_processo ?? $processo->id).'.pdf',
            'caminho_arquivo' => $caminho,
            'tipo_anexo' => 'requerimento_assinado',
            'versao' => $anterior ? $anterior->versao + 1 : 1,
            'anexo_origem_id' => $anterior?->cadeiaId(),
        ]);
    }

    // A tela de correção não usa mais o wizard — o botão de envio volta a ser o do formulário.
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Enviar para Análise'),
            $this->getCancelFormAction(),
        ];
    }
}
