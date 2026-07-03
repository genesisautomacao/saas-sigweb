<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaChamado;
use App\Models\Chamado;
use App\Models\FaseChamado;
use App\Models\FluxoChamado;
use App\Models\MensagemChamado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ChamadoController extends Controller
{
    private function tenantId(Request $request): ?int
    {
        return $request->user()->tenants()->first()?->id;
    }

    /** Fiscal = usuário da equipe (tem papel). Cidadão = sem papel. Base do "categoria privada" (159/189). */
    private function ehFiscal(Request $request, int $tenantId): bool
    {
        return DB::table('model_has_roles')
            ->where('model_id', $request->user()->id)
            ->where('model_type', User::class)
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /** GET /api/categorias-chamado — cidadão não vê categorias privadas (159/189). */
    public function categorias(Request $request)
    {
        $tenantId = $this->tenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }

        $q = CategoriaChamado::query()->where('tenant_id', $tenantId)->orderBy('ordem')->orderBy('nome');
        if (! $this->ehFiscal($request, $tenantId)) {
            $q->where('privada', false);
        }

        return response()->json(['data' => $q->get(['id', 'nome', 'pai_id', 'cor', 'icone', 'privada'])]);
    }

    /** GET /api/chamados — chamados do usuário logado neste tenant. */
    public function index(Request $request)
    {
        $tenantId = $this->tenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }

        $chamados = Chamado::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $request->user()->id)
            ->with(['categoria:id,nome', 'faseAtual:id,nome'])
            ->orderBy('created_at', 'desc')
            ->take(200)
            ->get();

        return response()->json(['data' => $chamados]);
    }

    /** POST /api/chamados — cria uma solicitação (app). */
    public function store(Request $request)
    {
        $tenantId = $this->tenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'categoria_chamado_id' => 'nullable|integer',
            'fluxo_chamado_id' => 'nullable|integer',
            'descricao' => 'required|string|min:1',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
            'respostas_boletim' => 'nullable|array',
            'fotos' => 'nullable|array',
            'fotos.*' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'tenant_id' => $tenantId,
            'user_id' => $request->user()->id,
            'categoria_chamado_id' => $request->input('categoria_chamado_id'),
            'fluxo_chamado_id' => $request->input('fluxo_chamado_id'),
            'solicitante_nome' => $request->user()->name,
            'solicitante_email' => $request->user()->email,
            'descricao' => $request->input('descricao'),
            'respostas_boletim' => $request->input('respostas_boletim'),
            'status' => 'aberto',
        ];

        if ($request->filled('lat') && $request->filled('lon')) {
            $data['geo'] = ['type' => 'Point', 'coordinates' => [(float) $request->lon, (float) $request->lat]];
        }

        if ($data['fluxo_chamado_id']) {
            $data['fase_atual_id'] = FaseChamado::where('fluxo_chamado_id', $data['fluxo_chamado_id'])
                ->orderBy('ordem')->value('id');
        }

        // Fotos: aceita array de data-URI base64 (mesmo formato das coletas) → salva e guarda os paths (G2).
        if (is_array($request->input('fotos'))) {
            $paths = [];
            foreach ($request->input('fotos') as $foto) {
                if (is_string($foto) && str_contains($foto, 'base64,')) {
                    $paths[] = $this->salvarImagemBase64($foto);
                }
            }
            if ($paths) {
                $data['fotos'] = $paths;
            }
        }

        $chamado = Chamado::create($data);

        return response()->json(['data' => $chamado], 201);
    }

    /** GET /api/fluxos-chamado?categoria_id= — fluxos ativos + boletim, para o app renderizar o questionário (G1). */
    public function fluxos(Request $request)
    {
        $tenantId = $this->tenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }

        $q = FluxoChamado::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->orderBy('ordem');

        if ($request->filled('categoria_id')) {
            $q->where('categoria_chamado_id', $request->input('categoria_id'));
        }

        return response()->json([
            'data' => $q->get(['id', 'nome', 'categoria_chamado_id', 'ativo', 'boletim']),
        ]);
    }

    /** GET /api/chamados/{id}/mensagens — cidadão vê só públicas (170). */
    public function mensagens(Request $request, int $id)
    {
        $tenantId = $this->tenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }

        $chamado = Chamado::where('tenant_id', $tenantId)->find($id);
        if (! $chamado) {
            return response()->json(['error' => 'Chamado não encontrado.'], 404);
        }

        $q = MensagemChamado::where('chamado_id', $id)->orderBy('created_at');
        if (! $this->ehFiscal($request, $tenantId)) {
            $q->where('publica', true);
        }

        return response()->json(['data' => $q->get()]);
    }

    /** POST /api/chamados/{id}/mensagens — cidadão manda mensagem (sempre pública). */
    public function enviarMensagem(Request $request, int $id)
    {
        $tenantId = $this->tenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }

        $chamado = Chamado::where('tenant_id', $tenantId)->find($id);
        if (! $chamado) {
            return response()->json(['error' => 'Chamado não encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), ['texto' => 'required|string|min:1|max:2000']);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ehFiscal = $this->ehFiscal($request, $tenantId);

        $msg = MensagemChamado::create([
            'tenant_id' => $tenantId,
            'chamado_id' => $id,
            'user_id' => $request->user()->id,
            'texto' => $request->input('texto'),
            'publica' => $ehFiscal ? (bool) $request->input('publica', true) : true,
        ]);

        return response()->json(['data' => $msg], 201);
    }

    /** Decodifica um data-URI base64 e salva no disco público, devolvendo o path relativo. */
    private function salvarImagemBase64(string $base64String): string
    {
        $imageParts = explode(';base64,', $base64String);
        $imageTypeAux = explode('image/', $imageParts[0]);
        $imageType = $imageTypeAux[1] ?? 'jpeg';
        $imageBase64 = base64_decode($imageParts[1] ?? '');
        $fileName = Str::uuid().'.'.$imageType;
        $filePath = 'chamados_fotos/'.$fileName;

        Storage::disk('public')->put($filePath, $imageBase64);

        return $filePath;
    }
}
