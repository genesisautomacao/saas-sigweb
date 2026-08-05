<?php

namespace App\Filament\Pages;

use App\Models\ColetaImobiliaria;
use App\Services\Coleta\ColetaValidacaoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Onda 8/D2-D3 — Relatório de Validação da Coleta (passo 8 do fluxo de campo).
 *
 * A empresa/prefeitura confere aqui O QUE o coletor gravou (antes→depois por campo,
 * inconformidades com GPS, divergências de proprietário) e formaliza a aprovação
 * com "Marcar campanha como validada" — o marco anterior à integração tributária.
 */
class ValidacaoColetaPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Validação da Coleta';

    protected static ?string $title = 'Validação da Coleta';

    protected static ?string $navigationGroup = 'Coleta cadastral';

    protected static ?int $navigationSort = 32;

    protected static string $view = 'filament.pages.validacao-coleta';

    public static function canAccess(): bool
    {
        return auth()->user()?->temPermissao('view_produtividade') ?? false;
    }

    public int $tenantId = 0;

    public string $dataInicio = '';

    public string $dataFim = '';

    public ?int $coletorId = null;

    public ?string $status = null;

    public string $campanha = ColetaImobiliaria::CAMPANHA_PADRAO;

    public function mount(): void
    {
        $this->tenantId = Filament::getTenant()?->id ?? 0;
    }

    protected function filtros(): array
    {
        return [
            'inicio' => $this->dataInicio ?: null,
            'fim' => $this->dataFim ?: null,
            'coletor_id' => $this->coletorId,
            'status' => $this->status,
            'campanha' => $this->campanha,
        ];
    }

    #[Computed]
    public function dados(): array
    {
        if (! $this->tenantId) {
            return ['linhas' => [], 'resumo' => ['total' => 0, 'coletados' => 0, 'pendentes' => 0, 'inconformidades' => 0, 'com_alteracoes' => 0, 'divergencias' => 0], 'divergencias' => []];
        }

        return ColetaValidacaoService::dados($this->tenantId, $this->filtros());
    }

    #[Computed]
    public function coletores(): array
    {
        return $this->tenantId ? ColetaValidacaoService::coletores($this->tenantId) : [];
    }

    #[Computed]
    public function campanhas(): array
    {
        return $this->tenantId ? ColetaValidacaoService::campanhas($this->tenantId) : [ColetaImobiliaria::CAMPANHA_PADRAO];
    }

    /** Registro de validação da campanha atual (null = ainda não validada). */
    #[Computed]
    public function validacao(): ?array
    {
        $registro = data_get(Filament::getTenant()?->data, "coleta_validacao.{$this->campanha}");

        return is_array($registro) ? $registro : null;
    }

    // ------------------------------------------------------------------
    // Ações do cabeçalho
    // ------------------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('validar_campanha')
                ->label(fn () => $this->validacao
                    ? 'Desfazer validação da campanha'
                    : 'Marcar campanha como validada')
                ->icon(fn () => $this->validacao ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-check-badge')
                ->color(fn () => $this->validacao ? 'gray' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn () => $this->validacao
                    ? "Desfazer a validação da campanha \"{$this->campanha}\"?"
                    : "Validar a campanha \"{$this->campanha}\"?")
                ->modalDescription(fn () => $this->validacao
                    ? 'O carimbo de validação será removido do relatório.'
                    : 'Registra que a prefeitura conferiu e aprovou as informações desta campanha de coleta — o relatório passa a sair carimbado como VALIDADO. É o marco formal antes da integração ao sistema tributário.')
                ->action(fn () => $this->alternarValidacao()),

            Actions\Action::make('exportar_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => $this->exportarPdf()),

            Actions\Action::make('exportar_excel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(fn () => $this->exportarExcel()),
        ];
    }

    protected function alternarValidacao(): void
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return;
        }

        $data = (array) $tenant->data;

        if ($this->validacao) {
            unset($data['coleta_validacao'][$this->campanha]);
        } else {
            $data['coleta_validacao'][$this->campanha] = [
                'validado_em' => now()->format('d/m/Y H:i'),
                'user_id' => auth()->id(),
                'nome' => auth()->user()?->name,
            ];
        }

        $tenant->data = $data;
        $tenant->save();

        unset($this->validacao); // limpa o #[Computed] memoizado

        Notification::make()
            ->title($this->validacao ? 'Campanha validada!' : 'Validação desfeita.')
            ->success()
            ->send();
    }

    // ------------------------------------------------------------------
    // Exportações (mesmos dados da tela — #[Computed] memoiza)
    // ------------------------------------------------------------------

    public function exportarPdf()
    {
        $dados = $this->dados;

        $pdf = Pdf::loadView('pdf.validacao-coleta', [
            'tenant' => Filament::getTenant(),
            'linhas' => $dados['linhas'],
            'resumo' => $dados['resumo'],
            'divergencias' => $dados['divergencias'],
            'campanha' => $this->campanha,
            'validacao' => $this->validacao,
            'periodo' => ($this->dataInicio || $this->dataFim)
                ? trim(($this->dataInicio ? \Carbon\Carbon::parse($this->dataInicio)->format('d/m/Y') : '…')
                    .' a '.($this->dataFim ? \Carbon\Carbon::parse($this->dataFim)->format('d/m/Y') : '…'))
                : 'todo o período',
            'coletor' => $this->coletorId ? ($this->coletores[$this->coletorId] ?? null) : null,
            'dataHora' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'validacao-coleta-'.now()->format('YmdHis').'.pdf'
        );
    }

    public function exportarExcel()
    {
        $dados = $this->dados;
        $path = storage_path('app/validacao-coleta-'.now()->format('YmdHis').'.xlsx');

        $writer = SimpleExcelWriter::create($path, 'xlsx')->nameCurrentSheet('Coletas');

        foreach ($dados['linhas'] as $l) {
            $writer->addRow([
                'Lote' => $l['lote'],
                'Quadra' => $l['quadra'],
                'Coletor' => $l['coletor'],
                'Coletado em' => $l['coletado_em'],
                'Status' => $l['status_rotulo'],
                'Observação' => $l['observacao'],
                'Inconformidade' => $l['inconformidade'],
                'GPS da inconformidade' => $l['inconformidade_gps'],
                'Nº de alterações' => count($l['alteracoes']),
            ]);
        }

        $writer->addNewSheetAndMakeItCurrent()->nameCurrentSheet('Alterações');

        foreach ($dados['linhas'] as $l) {
            foreach ($l['alteracoes'] as $a) {
                $writer->addRow([
                    'Lote' => $l['lote'],
                    'Quadra' => $l['quadra'],
                    'Onde' => $a['contexto'],
                    'Campo' => $a['campo'],
                    'Antes' => $a['de'],
                    'Depois' => $a['para'],
                    'Coletor' => $l['coletor'],
                    'Quando' => $l['coletado_em'],
                ]);
            }
        }

        $writer->addNewSheetAndMakeItCurrent()->nameCurrentSheet('Divergências de Proprietário');

        foreach ($dados['divergencias'] as $d) {
            $writer->addRow([
                'Lote' => $d['lote'],
                'Quadra' => $d['quadra'],
                'Inscrição' => $d['inscricao'],
                'Proprietário oficial' => $d['oficial_nome'],
                'CPF/CNPJ oficial' => $d['oficial_cpf_cnpj'],
                'Proprietário divergente (coleta)' => $d['divergente_nome'],
                'CPF/CNPJ divergente (coleta)' => $d['divergente_cpf_cnpj'],
            ]);
        }

        $writer->close();

        return response()->download($path)->deleteFileAfterSend();
    }
}
