<?php

namespace App\Filament\Pages;

use App\Models\CampoCustomizado;
use App\Models\CampoDominio;
use App\Services\Coleta\CampoDominioService;
use App\Traits\HasTenantModule;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * R67-2 — DETALHE de uma entidade: personalização dos campos padrão
 * (rótulo, lista de valores quando aplicável e uso) daquela entidade.
 * Aberta a partir da lista (CamposPadraoPage); não aparece no menu.
 */
class CamposPadraoEntidadePage extends Page implements HasForms
{
    use HasTenantModule, InteractsWithForms;

    protected static ?string $tenantModule = 'imobiliario';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.campos-padrao-entidade';

    protected static ?string $slug = 'campos-padrao/{entidade}';

    public string $entidade = '';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gerenciar_campos_customizados') ?? false;
    }

    public function getTitle(): string
    {
        return 'Campos Padrão — '.(CampoCustomizado::ENTIDADES[$this->entidade] ?? ucfirst($this->entidade));
    }

    public function getSubheading(): ?string
    {
        // Nos campos fiscais da unidade só rótulo/visibilidade fazem sentido
        // (os valores vêm do sistema tributário, não do usuário).
        return $this->entidade === 'unidade'
            ? 'Personalize o nome exibido e oculte o que o município não usa. Os valores destes campos vêm do sistema tributário.'
            : 'Personalize o nome e a lista de valores conforme a realidade do município — os dados já gravados são preservados.';
    }

    public function mount(string $entidade): void
    {
        abort_unless(array_key_exists($entidade, CampoDominioService::PADROES), 404);

        $this->entidade = $entidade;

        $estado = [];

        foreach (array_keys(CampoDominioService::PADROES[$entidade]) as $campo) {
            $dominio = CampoDominio::where('entidade', $entidade)->where('campo', $campo)->first();

            $estado["dom_{$campo}"] = [
                'label' => $dominio?->label,
                'opcoes' => $dominio?->opcoes ?? [],
                'visivel' => $dominio?->visivel ?? true,
            ];
        }

        $this->form->fill($estado);
    }

    public function form(Form $form): Form
    {
        $itens = [];

        foreach (CampoDominioService::PADROES[$this->entidade] ?? [] as $campo => $padrao) {
            $temLista = ! empty($padrao['opcoes']);

            $itens[] = Forms\Components\Fieldset::make($padrao['label'])
                ->schema(array_values(array_filter([
                    Forms\Components\TextInput::make("dom_{$campo}.label")
                        ->label('Nome no seu município')
                        ->placeholder($padrao['label'])
                        ->helperText('Em branco = nome padrão do sistema.')
                        ->maxLength(255),

                    $temLista
                        ? Forms\Components\TagsInput::make("dom_{$campo}.opcoes")
                            ->label('Valores aceitos (Enter para separar)')
                            ->placeholder(implode(', ', array_values($padrao['opcoes'])))
                            ->helperText('Vazio = lista padrão do sistema.')
                        : null,

                    Forms\Components\Toggle::make("dom_{$campo}.visivel")
                        ->label('Usar este campo')
                        ->helperText('Desligado: o campo some dos formulários e do app (os dados já gravados são preservados).')
                        ->default(true),
                ])))
                ->columns(2);
        }

        return $form->schema($itens)->statePath('data');
    }

    public function salvar(): void
    {
        $dados = $this->form->getState();
        $tenantId = Filament::getTenant()->id;

        foreach (array_keys(CampoDominioService::PADROES[$this->entidade] ?? []) as $campo) {
            $estado = $dados["dom_{$campo}"] ?? [];

            // na_coleta/obrigatorio_coleta pertencem ao Boletim de Coleta — preservados aqui.
            $atual = CampoDominio::where('entidade', $this->entidade)->where('campo', $campo)->first();

            CampoDominio::updateOrCreate(
                ['tenant_id' => $tenantId, 'entidade' => $this->entidade, 'campo' => $campo],
                [
                    'label' => filled($estado['label'] ?? null) ? $estado['label'] : null,
                    'opcoes' => ! empty($estado['opcoes']) ? array_values($estado['opcoes']) : null,
                    'visivel' => (bool) ($estado['visivel'] ?? true),
                    'na_coleta' => $atual?->na_coleta ?? true,
                    'obrigatorio_coleta' => $atual?->obrigatorio_coleta ?? false,
                ]
            );
        }

        CampoDominioService::limparCache();

        Notification::make()
            ->success()
            ->title('Campos atualizados')
            ->body('Os novos nomes já valem nos formulários, relatórios e no app.')
            ->send();
    }
}
