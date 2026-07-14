<?php

namespace App\Filament\Cidadao\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Cidadao\Resources\ProcessoDigitalResource;
use App\Services\Processo\ProcessoFormService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProcessoDigital extends CreateRecord
{
    protected static string $resource = ProcessoDigitalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();
        $tenant = $user?->tenants->first() ?? \App\Models\Tenant::first();

        $data['tenant_id'] = $tenant?->id;
        $data['requerente_id'] = $user?->id;

        $ano = date('Y');
        $data['codigo_processo'] = "PROC-{$ano}-".strtoupper(Str::random(5));

        // A etapa de ABERTURA (1ª, do solicitante) — a resposta é gravada aqui.
        $primeiraEtapa = \App\Models\BpmnEtapa::where('bpmn_fluxo_id', $data['bpmn_fluxo_id'])
            ->orderBy('ordem')
            ->orderBy('id')
            ->first();

        // PD-1 — o processo só nasce aguardando o cidadão se o requerimento for exigido
        // JÁ NA ABERTURA (etapa do requerimento = 1ª etapa). Se a etapa marcada for outra,
        // o fluxo segue normal e o gate dispara quando o processo chegar àquela etapa.
        $fluxo = \App\Models\BpmnFluxo::find($data['bpmn_fluxo_id']);
        $etapaRequerimento = $fluxo?->etapaRequerimento();
        $seguraNaAbertura = $etapaRequerimento && $primeiraEtapa && (int) $etapaRequerimento->id === (int) $primeiraEtapa->id;

        $data['status'] = $seguraNaAbertura ? 'aguardando_solicitante' : 'em_andamento';
        $data['etapa_atual_id'] = $primeiraEtapa?->id;

        if (! isset($data['dados_formulario'])) {
            $data['dados_formulario'] = [];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $processo = $this->record;
        $user = Filament::auth()->user();

        // 1. Nascimento do processo (histórico)
        if ($processo->etapa_atual_id) {
            \App\Models\ProcessoTramitacao::create([
                'tenant_id' => $processo->tenant_id,
                'processo_digital_id' => $processo->id,
                'etapa_origem_id' => null,
                'etapa_destino_id' => $processo->etapa_atual_id,
                'usuario_id' => $user->id,
                'parecer' => 'Processo aberto pelo Portal do Cidadão.',
                'status_parecer' => 'encaminhado',
            ]);
        }

        // 2. Grava a resposta da etapa de abertura (+ ProcessoAnexo dos uploads nomeados)
        ProcessoFormService::salvarRespostaEtapa($processo, $processo->etapaAtual, $user->id);

        // 3. PD-1 — fluxo com requerimento: NÃO avança; o processo fica com o cidadão
        //    até ele gerar o requerimento, assinar e anexar o PDF assinado (gate no EditProcessoDigital).
        if ($processo->refresh()->precisaRequerimentoAssinado()) {
            \App\Services\Processo\ProcessoNotificacaoService::notificarTransicao(
                $processo,
                'encaminhado',
                'Gere o requerimento, assine e anexe o PDF assinado para enviar o processo à análise.'
            );

            \Filament\Notifications\Notification::make()
                ->info()
                ->title('Solicitação registrada — falta o requerimento assinado')
                ->body('Agora gere o requerimento (já preenchido com os dados enviados), assine e anexe o PDF assinado para concluir o envio.')
                ->persistent()
                ->send();

            return;
        }

        // 4. Auto-avança: sai do cidadão e vai para a próxima etapa (fila do setor) — item 7
        ProcessoFormService::avancarProximaEtapa($processo, $user->id);
    }

    // PD-1 — com requerimento pendente, aterrissa direto na tela de assinatura (edit).
    protected function getRedirectUrl(): string
    {
        if ($this->record->refresh()->precisaRequerimentoAssinado()) {
            return static::getResource()::getUrl('edit', ['record' => $this->record]);
        }

        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }

    // Item 6 — o envio fica no botão "Enviar para Análise" da última etapa do wizard.
    protected function getFormActions(): array
    {
        return [];
    }
}
