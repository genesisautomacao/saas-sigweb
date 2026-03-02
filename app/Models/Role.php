<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    // Dizemos ao Filament a qual transportadora esse papel pertence
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}