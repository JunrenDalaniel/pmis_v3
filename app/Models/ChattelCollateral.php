<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChattelCollateral extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id',
        'owner_name',
        'mv_file_no',
        'plate_no',
        'engine_no',
        'chassis_no',
        'year_model',
        'vehicle_description',
        'encumbered_to',
        'registration_renewal_date',
        'expire_date',
        'cm_status',
    ];

    protected $casts = [
        'registration_renewal_date' => 'date',
        'expire_date' => 'date',
    ];

    public function setOwnerNameAttribute($value): void
    {
        $this->attributes['owner_name'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setVehicleDescriptionAttribute($value): void
    {
        $this->attributes['vehicle_description'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setEncumberedToAttribute($value): void
    {
        if (blank($value)) {
            $this->attributes['encumbered_to'] = 'NEW';
            return;
        }

        $val = mb_strtoupper(trim((string) $value));

        $this->attributes['encumbered_to'] = match ($val) {
            'OIC ENCUMBERED', 'ENCUMBERED' => 'OIC ENCUMBERED',
            'REL/E', 'RELE' => 'REL/E',
            default => 'NEW',
        };
    }

    public function setPlateNoAttribute($value): void
    {
        $this->attributes['plate_no'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function setMvFileNoAttribute($value): void
    {
        $this->attributes['mv_file_no'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function setEngineNoAttribute($value): void
    {
        $this->attributes['engine_no'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function setChassisNoAttribute($value): void
    {
        $this->attributes['chassis_no'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
