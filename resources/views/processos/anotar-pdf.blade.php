<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Anotar PDF — {{ $anexo->nome_arquivo }}</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, Arial, sans-serif; background: #374151; color: #111827; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
            padding: 10px 14px; background: #1f2937; color: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.3); }
        .toolbar button, .toolbar a { border: 0; border-radius: 6px; padding: 8px 12px; font-weight: 600; cursor: pointer;
            background: #374151; color: #fff; text-decoration: none; font-size: 13px; }
        .toolbar button.active { background: #2563eb; }
        .toolbar button.save { background: #16a34a; }
        .toolbar .sep { flex: 1; }
        .toolbar input[type=color] { width: 36px; height: 32px; border: 0; background: transparent; cursor: pointer; }
        .pages { padding: 20px; display: flex; flex-direction: column; align-items: center; gap: 20px; }
        .page-wrap { background: #fff; box-shadow: 0 4px 14px rgba(0,0,0,.35); }
        #status { font-size: 13px; opacity: .85; }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong style="font-size:14px">✏️ {{ $anexo->nome_arquivo }}</strong>
        <span class="sep"></span>
        <button data-mode="select" class="active" onclick="setMode('select', this)">Selecionar</button>
        <button data-mode="text" onclick="setMode('text', this)">Texto</button>
        <button data-mode="rect" onclick="setMode('rect', this)">Destaque</button>
        <button data-mode="pen" onclick="setMode('pen', this)">Caneta</button>
        <input type="color" id="cor" value="#ef4444" title="Cor" onchange="setColor(this.value)">
        <button onclick="apagarSelecionado()">Apagar</button>
        <span class="sep"></span>
        <span id="status">Carregando PDF…</span>
        <button class="save" onclick="salvar()">Salvar cópia anotada</button>
        <button onclick="window.close()">Fechar</button>
    </div>

    <div class="pages" id="pages"></div>

    <script>
        const PDF_URL = @json($pdfUrl);
        const SAVE_URL = @json(route('processo-anexo.salvar', $anexo));
        const CSRF = document.querySelector('meta[name=csrf-token]').content;

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let modo = 'select';
        let cor = '#ef4444';
        const canvases = [];
        let focado = null;

        function setColor(v) {
            cor = v;
            canvases.forEach(fc => { if (fc.freeDrawingBrush) fc.freeDrawingBrush.color = v; });
            const obj = focado && focado.getActiveObject();
            if (obj) {
                if (obj.type === 'i-text') obj.set('fill', v); else obj.set('stroke', v);
                focado.renderAll();
            }
        }

        function setMode(m, btn) {
            modo = m;
            document.querySelectorAll('.toolbar button[data-mode]').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            canvases.forEach(fc => {
                fc.isDrawingMode = (m === 'pen');
                fc.selection = (m === 'select');
                if (fc.freeDrawingBrush) { fc.freeDrawingBrush.color = cor; fc.freeDrawingBrush.width = 3; }
            });
        }

        function apagarSelecionado() {
            if (!focado) return;
            const obj = focado.getActiveObject();
            if (obj) { focado.remove(obj); focado.discardActiveObject(); focado.renderAll(); }
        }

        function registrarHandlers(fc) {
            fc.on('mouse:down', function (opt) {
                focado = fc;
                if (modo === 'text') {
                    const p = fc.getPointer(opt.e);
                    const t = new fabric.IText('Texto', { left: p.x, top: p.y, fontSize: 20, fill: cor });
                    fc.add(t); fc.setActiveObject(t); t.enterEditing();
                    setMode('select', document.querySelector('.toolbar button[data-mode=select]'));
                } else if (modo === 'rect') {
                    const p = fc.getPointer(opt.e);
                    const r = new fabric.Rect({ left: p.x, top: p.y, width: 140, height: 44,
                        fill: 'rgba(250,204,21,0.35)', stroke: cor, strokeWidth: 2 });
                    fc.add(r); fc.setActiveObject(r);
                    setMode('select', document.querySelector('.toolbar button[data-mode=select]'));
                }
            });
        }

        async function init() {
            try {
                const pdf = await pdfjsLib.getDocument(PDF_URL).promise;
                const container = document.getElementById('pages');

                for (let i = 1; i <= pdf.numPages; i++) {
                    const page = await pdf.getPage(i);
                    const viewport = page.getViewport({ scale: 1.4 });

                    const bg = document.createElement('canvas');
                    bg.width = viewport.width; bg.height = viewport.height;
                    await page.render({ canvasContext: bg.getContext('2d'), viewport }).promise;
                    const dataUrl = bg.toDataURL('image/png');

                    const wrap = document.createElement('div');
                    wrap.className = 'page-wrap';
                    const el = document.createElement('canvas');
                    el.width = viewport.width; el.height = viewport.height;
                    wrap.appendChild(el);
                    container.appendChild(wrap);

                    const fc = new fabric.Canvas(el, { selection: true });
                    fabric.Image.fromURL(dataUrl, function (img) {
                        fc.setBackgroundImage(img, fc.renderAll.bind(fc), {
                            scaleX: fc.width / img.width,
                            scaleY: fc.height / img.height,
                        });
                    });
                    fc.freeDrawingBrush.color = cor;
                    fc.freeDrawingBrush.width = 3;
                    registrarHandlers(fc);
                    canvases.push(fc);
                    if (!focado) focado = fc;
                }
                document.getElementById('status').textContent = pdf.numPages + ' página(s) carregada(s).';
            } catch (e) {
                console.error(e);
                document.getElementById('status').textContent = 'Erro ao carregar o PDF.';
            }
        }

        async function salvar() {
            const status = document.getElementById('status');
            if (canvases.length === 0) { status.textContent = 'Nada para salvar.'; return; }
            status.textContent = 'Gerando PDF anotado…';

            const { jsPDF } = window.jspdf;
            let doc = null;
            canvases.forEach((fc, idx) => {
                const w = fc.getWidth(), h = fc.getHeight();
                const img = fc.toDataURL({ format: 'png', multiplier: 1 });
                if (idx === 0) {
                    doc = new jsPDF({ orientation: w > h ? 'l' : 'p', unit: 'px', format: [w, h] });
                } else {
                    doc.addPage([w, h], w > h ? 'l' : 'p');
                }
                doc.addImage(img, 'PNG', 0, 0, w, h);
            });

            const base64 = doc.output('datauristring');

            try {
                const resp = await fetch(SAVE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ pdf_base64: base64 }),
                });
                const json = await resp.json();
                if (json.ok) {
                    status.textContent = 'Cópia anotada salva (versão ' + json.versao + ').';
                    alert('Cópia anotada salva com sucesso (versão ' + json.versao + '). Você já pode fechar esta aba.');
                } else {
                    status.textContent = 'Falha ao salvar.';
                }
            } catch (e) {
                console.error(e);
                status.textContent = 'Erro ao enviar o PDF.';
            }
        }

        init();
    </script>
</body>
</html>
