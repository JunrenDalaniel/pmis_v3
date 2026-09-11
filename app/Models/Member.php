<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_no',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birth_date',
        'place_of_birth',
        'sex',
        'civil_status',
        'tin',
        'sss_gsis',
        'email',
        'phone',
        'address',
        'occupation',
        'office_number',
        'employer_name',
        'employment_status',
        'office_address',
        'remarks',
        'branch_office_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'birth_date' => 'date',
    ];

    protected $appends = [
        'full_name',
    ];

    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setMiddleNameAttribute($value): void
    {
        $this->attributes['middle_name'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setSuffixAttribute($value): void
    {
        $this->attributes['suffix'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = filled($value) ? mb_strtolower(trim((string) $value)) : null;
    }

    public function setAddressAttribute($value): void
    {
        $this->attributes['address'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function getFullNameAttribute(): string
    {
        $fullName = "{$this->last_name}, {$this->first_name}";
        if ($this->middle_name) {
            $fullName .= " {$this->middle_name}";
        }
        if ($this->suffix) {
            $fullName .= " {$this->suffix}";
        }
        return trim($fullName);
    }
}
