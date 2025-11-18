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
}

