<?php 

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class LogFileCursor extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_log_file_cursors';

    protected $fillable = ['resolved_path', 'byte_offset', 'updated_at'];
    
    protected function casts(): array
    {
        return [
            'byte_offset' => 'integer',
            'updated_at' => 'datetime',
        ];
    }
}