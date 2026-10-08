<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChamadoMapaResource\Pages;
use App\Models\ChamadoMapa;
use App\Services\ChamadosMapa\ChamadoMapaService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * R80-1 — Chamados abertos pelo cidadão no MAPA PÚBLICO (módulo `chamados_mapa`).
 * A prefeitura não cria chamado aqui: só acompanha, muda a situação e responde.
 */
class ChamadoMapaResource extends Resource
{
    use \App\Traits\HasTenantModule;

    protected static ?string $tenantModule = 'chamados_mapa';

    protected static ?string $model = ChamadoMapa::class;

    protected static ?string $tenantRelationshipName = 'chamadosMapa';

    protected static ?string $navigationIcon = 'heroicon-o-phone';

    protected static ?string $navigationGroup = 'Chamados pelo Mapa';

    protected static ?string $navigationLabel = 'Chamados recebidos';

    protected static ?string $modelLabel = 'Chamado pelo Mapa';

    protected static ?string $pluralModelLabel = 'Chamados pelo Mapa';

    protected static ?string $slug = 'chamados-mapa';

    protected static ?string $recordTitleAttribute = 'protocolo';

    public static function getNavigationBadge(): ?string
    {
        $pendentes = static::getModel()::query()->where('situacao', 'pendente')->count();

        return $pendentes > 0 ? (string) $pendentes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Chamados pendentes';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('protocolo')->label('Protocolo')
                    ->searchable()->copyable()->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')->label('Recebido em')
                    ->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('assunto')->label('Tipo')->badge()
                    ->formatStateUsing(fn ($state) => ChamadoMapa::ASSUNTOS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'reclamacao' => 'danger',
                        'sugestao' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('titulo')->label('Título')->searchable()->limit(45)->wrap(),
                Tables\Columns\TextColumn::make('nome')->label('Cidadão')->searchable()
                    ->description(fn (ChamadoMapa $r) => $r->celular ?: $r->email),
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()
                    ->formatStateUsing(fn ($state) => ChamadoMapa::rotuloSituacao($state))
                    ->color(fn ($state) => ChamadoMapa::SITUACOES[$state]['filament'] ?? 'gray'),
                Tables\Columns\IconColumn::make('tem_foto')->label('Foto')->boolean()
                    ->state(fn (ChamadoMapa $r) => filled($r->foto))
                    ->trueIcon('heroicon-o-photo')->falseIcon('heroicon-o-minus')->alignCenter(),
                Tables\Columns\IconColumn::make('tem_local')->label('No mapa')->boolean()
                    ->state(fn (ChamadoMapa $r) => $r->getRawOriginal('geo') !== null)
                    ->trueIcon('heroicon-o-map-pin')->falseIcon('heroicon-o-minus')->alignCenter(),
            ])
            ->filters([
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\DatePicker::make('de')->label('Recebidos de'),
                        Forms\Components\DatePicker::make('ate')->label('até'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['de'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['ate'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $ind = [];
                        if ($data['de'] ?? null) {
                            $ind[] = 'De '.\Carbon\Carbon::parse($data['de'])->format('d/m/Y');
                        }
                        if ($data['ate'] ?? null) {
                            $ind[] = 'Até '.\Carbon\Carbon::parse($data['ate'])->format('d/m/Y');
                        }

                        return $ind;
                    }),
                Tables\Filters\SelectFilter::make('assunto')->label('Tipo de chamado')
                    ->options(ChamadoMapa::ASSUNTOS)->multiple(),
                Tables\Filters\SelectFilter::make('situacao')->label('Situação')
                    ->options(collect(ChamadoMapa::SITUACOES)->map(fn ($s) => $s['label'])->all())->multiple(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('alterarSituacao')
                    ->label('Situação')->icon('heroicon-o-arrow-path')->color('primary')
                    ->modalHeading(fn (ChamadoMapa $r) => "Chamado {$r->protocolo}")
                    ->fillForm(fn (ChamadoMapa $r) => static::dadosSituacao($r))
                    ->form(static::formularioSituacao())
                    ->action(fn (ChamadoMapa $r, array $data) => static::salvarSituacao($r, $data)),
                Tables\Actions\Action::make('verNoMapa')
                    ->label('Mapa')->icon('heroicon-o-map')->color('gray')
                    ->visible(fn (ChamadoMapa $r) => $r->getRawOriginal('geo') !== null)
                    ->url(fn (ChamadoMapa $r) => static::urlNoMapa($r), shouldOpenInNewTab: true),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Chamado')->schema([
                Infolists\Components\TextEntry::make('protocolo')->label('Protocolo')->weight('bold')->copyable(),
                Infolists\Components\TextEntry::make('created_at')->label('Recebido em')->dateTime('d/m/Y H:i'),
                Infolists\Components\TextEntry::make('situacao')->label('Situação')->badge()
                    ->formatStateUsing(fn ($state) => ChamadoMapa::rotuloSituacao($state))
                    ->color(fn ($state) => ChamadoMapa::SITUACOES[$state]['filament'] ?? 'gray'),
                Infolists\Components\TextEntry::make('assunto')->label('Tipo')->badge()
                    ->formatStateUsing(fn ($state) => ChamadoMapa::ASSUNTOS[$state] ?? $state),
                Infolists\Components\TextEntry::make('titulo')->label('Título')->columnSpan(2),
                Infolists\Components\TextEntry::make('descricao')->label('Descrição')->columnSpanFull()
                    ->formatStateUsing(fn ($state) => new HtmlString(nl2br(e($state)))),
            ])->columns(3),

            Infolists\Components\Section::make('Cidadão')->schema([
                Infolists\Components\TextEntry::make('nome')->label('Nome'),
                Infolists\Components\TextEntry::make('celular')->label('Celular')->placeholder('—')
                    ->url(fn (ChamadoMapa $r) => $r->celular ? 'https://wa.me/55'.preg_replace('/\D/', '', $r->celular) : null, shouldOpenInNewTab: true),
                Infolists\Components\TextEntry::make('email')->label('E-mail')->placeholder('—')
                    ->url(fn (ChamadoMapa $r) => $r->email ? 'mailto:'.$r->email : null),
                Infolists\Components\TextEntry::make('cpf')->label('CPF')->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $state) : null),
            ])->columns(3),

            Infolists\Components\Section::make('Foto e localização')->schema([
                Infolists\Components\TextEntry::make('foto')->label('Foto enviada')->placeholder('Sem foto')
                    ->formatStateUsing(function (ChamadoMapa $record) {
                        $url = $record->urlFoto();

                        return $url
                            ? new HtmlString('<a href="'.e($url).'" target="_blank"><img src="'.e($url).'" alt="Foto do chamado" style="max-height:320px; max-width:100%; border-radius:8px;"></a>')
                            : 'Foto indisponível';
                    }),
                Infolists\Components\TextEntry::make('local')->label('Local informado')
                    ->state(function (ChamadoMapa $record) {
                        $c = $record->coordenadas();

                        return $c ? number_format($c['lat'], 6, '.', '').', '.number_format($c['lon'], 6, '.', '') : 'O cidadão não marcou o local';
                    })
                    ->copyable(fn (ChamadoMapa $record) => $record->coordenadas() !== null)
                    ->url(fn (ChamadoMapa $record) => $record->getRawOriginal('geo') !== null ? static::urlNoMapa($record) : null, shouldOpenInNewTab: true),
            ])->columns(2),

            Infolists\Components\Section::make('Atendimento')->schema([
                Infolists\Components\TextEntry::make('resposta')->label('Resposta ao cidadão')->placeholder('—')->columnSpanFull()
                    ->formatStateUsing(fn ($state) => new HtmlString(nl2br(e($state)))),
                Infolists\Components\TextEntry::make('observacao_interna')->label('Observação interna')->placeholder('—')->columnSpanFull()
                    ->formatStateUsing(fn ($state) => new HtmlString(nl2br(e($state)))),
                Infolists\Components\TextEntry::make('atendidoPor.name')->label('Última alteração por')->placeholder('—'),
                Infolists\Components\TextEntry::make('situacao_alterada_em')->label('Situação alterada em')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])->columns(2),
        ]);
    }

    /** Formulário da mudança de situação (lista e tela do chamado). */
    public static function formularioSituacao(): array
    {
        return [
            Forms\Components\Select::make('situacao')->label('Situação')->required()->native(false)
                ->options(collect(ChamadoMapa::SITUACOES)->map(fn ($s) => $s['label'])->all()),
            Forms\Components\Textarea::make('resposta')->label('Resposta ao cidadão')->rows(3)
                ->helperText('Vai no e-mail ao cidadão.'),
            Forms\Components\Textarea::make('observacao_interna')->label('Observação interna')->rows(2)
                ->helperText('Só a equipe da prefeitura vê.'),
            Forms\Components\Toggle::make('avisar')->label('Avisar o cidadão por e-mail')->default(true)
                ->visible(fn (Forms\Get $get) => filled($get('email')))
                ->helperText(fn (Forms\Get $get) => 'Enviado para '.$get('email')),
            Forms\Components\Hidden::make('email')->dehydrated(false),
        ];
    }

    public static function dadosSituacao(ChamadoMapa $r): array
    {
        return [
            'situacao' => $r->situacao,
            'resposta' => $r->resposta,
            'observacao_interna' => $r->observacao_interna,
            'avisar' => true,
            'email' => $r->email,
        ];
    }

    public static function salvarSituacao(ChamadoMapa $r, array $data): void
    {
        $enviado = app(ChamadoMapaService::class)->alterarSituacao(
            $r,
            $data['situacao'],
            $data['resposta'] ?? null,
            $data['observacao_interna'] ?? null,
            (bool) ($data['avisar'] ?? false),
            auth()->id(),
        );

        Notification::make()
            ->title('Situação atualizada: '.ChamadoMapa::rotuloSituacao($r->situacao))
            ->body($enviado ? "O cidadão foi avisado em {$r->email}." : null)
            ->success()->send();
    }

    public static function urlNoMapa(ChamadoMapa $r): ?string
    {
        $c = $r->coordenadas();
        $tenant = Filament::getTenant();

        return $c && $tenant
            ? url('/app/'.$tenant->slug.'/mapa-interativo?layer=chamados_mapa&focus_lat='.$c['lat'].'&focus_lon='.$c['lon'].'&zoom=19')
            : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChamadosMapa::route('/'),
            'view' => Pages\ViewChamadoMapa::route('/{record}'),
        ];
    }
}
