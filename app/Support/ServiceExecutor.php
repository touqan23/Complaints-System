<?php

namespace App\Support;
use App\Services\FirebaseNotificationService;
use App\Services\LoggingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ServiceExecutor
{
    public function __construct(private LoggingService $logs, private FirebaseNotificationService $notify) {}

    public function run(
        callable $callback,
        string $channel,
        string $action,
        array $context = [],
        bool $transactional = false,
        ?Model $model = null
    ) {
        try {
            if ($transactional) {
                return DB::transaction(fn () => $callback());
            }

            return $callback();

        } catch (\Throwable $e) {
            $this->notify->notifyAdmin();
            $this->logs->{$channel.'Error'}(
                $model,
                "$action FAILED — system error",
                $e,
                $context
            );

            throw $e;
        }
    }
}

