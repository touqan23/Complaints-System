<?php

namespace App\Http\Controllers;

use App\Support\ServiceExecutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    public function __construct(
        protected ServiceExecutor $executor
    ) {}

    protected function exec(
        callable $callback,
        string $channel,
        string $action,
        array $context = [],
        bool $transactional = false,
        ?Model $model = null
    ) {
        return $this->executor->run(
            $callback,
            $channel,
            $action,
            $context,
            $transactional,
            $model
        );
    }
}
