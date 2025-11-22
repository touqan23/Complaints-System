<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notes extends Model
{
    use HasFactory;
    protected $fillable = ['complaint_id' ,'employee_id', 'note'];
    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function employee(){
        return $this->belongsTo(Employee::class);
    }
}
