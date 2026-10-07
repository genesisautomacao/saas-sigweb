<?php

namespace App\Services\Fiscal;

use App\Models\Pessoa;
use Illuminate\Support\Str;

/**
 * Resolve o PROPRIETÁRIO (Pessoa) a partir do payload canônico do sistema tributário
 * (proprietario_name / proprietario_cpf / proprietario_cnpj, já traduzidos pelo de/para).
 * Usado pelo comando sigweb:sincronizar-imoveis (Cajazeiras, 2026-10-07).
 *
 * Deduplicação SEGURA para as prefeituras que já estão em produção (a sincronização
 * antiga criava a Pessoa só pelo nome, sem documento):
 *   1. documento (CNPJ, senão CPF) VÁLIDO → procura por ele, em qualquer formatação;
 *   2. não achou → procura pelo NOME sem documento e grava o documento nela
 *      (é a Pessoa que a sincronização antiga criou — nunca vira duplicata);
 *   3. não achou → cria. Sem documento válido → firstOrCreate pelo nome, igual a antes.
 *
 * "Válido" = passa nos dígitos verificadores. O tributário de Cajazeiras manda o CÓDIGO
 * DO IMÓVEL com zeros à esquerda no campo CPF (00000019337): usado cru, fundiria
 * milhares de imóveis numa só pessoa. Nomes-curinga (DESCONHECIDO, NÃO INFORMADO…)
 * não viram Pessoa — a unidade fica sem proprietário (e mantém o que já tinha).
 */
class ProprietarioService
{
    /** Comparados sem acento e em maiúsculas (normalizarNome). */
    public const NOMES_IGNORADOS = [
        'DESCONHECIDO', 'DESCONHECIDA', 'DESCONHECIDOS',
        'NAO INFORMADO', 'NAO INFORMADA', 'SEM INFORMACAO',
        'SEM PROPRIETARIO', 'PROPRIETARIO DESCONHECIDO', 'PROPRIETARIO NAO INFORMADO',
        'NAO CADASTRADO', 'NAO IDENTIFICADO', 'IGNORADO', 'INDEFINIDO',
        'N/A', 'NA', '-', '--', '.', '0', 'NULL',
    ];

    public static function resolver(array $dados, int $tenantId): ?Pessoa
    {
        $nome = self::normalizarNome($dados['proprietario_name'] ?? null);

        if ($nome === null) {
            return null;
        }

        $cnpj = self::cnpjValido($dados['proprietario_cnpj'] ?? null);

        if ($cnpj) {
            return self::porDocumento('cnpj', $cnpj, $nome, 'juridica', $tenantId);
        }

        $cpf = self::cpfValido($dados['proprietario_cpf'] ?? null);

        if ($cpf) {
            return self::porDocumento('cpf', $cpf, $nome, 'fisica', $tenantId);
        }

        // Mesma regra da sincronização original (sem documento confiável).
        return Pessoa::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => $nome],
            ['type' => 'fisica'],
        );
    }

    protected static function porDocumento(string $coluna, string $digitos, string $nome, string $tipo, int $tenantId): Pessoa
    {
        $base = Pessoa::query()->where('tenant_id', $tenantId);

        // 1) já existe com este documento (gravado com ou sem máscara)?
        $pessoa = (clone $base)
            ->whereNotNull($coluna)
            ->whereRaw("regexp_replace({$coluna}, '[^0-9]', '', 'g') = ?", [$digitos])
            ->orderBy('id')
            ->first();

        if ($pessoa) {
            return $pessoa;
        }

        // 2) existe pelo nome SEM documento (criada pela sincronização antiga)? Enriquece.
        $pessoa = (clone $base)
            ->where('name', $nome)
            ->whereNull($coluna)
            ->orderBy('id')
            ->first();

        if ($pessoa) {
            $pessoa->update([$coluna => $digitos]);

            return $pessoa;
        }

        // 3) pessoa nova (homônimo com outro documento também cai aqui — correto)
        return Pessoa::create([
            'tenant_id' => $tenantId,
            'name' => $nome,
            $coluna => $digitos,
            'type' => $tipo,
        ]);
    }

    /** Apara e colapsa espaços; vazio ou nome-curinga → null. */
    public static function normalizarNome(mixed $nome): ?string
    {
        if (! is_string($nome) && ! is_numeric($nome)) {
            return null;
        }

        $nome = trim(preg_replace('/\s+/u', ' ', (string) $nome));

        if ($nome === '') {
            return null;
        }

        $chave = mb_strtoupper(Str::ascii($nome));

        return in_array($chave, self::NOMES_IGNORADOS, true) ? null : $nome;
    }

    /** CPF com dígitos verificadores corretos → 11 dígitos; senão null. */
    public static function cpfValido(mixed $valor): ?string
    {
        $d = preg_replace('/\D/', '', (string) ($valor ?? ''));

        if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) {
            return null;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;

            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $d[$i] * (($t + 1) - $i);
            }

            $dv = ($soma * 10) % 11 % 10;

            if ((int) $d[$t] !== $dv) {
                return null;
            }
        }

        return $d;
    }

    /** CNPJ com dígitos verificadores corretos → 14 dígitos; senão null. */
    public static function cnpjValido(mixed $valor): ?string
    {
        $d = preg_replace('/\D/', '', (string) ($valor ?? ''));

        if (strlen($d) !== 14 || preg_match('/^(\d)\1{13}$/', $d)) {
            return null;
        }

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([[12, $pesos1], [13, $pesos2]] as [$pos, $pesos]) {
            $soma = 0;

            foreach ($pesos as $i => $peso) {
                $soma += (int) $d[$i] * $peso;
            }

            $resto = $soma % 11;
            $dv = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $d[$pos] !== $dv) {
                return null;
            }
        }

        return $d;
    }
}
