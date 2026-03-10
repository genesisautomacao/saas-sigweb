<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OsMaterial extends Model
{
    use HasFactory;

    protected $table = 'os_materiais';
    
    // Desativa os timestamps nativos se não existirem na migration
    public $timestamps = false; 

    protected $fillable = [
        'ordem_servico_id',
        'produto_id',
        'local_estoque_id',
        'quantidade',
    ];

    protected $casts = [
        'quantidade' => 'decimal:2',
    ];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function localEstoque()
    {
        return $this->belongsTo(LocalEstoque::class, 'local_estoque_id');
    }
}