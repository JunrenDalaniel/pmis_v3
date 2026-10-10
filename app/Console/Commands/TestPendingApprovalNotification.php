<?php

namespace App\Console\Commands;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class TestPendingApprovalNotification extends Command
{
    protected $signature = 'pmis:test-notification {--user= : User ID to send to (default: first HO user)}';
    protected $description = 'Send a test Pending Approval notification to verify the sound widget works.';

    public function handle(): int
    {
        // Find a Head Office user to send notification to
        $userId = $this->option('user');

        if ($userId) {
            $user = User::find($userId);
        } else {
            // Find first user where getAllowedBranchIds() returns null (HO user)
            $user = User::all()->first(fn($u) => method_exists($u, 'getAllowedBranchIds') && $u->getAllowedBranchIds() === null);
        }

        if (!$user) {
            $this->error('No Head Office user found. Use --user=ID to specify a user ID.');
            return self::FAILURE;
        }

        $this->info("Sending test notification to: {$user->name} (ID: {$user->id})");

        Notification::make()
            ->title('🔔 [TEST] Pending Approval Alert')
            ->body('Ito ay test notification. Kapag may tunog, gumagana na ang notification sound!')
            ->warning()
            ->persistent()
            ->sendToDatabase($user);

        $this->info('✅ Test notification sent! Buksan ang admin panel at tignan ang bell icon.');
        $this->info('   URL: http://localhost/pmis_v3/public/admin');

        return self::SUCCESS;
    }
}