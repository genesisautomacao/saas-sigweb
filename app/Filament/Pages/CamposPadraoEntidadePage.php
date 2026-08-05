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
        return auth()->user()?->temPermissao('gerenciar_campos_customizados') ?? false;
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
            : 'Personalize como o município chama cada campo e cada valor. A lista de valores é fixa do sistema — se precisar de um valor que não existe aqui, crie um Campo Customizado.';
    }

    public function mount(string $entidade): void
    {
        abort_unless(array_key_exists($entidade, CampoDominioService::PADROES), 404);

        $this->entidade = $entidade;

        $estado = [];

        foreach (array_keys(CampoDominioService::PADROES[$entidade]) as $campo) {
            $dominio = CampoDominio::where('entidade', $entidade)->where('campo', $campo)->first();

            // Só chaves que existem no PADROES. Blinda a tela contra o formato legado
            // (lista solta de rótulos) caso a normalização ainda não tenha rodado.
            $chavesValidas = array_keys(CampoDominioService::PADROES[$entidade][$campo]['opcoes'] ?? []);
            $salvas = $dominio?->opcoes ?? [];
            $opcoes = [];
            foreach ($chavesValidas as $chave) {
                if (filled($salvas[$chave] ?? null)) {
                    $opcoes[$chave] = $salvas[$chave];
                }
            }

            $estado["dom_{$campo}"] = [
                'label' => $dominio?->label,
                'opcoes' => $opcoes,
                'visivel' => $dominio?->visivel ?? true,
            ];
        }

        $this->form->fill($estado);
    }

    public function form(Form $form): Form
    {
        $itens = [];

        foreach (CampoDominioService::PADROES[$this->entidade] ?? [] as $campo => $padrao) {
            $linhas = [
                Forms\Components\TextInput::make("dom_{$campo}.label")
                    ->label('Nome no seu município')
                    ->placeholder($padrao['label'])
                    ->helperText('Em branco = nome padrão do sistema.')
                    ->maxLength(255),

                Forms\Components\Toggle::make("dom_{$campo}.visivel")
                    ->label('Usar este campo')
                    ->helperText('Desligado: o campo some dos formulários e do app (os dados já gravados são preservados).')
                    ->default(true),
            ];

            // Lista de valores: o município RENOMEIA cada valor do sistema.
            // Não pode acrescentar nem remover valor — a chave gravada no banco é fixa
            // (decisão D6). Precisa de um valor que não existe? Crie um campo customizado.
            if (! empty($padrao['opcoes'])) {
                $linhas[] = Forms\Components\Placeholder::make("ajuda_{$campo}")
                    ->label('Nomes dos valores')
                    ->content('Renomeie como o município chama cada valor. Em branco = nome padrão. Para um valor que não existe aqui, crie um Campo Customizado.')
                    ->columnSpanFull();

                foreach ($padrao['opcoes'] as $chave => $rotuloPadrao) {
                    $linhas[] = Forms\Components\TextInput::make("dom_{$campo}.opcoes.{$chave}")
                        ->label($rotuloPadrao)
                        ->placeholder($rotuloPadrao)
                        ->maxLength(255);
                }
            }

            $itens[] = Forms\Components\Fieldset::make($padrao['label'])
                ->schema($linhas)
                ->columns(3);
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

            // Guarda o mapa `chave do sistema => rótulo do município`, só com o que
            // foi realmente renomeado. Chave fora do PADROES é descartada — a lista de
            // valores é imutável (D6) e aceitar chave estranha reabriria o bug de gravar
            // rótulo como valor na coluna.
            $chavesValidas = array_keys(CampoDominioService::PADROES[$this->entidade][$campo]['opcoes'] ?? []);
            $mapaOpcoes = [];
            foreach ($chavesValidas as $chave) {
                $rotulo = $estado['opcoes'][$chave] ?? null;
                if (filled($rotulo)) {
                    $mapaOpcoes[$chave] = $rotulo;
                }
            }

            CampoDominio::updateOrCreate(
                ['tenant_id' => $tenantId, 'entidade' => $this->entidade, 'campo' => $campo],
                [
                    'label' => filled($estado['label'] ?? null) ? $estado['label'] : null,
                    'opcoes' => $mapaOpcoes ?: null,
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
