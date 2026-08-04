<?php

namespace App\Filament\Pages\Traits;

use App\Filament\Resources\SecaoLogradouroResource;
use App\Models\Logradouro;
use App\Models\SecaoLogradouro;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

trait HasSecaoLogradouroActions
{
    /**
     * T1.3 — valida a unicidade código+lado ANTES do banco, para o usuário receber
     * uma mensagem amigável em vez do erro SQL do índice `secoes_logradouro_codigo_lado_unique`
     * (que permanece como trava final contra corrida).
     */
    protected function secaoConflitaCodigoLado(array $data, ?int $ignorarId = null): bool
    {
        if (blank($data['codigo'] ?? null) || blank($data['lado'] ?? null) || blank($data['logradouro_id'] ?? null)) {
            return false;
        }

        return SecaoLogradouro::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('logradouro_id', $data['logradouro_id'])
            ->where('codigo', $data['codigo'])
            ->where('lado', $data['lado'])
            ->whereNull('deleted_at')
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists();
    }

    protected function avisarConflitoSecao(array $data): void
    {
        $lado = \App\Services\Coleta\CampoDominioService::rotuloValor('secao_logradouro', 'lado', $data['lado']) ?? $data['lado'];

        Notification::make()
            ->danger()
            ->title('Código já usado neste lado')
            ->body("Já existe a seção \"{$data['codigo']}\" do lado {$lado} neste logradouro. Use outro código — ou selecione o outro lado, se esta for a seção da calçada oposta.")
            ->persistent()
            ->send();
    }

    public ?int $secaoLogradouroAtivoId = null;

    // Auto-detecção topológica + cálculo de extensão pré-criação (preenchidos em interceptarDesenho)
    public ?int $secaoLogradouroLogradouroPreSelecionadoId = null;
    public ?float $secaoLogradouroExtensaoCalculada = null;

    public function criarSecaoLogradouroAction(): Action
    {
        return Action::make('criarSecaoLogradouro')
            ->modalHeading('Cadastrar Nova Seção de Logradouro')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar')
            ->fillForm(function (): array {
                return [
                    'logradouro_id' => $this->secaoLogradouroLogradouroPreSelecionadoId,
                ];
            })
            ->form([
                Placeholder::make('extensao_calculada')
                    ->label('Extensão calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->secaoLogradouroExtensaoCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->secaoLogradouroExtensaoCalculada, 2, ',', '.') . ' m</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a linha no mapa primeiro.</em>'
                    )),
                Select::make('logradouro_id')
                    ->label('Logradouro')
                    ->helperText('Logradouro vinculado. Pode alterar se necessário.')
                    ->options(Logradouro::where('tenant_id', $this->tenantId)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('codigo')
                    ->label('Código da Seção (métrico)')
                    ->maxLength(50),
                // Item 44 — lista do sistema, rótulo white-label. Desenhar a seção sobre
                // a TESTADA (frente dos lotes do lado), não sobre o eixo da rua.
                \App\Services\Coleta\CampoDominioService::aplicar(
                    Select::make('lado')->placeholder('Selecione...')->nullable(),
                    'secao_logradouro', 'lado'
                ),
                TextInput::make('name')
                    ->label('Nome / Identificação da Seção')
                    ->maxLength(255),
                // Refatoração PoC Tangará: tipo_pavimentacao é campo customizado (kit)
                ...\App\Services\Coleta\CampoCustomizadoService::componentes('secao_logradouro'),

                // T1.7 (item 17) — foto da seção direto pelo mapa (1 foto; mais no cadastro)
                \Filament\Forms\Components\FileUpload::make('nova_foto')
                    ->label('Foto da Seção')
                    ->helperText('Opcional. Para mais de uma foto, use o cadastro da seção.')
                    ->directory('secoes_logradouro/fotos')
                    ->image()
                    ->maxSize(5120),
            ])
            ->action(function (array $data, \Filament\Actions\Action $action) {
                if ($this->secaoConflitaCodigoLado($data)) {
                    $this->avisarConflitoSecao($data);
                    $action->halt();
                }

                $novaFoto = $data['nova_foto'] ?? null;
                unset($data['nova_foto']);

                $data['tenant_id']    = $this->tenantId;
                $data['geo']          = $this->geometriaRascunho;
                $data['code']         = (string) Str::uuid();
                $data['extensao_geo'] = $this->secaoLogradouroExtensaoCalculada;

                $registro = SecaoLogradouro::create($data);

                if ($novaFoto) {
                    $registro->fotos()->create([
                        'tenant_id' => $this->tenantId,
                        'name' => 'Foto da seção',
                        'path' => $novaFoto,
                        'type' => 'Foto',
                    ]);
                }

                // Recalcula extensão direto do PostGIS pra garantir precisão
                try {
                    DB::statement('UPDATE secoes_logradouro SET extensao_geo = ST_Length(geo::geography) WHERE id = ?', [$registro->id]);
                } catch (\Exception $e) {
                    // Ignora silenciosamente se a coluna ainda não existir (deploy fora de ordem)
                }

                Notification::make()->title('Seção de Logradouro criada!')->success()->send();

                $this->dispatch('adicionar-secao_logradouro-mapa', [
                    'id'   => $registro->id,
                    'name' => $registro->name ?: ('Seção #' . $registro->sequential_id),
                    'geo'  => $this->geometriaRascunho,
                ]);
                $this->dispatch('limpar-rascunho-mapa');

                $this->secaoLogradouroLogradouroPreSelecionadoId = null;
                $this->secaoLogradouroExtensaoCalculada = null;
            });
    }

    public function opcoesSecaoLogradouroAction(): Action
    {
        return Action::make('opcoesSecaoLogradouro')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Seção #' . SecaoLogradouro::find($this->secaoLogradouroAtivoId)?->sequential_id)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = SecaoLogradouro::find($this->secaoLogradouroAtivoId);
                return [
                    'name'               => $reg?->name,
                    'codigo'             => $reg?->codigo,
                    'lado'               => $reg?->lado,
                    'logradouro_id'      => $reg?->logradouro_id,
                    'dados_customizados' => $reg?->dados_customizados ?? [],
                ];
            })
            ->form([
                Placeholder::make('extensao_atual')
                    ->label('Extensão atual')
                    ->content(function (): HtmlString {
                        $reg = SecaoLogradouro::find($this->secaoLogradouroAtivoId);
                        $valor = $reg?->extensao_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),
                Select::make('logradouro_id')
                    ->label('Logradouro')
                    ->options(Logradouro::where('tenant_id', $this->tenantId)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('codigo')
                    ->label('Código da Seção (métrico)')
                    ->maxLength(50),
                \App\Services\Coleta\CampoDominioService::aplicar(
                    Select::make('lado')->placeholder('Selecione...')->nullable(),
                    'secao_logradouro', 'lado'
                ),
                TextInput::make('name')
                    ->label('Nome / Identificação da Seção')
                    ->maxLength(255),
                ...\App\Services\Coleta\CampoCustomizadoService::componentes('secao_logradouro'),

                // T1.7 (item 17) — fotos atuais + inclusão rápida pelo mapa
                Placeholder::make('fotos_atuais')
                    ->label('Fotos da Seção')
                    ->content(function (): HtmlString {
                        $fotos = SecaoLogradouro::find($this->secaoLogradouroAtivoId)?->fotos ?? collect();

                        if ($fotos->isEmpty()) {
                            return new HtmlString('<em style="color:#9ca3af;">Nenhuma foto cadastrada.</em>');
                        }

                        $html = '<div style="display:flex;gap:6px;flex-wrap:wrap;">';
                        foreach ($fotos as $foto) {
                            $url = asset('storage/'.$foto->path);
                            $legenda = htmlspecialchars($foto->name ?? '', ENT_QUOTES, 'UTF-8');
                            $html .= '<a href="' . $url . '" target="_blank" title="' . $legenda . '">'
                                . '<img src="' . $url . '" alt="' . $legenda . '" '
                                . 'style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;" />'
                                . '</a>';
                        }

                        return new HtmlString($html . '</div>');
                    }),

                \Filament\Forms\Components\FileUpload::make('nova_foto')
                    ->label('Adicionar foto')
                    ->helperText('Opcional. Excluir/legendar fotos: cadastro da seção.')
                    ->directory('secoes_logradouro/fotos')
                    ->image()
                    ->maxSize(5120),
            ])
            ->action(function (array $data, \Filament\Actions\Action $action) {
                if ($this->secaoConflitaCodigoLado($data, $this->secaoLogradouroAtivoId)) {
                    $this->avisarConflitoSecao($data);
                    $action->halt();
                }

                $novaFoto = $data['nova_foto'] ?? null;
                unset($data['nova_foto']);

                $reg = SecaoLogradouro::find($this->secaoLogradouroAtivoId);
                if ($reg) {
                    $reg->update($data);

                    if ($novaFoto) {
                        $reg->fotos()->create([
                            'tenant_id' => $this->tenantId,
                            'name' => 'Foto da seção',
                            'path' => $novaFoto,
                            'type' => 'Foto',
                        ]);
                    }

                    $this->dispatch('atualizar-label-secao_logradouro', [
                        'id'   => $reg->id,
                        'name' => $reg->name ?: ('Seção #' . $reg->sequential_id),
                    ]);
                    Notification::make()->title('Atualizado!')->success()->send();
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_secao_logradouro')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-secao_logradouro', id: $this->secaoLogradouroAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_secao_logradouro')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        SecaoLogradouro::find($this->secaoLogradouroAtivoId)?->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-secao_logradouro-mapa', ['id' => $this->secaoLogradouroAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}
