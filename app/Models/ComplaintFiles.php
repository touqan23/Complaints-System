<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintFiles extends Model
{
    protected $fillable = [
        'complaint_id',
        'url',
        'type',
        'status',
        'original_name',
        'local_path',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class , 'complaint_id');
    }

}
