<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LoggingService
{
    protected function write(model $model,$channel, $action, $details = [] ?? null)
    {
        $detailsJson = is_array($details) ? http_build_query($details) : $details;
        $user = auth()->user();
        Log::channel($channel)->info($action, [
            'user_id'   => auth()->user()?->id,
            'user_name' => auth()->user()?->f_name,
            'ip'        => request()->ip(),
            'details'   => $details,
        ]);

        activity($channel)
            ->performedOn($model)
            ->causedBy(auth()->user() ?? $model)
            ->withProperties($detailsJson)
            ->log($action);
    }

    public function auth(model $model , $action, $details = [])
    {
        $this->write($model,'auth', $action, $details);
    }

    public function complaint(model $model, $action, $details = [])
    {
        $this->write($model,'complaint', $action, $details);
    }

    public function admin(model $model, $action, $details = [])
    {
        $this->write($model,'admin', $action, $details);
    }

    public function system(model $model, $action, $details = [])
    {
        $this->write($model,'system', $action, $details);
    }
}

