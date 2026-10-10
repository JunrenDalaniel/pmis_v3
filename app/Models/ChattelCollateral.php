<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChattelCollateral extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id',
        'owner_name',
        'mv_file_no',
        'plate_no',
        'engine_no',
        'chassis_no',
        'year_model',
        'vehicle_description',
        'encumbered_to',
        'registration_renewal_date',
        'expire_date',
        'cm_status',
    ];

    protected $casts = [
        'registration_renewal_date' => 'date',
        'expire_date' => 'date',
    ];

    public function setOwnerNameAttribute($value): void
    {
        $this->attributes['owner_name'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setVehicleDescriptionAttribute($value): void
    {
        $this->attributes['vehicle_description'] = filled($value) ? Str::title(mb_strtolower(trim((string) $value))) : null;
    }

    public function setEncumberedToAttribute($value): void
    {
        if (blank($value)) {
            $this->attributes['encumbered_to'] = 'NEW';
            return;
        }

        $val = mb_strtoupper(trim((string) $value));

        $this->attributes['encumbered_to'] = match ($val) {
            'OIC ENCUMBERED', 'ENCUMBERED' => 'OIC ENCUMBERED',
            'REL/E', 'RELE' => 'REL/E',
            default => 'NEW',
        };
    }

    public function setPlateNoAttribute($value): void
    {
        $this->attributes['plate_no'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function setMvFileNoAttribute($value): void
    {
        $this->attributes['mv_file_no'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function setEngineNoAttribute($value): void
    {
        $this->attributes['engine_no'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function setChassisNoAttribute($value): void
    {
        $this->attributes['chassis_no'] = filled($value) ? mb_strtoupper(trim((string) $value)) : null;
    }

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    protected static function booted(): void
    {
        static::saving(function (ChattelCollateral $collateral) {
            if (blank($collateral->registration_renewal_date) && filled($collateral->plate_no)) {
                $computed = static::computeRenewalDateFromPlate($collateral->plate_no);
                if ($computed) {
                    $collateral->registration_renewal_date = $computed;
                }
            }
        });
    }

    /**
     * Compute LTO renewal month and schedule details based on Philippine LTO plate number rules.
     *
     * Rules:
     * - Last digit: Renewal Month (1=Jan, 2=Feb, ..., 9=Sep, 0=Oct)
     * - 2nd to last digit: Weekly working days schedule (1-3: 1st week, 4-6: 2nd week, 7-8: 3rd week, 9-0: 4th week)
     */
    public static function computeRenewalScheduleFromPlate(?string $plateNo, ?int $referenceYear = null): ?array
    {
        if (blank($plateNo)) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $plateNo);
        if (empty($digits)) {
            return null;
        }

        $lastDigit = (int) substr($digits, -1);
        $month = match ($lastDigit) {
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            0 => 10,
        };

        $monthName = match ($month) {
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
        };

        $day = 1;
        $weekDesc = '1st week (1st–7th working days)';
        if (strlen($digits) >= 2) {
            $secondLastDigit = (int) substr($digits, -2, 1);
            match ($secondLastDigit) {
                1, 2, 3 => [$day = 1, $weekDesc = '1st week (1st–7th working days)'],
                4, 5, 6 => [$day = 8, $weekDesc = '2nd week (8th–14th working days)'],
                7, 8    => [$day = 15, $weekDesc = '3rd week (15th–21st working days)'],
                9, 0    => [$day = 22, $weekDesc = '4th week (22nd to month end)'],
                default => [$day = 1, $weekDesc = '1st week (1st–7th working days)'],
            };
        }

        $currYear = $referenceYear ?: (int) now()->year;
        $currMonth = (int) now()->month;

        $targetYear = ($month < $currMonth) ? ($currYear + 1) : $currYear;

        return [
            'month'           => $month,
            'day'             => $day,
            'year'            => $targetYear,
            'formatted_month' => sprintf('%04d-%02d', $targetYear, $month),
            'formatted_date'  => sprintf('%04d-%02d-%02d', $targetYear, $month, $day),
            'month_name'      => $monthName,
            'week_desc'       => $weekDesc,
            'description'     => "LTO: {$monthName} (Ending {$lastDigit}) • {$weekDesc}",
        ];
    }

    public static function computeRenewalMonthFromPlate(?string $plateNo, ?int $referenceYear = null): ?string
    {
        $sched = static::computeRenewalScheduleFromPlate($plateNo, $referenceYear);
        return $sched ? $sched['formatted_month'] : null;
    }

    public static function computeRenewalDateFromPlate(?string $plateNo, ?int $referenceYear = null): ?string
    {
        $sched = static::computeRenewalScheduleFromPlate($plateNo, $referenceYear);
        return $sched ? $sched['formatted_date'] : null;
    }

    public static function getLtoScheduleDescription(?string $plateNo): ?string
    {
        $sched = static::computeRenewalScheduleFromPlate($plateNo);
        return $sched ? $sched['description'] : null;
    }
}
