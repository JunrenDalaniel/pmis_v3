<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApplicationStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function setNameAttribute($value): void
    {
        if (!filled($value)) {
            $this->attributes['name'] = null;
            return;
        }

        // Preserve known uppercase acronyms (BIR, ROD, LTO, REM, CM)
        // instead of blindly lowercasing via Str::title(mb_strtolower())
        $acronyms = ['BIR', 'ROD', 'LTO', 'REM', 'CM'];
        $titled = Str::title(mb_strtolower(trim((string) $value)));

        // Restore acronyms that were incorrectly lowercased
        foreach ($acronyms as $acronym) {
            $titled = preg_replace(
                '/\b' . preg_quote(Str::title(mb_strtolower($acronym)), '/') . '\b/',
                $acronym,
                $titled
            );
        }

        $this->attributes['name'] = $titled;
    }

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = filled($value) ? Str::slug(trim((string) $value), '_') : null;
    }
}
