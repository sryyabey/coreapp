<?php

namespace App\Models\ShiftCal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CloudBackup extends Model
{
    use HasFactory;

    protected $table = 'shiftcal_cloud_backups';

    protected $guarded = ['id', 'app_id', 'user_id'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'revision' => 'integer'];
    }
}
