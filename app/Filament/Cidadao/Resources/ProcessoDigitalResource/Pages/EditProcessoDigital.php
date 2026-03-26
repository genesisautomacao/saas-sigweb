<?php

namespace App\Filament\Cidadao\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Cidadao\Resources\ProcessoDigitalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Facades\Filament;

class EditProcessoDigital extends EditRecord
{
    protected static string $resource = ProcessoDigitalResource::class;

    // Variável temporária para segurar os arquivos durante o salvamento
    public array $anexosPendentes = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('anexos_temporarios', $data)) {
            $this->anexosPendentes = $data['anexos_temporarios'] ?? [];
            unset($data['anexos_temporarios']);
        }

        // 🛑 MÁGICA: Se estava pendente de correção, o ato de salvar devolve para o analista!
        if ($this->record->status === 'pendente_correcao') {
            $data['status'] = 'em_andamento';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $processo = $this->record;
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();

        // (Aqui fica a lógica de comparar e salvar os anexos que já fizemos antes)
        $anexosAtuais = \App\Models\ProcessoAnexo::where('processo_digital_id', $processo->id)->pluck('caminho_arquivo')->toArray();
        $novosAnexos = $this->anexosPendentes;

        $paraDeletar = array_diff($anexosAtuais, $novosAnexos);
        if (!empty($paraDeletar)) {
            \App\Models\ProcessoAnexo::where('processo_digital_id', $processo->id)->whereIn('caminho_arquivo', $paraDeletar)->delete();
        }

        $paraAdicionar = array_diff($novosAnexos, $anexosAtuais);
        foreach ($paraAdicionar as $caminho) {
            \App\Models\ProcessoAnexo::create([
                'tenant_id' => $processo->tenant_id,
                'processo_digital_id' => $processo->id,
                'usuario_id' => $user->id,
                'nome_arquivo' => basename($caminho),
                'caminho_arquivo' => $caminho,
                'tipo_anexo' => 'original',
            ]);
        }

        // 🛑 GERA HISTÓRICO DE REENVIO DO CIDADÃO
        if ($processo->wasChanged('status') && $processo->status === 'em_andamento') {
            \App\Models\ProcessoTramitacao::create([
                'tenant_id' => $processo->tenant_id,
                'processo_digital_id' => $processo->id,
                'etapa_origem_id' => $processo->etapa_atual_id,
                'etapa_destino_id' => $processo->etapa_atual_id,
                'usuario_id' => $user->id,
                'parecer' => 'O munícipe realizou as correções solicitadas e reenviou o processo para análise.',
                'status_parecer' => 'encaminhado',
            ]);
        }
    }
}