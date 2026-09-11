<?php

namespace App\Observers;

use App\Models\LoanApplication;
use Illuminate\Support\Facades\Cache;

class LoanApplicationObserver
{
    /**
     * Clear all cached loan counts and statistics whenever a loan application is created, updated, or deleted.
     */
    public function saved(LoanApplication $loanApplication): void
    {
        $this->clearLoanCaches();
    }

    public function deleted(LoanApplication $loanApplication): void
    {
        $this->clearLoanCaches();
    }

    public function restored(LoanApplication $loanApplication): void
    {
        $this->clearLoanCaches();
    }

    protected function clearLoanCaches(): void
    {
        Cache::forget('badge_pending_approval_count');
        Cache::forget('badge_approved_count');
        Cache::forget('badge_disapproved_count');
        Cache::forget('approved_loan_tab_counts');
    }
}
