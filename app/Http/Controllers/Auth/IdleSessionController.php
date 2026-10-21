<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountLog;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdleSessionController extends Controller
{
    /**
     * Heartbeat ping to keep session alive when user confirms they are still active.
     */
    public function keepAlive(Request $request): JsonResponse
    {
        // Touch session
        $request->session()->put('last_keep_alive', now()->timestamp);

        return response()->json([
            'status' => 'alive',
            'csrf_token' => csrf_token(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Terminate the session due to inactivity timeout.
     */
    public function idleLogout(Request $request): JsonResponse
    {
        $user = auth()->user();
        $sessionId = session()?->getId();

        // 1. Locate active account log
        $log = null;
        if ($sessionId) {
            $log = AccountLog::where('session_id', $sessionId)
                ->where('status', 'active')
                ->latest('login_at')
                ->first();
        }

        if (! $log && $user) {
            $log = AccountLog::where('user_id', $user->id)
                ->where('status', 'active')
                ->latest('login_at')
                ->first();
        }

        // 2. Mark account log as timed_out
        if ($log) {
            $log->update([
                'logout_at' => now(),
                'status' => 'timed_out',
                'failure_reason' => 'Auto-Logout: 15-minute Inactivity Timeout',
            ]);
        }

        // 3. Terminate Filament auth & invalidate session
        Filament::auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 4. Flash banner message for login page
        session()->flash(
            'inactivity_message',
            'Ang iyong session ay natapos dahil sa 15 minutong kawalan ng aktibidad (Inactivity Timeout). Mangyaring mag-log in muli.'
        );

        return response()->json([
            'success' => true,
            'redirect' => filament()->getLoginUrl(),
        ]);
    }
}
