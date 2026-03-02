<?php

namespace App\Models;

use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class Lead extends Model
{
    use HasFactory;
    use HasTenantSequentialId;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'sequential_id', 'name', 'surname', 'document',
        'cnae_code', 'cnae_name', 'contact_name', 'email', 'phone', 'whatsapp',
        'zip_code', 'address', 'number', 'complement', 'neighborhood', 'city', 'state',
        'latitude', 'longitude', 'seller_id', 'lead_status_id', 'lead_potential_id', 
        'lead_source_id', 'notes', 'last_follow_up_note', 'last_follow_up_date',
    ];

    protected function casts(): array
    {
        return [
            'last_follow_up_date' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'lead_status_id');
    }

    public function potential(): BelongsTo
    {
        return $this->belongsTo(LeadPotential::class, 'lead_potential_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }
}