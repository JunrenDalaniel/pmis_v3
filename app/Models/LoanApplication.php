<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class LoanApplication extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

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
        $this->addMediaCollection('loan_application_documents');
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
        'notary_name', 'notarized_date',
        // BIR
        'bir_office', 'bir_payment_date', 'bir_or_no', 'bir_reference_no', 'bir_bank_confirmation_no', 'bir_amount_paid',
        // ROD (REM only)
        'rod_entry_no', 'rod_payment_date', 'rod_or_no', 'rod_amount_paid', 'rod_release_entry_no', 'rod_release_date',
        // PPSR (CM only)
        'ppsr_notice_no', 'ppsr_receipt_no', 'ppsr_payment_date', 'ppsr_amount_paid', 'ppsr_transaction_no',
        // LTO (CM only)
        'lto_office', 'lto_payment_date', 'lto_or_no', 'lto_amount_paid', 'lto_release_date',
        // Assessor's
        'assessors_office', 'assessors_received_date', 'assessors_release_date',
        // Encumbered
        'encumbered_date', 'annotation_no',
    ];

    protected $casts = [
        'requested_amount'      => 'decimal:2',
        'date_granted'          => 'date',
        'maturity_date'         => 'date',
        'status_changed_at'     => 'datetime',
        'notarized_date'        => 'date',
        'bir_payment_date'      => 'date',
        'bir_amount_paid'       => 'decimal:2',
        'rod_payment_date'      => 'date',
        'rod_amount_paid'       => 'decimal:2',
        'rod_release_date'      => 'date',
        'ppsr_payment_date'     => 'date',
        'ppsr_amount_paid'      => 'decimal:2',
        'lto_payment_date'      => 'date',
        'lto_amount_paid'       => 'decimal:2',
        'lto_release_date'      => 'date',
        'assessors_received_date' => 'date',
        'assessors_release_date'  => 'date',
        'encumbered_date'       => 'date',
    ];

    protected static function booted(): void
    {
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

    public function applicationNotes(): HasMany
    {
        return $this->hasMany(LoanApplicationNote::class)->latest();
    }
}
