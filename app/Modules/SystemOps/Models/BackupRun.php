<?php

namespace App\Modules\SystemOps\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class BackupRun extends Model
{
    protected $table = 'backup_runs';

    protected $fillable = [
        'type', 'status', 'file_path', 'size_bytes', 'checksum_sha256',
        'note', 'error', 'actor_id', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'size_bytes'  => 'integer',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
