<?php

namespace App\Factories;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use App\Enums\NotificationPlatform;

class FirebaseMessagingFactory
{
    public static function make(NotificationPlatform $platform)
    {
        $config = config("firebase.projects.{$platform->value}");

        return (new Factory)
            ->withServiceAccount(base_path($config['credentials']))
            ->createMessaging();
    }
}

