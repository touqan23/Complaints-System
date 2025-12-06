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
        'created_by',
        'action',
        'field_name',
        'old_value',
        'new_value',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime'
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
