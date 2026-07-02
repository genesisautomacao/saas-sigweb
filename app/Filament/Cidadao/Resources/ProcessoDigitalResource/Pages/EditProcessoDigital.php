<?php

namespace App\Filament\Cidadao\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Cidadao\Resources\ProcessoDigitalResource;
use App\Services\Processo\ProcessoFormService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Facades\Filament;

class EditProcessoDigital extends EditRecord
{
    protected static string $resource = ProcessoDigitalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $processo = $this->record;
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();

        // Grava a resposta da etapa atual (+ ProcessoAnexo dos uploads nomeados)
        ProcessoFormService::salvarRespostaEtapa($processo, $processo->etapaAtual, $user->id);

        // Auto-avança: o cidadão respondeu/corrigiu → segue para a próxima etapa (item 7)
        ProcessoFormService::avancarProximaEtapa($processo, $user->id);
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
