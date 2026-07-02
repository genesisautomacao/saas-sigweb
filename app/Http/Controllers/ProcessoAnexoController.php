<?php

namespace App\Http\Controllers;

use App\Models\ProcessoAnexo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'anexo'  => $anexo,
            'pdfUrl' => Storage::url($anexo->caminho_arquivo),
        ]);
    }

    /** Recebe o PDF anotado (base64) e grava como nova versão do anexo. */
    public function salvar(Request $request, ProcessoAnexo $anexo)
    {
        $this->autorizar($anexo);

        $request->validate(['pdf_base64' => 'required|string']);

        $b64 = preg_replace('#^data:application/pdf;base64,#', '', $request->input('pdf_base64'));
        $binary = base64_decode($b64, true);
        abort_if($binary === false, 422, 'Conteúdo PDF inválido.');

        $origemId = $anexo->anexo_origem_id ?? $anexo->id;

        $maxVersao = (int) ProcessoAnexo::withoutGlobalScopes()
            ->where('processo_digital_id', $anexo->processo_digital_id)
            ->where(fn ($q) => $q->where('id', $origemId)->orWhere('anexo_origem_id', $origemId))
            ->max('versao');

        $novaVersao = max($maxVersao, 1) + 1;

        $path = 'processos_anexos/' . Str::uuid() . '.pdf';
        Storage::disk('public')->put($path, $binary);

        $novo = ProcessoAnexo::create([
            'tenant_id'           => $anexo->tenant_id,
            'processo_digital_id' => $anexo->processo_digital_id,
            'usuario_id'          => auth()->id(),
            'nome_arquivo'        => pathinfo($anexo->nome_arquivo, PATHINFO_FILENAME) . '-anotado-v' . $novaVersao . '.pdf',
            'caminho_arquivo'     => $path,
            'tipo_anexo'          => 'anotado',
            'versao'              => $novaVersao,
            'anexo_origem_id'     => $origemId,
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
