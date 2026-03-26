<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        wire:ignore
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            modeler: null,
            init() {
                // Carrega o script apenas se não existir
                if (typeof BpmnJS === 'undefined') {
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/bpmn-js@17.0.2/dist/bpmn-modeler.development.js';
                    script.onload = () => this.initModeler();
                    document.head.appendChild(script);
                } else {
                    this.initModeler();
                }
            },
            initModeler() {
                this.modeler = new BpmnJS({ 
                    container: this.$refs.canvas,
                    keyboard: { bindTo: window }
                });
                
                if (this.state && this.state.trim() !== '') {
                    this.modeler.importXML(this.state);
                } else {
                    // Cria um diagrama em branco padrão se for um novo registo
                    this.modeler.createDiagram();
                }

                // Sempre que o utilizador arrastar uma caixinha, guarda o XML no Filament
                this.modeler.on('commandStack.changed', async () => {
                    try {
                        const { xml } = await this.modeler.saveXML({ format: true });
                        this.state = xml;
                    } catch (err) {
                        console.error('Erro ao guardar BPMN', err);
                    }
                });
            }
        }"
    >
        {{-- Estilos do BPMN --}}
        <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.0.2/dist/assets/diagram-js.css">
        <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.0.2/dist/assets/bpmn-js.css">
        <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.0.2/dist/assets/bpmn-font/css/bpmn.css">

        {{-- O Quadro de Desenho --}}
        <div class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white overflow-hidden shadow-sm">
            <div x-ref="canvas" style="height: 650px; width: 100%;"></div>
        </div>
        
        <p class="text-xs text-gray-500 mt-2">
            <strong>Dica:</strong> Utilize o menu lateral esquerdo do quadro para arrastar as etapas (Tasks), inícios e fins do processo. O desenho será guardado automaticamente.
        </p>
    </div>
</x-dynamic-component>