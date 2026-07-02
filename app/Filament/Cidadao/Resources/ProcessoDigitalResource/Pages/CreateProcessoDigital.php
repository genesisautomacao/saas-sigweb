<?php

namespace App\Filament\Cidadao\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Cidadao\Resources\ProcessoDigitalResource;
use App\Services\Processo\ProcessoFormService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Filament\Facades\Filament;

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
        $data['codigo_processo'] = "PROC-{$ano}-" . strtoupper(Str::random(5));

        // A etapa de ABERTURA (1ª, do solicitante) — a resposta é gravada aqui.
        $primeiraEtapa = \App\Models\BpmnEtapa::where('bpmn_fluxo_id', $data['bpmn_fluxo_id'])
            ->orderBy('ordem')
            ->orderBy('id')
            ->first();

        $data['status'] = 'em_andamento';
        $data['etapa_atual_id'] = $primeiraEtapa?->id;

        if (!isset($data['dados_formulario'])) {
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

        // 3. Auto-avança: sai do cidadão e vai para a próxima etapa (fila do setor) — item 7
        ProcessoFormService::avancarProximaEtapa($processo, $user->id);
    }

    // Item 6 — o envio fica no botão "Enviar para Análise" da última etapa do wizard.
    protected function getFormActions(): array
    {
        return [];
    }
}
