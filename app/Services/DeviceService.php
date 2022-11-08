<?php

namespace App\Services;

use App\Models\UserDevice;

class DeviceService
{
  function addDevice($user, $deviceRequest)
  {
    $device = UserDevice::updateOrCreate(
      ['identifier' => $deviceRequest['identifier']],
      $deviceRequest
    );
    if (!$user->devices->contains($device)) {
      $user->devices()->attach($device);
    }
    return $device;
  }

  function removeFromDevice($user, $deviceIdentifier)
  {
    $device = UserDevice::where('identifier', $deviceIdentifier)->first();
    if ($user->devices->contains($device)) {
      $user->devices()->detach($device);
    }
    return $device;
  }
}
