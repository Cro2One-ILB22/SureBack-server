<?php

namespace App\Services;

use App\Models\CorporateTransaction;
use App\Models\CustomerStory;
use App\Models\FinancialTransaction;
use App\Models\StoryToken;
use App\Models\SuccessfulTransaction;
use App\Models\TransactionCategory;
use App\Models\TransactionStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoryService
{
  public function __construct()
  {
    $this->instagramService = new InstagramService();
  }

  public function generateToken(User $user, $cashbackAmount)
  {
    return DB::transaction(function () use ($user, $cashbackAmount) {
      $balance_before = $user->balance;
      $points_before = $user->points;
      $userPayingPower = $this->payStory($user->balance, $user->points, $cashbackAmount);

      if (!$userPayingPower) {
        return response()->json(['message' => 'Insufficient balance'], 400);
      }

      $balance_after = $userPayingPower['balance'];
      $points_after = $userPayingPower['points'];

      $transactionCategory = TransactionCategory::where('slug', 'story')->first();
      $transactionStatus = TransactionStatus::where('name', 'success')->first();
      $transaction = new FinancialTransaction([
        'amount' => $cashbackAmount,
        'type' => 'D',
      ]);
      $transaction->user()->associate($user);
      $transaction->category()->associate($transactionCategory);
      $transaction->status()->associate($transactionStatus);
      $transaction->save();

      $successfulTransaction = new SuccessfulTransaction([
        'balance_before' => $balance_before,
        'balance_after' => $balance_after,
        'points_before' => $points_before,
        'points_after' => $points_after,
      ]);

      $successfulTransaction->transaction()->associate($transaction);
      $successfulTransaction->save();

      $user->balance = $balance_after;
      $user->points = $points_after;
      $user->save();

      $corporateBalanceBefore = 0;
      $corporateTransaction = CorporateTransaction::get()->last();

      if ($corporateTransaction) {
        $corporateBalanceBefore = $corporateTransaction->balance_after;
      }

      $corporateTransaction = new CorporateTransaction([
        'amount' => $cashbackAmount,
        'type' => 'C',
        'balance_before' => $corporateBalanceBefore,
        'balance_after' => $corporateBalanceBefore + $cashbackAmount,
      ]);
      $corporateTransaction->financialTransaction()->associate($transaction);
      $corporateTransaction->save();

      $instagramId = $user->instagram_id;
      $token = hash("crc32", $instagramId . time());
      $storyToken = new StoryToken([
        'token' => $token,
        'expires_at' => now()->addHours(18),
      ]);
      $storyToken->partner()->associate($user);
      $storyToken->save();
      $storyToken->transactions()->attach($transaction);
      return response()->json(['token' => $token]);
    });
  }


  private function payStory($balance, $points, $paymentAmount)
  {
    $points -= $paymentAmount;
    if ($points < 0) {
      $balance += $points;
      $points = 0;
    }
    if ($balance < 0) {
      return false;
    }
    return [
      'balance' => $balance,
      'points' => $points
    ];
  }
}
