<?php

namespace App\Filament\Widgets;

use App\Models\LoanApplication;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PendingApprovalSoundWidget extends Widget
{
    // Non-static: matches the parent Widget class declaration
    protected string $view = 'filament.widgets.pending-approval-sound-widget';

    protected static bool $isLazy = false;

    // Sort last — this widget has no visible UI, purely functional
    protected static ?int $sort = 999;

    // Tracks the last known count to detect increases
    public int $lastCount = 0;

    // Sound configuration passed to the Blade/Alpine layer
    public array $soundConfig = [];

    public function mount(): void
    {
        // Initialize with current count so we do NOT sound on first page load
        $this->lastCount = $this->getPendingCount();
        $this->soundConfig = self::loadSoundConfig();
    }

    /**
     * Load sound preference from storage.
     * Returns array with keys: type (preset|custom), preset, custom_url.
     */
    public static function loadSoundConfig(): array
    {
        $defaults = [
            'type'       => 'preset',
            'preset'     => 'classic_chime',
            'custom_url' => null,
        ];

        try {
            if (Storage::disk('local')->exists('pmis-sound-pref.json')) {
                $stored = json_decode(Storage::disk('local')->get('pmis-sound-pref.json'), true);
                return array_merge($defaults, $stored ?? []);
            }
        } catch (\Throwable) {}

        return $defaults;
    }

    /**
     * Called by Livewire wire:poll every 30 seconds.
     * If the count has gone up, dispatch a chime event and show a toast.
     */
    public function checkForNewPending(): void
    {
        $currentCount = $this->getPendingCount();

        if ($currentCount > $this->lastCount) {
            $newCount = $currentCount - $this->lastCount;

            // Dispatch browser event for the sound (handled in Alpine/Blade)
            $this->dispatch('new-pending-approval', count: $newCount, total: $currentCount);

            // Filament toast notification
            $label = $newCount === 1
                ? '1 bagong loan application ang naghihintay ng approval.'
                : "{$newCount} bagong loan applications ang naghihintay ng approval.";

            Notification::make()
                ->title('🔔 Pending Approval Alert')
                ->body($label)
                ->warning()
                ->persistent()
                ->send();
        }

        $this->lastCount = $currentCount;
    }

    protected function getPendingCount(): int
    {
        return LoanApplication::where('approval_status', 'Pending Approval')->count();
    }

    /**
     * Only HO users (getAllowedBranchIds() === null) can see/hear this widget.
     */
    public static function canView(): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        return $user->getAllowedBranchIds() === null;
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }
}