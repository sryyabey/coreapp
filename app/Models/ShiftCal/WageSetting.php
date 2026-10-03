<?php

namespace App\Models\ShiftCal;

use Database\Factories\ShiftCal\WageSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['hourly_rate', 'overtime_multiplier', 'currency', 'weekly_target_minutes'])]
class WageSetting extends Model
{
    /** @use HasFactory<WageSettingFactory> */
    use HasFactory, HasUuids;

    protected $table = 'shiftcal_wage_settings';

    protected function casts(): array
    {
        return ['hourly_rate' => 'decimal:2', 'overtime_multiplier' => 'decimal:2', 'weekly_target_minutes' => 'integer'];
    }
}
