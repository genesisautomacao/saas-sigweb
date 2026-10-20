<?php

/**
 * Refaz o bloco de resumo do plano da PoC a partir das marcas de cada item.
 *
 * Uso (na raiz do projeto): php docs/poc_atual/atualizar_resumo.php [arquivo.md]
 * Padrão: docs/poc_atual/plano_poc_nova_santa_rosa.md
 *
 * Linha de item:  - [x] **15** — texto
 * Marcas: [x] concluído · [~] parcial · [ ] pendente · [-] fora da PoC
 */
$arquivo = $argv[1] ?? __DIR__.'/plano_poc_nova_santa_rosa.md';
$total = 114;
$meta = 109;

$texto = file_get_contents($arquivo);
if ($texto === false) {
    fwrite(STDERR, "Não consegui ler {$arquivo}\n");
    exit(1);
}

$nomes = ['x' => 'concluido', '~' => 'parcial', ' ' => 'pendente', '-' => 'fora'];
$geral = array_fill_keys(array_values($nomes), 0);
$etapas = [];
$vistos = [];
$etapaAtual = '—';

foreach (preg_split('/\R/', $texto) as $linha) {
    if (preg_match('/^## (Etapa \d+[^\n]*|Fora da PoC[^\n]*)$/u', $linha, $m)) {
        $etapaAtual = trim($m[1]);
        continue;
    }
    if (! preg_match('/^- \[([x~ \-])\] \*\*(\d+)\*\*/u', $linha, $m)) {
        continue;
    }
    $status = $nomes[$m[1]];
    $item = (int) $m[2];
    $vistos[$item] = ($vistos[$item] ?? 0) + 1;
    $geral[$status]++;
    $etapas[$etapaAtual] ??= array_fill_keys(array_values($nomes), 0);
    $etapas[$etapaAtual][$status]++;
}

$avisos = [];
$repetidos = array_keys(array_filter($vistos, fn ($n) => $n > 1));
$faltando = array_diff(range(1, $total), array_keys($vistos));
if ($repetidos) {
    $avisos[] = 'Itens repetidos: '.implode(', ', $repetidos);
}
if ($faltando) {
    $avisos[] = 'Itens do edital sem linha no plano: '.implode(', ', $faltando);
}

$pct = fn (int $n): string => number_format($n * 100 / $total, 1, ',', '.').'%';
$faltam = max(0, $meta - $geral['concluido']);

$r = [];
$r[] = '<!-- RESUMO:INICIO -->';
$r[] = '## Resumo';
$r[] = '';
$r[] = '| Situação | Itens | % |';
$r[] = '|---|---:|---:|';
$r[] = "| ✅ Concluídos | {$geral['concluido']} | {$pct($geral['concluido'])} |";
$r[] = "| 🟡 Parciais | {$geral['parcial']} | {$pct($geral['parcial'])} |";
$r[] = "| ⏳ Pendentes | {$geral['pendente']} | {$pct($geral['pendente'])} |";
$r[] = "| ⛔ Fora da PoC | {$geral['fora']} | {$pct($geral['fora'])} |";
$r[] = '';
$r[] = "**Atendimento: {$geral['concluido']}/{$total} ({$pct($geral['concluido'])}) · Meta 95% = {$meta} itens · "
    .($faltam > 0 ? "faltam {$faltam}" : 'META ATINGIDA').'**';
$r[] = '';
$r[] = '| Etapa | ✅ | 🟡 | ⏳ | ⛔ |';
$r[] = '|---|---:|---:|---:|---:|';
foreach ($etapas as $nome => $c) {
    $r[] = "| {$nome} | {$c['concluido']} | {$c['parcial']} | {$c['pendente']} | {$c['fora']} |";
}
foreach ($avisos as $aviso) {
    $r[] = '';
    $r[] = "> ⚠️ {$aviso}";
}
$r[] = '';
$r[] = '_Atualizado em '.date('d/m/Y H:i').'._';
$r[] = '<!-- RESUMO:FIM -->';

$novo = preg_replace(
    '/<!-- RESUMO:INICIO -->.*?<!-- RESUMO:FIM -->/su',
    str_replace('$', '\$', implode("\n", $r)),
    $texto,
    1,
    $trocas
);
if ($trocas !== 1) {
    fwrite(STDERR, "Marcadores <!-- RESUMO:INICIO --> / <!-- RESUMO:FIM --> não encontrados.\n");
    exit(1);
}

file_put_contents($arquivo, $novo);
echo "Resumo atualizado: {$geral['concluido']} concluídos · {$geral['parcial']} parciais · {$geral['pendente']} pendentes · {$geral['fora']} fora".($avisos ? ' · '.implode(' · ', $avisos) : '').PHP_EOL;
