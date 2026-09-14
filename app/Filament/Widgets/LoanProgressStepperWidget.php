<?php

namespace App\Filament\Widgets;

use App\Models\LoanApplication;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class LoanProgressStepperWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.loan-progress-stepper-widget';

    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        // Only display on Edit pages with a record, never on the Dashboard
        return ! request()->routeIs('filament.admin.pages.dashboard');
    }

    public function getStages(): array
    {
        $allStages = [
            ['name' => 'Initial Assessment', 'code' => 'initial_assessment', 'cm_only' => false],
            ['name' => 'For Notary',         'code' => 'for_notary',         'cm_only' => false],
            ['name' => 'Notarized',          'code' => 'notarized',          'cm_only' => false],
            ['name' => 'For BIR',            'code' => 'for_bir',            'cm_only' => false],
            ['name' => 'BIR Paid',           'code' => 'bir_paid',           'cm_only' => false],
            ['name' => 'For ROD',            'code' => 'for_rod',            'cm_only' => false],
            ['name' => 'ROD Released',       'code' => 'rod_released',       'cm_only' => false],
            ['name' => 'For LTO',            'code' => 'for_lto',            'cm_only' => true],
            ['name' => "For Assessor's",     'code' => 'for_assessors',      'cm_only' => false],
            ['name' => 'Encumbered',         'code' => 'encumbered',         'cm_only' => false],
        ];

        $isChattel = $this->record && (
            str_contains(strtolower((string)$this->record->collateral_type), 'chattel') ||
            str_contains(strtolower((string)$this->record->collateral_type), 'cm')
        );

        return array_values(array_filter($allStages, function ($s) use ($isChattel) {
            if ($s['cm_only'] && ! $isChattel) {
                return false;
            }
            return true;
        }));
    }

    public function getCurrentStageIndex(): int
    {
        if (! $this->record) {
            return 0;
        }

        $stages = $this->getStages();
        $currentName = $this->record->status;

        foreach ($stages as $index => $stage) {
            if ($stage['name'] === $currentName) {
                return $index;
            }
        }

        return 0;
    }

    public function advanceToStage(string $targetStage): void
    {
        if (! $this->record) {
            return;
        }

        // Authorization check: branch users cannot advance loan stages
        $user = auth()->user();
        $isBranchUser = $user?->isBranchUser()
            || $user?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
            || in_array($user?->role?->code, ['mortgage_document_specialist', 'branch_manager']);

        if ($isBranchUser) {
            Notification::make()
                ->title('Unauthorized')
                ->body('Branch users cannot advance loan document stages.')
                ->danger()
                ->send();
            return;
        }

        // Whitelist validation: targetStage must be a known stage name
        $validStageNames = array_column($this->getStages(), 'name');
        if (! in_array($targetStage, $validStageNames, true)) {
            Notification::make()
                ->title('Invalid Stage')
                ->body('The selected stage is not valid.')
                ->danger()
                ->send();
            return;
        }

        $oldStatus = $this->record->status;
        $this->record->status = $targetStage;
        $this->record->status_changed_at = now();
        $this->record->save();

        Notification::make()
            ->title('Stage Updated')
            ->body("Application stage advanced from '{$oldStatus}' to '{$targetStage}'.")
            ->success()
            ->send();

        // Safe redirect — do NOT use request()->header('Referer') as it is attacker-controlled
        $this->redirect(url()->current());
    }
}
