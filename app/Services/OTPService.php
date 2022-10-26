<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\OtpFactor;

class OTPService
{
  public function generateInstagramOTP($reqData)
  {
    $reqData = $this->convertReqDataForInstagram($reqData);
    return $this->generateOTP($reqData);
  }

  public function verifyInstagramOTP($reqData)
  {
    $reqData = $this->convertReqDataForInstagram($reqData);
    return $this->verifyOTP($reqData);
  }

  public function generateOTP($reqData)
  {
    $code = random_int(100000, 999999);
    $otp = Otp::where('code', $code)->first();
    if ($otp) {
      return $this->generateOTP($reqData);
    }

    $owner = $reqData['owner'];
    $factor = $reqData['factor'];
    $otpFactor = OtpFactor::where('slug', $factor)->first();

    $existingOtp = Otp::whereBelongsTo($otpFactor, 'factor')->whereOwner($owner)->first();

    if ($existingOtp) {
      $existingOtp->delete();
    }

    $otp = new Otp([
      'code' => $code,
      'owner' => $owner,
      'expires_at' => now()->addMinutes(5),
    ]);
    $otp->factor()->associate($otpFactor);
    $otp->save();

    return [
      'otp' => $code,
      'expires_in' => $otp->expires_at->diffInSeconds(now()),
    ];
  }

  public function verifyOTP($reqData)
  {
    $owner = $reqData['owner'];
    $factor = $reqData['factor'];
    $code = $reqData['otp'];
    $otpFactor = OtpFactor::where('slug', $factor)->first();

    $otp = Otp::whereBelongsTo($otpFactor, 'factor')
      ->whereOwner($owner)
      ->where('code', $code)
      ->where('expires_at', '>', now())
      ->first();

    if (!$otp) {
      return false;
    }

    $otp->delete();

    return true;
  }

  private function convertReqDataForInstagram($reqData)
  {
    $reqData['owner'] = $reqData['instagram_id'];
    unset($reqData['instagram_id']);
    $reqData['factor'] = 'instagram';
    return $reqData;
  }
}
