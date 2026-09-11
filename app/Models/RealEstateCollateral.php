<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RealEstateCollateral extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id',
        'title_details',
        'title_number',
        'title_owner_name',
        'location',
        'rod_branch_id',
        'tax_dec_land',
        'land_owner_name',
        'pin_land',
        'building_tax_declarations',
        'tax_dec_bldg',
        'bldg_owner_name',
        'pin_bldg',
        'rem_status',
    ];

    protected $casts = [
        'building_tax_declarations' => 'array',
    ];

    public function rodBranch(): BelongsTo
    {
        return $this->belongsTo(RodBranch::class);
    }

    public function setTitleNumberAttribute($value): void
    {
        $this->attributes['title_number'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function setTitleOwnerNameAttribute($value): void
    {
        $this->attributes['title_owner_name'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setLandOwnerNameAttribute($value): void
    {
        $this->attributes['land_owner_name'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setBldgOwnerNameAttribute($value): void
    {
        $this->attributes['bldg_owner_name'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setTaxDecLandAttribute($value): void
    {
        $this->attributes['tax_dec_land'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function setTaxDecBldgAttribute($value): void
    {
        $this->attributes['tax_dec_bldg'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function setPinLandAttribute($value): void
    {
        $this->attributes['pin_land'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function setPinBldgAttribute($value): void
    {
        $this->attributes['pin_bldg'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
