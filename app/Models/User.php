<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'branch_office_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->role_id && ($user->wasChanged('role_id') || ! $user->roles()->exists())) {
                $role = Role::find($user->role_id);
                if ($role) {
                    $user->syncRoles([$role->name]);
                }
            }
        });
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = filled($value) ? mb_strtolower(trim((string) $value)) : null;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class, 'branch_office_id');
    }

    public function branches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(BranchOffice::class, 'user_branch_office', 'user_id', 'branch_office_id');
    }

    /**
     * Get array of branch IDs this user is authorized to access (Security Shield).
     * Returns null if user has nationwide access across all branches.
     */
    public function getAllowedBranchIds(): ?array
    {
        // Head Office / Super Admin roles see ALL branches (return null = nationwide access)
        if ($this->hasRole(['super_admin', 'Super Admin']) || in_array($this->role?->code, ['super_admin', 'operation_division_manager', 'chief_executive_officer', 'audit', 'compliance_specialist', 'appraiser'])) {
            return null;
        }

        // 1. Multi-Branch Pivot Assignments
        $pivotBranches = $this->branches()->pluck('branch_offices.id')->toArray();
        if (!empty($pivotBranches)) {
            return array_map('intval', $pivotBranches);
        }

        // 2. Area Manager Role Fallback (Area 1, Area 2, Area 3)
        if ($this->hasRole(['Area Manager 1 (AM1)', 'area_manager_1']) || $this->role?->code === 'area_manager_1') {
            return BranchOffice::where('area', 'like', '%1%')->pluck('id')->toArray();
        }
        if ($this->hasRole(['Area Manager 2 (AM2)', 'area_manager_2']) || $this->role?->code === 'area_manager_2') {
            return BranchOffice::where('area', 'like', '%2%')->pluck('id')->toArray();
        }
        if ($this->hasRole(['Area Manager 3 (AM3)', 'area_manager_3']) || $this->role?->code === 'area_manager_3') {
            return BranchOffice::where('area', 'like', '%3%')->pluck('id')->toArray();
        }

        // 3. Single Primary Branch Assignment
        if ($this->branch_office_id) {
            return [(int) $this->branch_office_id];
        }

        return [];
    }

    public function isBranchUser(): bool
    {
        return $this->role?->scope === 'branch';
    }

    public function isHeadOfficeUser(): bool
    {
        return $this->role?->scope === 'head_office';
    }
}
