<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class LoanApplication extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('loan_application')
            ->setDescriptionForEvent(function (string $eventName) {
                if ($this->wasChanged('approval_status') || $this->isDirty('approval_status')) {
                    if ($this->approval_status === 'Approved') {
                        return "Loan application {$this->application_no} was APPROVED";
                    }
                    if ($this->approval_status === 'Disapproved') {
                        return "Loan application {$this->application_no} was DISAPPROVED";
                    }
                }

                return "Loan application {$this->application_no} was {$eventName}";
            });
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('primary_loan_documents');
        $this->addMediaCollection('kyc_borrower_documents');
        $this->addMediaCollection('rem_collateral_documents');
        $this->addMediaCollection('cm_collateral_documents');
        $this->addMediaCollection('agency_processing_receipts');
        $this->addMediaCollection('loan_application_documents')
            ->useDisk('public')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
    }

    protected $fillable = [
        'application_no',
        'loan_account_no',
        'member_id',
        'branch_office_id',
        'requested_amount',
        'date_granted',
        'maturity_date',
        'collateral_type',
        'status',
        'status_changed_at',
        'approval_status',
        'sub_status',
        'deficiency_notes',
        'current_rod_status_id',
        'current_rod_branch_id',
        'created_by_user_id',
        'notes',
        // Notary
        'notary_name', 'notarized_date', 'notary_doc_no', 'notary_page_no', 'notary_book_no', 'notary_series_no',
        // BIR
        'bir_office', 'bir_payment_date', 'bir_or_no', 'bir_reference_no', 'bir_bank_confirmation_no', 'bir_amount_paid',
        // ROD (REM only)
        'rod_entry_no', 'rod_payment_date', 'rod_or_no', 'rod_amount_paid', 'rod_release_entry_no', 'rod_release_date',
        // PPSR (CM only)
        'ppsr_notice_no', 'ppsr_initial_notice_date', 'ppsr_ctc_notice_date', 'ppsr_receipt_no', 'ppsr_payment_date', 'ppsr_amount_paid', 'ppsr_transaction_no',
        // LTO (CM only)
        'lto_office', 'lto_payment_date', 'lto_or_no', 'lto_amount_paid', 'lto_release_date',
        // Assessor's
        'assessors_office', 'assessors_received_date', 'assessors_release_date',
        // Encumbered, Vault & Mortgage Release
        'encumbered_date', 'annotation_no', 'vault_location',
        'mortgage_release_status', 'cancellation_mode', 'clearance_no',
        'cancellation_date', 'cancellation_entry_no', 'cancellation_or_no', 'cancellation_fee',
        'title_released_date', 'title_released_to', 'title_released_by', 'title_release_remarks',
        'claimant_type', 'rep_id_details', 'rep_spa_details',
        'is_title_returned', 'is_cancellation_deed_returned', 'is_sec_cert_returned', 'is_promissory_note_returned',
        'compliance_checklist',
    ];

    protected $casts = [
        'requested_amount'              => 'decimal:2',
        'date_granted'                  => 'date',
        'maturity_date'                 => 'date',
        'status_changed_at'             => 'datetime',
        'notarized_date'                => 'date',
        'bir_payment_date'              => 'date',
        'bir_amount_paid'               => 'decimal:2',
        'rod_payment_date'              => 'date',
        'rod_amount_paid'               => 'decimal:2',
        'rod_release_date'              => 'date',
        'ppsr_initial_notice_date'      => 'date',
        'ppsr_ctc_notice_date'          => 'date',
        'ppsr_payment_date'             => 'date',
        'ppsr_amount_paid'              => 'decimal:2',
        'lto_payment_date'              => 'date',
        'lto_amount_paid'               => 'decimal:2',
        'lto_release_date'              => 'date',
        'assessors_received_date'       => 'date',
        'assessors_release_date'        => 'date',
        'encumbered_date'               => 'date',
        'cancellation_date'             => 'date',
        'cancellation_fee'              => 'decimal:2',
        'title_released_date'           => 'date',
        'is_title_returned'             => 'boolean',
        'is_cancellation_deed_returned' => 'boolean',
        'is_sec_cert_returned'          => 'boolean',
        'is_promissory_note_returned'   => 'boolean',
        'compliance_checklist'          => 'array',
    ];

    protected static function booted(): void
    {
        // Sync Application Stage with Mortgage Release Status (unless stage was changed manually in the same save)
        static::saving(function (LoanApplication $loanApplication) {
            if ($loanApplication->isDirty('mortgage_release_status') && ! $loanApplication->isDirty('status')) {
                $mappedStage = match ($loanApplication->mortgage_release_status) {
                    'released_self_cancellation' => 'Released For Self-Cancellation',
                    'for_cancellation'           => 'For Cancellation',
                    'cancelled_released'         => 'Mortgage Released',
                    default                      => null,
                };

                if ($mappedStage !== null) {
                    $loanApplication->status = $mappedStage;
                }
            }
        });

        // Auto-record status_changed_at when status OR approval_status changes
        static::updating(function (LoanApplication $loanApplication) {
            if ($loanApplication->isDirty('status') || $loanApplication->isDirty('approval_status')) {
                $loanApplication->status_changed_at = now();
            }
        });

        // Auto-record status_changed_at on first create
        static::creating(function (LoanApplication $loanApplication) {
            if (empty($loanApplication->status_changed_at)) {
                $loanApplication->status_changed_at = now();
            }
        });

        static::saved(function (LoanApplication $loanApplication) {
            if (filled($loanApplication->notes) && ($loanApplication->wasRecentlyCreated || $loanApplication->wasChanged('notes'))) {
                $lastNote = $loanApplication->applicationNotes()->first();
                if (! $lastNote || $lastNote->note !== $loanApplication->notes) {
                    $loanApplication->applicationNotes()->create([
                        'user_id' => auth()->id() ?? $loanApplication->created_by_user_id,
                        'stage'   => $loanApplication->status ?? 'Initial Assessment',
                        'note'    => $loanApplication->notes,
                    ]);
                }
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function currentRodStatus(): BelongsTo
    {
        return $this->belongsTo(RodStatus::class, 'current_rod_status_id');
    }

    public function currentRodBranch(): BelongsTo
    {
        return $this->belongsTo(RodBranch::class, 'current_rod_branch_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function realEstateCollaterals(): HasMany
    {
        return $this->hasMany(RealEstateCollateral::class);
    }

    public function chattelCollaterals(): HasMany
    {
        return $this->hasMany(ChattelCollateral::class);
    }

    public function chattelCollateral(): HasMany
    {
        return $this->hasMany(ChattelCollateral::class);
    }

    public function applicationNotes(): HasMany
    {
        return $this->hasMany(LoanApplicationNote::class);
    }

    /**
     * Check if a loan application is considered legally encumbered/secured.
     * Regular loans: status = 'Encumbered'.
     * Certifications (REM/CM): status = 'Notarized' or 'Encumbered' (since notary completes the process).
     */
    public function isEncumbered(): bool
    {
        if (strtolower((string) $this->status) === 'encumbered') {
            return true;
        }

        if (in_array($this->collateral_type, ['rem_certification', 'cm_certification']) && in_array(strtolower((string) $this->status), ['notarized', 'encumbered'])) {
            return true;
        }

        return false;
    }

    /**
     * Scope query to encumbered / fully secured records.
     */
    public function scopeEncumbered($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'Encumbered')
              ->orWhere(function ($sub) {
                  $sub->whereIn('collateral_type', ['rem_certification', 'cm_certification'])
                      ->whereIn('status', ['Notarized', 'Encumbered']);
              });
        });
    }

    /**
     * Scope query to active pipeline / on-process records.
     */
    public function scopeOnProcess($query)
    {
        return $query->where('approval_status', 'Approved')
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotIn('collateral_type', ['rem_certification', 'cm_certification'])
                        ->whereNotIn('status', ['Encumbered', 'Released', 'Disapproved']);
                })->orWhere(function ($sub) {
                    $sub->whereIn('collateral_type', ['rem_certification', 'cm_certification'])
                        ->whereNotIn('status', ['Notarized', 'Encumbered', 'Released', 'Disapproved']);
                });
            });
    }
}
