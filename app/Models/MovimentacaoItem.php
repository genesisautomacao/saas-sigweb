<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimentacaoItem extends Model
{
    use HasFactory;

    protected $table = 'movimentacao_itens';

    protected $fillable = [
        'movimentacao_id',
        'produto_id',
        'quantity',
        'unitary_value',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unitary_value' => 'decimal:2',
    ];

    public function movimentacao()
    {
        return $this->belongsTo(EstoqueMovimentacao::class, 'movimentacao_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}