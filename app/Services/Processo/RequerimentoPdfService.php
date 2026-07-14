<?php

namespace App\Services\Processo;

use App\Models\ProcessoAnexo;
use App\Models\ProcessoDigital;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PD-1 — Requerimento assinado antes da análise.
 * Gera o PDF do requerimento a partir do `template_requerimento` do fluxo (HTML do RichEditor),
 * substituindo placeholders `{{chave}}` por dados do solicitante/processo/imóvel/formulário.
 * Persiste o PDF gerado como ProcessoAnexo `requerimento_gerado` (auditoria) e devolve o download.
 *
 * ⚠️ Segurança: substituição por preg_replace_callback com valores escapados (e()) —
 * NUNCA Blade::render em conteúdo autorado por usuário (RCE).
 */
class RequerimentoPdfService
{
    private const MESES = [
        1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    public function gerar(ProcessoDigital $processo)
    {
        $processo->loadMissing(['fluxo', 'requerente', 'tenant']);

        $conteudo = $this->renderizarHtml($processo);

        $pdf = Pdf::loadView('pdf.requerimento-template', [
            'processo' => $processo,
            'tenant' => $processo->tenant,
            'conteudo' => $conteudo,
            'requerenteNome' => $processo->requerente?->name,
            'requerenteCpf' => $processo->requerente?->pessoaNoTenant($processo->tenant_id)?->cpf,
            'localData' => $this->localData($processo),
            'dataHora' => now()->format('d/m/Y H:i:s'),
        ]);
        $pdf->setPaper('a4', 'portrait');

        $binario = $pdf->output();

        $this->persistirGerado($processo, $binario);

        $fileName = 'requerimento-'.($processo->codigo_processo ?? $processo->id).'.pdf';

        return response()->streamDownload(function () use ($binario) {
            echo $binario;
        }, $fileName);
    }

    /** Substitui os placeholders {{chave}} do template pelo valor escapado. */
    public function renderizarHtml(ProcessoDigital $processo): string
    {
        $template = (string) $processo->fluxo?->template_requerimento;
        $valores = $this->placeholders($processo);

        return preg_replace_callback(
            '/\{\{\s*([a-z0-9:_.\-]+)\s*\}\}/i',
            function ($match) use ($valores) {
                $chave = mb_strtolower($match[1]);

                // Chave desconhecida fica literal — a prefeitura enxerga o typo no PDF.
                return array_key_exists($chave, $valores) ? e($valores[$chave] ?? '') : $match[0];
            },
            $template
        );
    }

    /** Mapa chave => valor dos placeholders disponíveis para o template. */
    public function placeholders(ProcessoDigital $processo): array
    {
        $pessoa = $processo->requerente?->pessoaNoTenant($processo->tenant_id);
        $endereco = $pessoa?->enderecos()->first();

        $valores = [
            // Solicitante
            'nome' => $processo->requerente?->name,
            'cpf' => $pessoa?->cpf,
            'rg' => $pessoa?->rg,
            'telefone' => $pessoa?->telefone,
            'email' => $processo->requerente?->email,
            'endereco' => $endereco ? trim(collect([
                $endereco->address,
                $endereco->number,
                $endereco->complement,
                $endereco->neighborhood,
                $endereco->city ? ($endereco->city.($endereco->state ? '/'.$endereco->state : '')) : null,
                $endereco->cep ? ('CEP '.$endereco->cep) : null,
            ])->filter()->implode(', ')) : null,

            // Processo
            'protocolo' => $processo->codigo_processo,
            'fluxo' => $processo->fluxo?->nome,
            'data' => now()->format('d/m/Y'),
            'data_extenso' => $this->dataExtenso(),
            'municipio' => $processo->tenant?->name,
        ];

        // Imóvel vinculado (se houver)
        $imovel = ProcessoFormService::dadosImovel($processo->lote_id);
        $unidade = $imovel['unidades'][0] ?? [];
        $valores += [
            'lote' => $imovel['numero_lote'] ?? null,
            'quadra' => $imovel['quadra'] ?? null,
            'loteamento' => $imovel['loteamento'] ?? null,
            'endereco_imovel' => $imovel['localizacao'] ?? null,
            'inscricao' => $unidade['inscricao'] ?? null,
            'cadastro' => $unidade['cadastro'] ?? null,
        ];

        // Respostas do formulário: {{campo:slug-do-label}} — de TODAS as etapas já respondidas
        // (o requerimento pode ser exigido numa etapa posterior à abertura; em slug repetido
        // entre etapas, vale o da etapa mais adiante na ordem).
        $etapas = $processo->fluxo?->etapas()->orderBy('ordem')->orderBy('id')->get() ?? collect();
        foreach ($etapas as $etapa) {
            $respostas = data_get($processo->dados_formulario, ProcessoFormService::chaveEtapa($etapa->id), []);
            foreach ((array) $respostas as $slug => $valor) {
                $valores['campo:'.mb_strtolower($slug)] = $this->formatarValor($valor);
            }
        }

        return $valores;
    }

    /** Grava o PDF gerado como ProcessoAnexo `requerimento_gerado` (versionado — regerar não sobrescreve). */
    protected function persistirGerado(ProcessoDigital $processo, string $binario): ProcessoAnexo
    {
        $anterior = ProcessoAnexo::withoutGlobalScopes()
            ->where('processo_digital_id', $processo->id)
            ->where('tipo_anexo', 'requerimento_gerado')
            ->orderByDesc('id')
            ->first();

        $path = 'processos_anexos/'.Str::uuid().'.pdf';
        Storage::disk('public')->put($path, $binario);

        return ProcessoAnexo::create([
            'tenant_id' => $processo->tenant_id,
            'processo_digital_id' => $processo->id,
            'etapa_id' => $processo->etapa_atual_id,
            'usuario_id' => auth()->id(),
            'nome_arquivo' => 'Requerimento — '.($processo->codigo_processo ?? $processo->id).'.pdf',
            'caminho_arquivo' => $path,
            'tipo_anexo' => 'requerimento_gerado',
            'versao' => $anterior ? $anterior->versao + 1 : 1,
            'anexo_origem_id' => $anterior?->cadeiaId(),
        ]);
    }

    protected function localData(ProcessoDigital $processo): string
    {
        return trim(($processo->tenant?->name ? $processo->tenant->name.', ' : '').$this->dataExtenso());
    }

    protected function dataExtenso(): string
    {
        $agora = now();

        return $agora->day.' de '.self::MESES[$agora->month].' de '.$agora->year;
    }

    protected function formatarValor($valor): string
    {
        if (is_array($valor)) {
            if (isset($valor['lat'], $valor['lon'])) {
                return number_format((float) $valor['lat'], 5).', '.number_format((float) $valor['lon'], 5);
            }

            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $valor));
        }

        return (string) $valor;
    }
}
