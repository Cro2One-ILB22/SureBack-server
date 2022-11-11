<?php

namespace App\Services;

use App\Models\Device;

class DeviceService
{
  function addDevice($user, $deviceRequest)
  {
    $device = Device::updateOrCreate(
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
    $device = Device::where('identifier', $deviceIdentifier)->first();
    if ($user->devices->contains($device)) {
      $user->devices()->detach($device);
    }
    return $device;
  }
}
