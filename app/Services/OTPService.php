<?php

namespace App\Services;

use App\Enums\VariableEnum;
use App\Models\Otp;
use App\Models\OtpFactor;
use App\Models\Variable;

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
    $encryptedCode = CryptoService::encrypt(strval($code));
    $otp = Otp::where('code', $encryptedCode)->first();
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
      'dm_to_instagram' => Variable::where('key', VariableEnum::IG_USERNAME)->first()->value,
    ];
  }

  public function verifyOTP($reqData)
  {
    $owner = $reqData['owner'];
    $factor = $reqData['factor'];
    $code = $reqData['otp'];
    $otpFactor = OtpFactor::where('slug', $factor)->first();
    $encryptedCode = CryptoService::encrypt(strval($code));

    $otp = Otp::whereBelongsTo($otpFactor, 'factor')
      ->whereOwner($owner)
      ->where('code', $encryptedCode)
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
