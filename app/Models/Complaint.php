<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class Complaint extends Model
{
    use HasFactory;

    protected static $logAttributes = [
        'type',
        'location',
        'description',
        'status',
        'department_id',
    ];

    protected static $logName = 'complaint';

    protected static $logOnlyDirty = true;

    protected $fillable = [
        'citizen_id',
        'department_id',
        'type',
        'location',
        'description',
        'reference_number',
        'status',
        'notes',
        'locked_by',
        'locked_at'
    ];

    protected $casts = [
         'locked_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'processing_finished_at' => 'datetime',
];


//    public function getActivitylogOptions(): LogOptions
//    {
//        return LogOptions::defaults()
//            ->logOnly(self::$logAttributes)
//            ->useLogName(self::$logName)
//            ->logOnlyDirty()
//            ->setDescriptionForEvent(function (string $eventName) {
//                return "Complaint was {$eventName}";
//            });
//    }

    public function citizen()
    {
        return $this->belongsTo(Citizen::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }


    public function files()
    {
        return $this->hasMany(ComplaintFiles::class);
    }

    public function notes()
    {
        return $this->hasMany(Notes::class);
    }

    //versioning

    public function versions()
    {
        return $this->hasMany(ComplaintVersion::class);
    }


}
