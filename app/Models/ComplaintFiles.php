<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintFiles extends Model
{
    use HasFactory;

    protected $fillable = ['complaint_id', 'url', 'type'];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }
}
