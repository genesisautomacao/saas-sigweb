<?php

namespace App\Services\Processo;

use App\Models\BpmnEtapa;
use App\Models\ProcessoAnexo;
use App\Models\ProcessoDigital;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Gera o PDF de acompanhamento de um Processo Digital (com brasão da prefeitura).
 * Segue o padrão dos ExportServices (DomPDF + blade view + streamDownload).
 */
class ProcessoDigitalPdfService
{
    public function gerar(ProcessoDigital $processo)
    {
        $processo->loadMissing(['fluxo', 'etapaAtual', 'requerente', 'tenant']);
        $tenant = $processo->tenant;
        $dataHora = now()->format('d/m/Y H:i:s');

        // Requerente: nome/email (User) + CPF/telefone (Pessoa vinculada)
        $pessoa = $processo->requerente?->pessoaNoTenant($processo->tenant_id);
        $requerente = [
            'nome' => $processo->requerente?->name,
            'email' => $processo->requerente?->email,
            'cpf' => $pessoa?->cpf,
            'telefone' => $pessoa?->telefone,
        ];

        $imovel = ProcessoFormService::dadosImovel($processo->lote_id);

        // Respostas por etapa (arquivos vão para a seção Documentos)
        $respostas = [];
        $etapas = $processo->fluxo ? $processo->fluxo->etapas()->orderBy('ordem')->orderBy('id')->get() : collect();
        $dados = $processo->dados_formulario ?? [];
        foreach ($etapas as $etapa) {
            $resp = $dados['etapa_' . $etapa->id] ?? null;
            if (empty($resp) || !is_array($resp)) {
                continue;
            }
            $labels = [];
            $tipos = [];
            foreach (($etapa->campos_formulario ?? []) as $campo) {
                $lbl = $campo['data']['label_campo'] ?? 'Campo';
                $slug = Str::slug($lbl);
                $labels[$slug] = $lbl;
                $tipos[$slug] = $campo['type'] ?? 'texto';
            }
            $itens = [];
            foreach ($resp as $slug => $valor) {
                if (($tipos[$slug] ?? '') === 'arquivo') {
                    continue;
                }
                $itens[] = ['label' => $labels[$slug] ?? $slug, 'valor' => $this->formatar($valor)];
            }
            if ($itens) {
                $respostas[] = ['etapa' => $etapa->nome, 'itens' => $itens];
            }
        }

        $documentos = ProcessoAnexo::where('processo_digital_id', $processo->id)
            ->where('tipo_anexo', '!=', 'requerimento_gerado')
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => [
                'nome' => $a->nome_arquivo,
                'url' => $a->caminho_arquivo ? asset('storage/' . ltrim($a->caminho_arquivo, '/')) : null,
            ])
            ->all();

        // Histórico (tramitações)
        $etapaNomes = BpmnEtapa::where('bpmn_fluxo_id', $processo->bpmn_fluxo_id)->pluck('nome', 'id');
        $labelDecisao = ['aprovado' => 'Aprovado', 'reprovado' => 'Reprovado', 'encaminhado' => 'Encaminhado', 'concluido' => 'Concluído'];
        $historico = $processo->tramitacoes()->with('responsavel')->orderBy('created_at')->get()->map(function ($t) use ($etapaNomes, $labelDecisao) {
            return [
                'data' => $t->created_at?->format('d/m/Y H:i'),
                'decisao' => $labelDecisao[$t->status_parecer] ?? ucfirst((string) $t->status_parecer),
                'origem' => $t->etapa_origem_id ? ($etapaNomes[$t->etapa_origem_id] ?? '—') : 'Abertura',
                'destino' => $t->etapa_destino_id ? ($etapaNomes[$t->etapa_destino_id] ?? '—') : 'Conclusão',
                'usuario' => $t->responsavel?->name ?? 'Sistema',
                'parecer' => $t->parecer,
            ];
        })->all();

        $statusLabel = match ($processo->status) {
            'em_andamento' => 'Em andamento',
            'aguardando_solicitante' => 'Aguardando cidadão',
            'pendente_correcao' => 'Em correção',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
            default => ucfirst((string) $processo->status),
        };

        $pdf = Pdf::loadView('pdf.processo-digital-template', compact(
            'processo', 'tenant', 'dataHora', 'requerente', 'imovel', 'respostas', 'documentos', 'historico', 'statusLabel'
        ));
        $pdf->setPaper('a4', 'portrait');

        $fileName = 'processo-' . ($processo->codigo_processo ?? $processo->id) . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    private function formatar($valor): string
    {
        if (is_array($valor)) {
            if (isset($valor['lat'], $valor['lon'])) {
                return number_format((float) $valor['lat'], 5) . ', ' . number_format((float) $valor['lon'], 5);
            }
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $valor));
        }
        return (string) $valor;
    }
}
