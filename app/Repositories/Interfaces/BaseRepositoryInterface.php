<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface BaseRepositoryInterface {

    public function all();
    public function find(int|string $id);
    public function findBy(string $column, mixed $value);
    public function create(array $data);
    public function update($model, mixed $data);
    public function delete($model);
    public function verifyOtp($model, string $otp);


}

