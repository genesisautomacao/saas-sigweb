{{-- CSS próprio das pages customizadas (Produtividade, Validação da Coleta,
     Progresso dos Processos...). As blades custom NÃO passam pelo build do
     Tailwind (purge remove as utilities), então o layout vive aqui com classes
     pg-* garantidas. Suporta dark mode via .dark do Filament. --}}
<style>
    /* Filtros ------------------------------------------------------------- */
    .pg-filtros { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px; margin-bottom: 22px; }
    .pg-filtros > div { display: flex; flex-direction: column; }
    .pg-filtros label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; margin-bottom: 5px; }
    .pg-filtros select, .pg-filtros input { min-width: 185px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 10px; background: #fff; font-size: 14px; color: #1f2937; }
    .pg-filtros select { padding-right: 36px; }
    .dark .pg-filtros label { color: #9ca3af; }
    .dark .pg-filtros select, .dark .pg-filtros input { background: #111827; border-color: #374151; color: #e5e7eb; }

    /* Cards de resumo ------------------------------------------------------ */
    .pg-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(185px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .pg-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 15px 18px; box-shadow: 0 1px 3px rgba(0, 0, 0, .06); }
    .dark .pg-card { background: #111827; border-color: #374151; }
    .pg-card .pg-t { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; }
    .dark .pg-card .pg-t { color: #9ca3af; }
    .pg-card .pg-v { font-size: 26px; font-weight: 800; margin-top: 6px; color: #111827; line-height: 1.1; }
    .dark .pg-card .pg-v { color: #f9fafb; }
    .pg-card.pg-verde { background: #ecfdf5; border-color: #a7f3d0; } .pg-card.pg-verde .pg-t, .pg-card.pg-verde .pg-v { color: #047857; }
    .pg-card.pg-amarelo { background: #fffbeb; border-color: #fde68a; } .pg-card.pg-amarelo .pg-t, .pg-card.pg-amarelo .pg-v { color: #b45309; }
    .pg-card.pg-vermelho { background: #fef2f2; border-color: #fecaca; } .pg-card.pg-vermelho .pg-t, .pg-card.pg-vermelho .pg-v { color: #b91c1c; }
    .pg-card.pg-laranja { background: #fff7ed; border-color: #fed7aa; } .pg-card.pg-laranja .pg-t, .pg-card.pg-laranja .pg-v { color: #c2410c; }
    .pg-card.pg-azul { background: #eff6ff; border-color: #bfdbfe; } .pg-card.pg-azul .pg-t, .pg-card.pg-azul .pg-v { color: #1d4ed8; }
    .dark .pg-card.pg-verde, .dark .pg-card.pg-amarelo, .dark .pg-card.pg-vermelho, .dark .pg-card.pg-laranja, .dark .pg-card.pg-azul { background: #111827; }

    /* Painéis com tabela --------------------------------------------------- */
    .pg-painel { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 1px 3px rgba(0, 0, 0, .06); overflow: hidden; margin-bottom: 22px; }
    .dark .pg-painel { background: #111827; border-color: #374151; }
    .pg-painel.pg-borda-laranja { border-color: #fdba74; }
    .pg-painel-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #f3f4f6; font-weight: 600; font-size: 14px; color: #111827; }
    .dark .pg-painel-head { border-color: #1f2937; color: #f9fafb; }
    .pg-painel-head .pg-sub { font-size: 12px; font-weight: 400; color: #6b7280; white-space: nowrap; }
    .pg-scroll { overflow-x: auto; }
    .pg-table { width: 100%; font-size: 13.5px; border-collapse: collapse; }
    .pg-table th { text-align: left; padding: 9px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: .02em; color: #6b7280; background: #f9fafb; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    .dark .pg-table th { background: #1f2937; color: #9ca3af; border-color: #374151; }
    .pg-table td { padding: 9px 14px; border-bottom: 1px solid #f3f4f6; vertical-align: top; color: #374151; }
    .dark .pg-table td { border-color: #1f2937; color: #d1d5db; }
    .pg-table tr:last-child td { border-bottom: none; }
    .pg-vazio { padding: 34px 16px; text-align: center; color: #9ca3af; font-size: 13px; }

    /* Badges + barras de progresso ----------------------------------------- */
    .pg-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
    .pg-badge.pg-b-verde { background: #d1fae5; color: #047857; }
    .pg-badge.pg-b-amarelo { background: #fef3c7; color: #b45309; }
    .pg-badge.pg-b-vermelho { background: #fee2e2; color: #b91c1c; }
    .pg-badge.pg-b-cinza { background: #f3f4f6; color: #4b5563; }
    .pg-barra { display: flex; align-items: center; gap: 8px; min-width: 150px; }
    .pg-barra .pg-trilho { flex: 1; height: 6px; border-radius: 3px; background: #e5e7eb; overflow: hidden; }
    .dark .pg-barra .pg-trilho { background: #374151; }
    .pg-barra .pg-preenchido { height: 6px; border-radius: 3px; background: #10b981; }
    .pg-barra .pg-pct { font-size: 12px; color: #6b7280; width: 44px; text-align: right; }

    /* Avisos / carimbo ------------------------------------------------------ */
    .pg-carimbo { display: flex; align-items: center; gap: 12px; border: 1px solid #6ee7b7; background: #ecfdf5; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; }
    .dark .pg-carimbo { background: #064e3b22; border-color: #065f46; }
    .pg-carimbo strong { color: #047857; font-size: 14px; display: block; }
    .pg-carimbo span { color: #059669; font-size: 12px; }
</style>
