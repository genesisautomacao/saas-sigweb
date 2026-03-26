<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\SolicitacaoManutencao;
use App\Models\Arvore;

class SolicitacaoManutencaoSyncController extends Controller
{
    public function pull(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()->id;
        
        $solicitacoes = SolicitacaoManutencao::with('asset')
            ->where('tenant_id', $tenantId)
            ->get()
            ->map(function($sol) {
                
                $assetTypeApp = null;
                if ($sol->asset_type === 'App\Models\Arvore') {
                    $assetTypeApp = 'arvore';
                }

                return [
                    'id' => $sol->code, 
                    'asset_type' => $assetTypeApp,
                    'asset_code' => $sol->asset ? $sol->asset->code : null, 
                    'tipo_servico' => $sol->tipo_servico,
                    'prioridade' => $sol->prioridade,
                    'status' => $sol->status,
                    'observacao' => $sol->observacao,
                    'solicitante_nome' => $sol->solicitante_nome,
                    // Devolve o caminho da foto para o app (caso precisem exibir lá)
                    'foto_ocorrencia' => $sol->foto_ocorrencia,
                ];
            });

        return response()->json([
            'changes' => [
                'solicitacoes_manutencao' => [
                    'created' => $solicitacoes,
                    'updated' => [],
                    'deleted' => [],
                ]
            ],
            'timestamp' => now()->timestamp,
        ]);
    }

    public function push(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()->id;
        $changes = $request->input('changes'); 

        if (!isset($changes['solicitacoes_manutencao'])) {
            return response()->json(['message' => 'Nada para sincronizar'], 200);
        }

        DB::beginTransaction();

        try {
            // 1. CRIADOS
            if (!empty($changes['solicitacoes_manutencao']['created'])) {
                foreach ($changes['solicitacoes_manutencao']['created'] as $solApp) {
                    
                    $asset = null;
                    $assetClass = null;

                    if ($solApp['asset_type'] === 'arvore') {
                        $asset = Arvore::where('tenant_id', $tenantId)->where('code', $solApp['asset_code'])->first();
                        $assetClass = Arvore::class;
                    }

                    if ($asset) {
                        $solicitacao = new SolicitacaoManutencao();
                        $solicitacao->tenant_id = $tenantId;
                        $solicitacao->code = $solApp['id']; 
                        
                        $solicitacao->asset_type = $assetClass; 
                        $solicitacao->asset_id = $asset->id;
                        
                        $solicitacao->tipo_servico = $solApp['tipo_servico'] ?? 'Manutenção';
                        $solicitacao->prioridade = $solApp['prioridade'] ?? 'media';
                        $solicitacao->status = $solApp['status'] ?? 'pendente';
                        $solicitacao->observacao = $solApp['observacao'] ?? null;
                        $solicitacao->solicitante_nome = !empty($solApp['solicitante_nome']) ? $solApp['solicitante_nome'] : $request->user()->name;

                        // 🛑 TRATAMENTO DA FOTO EM BASE64
                        if (!empty($solApp['foto_ocorrencia']) && str_starts_with($solApp['foto_ocorrencia'], 'data:image')) {
                            $solicitacao->foto_ocorrencia = $this->salvarImagemBase64($solApp['foto_ocorrencia']);
                        }

                        $solicitacao->save();
                    }
                }
            }

            // 2. ATUALIZADOS
            if (!empty($changes['solicitacoes_manutencao']['updated'])) {
                foreach ($changes['solicitacoes_manutencao']['updated'] as $solApp) {
                    $solicitacao = SolicitacaoManutencao::where('tenant_id', $tenantId)
                                    ->where('code', $solApp['id'])
                                    ->first();

                    if ($solicitacao) {
                        $solicitacao->tipo_servico = $solApp['tipo_servico'] ?? $solicitacao->tipo_servico;
                        $solicitacao->prioridade = $solApp['prioridade'] ?? $solicitacao->prioridade;
                        $solicitacao->status = $solApp['status'] ?? $solicitacao->status;
                        $solicitacao->observacao = $solApp['observacao'] ?? $solicitacao->observacao;
                        
                        // 🛑 TRATAMENTO DA FOTO EM BASE64 (Caso alterem a foto)
                        if (!empty($solApp['foto_ocorrencia']) && str_starts_with($solApp['foto_ocorrencia'], 'data:image')) {
                            // Opcional: Aqui poderíamos deletar a foto antiga com Storage::disk('public')->delete($solicitacao->foto_ocorrencia)
                            $solicitacao->foto_ocorrencia = $this->salvarImagemBase64($solApp['foto_ocorrencia']);
                        }

                        $solicitacao->save();
                    }
                }
            }

            // 3. DELETADOS
            if (!empty($changes['solicitacoes_manutencao']['deleted'])) {
                foreach ($changes['solicitacoes_manutencao']['deleted'] as $solAppId) {
                    $solicitacao = SolicitacaoManutencao::where('tenant_id', $tenantId)
                                    ->where('code', $solAppId)
                                    ->first();

                    if ($solicitacao) {
                        $solicitacao->delete();
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Função privada para decodificar e salvar o arquivo
     */
    private function salvarImagemBase64($base64String)
    {
        // Separa o cabeçalho ("data:image/jpeg;base64,") do conteúdo real
        $imageParts = explode(";base64,", $base64String);
        
        // Pega a extensão da imagem do cabeçalho
        $imageTypeAux = explode("image/", $imageParts[0]);
        $imageType = $imageTypeAux[1] ?? 'jpeg'; // default para jpeg se der erro

        $imageBase64 = base64_decode($imageParts[1]);

        // Gera um nome único para o arquivo
        $fileName = Str::uuid() . '.' . $imageType;
        
        // O mesmo diretório usado pelo Filament
        $filePath = 'solicitacoes_fotos/' . $fileName;

        // Salva fisicamente no disco public
        Storage::disk('public')->put($filePath, $imageBase64);

        return $filePath;
    }
}