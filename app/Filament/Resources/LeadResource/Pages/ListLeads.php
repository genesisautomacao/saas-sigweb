<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use App\Models\LeadPotential;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Services\ApiTools\CnpjService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Stmt\Label;
use Spatie\SimpleExcel\SimpleExcelReader;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;
    public int $skipped = 0; // Nova variável para contar os repetidos

    protected function getHeaderActions(): array
    {
        return [
            // --- 1. BOTÃO EXPORTAR (Mantido do lado de fora) ---
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\LeadExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $leads = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($leads);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\LeadExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $leads = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($leads);
                    }),
            ])
                ->label('Exportar')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),

            // --- 2. BOTÃO DROPDOWN DE AÇÕES ---
            Actions\ActionGroup::make([

                // Opção A: Criar Manualmente
                Actions\CreateAction::make()
                    ->label('Criar Manualmente')
                    ->icon('heroicon-o-pencil-square'),

                // Opção B: A Mágica do CNPJ
                Actions\Action::make('createFromCnpj')
                    ->label('Criar pelo CNPJ')
                    ->icon('heroicon-o-building-office-2')
                    ->color('primary')
                    ->modalHeading('Novo Lead via CNPJ')
                    ->modalDescription('Digite o CNPJ e clique na lupa para buscar os dados na Receita Federal.')
                    ->modalSubmitActionLabel('Salvar Lead')
                    ->form([
                        TextInput::make('document')
                            ->label('CNPJ')
                            ->mask('99.999.999/9999-99')
                            ->required()
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('search')
                                    ->icon('heroicon-m-magnifying-glass')
                                    ->action(function (Set $set, $state) {
                                        if (blank($state))
                                            return;

                                        // --- NOVA TRAVA: VERIFICA SE JÁ EXISTE ANTES DE GASTAR CRÉDITO DA API ---
                                        $cleanDoc = preg_replace('/[^0-9]/', '', $state);
                                        $tenant = \Filament\Facades\Filament::getTenant();

                                        $existingLead = Lead::where('tenant_id', $tenant?->id)
                                            ->where('document', $cleanDoc)
                                            ->first();

                                        if ($existingLead) {
                                            Notification::make()
                                                ->warning()
                                                ->title('Lead já existe!')
                                                ->body("A empresa {$existingLead->name} já está cadastrada na sua base.")
                                                ->send();
                                            return; // Aborta a busca
                                        }

                                        $service = app(CnpjService::class);
                                        $data = $service->query($state);

                                        if (isset($data['error'])) {
                                            Notification::make()
                                                ->danger()
                                                ->title('Erro na Busca')
                                                ->body($data['error'])
                                                ->send();
                                            return;
                                        }

                                        $set('name', data_get($data, 'company.name', ''));
                                        $set('surname', data_get($data, 'alias', ''));
                                        $set('email', data_get($data, 'emails.0.address', ''));
                                        $set('phone', data_get($data, 'phones.0.area', '') . data_get($data, 'phones.0.number', ''));

                                        $set('zip_code', data_get($data, 'address.zip', ''));
                                        $set('address', data_get($data, 'address.street', ''));
                                        $set('number', data_get($data, 'address.number', ''));
                                        $set('complement', data_get($data, 'address.details', ''));
                                        $set('neighborhood', data_get($data, 'address.district', ''));
                                        $set('city', data_get($data, 'address.city', ''));
                                        $set('state', data_get($data, 'address.state', ''));

                                        $set('cnae_code', data_get($data, 'mainActivity.id', ''));
                                        $set('cnae_name', data_get($data, 'mainActivity.text', ''));

                                        Notification::make()
                                            ->success()
                                            ->title('Dados Encontrados!')
                                            ->send();
                                    })
                            ),

                        Grid::make(2)->schema([
                            TextInput::make('name')->label('Razão Social')->required(),
                            TextInput::make('surname')->label('Nome Fantasia'),
                            TextInput::make('email')->label('E-mail')->email(),
                            TextInput::make('phone')->label('Telefone/WhatsApp'),
                        ]),

                        Grid::make(4)->schema([
                            TextInput::make('zip_code')->label('CEP'),
                            TextInput::make('address')->label('Endereço')->columnSpan(2),
                            TextInput::make('number')->label('Número'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('complement')->label('Complemento'),
                            TextInput::make('neighborhood')->label('Bairro'),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('city')->label('Cidade')->columnSpan(2),
                            TextInput::make('state')->label('UF'),
                        ]),

                        Grid::make(4)->schema([
                            TextInput::make('cnae_code')->Label('Código CNAE'),
                            TextInput::make('cnae_name')->Label('CNAE principal')->columnSpan(3),

                        ]),

                    ])
                    ->action(function (array $data) {
                        $data['document'] = preg_replace('/\D/', '', $data['document']);

                        if (!empty($data['phone'])) {
                            $data['phone'] = preg_replace('/\D/', '', $data['phone']);
                        }

                        // 1. Busca os IDs padrões dos campos das tabelas de status, potencial e origem
                        $tenant = \Filament\Facades\Filament::getTenant();

                        $defaultStatus = LeadStatus::where('tenant_id', $tenant->id)->where('is_default', true)->first();
                        $defaultPotentials = LeadPotential::where('tenant_id', $tenant->id)->where('is_default', true)->first();
                        $defaultOrigens = LeadSource::where('tenant_id', $tenant->id)->where('is_default', true)->first();

                        // seta o id de cada item que é is_default
                        $data['lead_status_id'] =  $defaultStatus['id'];
                        $data['lead_potential_id'] =  $defaultPotentials['id'];
                        $data['lead_source_id'] = $defaultOrigens['id'];

                        Lead::create($data);

                        Notification::make()
                            ->success()
                            ->title('Lead Prospectado com sucesso!')
                            ->send();
                    }),

                // Opção C: Importação Completa (Sua lógica original intocada)
                Actions\Action::make('import_leads')
                    ->label('Importar Leads')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color(Color::Green)
                    ->visible(function () {
                        /** @var \App\Models\User $user */
                        $user = \Filament\Facades\Filament::auth()->user();
                        return $user && $user->hasPermissionTo('import_leads');
                    })
                    ->form([
                        Forms\Components\Select::make('seller_id')
                            ->label('Vendedor Responsável')
                            ->options(function () {
                                $tenant = \Filament\Facades\Filament::getTenant();
                                return \App\Models\Seller::with('user')
                                    ->where('tenant_id', $tenant?->id)
                                    ->get()
                                    ->pluck('user.name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Deixe em branco se quiser importar os leads sem vendedor associado.'),

                        Forms\Components\FileUpload::make('file')
                            ->label('Arquivo Excel (.xlsx ou .csv)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv',
                            ])
                            ->required()
                            ->disk('local')
                            ->directory('imports/leads'),
                    ])
                    ->action(function (array $data) {
                        $filePath = Storage::disk('local')->path($data['file']);
                        $tenant = \Filament\Facades\Filament::getTenant();

                        // 1. Busca os IDs padrões (Caso a planilha venha vazia nesses campos)
                        $defaultStatus = LeadStatus::where('tenant_id', $tenant->id)->where('is_default', true)->first();
                        $defaultPotentials = LeadPotential::where('tenant_id', $tenant->id)->where('is_default', true)->first();
                        $defaultOrigens = LeadSource::where('tenant_id', $tenant->id)->where('is_default', true)->first();

                        // 2. DICIONÁRIOS EM MEMÓRIA (O Pulo do Gato da Performance)
                        // Transforma os registros em Arrays rápidos: ['nome em minusculo' => id]
                        $statusesMap = LeadStatus::where('tenant_id', $tenant->id)->get()
                            ->mapWithKeys(fn($item) => [mb_strtolower(trim($item->name)) => $item->id])->toArray();

                        $potentialsMap = LeadPotential::where('tenant_id', $tenant->id)->get()
                            ->mapWithKeys(fn($item) => [mb_strtolower(trim($item->name)) => $item->id])->toArray();

                        $sourcesMap = LeadSource::where('tenant_id', $tenant->id)->get()
                            ->mapWithKeys(fn($item) => [mb_strtolower(trim($item->name)) => $item->id])->toArray();

                        $rows = SimpleExcelReader::create($filePath)->getRows();

                        $count = 0;
                        $skipped = 0;

                        // Passamos os dicionários para dentro do loop usando o 'use'
                        $rows->each(function (array $row) use ($tenant, $data, $defaultStatus, $defaultPotentials, $defaultOrigens, $statusesMap, $potentialsMap, $sourcesMap, &$count, &$skipped) {
                            if (empty($row['razao_social'])) {
                                return;
                            }

                            // Trava Anti-Duplicidade
                            $document = preg_replace('/[^0-9]/', '', $row['cnpj_cpf'] ?? '');
                            if (!empty($document)) {
                                $exists = Lead::where('tenant_id', $tenant->id)->where('document', $document)->exists();
                                if ($exists) {
                                    $skipped++;
                                    return;
                                }
                            }

                            // 3. O MAPEAMENTO INTELIGENTE
                            // Lê da planilha, limpa espaços, joga pra minúsculo e tenta achar no Dicionário. 
                            // Se não achar (ou vier vazio), usa o padrão.
                            $excelStatus = mb_strtolower(trim($row['status'] ?? ''));
                            $statusId = $statusesMap[$excelStatus] ?? $defaultStatus?->id;

                            $excelPotential = mb_strtolower(trim($row['potencial'] ?? ''));
                            $potentialId = $potentialsMap[$excelPotential] ?? $defaultPotentials?->id;

                            $excelSource = mb_strtolower(trim($row['origem'] ?? ''));
                            $sourceId = $sourcesMap[$excelSource] ?? $defaultOrigens?->id;

                            Lead::create([
                                'tenant_id' => $tenant->id,
                                'seller_id' => $data['seller_id'],
                                'lead_status_id' => $statusId,           // <--- ID Injetado Dinamicamente!
                                'lead_potential_id' => $potentialId,     // <--- ID Injetado Dinamicamente!
                                'lead_source_id' => $sourceId,           // <--- ID Injetado Dinamicamente!
                                'name' => $row['razao_social'],
                                'surname' => $row['nome_fantasia'] ?? null,
                                'document' => $document ?? null,
                                'cnae_code' => $row['cnae_codigo'] ?? null,
                                'cnae_name' => $row['cnae_descricao'] ?? null,
                                'contact_name' => $row['nome_contato'] ?? null,
                                'email' => $row['email'] ?? null,
                                'phone' => $row['telefone'] ?? null,
                                'whatsapp' => $row['whatsapp'] ?? null,
                                'notes' => $row['notes'] ?? null,
                                'zip_code' => $row['cep'] ?? null,
                                'address' => $row['logradouro'] ?? null,
                                'number' => $row['numero'] ?? null,
                                'complement' => $row['complemento'] ?? null,
                                'neighborhood' => $row['bairro'] ?? null,
                                'city' => $row['cidade'] ?? null,
                                'state' => $row['uf'] ?? null,
                            ]);
                            $count++;
                        });

                        Storage::disk('local')->delete($data['file']);

                        Notification::make()
                            ->title("Importação Concluída")
                            ->body("{$count} novos leads importados. {$skipped} leads ignorados (já existiam no sistema).")
                            ->success()
                            ->send();
                    }),
                /* fim */
            ])
                ->label('Ações')
                ->icon('heroicon-m-arrow-down-on-square-stack')
                ->button()
                ->color('primary'),
        ];
    }
}