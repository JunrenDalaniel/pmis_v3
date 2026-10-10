<?php

namespace App\Observers;

use Spatie\Activitylog\Models\Activity;

class ActivityObserver
{
    public function creating(Activity $activity): void
    {
        $ip = request()?->ip();
        $userAgent = request()?->userAgent();

        $branchName = null;
        $branchOfficeId = null;

        if (!empty($activity->subject_type) && class_exists($activity->subject_type)) {
            try {
                if ($activity->subject) {
                    if (isset($activity->subject->branch_office_id)) {
                        $branchOfficeId = $activity->subject->branch_office_id;
                    }
                    if ($activity->subject->branchOffice) {
                        $branchName = $activity->subject->branchOffice->name;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore missing or detached subject
            }
        }

        if (! $branchOfficeId) {
            $user = auth()->user();
            if ($user && isset($user->branch_office_id)) {
                $branchOfficeId = $user->branch_office_id;
                $branchName = $user->branchOffice?->name;
            }
        }

        if (! $branchOfficeId && $branchName) {
            $branchOfficeId = \App\Models\BranchOffice::where('name', $branchName)->value('id');
        }

        $device = $this->parseUserAgent($userAgent);

        $currentUser = auth()->user();
        $targetUser = $currentUser ?? ($activity->causer instanceof \App\Models\User ? $activity->causer : null);
        $roleName = $targetUser?->role?->name ?? $targetUser?->roles?->first()?->name;
        if ($roleName === 'super_admin') {
            $roleName = 'Super Admin';
        }

        $extra = [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'client_device' => $device,
            'branch' => $branchName ?? 'Head Office / System',
            'branch_office_id' => $branchOfficeId,
            'role' => $roleName ?? ($targetUser ? 'Staff' : 'System'),
        ];

        $currentProps = $activity->properties ? $activity->properties->toArray() : [];
        $activity->properties = collect(array_merge($currentProps, $extra));
    }

    protected function parseUserAgent(?string $ua): string
    {
        if (empty($ua)) {
            return app()->runningInConsole() ? 'CLI / Artisan' : 'Unknown Device';
        }

        $browser = 'Browser';
        if (str_contains($ua, 'Edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($ua, 'Chrome/')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'Firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/')) {
            $browser = 'Safari';
        } elseif (str_contains($ua, 'MSIE') || str_contains($ua, 'Trident/')) {
            $browser = 'Internet Explorer';
        }

        $os = 'Unknown OS';
        if (str_contains($ua, 'Windows NT 10.0')) {
            $os = 'Windows 10/11';
        } elseif (str_contains($ua, 'Windows NT 6.3')) {
            $os = 'Windows 8.1';
        } elseif (str_contains($ua, 'Windows NT 6.1')) {
            $os = 'Windows 7';
        } elseif (str_contains($ua, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
            $os = 'iOS';
        } elseif (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS X')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'Linux')) {
            $os = 'Linux';
        }

        return "{$browser} on {$os}";
    }
}
