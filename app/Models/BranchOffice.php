<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Member;
use App\Models\LoanApplication;

class BranchOffice extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'manager_name',
        'manager_user_id',
        'manager_email',
        'manager_phone_no',
        'area',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function setManagerNameAttribute($value): void
    {
        $this->attributes['manager_name'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setManagerEmailAttribute($value): void
    {
        $this->attributes['manager_email'] = filled($value) ? mb_strtolower(trim((string) $value)) : null;
    }

    public function managerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class, 'branch_office_id');
    }

    public function assignedUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branch_office', 'branch_office_id', 'user_id');
    }
}
