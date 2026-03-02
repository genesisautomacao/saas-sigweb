<?php

namespace App\Filament\Pages;

use App\Models\Lead;
use App\Models\LeadStatus;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class LeadKanbanBoard extends Page
{
    // --- 1. CONFIGURAÇÕES DA PÁGINA ---
    protected static ?string $navigationIcon = 'heroicon-o-funnel';
    protected static ?string $navigationLabel = 'Funil de Vendas';
    protected static ?string $title = 'Funil de Vendas';
    protected static ?int $navigationSort = 2;

    // Apontamos para a NOSSA view customizada
    protected static string $view = 'filament.pages.lead-kanban-board';

    // --- 2. REGRA DE ACESSO ---
    public static function canAccess(): bool
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if (!$tenant)
            return false;

        $modules = $tenant->modules ?? [];
        /** @var \App\Models\User $user */
        $user = \Filament\Facades\Filament::auth()->user();

        return in_array('leads', $modules) && $user->can('viewAny', Lead::class);
    }

    // --- 3. DADOS: BUSCANDO OS STATUS ---
    public function getStatusesProperty(): EloquentCollection
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        return LeadStatus::where('tenant_id', $tenant?->id)->orderBy('order')->get();
    }

    // --- 4. DADOS: BUSCANDO OS LEADS (E agrupando por status) ---
    public function getLeadsProperty(): Collection
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        $query = Lead::with(['seller.user'])
            ->where('tenant_id', $tenant?->id)
            ->whereNotNull('lead_status_id');

        /** @var \App\Models\User $user */
        $user = \Filament\Facades\Filament::auth()->user();
        if ($user && $user->hasPermissionTo('view_my_leads')) {
            $query->where('seller_id', $user->seller?->id);
        }

        // Trazemos tudo e agrupamos pelo ID do status (Facilita muito no HTML)
        return $query->get()->groupBy('lead_status_id');
    }

    // --- 5. A AÇÃO NATIVA DE EDIÇÃO (Abrir a Modal) ---
    public function editLeadAction(): Action
    {
        return Action::make('editLead')
            ->hiddenLabel() // Oculta o botão padrão, pois vamos acionar via clique no card
            ->modalHeading('Detalhes e Movimentação do Lead')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->form([
                \Filament\Forms\Components\Grid::make(2)->schema([
                    // AQUI O VENDEDOR MUDA A COLUNA!
                    \Filament\Forms\Components\Select::make('lead_status_id')
                        ->label('Mover para o Status')
                        ->options(function () {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            return LeadStatus::where('tenant_id', $tenant?->id)
                                ->orderBy('order')
                                ->pluck('name', 'id');
                        })
                        ->required()
                        ->columnSpanFull(),

                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Empresa / Nome')
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('contact_name')
                        ->label('Pessoa de Contato'),
                    \Filament\Forms\Components\TextInput::make('phone')
                        ->label('Telefone Fixo'),
                    \Filament\Forms\Components\TextInput::make('whatsapp')
                        ->label('WhatsApp'),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Anotações')
                        ->columnSpanFull(),

                    \Filament\Forms\Components\DatePicker::make('last_follow_up_date')
                        ->label('Agendar Retorno')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    \Filament\Forms\Components\TextInput::make('last_follow_up_note')
                        ->label('Motivo do Retorno'),
                ])
            ])
            // Preenche o formulário com os dados do banco
            ->fillForm(function (array $arguments) {
                return Lead::find($arguments['lead_id'])->toArray();
            })
            // Salva as alterações
            ->action(function (array $data, array $arguments): void {
                Lead::find($arguments['lead_id'])->update($data);
            });
    }
}