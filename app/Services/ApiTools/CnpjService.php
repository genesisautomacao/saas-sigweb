<?php

namespace App\Services\ApiTools;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CnpjService
{

    /**
     * Consulta um CNPJ na API comercial  https://cnpja.com/
     * Plano pago
     * email de cadastro: contato@organosi.com.br
     * @param string $cnpj
     * @return array|null
     */

    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        // A chave de API é colocada diretamente aqui
        $this->apiKey = '77f7f7ac-1dd3-4539-bdbf-9a19792dd1c4-10573878-9ae3-45bd-bef5-4be2d75d6a18';

        // A URL base da API comercial
        $this->baseUrl = 'https://api.cnpja.com';
    }

    public function query(string $cnpj): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('CNPJá Service: API Key não configurada.');
            return ['error' => 'API Key não configurada.'];
        }

        $cleanedCnpj = preg_replace('/\D/', '', $cnpj);
        $url = "{$this->baseUrl}/office/{$cleanedCnpj}?registrations=BR";

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey
        ])->get($url);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('Falha na consulta à API CNPJá', [
            'status' => $response->status(),
            'response' => $response->body()
        ]);

        // Retorna um array com a chave 'error' para a modal saber que falhou
        return ['error' => 'Falha na consulta: ' . $response->status()];
    }
}