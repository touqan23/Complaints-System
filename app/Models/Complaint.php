<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'citizen_id',
        'government_entity_id',
        'type',
        'location',
        'description',
        'reference_number',
        'status',
        'notes',
        'locked_by',
        'locked_at'
    ];

    public function citizen()
    {
        return $this->belongsTo(Citizen::class);
    }

    public function governmentEntity()
    {
        return $this->belongsTo(GovernmentEntity::class);
    }

    public function files()
    {
        return $this->hasMany(ComplaintFiles::class);
    }

    public function notes()
    {
        return $this->hasMany(Notes::class);
    }

    protected $casts = [
        'locked_at' => 'datetime',
    ];



}
