<?php

namespace App\Filament\Pages;

use App\Models\Mensagem;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class MensagensPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Mensagens';
    protected static ?string $title = 'Mensagens';
    protected static ?string $navigationGroup = 'Coleta cadastral';
    protected static ?int $navigationSort = 33;
    protected static string $view = 'filament.pages.mensagens';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_mensagens') ?? false;
    }

    public int $tenantId = 0;
    public ?int $contatoSelecionadoId = null;
    public string $novaMsg = '';

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $this->tenantId = $tenant?->id ?? 0;
    }

    #[Computed]
    public function contatos(): array
    {
        if (!$this->tenantId) {
            return [];
        }

        $userId = auth()->id();

        // Lista usuários do tenant (exceto eu) que tenham AO MENOS uma role atribuída no tenant
        // — exclui cidadãos do portal sem permissões de funcionário
        $usuarios = DB::table('users as u')
            ->join('tenant_user as tu', 'tu.user_id', '=', 'u.id')
            ->where('tu.tenant_id', $this->tenantId)
            ->where('u.id', '!=', $userId)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('model_has_roles as mhr')
                    ->whereColumn('mhr.model_id', 'u.id')
                    ->where('mhr.model_type', \App\Models\User::class)
                    ->where('mhr.tenant_id', $this->tenantId);
            })
            ->select('u.id', 'u.name')
            ->distinct()
            ->get();

        return $usuarios->map(function ($u) use ($userId) {
            // Última mensagem trocada
            $ultima = Mensagem::query()
                ->where('tenant_id', $this->tenantId)
                ->where(function ($q) use ($userId, $u) {
                    $q->where(function ($q2) use ($userId, $u) {
                        $q2->where('remetente_id', $userId)->where('destinatario_id', $u->id);
                    })->orWhere(function ($q2) use ($userId, $u) {
                        $q2->where('remetente_id', $u->id)->where('destinatario_id', $userId);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->first();

            // Não lidas (enviadas por ele para mim sem lido_em)
            $naoLidas = Mensagem::query()
                ->where('tenant_id', $this->tenantId)
                ->where('remetente_id', $u->id)
                ->where('destinatario_id', $userId)
                ->whereNull('lido_em')
                ->count();

            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'ultima_msg' => $ultima?->texto,
                'ultima_em'  => $ultima?->created_at?->format('d/m H:i'),
                'nao_lidas'  => $naoLidas,
            ];
        })
            ->sortByDesc(fn($c) => $c['nao_lidas'])
            ->values()
            ->toArray();
    }

    #[Computed]
    public function conversa()
    {
        if (!$this->contatoSelecionadoId || !$this->tenantId) {
            return collect();
        }

        $userId = auth()->id();
        $contatoId = $this->contatoSelecionadoId;

        return Mensagem::query()
            ->where('tenant_id', $this->tenantId)
            ->where(function ($q) use ($userId, $contatoId) {
                $q->where(function ($q2) use ($userId, $contatoId) {
                    $q2->where('remetente_id', $userId)->where('destinatario_id', $contatoId);
                })->orWhere(function ($q2) use ($userId, $contatoId) {
                    $q2->where('remetente_id', $contatoId)->where('destinatario_id', $userId);
                });
            })
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function selecionarContato(int $id): void
    {
        $this->contatoSelecionadoId = $id;

        // Marcar como lidas todas as mensagens recebidas dele
        Mensagem::query()
            ->where('tenant_id', $this->tenantId)
            ->where('remetente_id', $id)
            ->where('destinatario_id', auth()->id())
            ->whereNull('lido_em')
            ->update(['lido_em' => now()]);
    }

    public function enviarMensagem(): void
    {
        $texto = trim($this->novaMsg);
        if ($texto === '' || !$this->contatoSelecionadoId || !$this->tenantId) {
            return;
        }
        if (mb_strlen($texto) > 2000) {
            Notification::make()->title('Mensagem muito longa (máx 2000 caracteres).')->danger()->send();
            return;
        }

        Mensagem::create([
            'tenant_id'       => $this->tenantId,
            'remetente_id'    => auth()->id(),
            'destinatario_id' => $this->contatoSelecionadoId,
            'texto'           => $texto,
        ]);

        $this->novaMsg = '';
    }
}
