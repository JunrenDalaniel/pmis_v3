<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HandlesApprovalWorkflow
{
    /**
     * Mutate form data before saving on any Edit page using this workflow.
     * Enforces branch-level restrictions: branch cannot alter sub_status or approval_status.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $currentUser = auth()->user();
        $isBranchUser = $currentUser?->isBranchUser()
            || $currentUser?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
            || in_array($currentUser?->role?->code, ['mortgage_document_specialist', 'branch_manager']);

        if ($isBranchUser && isset($this->record)) {
            // Branch accounts strictly cannot update stage condition / sub-status
            $data['sub_status'] = $this->record->sub_status ?? 'none';

            // Auto-transition Approved → Branch Updated when edited by Branch Specialist
            $isBranchSpecialist = $currentUser?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist']) || $currentUser?->role?->code === 'mortgage_document_specialist';
            if ($isBranchSpecialist && $this->record->approval_status === 'Approved') {
                $data['approval_status'] = 'Branch Updated';
            } else {
                // Branch accounts can ONLY transition Approved → Branch Updated
                // All other approval_status changes are locked to current value
                $allowedTransitions = ['Branch Updated'];
                if ($this->record->approval_status === 'Approved'
                    && in_array($data['approval_status'] ?? null, $allowedTransitions, true)) {
                    // Allow this specific transition
                } else {
                    $data['approval_status'] = $this->record->approval_status;
                }
            }
        }

        return $data;
    }

    /**
     * Called after any save on an Edit page.
     * Detects approval_status changes and sends Filament notifications
     * directly to the DB bell panel (bypasses queue driver).
     */
    protected function afterSave(): void
    {
        $record = $this->record;
        $currentUser = auth()->user();

        // ── Save branch_comment to notes history if provided ──────────────────
        if (filled($this->data['branch_comment'] ?? null)) {
            $isBranch = $currentUser?->isBranchUser()
                || $currentUser?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                || in_array($currentUser?->role?->code, ['mortgage_document_specialist', 'branch_manager']);

            $sourceLabel = $isBranch ? 'Branch Response' : 'HO Evaluator';

            $record->applicationNotes()->create([
                'user_id' => $currentUser?->id ?? $record->created_by_user_id,
                'stage'   => ($record->status ?? 'General') . " ({$sourceLabel})",
                'note'    => $this->data['branch_comment'],
            ]);
            $this->data['branch_comment'] = '';
        }

        if (! $record->wasChanged('approval_status')) {
            return;
        }

        // ── Send DB bell notification via shared static helper ─────────────────
        static::sendApprovalNotification($record, auth()->user());
    }

    /**
     * Send a persistent DB bell notification when approval_status changes.
     * Can be called from Edit page afterSave() OR directly from table actions.
     *
     * @param  \App\Models\LoanApplication  $record
     * @param  \App\Models\User|null  $currentUser
     */
    public static function sendApprovalNotification($record, ?User $currentUser): void
    {
        $newStatus = $record->approval_status;
        $appNo     = $record->application_no ?? "ID #{$record->id}";
        $borrower  = $record->member?->full_name ?? 'Unknown Borrower';

        // ── Build notification content based on new status ─────────────────
        [$title, $body, $icon, $color] = match ($newStatus) {
            'Approved' => [
                '✅ Loan Application Approved',
                "Application {$appNo} for {$borrower} has been APPROVED and moved to Released Loans.",
                'heroicon-o-check-circle',
                'success',
            ],
            'Disapproved' => [
                '❌ Loan Application Disapproved',
                "Application {$appNo} for {$borrower} has been DISAPPROVED. Check the Application Notes for the reason.",
                'heroicon-o-x-circle',
                'danger',
            ],
            'Pending Approval' => [
                '⏳ Resubmitted for Evaluation',
                "Application {$appNo} for {$borrower} has been returned to the FOR APPROVAL queue for re-evaluation.",
                'heroicon-o-clock',
                'warning',
            ],
            'Branch Updated' => [
                '🔔 Branch Has Updated the Loan',
                "Application {$appNo} for {$borrower} has been updated by the branch and is awaiting HO review.",
                'heroicon-o-arrow-path',
                'warning',
            ],
            default => [null, null, null, null],
        };

        if (! $title) {
            return;
        }

        // Collect recipients: current user + the record creator (if different)
        $recipients = collect();

        if ($currentUser) {
            $recipients->push($currentUser);
        }

        if ($record->created_by_user_id && $record->created_by_user_id !== $currentUser?->id) {
            $creator = User::find($record->created_by_user_id);
            if ($creator) {
                $recipients->push($creator);
            }
        }

        // Build Filament notification data payload
        $data = [
            'actions'   => [],
            'body'      => $body,
            'color'     => $color,
            'duration'  => 'persistent',
            'icon'      => $icon,
            'iconColor' => null,
            'status'    => null,
            'title'     => $title,
            'view'      => null,
            'viewData'  => [],
            'format'    => 'filament',
        ];

        $now = now();

        // Insert directly into the notifications table (bypasses queue)
        foreach ($recipients as $recipient) {
            DB::table('notifications')->insert([
                'id'              => Str::uuid()->toString(),
                'type'            => DatabaseNotification::class,
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id'   => $recipient->id,
                'data'            => json_encode($data),
                'read_at'         => null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }
}
