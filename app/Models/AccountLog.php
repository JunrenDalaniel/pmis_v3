<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'role_name',
        'branch_office_id',
        'branch_name',
        'ip_address',
        'isp',
        'host',
        'device',
        'user_agent',
        'session_id',
        'login_at',
        'logout_at',
        'status',
        'failure_reason',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' && is_null($this->logout_at);
    }

    public function getDurationAttribute(): ?string
    {
        if ($this->status === 'failed' || ! $this->login_at) {
            return '—';
        }

        $end = $this->logout_at ?? now();
        $diff = $this->login_at->diff($end);

        $hours = $diff->h + ($diff->days * 24);
        $minutes = $diff->i;

        $formatted = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";

        if ($this->is_active) {
            return "Active ({$formatted})";
        }

        return $formatted;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->whereNull('logout_at');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('login_at', today());
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
