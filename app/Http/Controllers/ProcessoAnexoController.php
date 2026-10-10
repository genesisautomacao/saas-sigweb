<?php

namespace App\Http\Controllers;

use App\Models\ProcessoAnexo;
use App\Support\Midia;
use Illuminate\Http\Request;

/**
 * Anotação em PDF de anexos do Processo Digital (item 222 / B15).
 * Editor em página própria (PDF.js + Fabric.js); ao salvar, cria uma CÓPIA anotada
 * como novo ProcessoAnexo (versao+1, anexo_origem_id), sem tocar no original.
 */
class ProcessoAnexoController extends Controller
{
    /** Abre o editor de anotação para um anexo PDF. */
    public function anotar(ProcessoAnexo $anexo)
    {
        $this->autorizar($anexo);

        return view('processos.anotar-pdf', [
            'anexo' => $anexo,
            // INF-2: servido pelo próprio sistema (mesma origem) — o PDF.js não depende do
            // CORS do bucket privado nem de link temporário expirando com o editor aberto.
            'pdfUrl' => route('processo-anexo.arquivo', $anexo),
        ]);
    }

    /** PDF do anexo para o editor de anotação (bytes via Midia: bucket ou VPS). */
    public function arquivo(ProcessoAnexo $anexo)
    {
        $this->autorizar($anexo);

        $conteudo = Midia::conteudo($anexo->caminho_arquivo);
        abort_if($conteudo === null, 404);

        return response($conteudo, 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * INF-2 — link ESTÁVEL de um anexo (PDF do processo, listas de documentos): confere
     * quem pede e redireciona para o arquivo (link temporário do bucket privado ou /storage
     * do legado). Cidadão só abre anexo do próprio processo; equipe, da própria prefeitura.
     */
    public function abrir(ProcessoAnexo $anexo)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if ($user->isCidadao()) {
            $requerente = \App\Models\ProcessoDigital::withoutGlobalScopes()
                ->whereKey($anexo->processo_digital_id)->value('requerente_id');
            abort_unless((int) $requerente === (int) $user->id, 403);
        } else {
            abort_unless($user->tenants()->whereKey($anexo->tenant_id)->exists(), 403);
        }

        $url = Midia::url($anexo->caminho_arquivo);
        abort_if($url === null, 404);

        return redirect()->away($url);
    }

    /** Recebe o PDF anotado (base64) e grava como nova versão do anexo. */
    public function salvar(Request $request, ProcessoAnexo $anexo)
    {
        $this->autorizar($anexo);

        $request->validate(['pdf_base64' => 'required|string']);

        // O jsPDF gera `data:application/pdf;filename=generated.pdf;base64,...` — o `filename=`
        // varia por versão. Corta tudo até `base64,` (cobre com/sem filename e base64 cru).
        $raw = (string) $request->input('pdf_base64');
        $pos = strpos($raw, 'base64,');
        $b64 = $pos !== false ? substr($raw, $pos + 7) : $raw;
        $b64 = str_replace(["\n", "\r", ' '], '', $b64);

        $binary = base64_decode($b64, true);
        abort_if($binary === false || $binary === '', 422, 'Conteúdo PDF inválido.');

        $origemId = $anexo->anexo_origem_id ?? $anexo->id;

        $maxVersao = (int) ProcessoAnexo::withoutGlobalScopes()
            ->where('processo_digital_id', $anexo->processo_digital_id)
            ->where(fn ($q) => $q->where('id', $origemId)->orWhere('anexo_origem_id', $origemId))
            ->max('versao');

        $novaVersao = max($maxVersao, 1) + 1;

        $path = Midia::salvar($binary, 'processos_anexos', Midia::slug($anexo->tenant_id), 'pdf');

        $novo = ProcessoAnexo::create([
            'tenant_id' => $anexo->tenant_id,
            'processo_digital_id' => $anexo->processo_digital_id,
            'usuario_id' => auth()->id(),
            'nome_arquivo' => pathinfo($anexo->nome_arquivo, PATHINFO_FILENAME).'-anotado-v'.$novaVersao.'.pdf',
            'caminho_arquivo' => $path,
            'tipo_anexo' => 'anotado',
            'versao' => $novaVersao,
            'anexo_origem_id' => $origemId,
        ]);

        return response()->json(['ok' => true, 'id' => $novo->id, 'versao' => $novaVersao]);
    }

    /** Garante que o usuário logado pertence ao tenant do anexo. */
    protected function autorizar(ProcessoAnexo $anexo): void
    {
        $user = auth()->user();
        abort_unless($user && $user->tenants()->whereKey($anexo->tenant_id)->exists(), 403);
    }
}
