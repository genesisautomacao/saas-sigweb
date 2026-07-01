<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PessoaDeficiencia extends Model
{
    use BelongsToTenant;

    protected $table = 'pessoa_deficiencias';

    protected $fillable = [
        'tenant_id', 'pessoa_id', 'tipo', 'cid', 'descricao',
    ];

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class);
    }
}
