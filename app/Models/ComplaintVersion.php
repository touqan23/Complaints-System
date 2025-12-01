<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintVersion extends Model
{
    use HasFactory;
    protected $fillable = [
        'complaint_id',
        'snapshot',
        'version',
        'created_by'
    ];

    protected $casts = [
        'snapshot' => 'array'
    ];
}
