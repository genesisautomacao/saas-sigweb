<?php

namespace App\Services\Exports;

use App\Models\Lote;
use App\Support\FotosLote;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Downloads .zip a partir da ficha do lote (R78-1, pedido da Engenharia):
 *  - fotos(): Lote_{n}_fotos.zip — as 3 fotos do lote (frontal + laterais);
 *  - lote():  lote_{n}.zip — Excel (Lote / Unidades / Edificações) + PDF detalhado + fotos/.
 *
 * O zip é montado em storage/app/exports e apagado depois do envio (sem fila).
 */
class LoteZipService
{
    /** Coluna => sufixo do arquivo dentro do zip. */
    public const FOTOS = [
        'foto_frontal' => 'frontal',
        'foto_lateral_esq' => 'lateral_esquerda',
        'foto_lateral_dir' => 'lateral_direita',
    ];

    public function __construct(private LoteExportService $export) {}

    /** Identificador do lote no nome dos arquivos (nº do lote; sem ele, o sequencial). */
    public static function identificador(Lote $lote): string
    {
        $id = trim((string) ($lote->numero_lote ?: $lote->sequential_id ?: $lote->id));

        return preg_replace('/[^\pL\pN\-]+/u', '_', $id) ?: (string) $lote->id;
    }

    /**
     * Fotos do lote que existem de fato: [nome no zip => bytes]. R79-1: lê do bucket
     * público ou do disco local conforme o caminho gravado (FotosLote).
     */
    public function fotosDisponiveis(Lote $lote, string $prefixo = ''): array
    {
        $nome = 'Lote_'.self::identificador($lote);
        $saida = [];

        foreach (self::FOTOS as $coluna => $sufixo) {
            $caminho = $lote->{$coluna};
            $conteudo = FotosLote::conteudo($caminho);
            if ($conteudo === null) {
                continue;
            }
            $ext = strtolower(pathinfo($caminho, PATHINFO_EXTENSION)) ?: 'jpg';
            $saida[$prefixo."{$nome}_{$sufixo}.{$ext}"] = $conteudo;
        }

        return $saida;
    }

    public static function nomeZipFotos(Lote $lote): string
    {
        return 'Lote_'.self::identificador($lote).'_fotos.zip';
    }

    public static function nomeZipLote(Lote $lote): string
    {
        return 'lote_'.self::identificador($lote).'.zip';
    }

    /** Lote_{n}_fotos.zip — null quando o lote não tem nenhuma foto. */
    public function fotos(Lote $lote): ?string
    {
        $fotos = $this->fotosDisponiveis($lote);
        if ($fotos === []) {
            return null;
        }

        return $this->montarZip(self::nomeZipFotos($lote), function (ZipArchive $zip) use ($fotos) {
            foreach ($fotos as $nome => $conteudo) {
                $zip->addFromString($nome, $conteudo);
            }
        });
    }

    /** lote_{n}.zip — planilha + PDF + pasta fotos/. */
    public function lote(Lote $lote): string
    {
        $id = self::identificador($lote);
        $lotes = Lote::query()->whereKey($lote->id)->get();

        $xlsx = $this->caminhoTemporario("lote_{$id}.xlsx");
        $this->export->escreverExcel($lotes, $xlsx);
        $pdf = $this->export->pdfBinario($lotes);

        try {
            return $this->montarZip(self::nomeZipLote($lote), function (ZipArchive $zip) use ($id, $xlsx, $pdf, $lote) {
                $zip->addFile($xlsx, "lote_{$id}.xlsx");
                $zip->addFromString("lote_{$id}.pdf", $pdf);
                foreach ($this->fotosDisponiveis($lote, 'fotos/') as $nome => $conteudo) {
                    $zip->addFromString($nome, $conteudo);
                }
            });
        } finally {
            File::delete($xlsx);
        }
    }

    private function montarZip(string $nome, callable $preencher): string
    {
        $caminho = $this->caminhoTemporario($nome);

        $zip = new ZipArchive;
        if ($zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Não foi possível criar {$nome}.");
        }
        $preencher($zip);
        $zip->close();

        return $caminho;
    }

    /**
     * Nome único no disco (dois usuários baixando o mesmo lote não colidem); o nome
     * amigável vai só no download — response()->download($caminho, $nome).
     */
    private function caminhoTemporario(string $nome): string
    {
        $dir = storage_path('app/exports');
        File::ensureDirectoryExists($dir);

        return $dir.'/'.uniqid('', true).'_'.$nome;
    }
}
