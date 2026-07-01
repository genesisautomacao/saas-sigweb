<?php

namespace App\Filament\Pages\Traits;

use App\Models\Logradouro;
use App\Models\SecaoLogradouro;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

trait HasLogradouroActions
{
    public ?int $logradouroAtivoId = null;

    // Pré-cálculo de extensão exibido no modal de criação (preenchido em interceptarDesenho)
    public ?float $logradouroExtensaoCalculada = null;

    public function criarLogradouroAction(): Action
    {
        return Action::make('criarLogradouro')
            ->modalHeading('Cadastrar Novo Logradouro')
            ->modalSubmitActionLabel('Salvar Logradouro')
            ->modalWidth('3xl')
            ->form([
                Placeholder::make('extensao_calculada')
                    ->label('Extensão calculada')
                    ->content(fn(): HtmlString => new HtmlString(
                        $this->logradouroExtensaoCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->logradouroExtensaoCalculada, 2, ',', '.') . ' m</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a rua no mapa primeiro.</em>'
                    )),

                TextInput::make('name')
                    ->label('Nome do Logradouro')
                    ->placeholder('Ex: Rua das Flores, Avenida Brasil...')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();
                $data['extensao_geo'] = $this->logradouroExtensaoCalculada;

                $logradouro = Logradouro::create($data);

                // Cacheia extensão (m) calculada via PostGIS — falha silenciosa caso a coluna
                // ainda não exista em ambientes legados.
                try {
                    DB::statement('UPDATE logradouros SET extensao_geo = ST_Length(geo::geography) WHERE id = ?', [$logradouro->id]);
                } catch (\Throwable $e) {
                }

                Notification::make()->title('Logradouro Criado!')->success()->send();

                // 🛑 Ação Cirúrgica de Adição
                $this->dispatch('adicionar-logradouro-mapa', [
                    'id' => $logradouro->id,
                    'name' => $logradouro->name,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');

                // Limpa a pré-detecção para a próxima criação não herdar dados
                $this->logradouroExtensaoCalculada = null;
            });
    }

    public function opcoesLogradouroAction(): Action
    {
        return Action::make('opcoesLogradouro')
            ->hiddenLabel()
            ->modalHeading(fn() => 'Editar Logradouro #' . $this->logradouroAtivoId)
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $logradouro = Logradouro::find($this->logradouroAtivoId);
                return [
                    'name' => $logradouro ? $logradouro->name : '',
                ];
            })
            ->form([
                Placeholder::make('extensao_atual')
                    ->label('Extensão atual')
                    ->content(function (): HtmlString {
                        $reg = Logradouro::find($this->logradouroAtivoId);
                        $valor = $reg?->extensao_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),

                TextInput::make('name')
                    ->label('Nome do Logradouro')
                    ->required()
                    ->maxLength(255),

                Placeholder::make('secoes_do_logradouro')
                    ->label('Seções deste Logradouro')
                    ->content(function (): HtmlString {
                        $secoes = SecaoLogradouro::where('logradouro_id', $this->logradouroAtivoId)
                            ->orderBy('sequential_id')
                            ->get();

                        if ($secoes->isEmpty()) {
                            return new HtmlString(
                                '<p style="color:#9ca3af;font-size:13px;margin:4px 0;">Nenhuma seção cadastrada.</p>'
                            );
                        }

                        $html = '<div style="overflow-x:auto;">'
                            . '<table style="width:100%;font-size:13px;border-collapse:collapse;">'
                            . '<thead><tr style="border-bottom:1px solid #e5e7eb;">'
                            . '<th style="text-align:left;padding:4px 8px;font-weight:600;color:#6b7280;">Nome</th>'
                            . '<th style="text-align:left;padding:4px 8px;font-weight:600;color:#6b7280;">Pavimento</th>'
                            . '<th style="text-align:right;padding:4px 8px;font-weight:600;color:#6b7280;">Extensão</th>'
                            . '<th style="padding:4px;"></th>'
                            . '</tr></thead><tbody>';

                        foreach ($secoes as $s) {
                            $coords = DB::table('secoes_logradouro')
                                ->selectRaw('ST_X(ST_PointOnSurface(geo)) AS lon, ST_Y(ST_PointOnSurface(geo)) AS lat')
                                ->where('id', $s->id)
                                ->first();

                            $nome = htmlspecialchars($s->name ?: ('Seção #' . $s->sequential_id), ENT_QUOTES, 'UTF-8');
                            $tipo = $s->tipo_pavimentacao ? ucfirst($s->tipo_pavimentacao) : '—';
                            $ext  = $s->extensao_geo !== null
                                ? number_format((float) $s->extensao_geo, 0, ',', '.') . ' m'
                                : '—';

                            $irBtn = '';
                            if ($coords && $coords->lat && $coords->lon) {
                                $lat = round((float) $coords->lat, 7);
                                $lon = round((float) $coords->lon, 7);
                                $irBtn = '<button type="button" '
                                    . 'onclick="window.irParaCoordenada(' . $lat . ',' . $lon . ',18);'
                                    . 'Livewire.dispatch(\'fechar-modal-filament\');" '
                                    . 'style="background:#7c3aed;color:#fff;border:none;border-radius:4px;'
                                    . 'padding:2px 10px;cursor:pointer;font-size:12px;white-space:nowrap;">'
                                    . 'Ir</button>';
                            }

                            $html .= '<tr style="border-bottom:1px solid #f3f4f6;">'
                                . '<td style="padding:4px 8px;">' . $nome . '</td>'
                                . '<td style="padding:4px 8px;">' . $tipo . '</td>'
                                . '<td style="padding:4px 8px;text-align:right;">' . $ext . '</td>'
                                . '<td style="padding:4px 8px;text-align:right;">' . $irBtn . '</td>'
                                . '</tr>';
                        }

                        $html .= '</tbody></table></div>';
                        return new HtmlString($html);
                    })
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                $logradouro = Logradouro::find($this->logradouroAtivoId);
                if ($logradouro) {
                    $logradouro->update($data);
                    Notification::make()->title('Nome Atualizado!')->success()->send();

                    // 🛑 Ação Cirúrgica de Edição
                    $this->dispatch('atualizar-label-logradouro', [
                        'id' => $logradouro->id,
                        'name' => $data['name']
                    ]);
                }
            })
            ->extraModalFooterActions([
                Action::make('nova_secao_logradouro')
                    ->label('Nova Seção')
                    ->color('secondary')
                    ->icon('heroicon-o-plus')
                    ->action(function () {
                        $this->secaoLogradouroLogradouroPreSelecionadoId = $this->logradouroAtivoId;
                        $this->dispatch('fechar-modal-filament');
                        $this->dispatch('iniciar-desenho-entidade', entityType: 'secao_logradouro');
                    }),

                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-logradouro', id: $this->logradouroAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),

                Action::make('excluir_logradouro')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        Logradouro::where('id', $this->logradouroAtivoId)->delete();
                        Notification::make()->title('Logradouro Excluído!')->success()->send();

                        // 🛑 Ação Cirúrgica de Exclusão
                        $this->dispatch('remover-logradouro-mapa', ['id' => $this->logradouroAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}
