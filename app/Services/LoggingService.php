<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoggingService
{
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    protected function write(
        ?Model $model,
        string $channel,
        string $action,
        string $level = self::LEVEL_INFO,
               $details = [],
        ?Throwable $exception = null
    ) {
        $user = auth()->user();
        $logData = [
            'user_id' => $user?->id ?? $model?->id ?? null,
            'user_name' => $user?->f_name ?? $model->user->f_name ?? null,
            'ip' => request()->ip(),
            'action' => $action,
            'level' => $level,
            'details' => $details,
        ];

        // Add exception details if present
        if ($exception) {
            $logData['exception'] = [
                'type' => class_basename($exception),
                'message' => $exception->getMessage() ?: 'No exception message',
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            // Special handling for DB exceptions
            if ($exception instanceof \Illuminate\Database\QueryException) {
                $logData['exception']['sql'] = $exception->getSql();
                $logData['exception']['bindings'] = $exception->getBindings();
            }
        }

        $this->writeToLog($channel, $level, $action, $logData);

        $this->writeToActivityLog($model, $user, $channel, $action, $logData);
    }

    protected function writeToLog(string $channel, string $level, string $action, array $data)
    {
        match ($level) {
            self::LEVEL_ERROR => Log::channel($channel)->error($action, $data),
            self::LEVEL_WARNING => Log::channel($channel)->warning($action, $data),
            default => Log::channel($channel)->info($action, $data),
        };
    }

    protected function writeToActivityLog(?Model $model, $user, string $channel, string $action, array $data)
    {
        $activity = activity($channel);

        if ($model) {
            $activity->performedOn($model);
        }

        $activity
            ->causedBy($user ?? $model)
            ->withProperties($data)
            ->log($action);
    }

    // ========== INFO LOGS ==========

    public function auth(?Model $model, string $action, array $details = [] ?? null)
    {
        $this->write($model, 'auth', $action, self::LEVEL_INFO, $details);
    }

    public function complaint(?Model $model, string $action, array $details = [] ?? null)
    {
        $this->write($model, 'complaint', $action, self::LEVEL_INFO, $details);
    }

    public function admin(?Model $model, string $action, array $details = [] ?? null)
    {
        $this->write($model, 'admin', $action, self::LEVEL_INFO, $details);
    }

    public function system(?Model $model, string $action, array $details = [] ?? null)
    {
        $this->write($model, 'system', $action, self::LEVEL_INFO, $details);
    }

    // ========== WARNING LOGS ==========

    public function authWarning(?Model $model, string $action, array $details = [] ?? null)
    {
        $this->write($model, 'auth', $action, self::LEVEL_WARNING, $details);
    }

    public function complaintWarning(?Model $model, string $action, array $details = [] ?? null)
    {
        $this->write($model, 'complaint', $action, self::LEVEL_WARNING, $details);
    }

    public function adminWarning(?Model $model, string $action, array $details = [] ?? null)
    {
        $this->write($model, 'admin', $action, self::LEVEL_WARNING, $details);
    }

    public function systemWarning(?Model $model, string $action, array $details = [] ?? null)
    {
        $this->write($model, 'system', $action, self::LEVEL_WARNING, $details);
    }

    // ========== ERROR LOGS ==========

    public function authError(?Model $model, string $action, Throwable $exception, array $details = [] ?? null)
    {
        $this->write($model, 'auth', $action, self::LEVEL_ERROR, $details, $exception);
    }

    public function complaintError(?Model $model, string $action, Throwable $exception, array $details = [] ?? null)
    {
        $this->write($model, 'complaint', $action, self::LEVEL_ERROR, $details, $exception);
    }

    public function adminError(?Model $model, string $action, Throwable $exception, array $details = [] ?? null)
    {
        $this->write($model, 'admin', $action, self::LEVEL_ERROR, $details, $exception);
    }

    public function systemError(?Model $model, string $action, Throwable $exception, array $details = [] ?? null)
    {
        $this->write($model, 'system', $action, self::LEVEL_ERROR, $details, $exception);
    }
}
