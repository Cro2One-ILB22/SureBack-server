<?php

namespace App\Services;

use App\Models\NotificationTopic;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
  function sendNotification($title, $body, $data = null, $tokens = null, $topic = null, $condition = null, $image = null)
  {
    $parameters = [
      'title' => $title,
      '--body' => $body,
    ];

    if ($data) {
      $parameters['--data'] = json_encode($data);
    }

    if ($tokens) {
      $parameters['--token'] = $tokens;
    } else if ($topic) {
      $parameters['--topic'] = $topic;
    } else if ($condition) {
      $parameters['--condition'] = $condition;
    } else {
      throw new \Exception('You must specify a token, topic or condition');
    }

    if ($image) {
      $parameters['--image'] = $image;
    }

    Artisan::call('message:send', $parameters);

    info('Notification sent successfully with parameters: ' . json_encode($parameters));
  }

  function sendAndSaveNotification($title, $body, $notificationSubscription, $data = [], $image = null)
  {
    if (!$notificationSubscription) {
      return Log::error('Notification subscription not found');
    }

    $devices = $notificationSubscription->user->devices;
    DB::transaction(function () use ($title, $body, $data, $image, $devices, $notificationSubscription) {
      if ($notificationSubscription && $notificationSubscription->is_active) {
        $data = array_merge($data, [
          'title' => $title,
          'body' => $body,
        ]);

        $notificationSubscription->notification()->create([
          'title' => $title,
          'body' => $body,
          'data' => json_encode($data),
        ]);

        $this->sendNotification(
          $title,
          $body,
          $data,
          image: $image,
          tokens: $devices->pluck('notification_token')->toArray()
        );

        info('Cashback approved notification sent');
      }
    });
  }

  function registerForNotification(User $user)
  {
    $user->notificationSubscriptions()->firstOrCreate([
      'user_id' => $user->id,
      'slug' => 'general',
    ], [
      'name' => 'General',
    ]);

    $notificationTopics = NotificationTopic::all();
    foreach ($notificationTopics as $notificationTopic) {
      $notificationSubscription = $user->notificationSubscriptions()->firstOrCreate([
        'user_id' => $user->id,
        'slug' => $notificationTopic->slug,
      ], [
        'name' => $notificationTopic->name,
      ]);

      $notificationSubscription->notificationSubscriptionable()->associate($notificationTopic);
      $notificationSubscription->save();
    }
  }
}
