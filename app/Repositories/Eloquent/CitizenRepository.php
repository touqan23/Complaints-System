<?php

namespace App\Repositories\Eloquent;

use App\Models\Citizen;
use App\Models\User;
use App\Repositories\Interfaces\BaseRepositoryInterface;
use App\Repositories\Interfaces\CitizenRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;

class CitizenRepository extends BaseRepository implements CitizenRepositoryInterface {

    public function __construct()
    {
        parent::__construct(Citizen::class);
    }

    public function create(array $data)
    {
        return parent::create([
            'national_number' => $data['national_number'],
            'email' => filter_var($data['identifier'], FILTER_VALIDATE_EMAIL) ? $data['identifier'] : null,
            'user_id' => $data['user_id'],
        ]);
    }

    ///توابع الادارة للادمن على المواطنين
    /** عرض جميع المواطنين */
    public function AllCitizen()
    {
        return Citizen::with('user')->get();
    }

    /** عرض مواطن واحد */
    public function findById($id)
    {
        return Citizen::with('user')->find($id);
    }

    /** تحديث بيانات المواطن */
    public function updateCitizen(Citizen $citizen, array $data)
    {
        // تحديث بيانات المستخدم
        if (isset($data['user'])) {
            $citizen->user->update(
                collect($data['user'])
                    ->only(['f_name', 'l_name', 'phone_number'])
                    ->toArray()
            );
        }

        // تحديث بيانات المواطن
        $citizen->update(
            collect($data)
                ->only(['email', 'national_number'])
                ->toArray()
        );

        return $citizen->fresh('user');
    }

    /** حذف مواطن */
    public function deleteCitizen(Citizen $citizen)
    {
        $citizen->user()->delete(); // cascade
        $citizen->delete();

        return true;
    }
}

