<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Interfaces\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;


class BaseRepository implements BaseRepositoryInterface {

    protected Model $model;
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    protected function query()
    {
        return $this->model->newQuery();
    }

    public function all()
    {
        return $this->query()->get();
    }

    public function find(int|string $id)
    {
        return $this->query()->find($id);
    }

    public function findBy(string $column, mixed $value): ?Model
    {
        return $this->query()
            ->where($column, $value)
            ->first();
    }

    public function create(array $data)
    {
        return $this->query()->create($data);
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
