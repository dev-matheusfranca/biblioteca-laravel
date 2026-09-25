<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CirculationPolicy extends Model
{
    use HasFactory;

    protected $table = 'politicas_circulacao';

    protected $fillable = [
        'version',
        'loan_days',
        'max_open_loans',
        'max_renewals',
        'renewal_days',
        'pickup_hours',
        'blocks_overdue',
        'timezone',
        'active_key',
        'changed_by',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'loan_days' => 'integer',
            'max_open_loans' => 'integer',
            'max_renewals' => 'integer',
            'renewal_days' => 'integer',
            'pickup_hours' => 'integer',
            'blocks_overdue' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return array<string, int|string|bool> */
    public function snapshot(): array
    {
        return [
            'policy_id' => $this->getKey(),
            'version' => $this->version,
            'loan_days' => $this->loan_days,
            'max_open_loans' => $this->max_open_loans,
            'max_renewals' => $this->max_renewals,
            'renewal_days' => $this->renewal_days,
            'pickup_hours' => $this->pickup_hours,
            'blocks_overdue' => $this->blocks_overdue,
            'timezone' => $this->timezone,
        ];
    }
}
