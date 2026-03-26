<?php

namespace App\Filament\Cidadao\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Cidadao\Resources\ProcessoDigitalResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Filament\Facades\Filament;

class CreateProcessoDigital extends CreateRecord
{
    protected static string $resource = ProcessoDigitalResource::class;

    // Variável temporária para segurar os arquivos durante o salvamento
    public array $anexosPendentes = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();
        $tenant = $user?->tenants->first() ?? \App\Models\Tenant::first();
        
        $data['tenant_id'] = $tenant?->id;
        $data['requerente_id'] = $user?->id;
        
        $ano = date('Y');
        $codigoAleatorio = strtoupper(Str::random(5));
        $data['codigo_processo'] = "PROC-{$ano}-{$codigoAleatorio}";
        
        // --- A MÁGICA DA ETAPA E STATUS ---
        // Busca a primeira etapa do fluxo BPMN selecionado
        $primeiraEtapa = \App\Models\BpmnEtapa::where('bpmn_fluxo_id', $data['bpmn_fluxo_id'])
            ->orderBy('id', 'asc')
            ->first();

        // Se o cidadão clicou em enviar, o processo já entra "Em Andamento" na 1ª Etapa
        $data['status'] = 'em_andamento';
        $data['etapa_atual_id'] = $primeiraEtapa ? $primeiraEtapa->id : null;

        if (!isset($data['dados_formulario'])) {
            $data['dados_formulario'] = [];
        }

        // --- A MÁGICA DO ANEXO ---
        // Salva os anexos na nossa variável temporária e limpa do array para não dar erro no BD
        if (isset($data['anexos_temporarios'])) {
            $this->anexosPendentes = $data['anexos_temporarios'];
            unset($data['anexos_temporarios']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $processo = $this->record;
        $user = Filament::auth()->user();

        // 1. GERA O HISTÓRICO DE TRAMITAÇÃO (O "Nascimento" do Processo)
        if ($processo->etapa_atual_id) {
            \App\Models\ProcessoTramitacao::create([
                'tenant_id' => $processo->tenant_id,
                'processo_digital_id' => $processo->id,
                'etapa_origem_id' => null, // Surgiu do nada (cidadão)
                'etapa_destino_id' => $processo->etapa_atual_id,
                'usuario_id' => $user->id,
                'parecer' => 'Processo aberto e encaminhado automaticamente pelo Portal do Cidadão.',
                'status_parecer' => 'encaminhado'
            ]);
        }

        // 2. SALVA OS ANEXOS NA TABELA CORRETA
        if (!empty($this->anexosPendentes)) {
            foreach ($this->anexosPendentes as $caminhoArquivo) {
                \App\Models\ProcessoAnexo::create([
                    'tenant_id' => $processo->tenant_id,
                    'processo_digital_id' => $processo->id,
                    'usuario_id' => $user->id,
                    'nome_arquivo' => basename($caminhoArquivo),
                    'caminho_arquivo' => $caminhoArquivo,
                    'tipo_anexo' => 'original',
                ]);
            }
        }
    }

    // 🛑 CORREÇÃO 3 (Parte B): Customiza o botão oficial do Filament
    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Enviar para Análise')
            ->icon('heroicon-o-paper-airplane');
    }

    protected function getCancelFormAction(): \Filament\Actions\Action
    {
        return parent::getCancelFormAction()->label('Cancelar');
    }

    // Oculta o botão de "Criar e Criar Outro" para não confundir o munícipe
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}