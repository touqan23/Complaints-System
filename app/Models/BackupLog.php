<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    use HasFactory;

    protected $fillable =[
        'file_path',
        'disk',
        'success',
        'message',
        'updated_at',
    ];
}
