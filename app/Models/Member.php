<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\LoanApplication;

class Member extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('member')
            ->setDescriptionForEvent(fn(string $eventName) => "Member record {$this->full_name} ({$this->member_no}) was {$eventName}");
    }

    protected $fillable = [
        'member_no',
        'cid',
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
        $this->attributes['first_name'] = filled($value) ? Str::title(mb_strtolower(trim(rtrim((string) $value, " \t\n\r\0\x0B,")))) : null;
    }

    public function setMiddleNameAttribute($value): void
    {
        $this->attributes['middle_name'] = filled($value) ? Str::title(mb_strtolower(trim(rtrim((string) $value, " \t\n\r\0\x0B,")))) : null;
    }

    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = filled($value) ? Str::title(mb_strtolower(trim(rtrim((string) $value, " \t\n\r\0\x0B,")))) : null;
    }

    public function setSuffixAttribute($value): void
    {
        $this->attributes['suffix'] = filled($value) ? Str::title(mb_strtolower(trim(rtrim((string) $value, " \t\n\r\0\x0B,")))) : null;
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
        $lastName = rtrim(trim((string) $this->last_name), ',');
        $firstName = trim((string) $this->first_name);
        $parts = array_filter([$lastName, $firstName]);
        $fullName = implode(', ', $parts);
        if ($this->middle_name) {
            $fullName .= " {$this->middle_name}";
        }
        if ($this->suffix) {
            $fullName .= " {$this->suffix}";
        }
        $cleaned = preg_replace('/,{2,}/', ',', trim($fullName));
        return $cleaned ?: '—';
    }

    public function getNaturalFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ]);
        return implode(' ', $parts);
    }
}
