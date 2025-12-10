<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Interfaces\BaseRepositoryInterface;


class BaseRepository implements BaseRepositoryInterface {
    protected $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model::all();
    }

    public function find(int|string $id)
    {
        return $this->model::find($id);
    }

    public function findBy(string $column, mixed $value)
    {
        return $this->model::where($column, $value)->first();
    }

    public function create(array $data)
    {
        return $this->model::create($data);
    }

    public function update($model, mixed $data)
    {
        return $model->update($data);
    }

    public function delete($model)
    {
        return $model->delete();
    }

}
