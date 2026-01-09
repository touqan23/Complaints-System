<?php

namespace App\Repositories\Cached;

use App\Repositories\Eloquent\BaseRepository;
use App\Repositories\Interfaces\BaseRepositoryInterface;
use Illuminate\Support\Facades\Cache;

abstract class CachedBaseRepository implements BaseRepositoryInterface
{
    protected string $cacheTag = 'default';
    protected int $cacheTTL = 10; // minutes
    protected BaseRepository $repository;
    public function __construct(BaseRepository $repository){$this->repository = $repository;}

    // READ METHODS (with cache)
    public function all()
    {
        return Cache::tags([$this->cacheTag])->remember(
            "{$this->cacheTag}:all",
            now()->addMinutes($this->cacheTTL),
            fn() => $this->repository->all()
        );
    }

    public function find(int|string $id)
    {
        return Cache::tags([$this->cacheTag])->remember(
            "{$this->cacheTag}:find:{$id}",
            now()->addMinutes($this->cacheTTL),
            fn() => $this->repository->find($id)
        );
    }

    public function findBy(string $column, mixed $value)
    {
        return Cache::tags([$this->cacheTag])->remember(
            "{$this->cacheTag}:findBy:{$column}:{$value}",
            now()->addMinutes($this->cacheTTL),
            fn() => $this->repository->findBy($column, $value)
        );
    }

    // WRITE METHODS (invalidate cache)
    public function create(array $data)
    {
        $result = $this->repository->create($data);
        $this->flushCache();
        $this->invalidateSpecificCache($result);
        return $result;
    }

    public function update($model, mixed $data)
    {
        $result = $this->repository->update($model, $data);
        $this->flushCache();
        $this->invalidateSpecificCache($model);
        return $result;
    }

    public function delete($model)
    {
        $result = $this->repository->delete($model);
        $this->flushCache();
        $this->invalidateSpecificCache($model);
        return $result;
    }

    // Cache helpers
    protected function flushCache(): void
    {
        Cache::tags([$this->cacheTag])->flush();
    }

    protected function invalidateSpecificCache($model): void
    {
        // Default: do nothing
        // Child classes can override to clear specific cache keys
    }
}
