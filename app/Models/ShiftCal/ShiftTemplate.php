<?php

namespace App\Models\ShiftCal;

use Database\Factories\ShiftCal\ShiftTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'type', 'start_time', 'end_time', 'end_day_offset', 'color', 'note'])]
class ShiftTemplate extends Model
{
    /** @use HasFactory<ShiftTemplateFactory> */
    use HasFactory, HasUuids;

    protected $table = 'shiftcal_shift_templates';

    protected function casts(): array
    {
        return ['end_day_offset' => 'integer'];
    }
}
