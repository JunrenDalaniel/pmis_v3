<?php

namespace App\Listeners;

use App\Models\AccountLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

class AuthenticationLogSubscriber
{
    public function handleLogin(Login $event): void
    {
        $ip = request()?->ip() ?? '127.0.0.1';
        $userAgent = request()?->userAgent();
        $device = $this->parseUserAgent($userAgent);
        $host = $this->resolveClientHost($ip);
        $isp = $this->resolveIsp($ip);
        $sessionId = session()?->getId();

        // Prevent duplicate login log creation if event fired within the last 5 seconds for the same session
        if ($sessionId && AccountLog::where('session_id', $sessionId)->where('created_at', '>=', now()->subSeconds(5))->exists()) {
            return;
        }

        // Auto-close previous active sessions for this user to avoid stale duplicate records
        if ($event->user) {
            AccountLog::where('user_id', $event->user->id)
                ->where('status', 'active')
                ->whereNull('logout_at')
                ->when($sessionId, fn ($q) => $q->where('session_id', '!=', $sessionId))
                ->update([
                    'logout_at' => now(),
                    'status' => 'logged_out',
                ]);
        }

        $roleName = $event->user?->role?->name ?? $event->user?->roles?->first()?->name;
        if ($roleName === 'super_admin') {
            $roleName = 'Super Admin';
        }

        AccountLog::create([
            'user_id' => $event->user?->id,
            'user_name' => $event->user?->name,
            'user_email' => $event->user?->email,
            'role_name' => $roleName ?? ($event->user ? 'Staff' : null),
            'branch_office_id' => $event->user?->branch_office_id,
            'branch_name' => $event->user?->branchOffice?->name ?? 'Head Office / System',
            'ip_address' => $ip,
            'isp' => $isp,
            'host' => $host,
            'device' => $device,
            'user_agent' => $userAgent,
            'session_id' => $sessionId,
            'login_at' => now(),
            'status' => 'active',
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        $sessionId = session()?->getId();
        $log = null;

        if ($sessionId) {
            $log = AccountLog::where('session_id', $sessionId)
                ->where('status', 'active')
                ->latest('login_at')
                ->first();
        }

        if (! $log && $event->user) {
            $log = AccountLog::where('user_id', $event->user->id)
                ->where('status', 'active')
                ->latest('login_at')
                ->first();
        }

        if ($log) {
            $log->update([
                'logout_at' => now(),
                'status' => 'logged_out',
            ]);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $ip = request()?->ip() ?? '127.0.0.1';
        $userAgent = request()?->userAgent();
        $device = $this->parseUserAgent($userAgent);
        $host = $this->resolveClientHost($ip);
        $isp = $this->resolveIsp($ip);

        $email = $event->credentials['email'] ?? $event->user?->email ?? 'Unknown';
        $user = $event->user;

        $failedRole = $user?->role?->name ?? $user?->roles?->first()?->name;
        if ($failedRole === 'super_admin') {
            $failedRole = 'Super Admin';
        }

        AccountLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Unregistered / Unknown',
            'user_email' => $email,
            'role_name' => $failedRole ?? ($user ? 'Staff' : 'Unregistered / Guest'),
            'branch_office_id' => $user?->branch_office_id,
            'branch_name' => $user?->branchOffice?->name ?? 'Unknown / N/A',
            'ip_address' => $ip,
            'isp' => $isp,
            'host' => $host,
            'device' => $device,
            'user_agent' => $userAgent,
            'session_id' => session()?->getId(),
            'login_at' => now(),
            'status' => 'failed',
            'failure_reason' => $user ? 'Incorrect Password' : 'Invalid Credentials',
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
        ];
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

    protected function resolveClientHost(?string $ip): string
    {
        $ip = $ip ?? '127.0.0.1';

        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
            return gethostname() ?: 'Localhost';
        }

        $resolved = @gethostbyaddr($ip);
        if ($resolved && $resolved !== $ip) {
            return $resolved;
        }

        return $ip;
    }

    protected function resolveIsp(?string $ip): string
    {
        if (empty($ip) || in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
            return 'Localhost (Dev)';
        }

        // Return immediately for private/local network ranges without network calls
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Local Network / LAN';
        }

        // Cache public IP ISP lookups for 24 hours
        return \Illuminate\Support\Facades\Cache::remember("ip_isp_{$ip}", 86400, function () use ($ip) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(1.5)->get("https://ip-api.com/json/{$ip}?fields=status,isp,org");
                if ($response->successful() && $response->json('status') === 'success') {
                    return $response->json('isp') ?: $response->json('org') ?: 'Internet Provider';
                }
            } catch (\Throwable $e) {
                // Graceful fallback on network timeout
            }

            return 'Internet Provider';
        });
    }
}
