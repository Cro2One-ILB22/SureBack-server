<?php

namespace App\Services;

use App\Enums\CoinTypeEnum;
use App\Enums\PaymentInstrumentEnum;
use App\Enums\TransactionCategoryEnum;
use App\Enums\TransactionStatusEnum;
use App\Enums\TransactionTypeEnum;
use App\Models\Cashback;
use App\Models\CoinExchange;
use App\Models\CustomerStory;
use App\Models\FinancialTransaction;
use App\Models\Ledger;
use App\Models\PaymentInstrument;
use App\Models\Purchase;
use App\Models\StoryToken;
use App\Models\TransactionCategory;
use App\Models\TransactionStatus;
use App\Models\User;
use App\Models\UserCoin;
use Illuminate\Support\Facades\DB;

class TransactionService
{
  function initUserCoin(User $customer, User $merchant, CoinTypeEnum $coinType): UserCoin
  {
    $userCoin = UserCoin::firstOrCreate([
      'customer_id' => $customer->id,
      'merchant_id' => $merchant->id,
      'coin_type' => $coinType,
    ]);

    return $userCoin;
  }

  function createPurchase(User $merchant, int $purchaseAmount, int $paymentAmount)
  {
    return DB::transaction(function () use ($merchant, $purchaseAmount, $paymentAmount) {
      $transactionCategory = TransactionCategory::where('slug', TransactionCategoryEnum::PURCHASE)->first();
      $transactionStatus = TransactionStatus::where('slug', TransactionStatusEnum::SUCCESS)->first();
      $paymentInstrument = PaymentInstrument::where('slug', PaymentInstrumentEnum::OTHER)->first();

      $customerTransaction = FinancialTransaction::create([
        'amount' => $paymentAmount,
        'type' => TransactionTypeEnum::DEBIT,
        'transaction_category_id' => $transactionCategory->id,
        'transaction_status_id' => $transactionStatus->id,
        'payment_instrument_id' => $paymentInstrument->id,
      ]);

      $merchantTransaction = FinancialTransaction::create([
        'amount' => $purchaseAmount,
        'type' => TransactionTypeEnum::CREDIT,
        'user_id' => $merchant->id,
        'transaction_category_id' => $transactionCategory->id,
        'transaction_status_id' => $transactionStatus->id,
        'payment_instrument_id' => $paymentInstrument->id,
      ]);

      $purchase = Purchase::create([
        'merchant_id' => $merchant->id,
        'purchase_amount' => $purchaseAmount,
        'payment_amount' => $paymentAmount,
        'customer_transaction_id' => $customerTransaction->id,
        'merchant_transaction_id' => $merchantTransaction->id,
      ]);

      return $purchase;
    });
  }

  function exchangeCoin(User $merchant, User $customer, int $usedCoins, Purchase $purchase)
  {
    return DB::transaction(function () use ($merchant, $customer, $usedCoins, $purchase) {
      $userCoin = UserCoin::where('customer_id', $customer->id)
        ->where('merchant_id', $merchant->id)
        ->where('type', CoinTypeEnum::LOCAL)
        ->first();

      $merchantCoin = $merchant->coins()->where('coin_type', CoinTypeEnum::LOCAL)->first();
      $customerCoin = $customer->coins()->where('coin_type', CoinTypeEnum::LOCAL)->first();

      $transactionStatus = TransactionStatus::where('slug', TransactionStatusEnum::SUCCESS)->first();
      $transactionCategory = TransactionCategory::where('slug', TransactionCategoryEnum::COIN_EXCHANGE)->first();
      $paymentInstrument = PaymentInstrument::where('slug', PaymentInstrumentEnum::COINS)->first();

      // customer
      $customerCoinTransaction = new FinancialTransaction([
        'amount' => $usedCoins,
        'type' => TransactionTypeEnum::DEBIT,
      ]);
      $customerCoinTransaction->status()->associate($transactionStatus);
      $customerCoinTransaction->category()->associate($transactionCategory);
      $customerCoinTransaction->paymentInstrument()->associate($paymentInstrument);
      $customerCoinTransaction->user()->associate($customer);
      $customerCoinTransaction->save();
      $customerCoinLedger = new Ledger([
        'before' => $customerCoin->outstanding,
        'after' => $customerCoin->outstanding - $usedCoins,
      ]);
      $customerCoinLedger->transaction()->associate($customerCoinTransaction);
      $customerCoinLedger->save();

      // merchant
      $merchantCoinTransaction = new FinancialTransaction([
        'amount' => $usedCoins,
        'type' => TransactionTypeEnum::CREDIT,
      ]);
      $merchantCoinTransaction->status()->associate($transactionStatus);
      $merchantCoinTransaction->category()->associate($transactionCategory);
      $merchantCoinTransaction->paymentInstrument()->associate($paymentInstrument);
      $merchantCoinTransaction->user()->associate($merchant);
      $merchantCoinTransaction->save();
      $merchantCoinLedger = new Ledger([
        'before' => $merchantCoin->outstanding,
        'after' => $merchantCoin->outstanding - $usedCoins,
      ]);
      $merchantCoinLedger->transaction()->associate($merchantCoinTransaction);
      $merchantCoinLedger->save();

      $coinExchange = new CoinExchange([
        'coin_type' => CoinTypeEnum::LOCAL,
      ]);
      $coinExchange->merchantTransaction()->associate($merchantCoinTransaction);
      $coinExchange->customerTransaction()->associate($customerCoinTransaction);
      $coinExchange->purchase()->associate($purchase);
      $coinExchange->save();

      $userCoin->exchanged += $usedCoins;
      $userCoin->outstanding -= $usedCoins;
      $userCoin->save();

      $merchantCoin->update([
        'exchanged' => $merchantCoin->exchanged + $usedCoins,
        'outstanding' => $merchantCoin->outstanding - $usedCoins,
      ]);
      $customerCoin->update([
        'exchanged' => $customerCoin->exchanged + $usedCoins,
        'outstanding' => $customerCoin->outstanding - $usedCoins,
      ]);
    });
  }

  function payStoryToken(StoryToken $token)
  {
    DB::transaction(function () use ($token) {
      $amount = $token->tokenCashback->amount;

      $transactionStatus = TransactionStatus::where('slug', TransactionStatusEnum::SUCCESS)->first();
      $paymentInstrument = PaymentInstrument::where('slug', PaymentInstrumentEnum::COINS)->first();

      $merchant = $token->purchase->merchant;
      $merchantCoin = $merchant->coins()
        ->where('coin_type', CoinTypeEnum::LOCAL)
        ->first();

      $merchantTransaction = new FinancialTransaction([
        'amount' => $amount,
        'type' => TransactionTypeEnum::DEBIT,
      ]);
      $merchantTransaction->status()->associate($transactionStatus);
      $merchantTransaction->category()->associate(TransactionCategory::where('slug', TransactionCategoryEnum::STORY)->first());
      $merchantTransaction->paymentInstrument()->associate($paymentInstrument);
      $merchantTransaction->user()->associate($merchant);
      $merchantTransaction->save();

      $token->transactions()->attach($merchantTransaction);

      $merchantLedger = new Ledger([
        'before' => $merchantCoin->outstanding,
        'after' => $merchantCoin->outstanding + $amount,
      ]);
      $merchantLedger->transaction()->associate($merchantTransaction);
      $merchantLedger->save();

      $merchantCoin->outstanding += $amount;
      $merchantCoin->all_time_reward += $amount;
      $merchantCoin->save();
    });
  }

  function sendCashback(CustomerStory $story)
  {
    $user = $story->customer;
    $merchant = $story->token->purchase->merchant;
    $cashbackAmount = $story->token->tokenCashback->amount;
    DB::transaction(function () use ($merchant, $user, $story, $cashbackAmount) {
      $this->confirmCustomerCashback($story);
      $this->payStoryToken($story->token);

      $userCoin = UserCoin::where('customer_id', $user->id)
        ->where('merchant_id', $merchant->id)
        ->where('coin_type', CoinTypeEnum::LOCAL)
        ->first();

      $userCoin->outstanding += $cashbackAmount;
      $userCoin->all_time_reward += $cashbackAmount;
      $userCoin->save();
    });
  }

  function addStoryToToken(CustomerStory $story, StoryToken $token)
  {
    DB::transaction(function () use ($story, $token) {
      $customer = $story->customer;

      $customerTransactionCategory = TransactionCategory::where('slug', TransactionCategoryEnum::CASHBACK)->first();
      $customerTransactionStatus = TransactionStatus::where('slug', TransactionStatusEnum::CREATED)->first();
      $customerPaymentInstrument = PaymentInstrument::where('slug', PaymentInstrumentEnum::COINS)->first();
      $customerCashbackTransaction = new FinancialTransaction([
        'amount' => $story->token->tokenCashback->amount,
        'type' => TransactionTypeEnum::CREDIT,
      ]);
      $customerCashbackTransaction->category()->associate($customerTransactionCategory);
      $customerCashbackTransaction->status()->associate($customerTransactionStatus);
      $customerCashbackTransaction->paymentInstrument()->associate($customerPaymentInstrument);
      $customerCashbackTransaction->user()->associate($customer);
      $customerCashbackTransaction->save();

      $cashback = new Cashback();
      $cashback->story()->associate($story);
      $cashback->transaction()->associate($customerCashbackTransaction);
      $cashback->save();

      $token->purchase->customer()->associate($customer);
      $token->purchase->save();
      $token->purchase->customerTransaction->user()->associate($customer);
      $token->purchase->customerTransaction->save();
    });
  }

  private function confirmCustomerCashback(CustomerStory $story)
  {
    DB::transaction(function () use ($story) {
      $customer = $story->customer;
      $amount = $story->tokenCashback->amount;

      $customerCoin = $customer->coins()
        ->where('coin_type', CoinTypeEnum::LOCAL)
        ->first();

      $transactionStatus = TransactionStatus::where('slug', TransactionStatusEnum::SUCCESS)->first();
      $paymentInstrument = PaymentInstrument::where('slug', PaymentInstrumentEnum::COINS)->first();

      $customerTransaction = $story->cashback->transaction;
      $customerTransaction->status()->associate($transactionStatus);
      $customerTransaction->paymentInstrument()->associate($paymentInstrument);
      $customerTransaction->save();

      $customerLedger = new Ledger([
        'before' => $customerCoin->outstanding,
        'after' => $customerCoin->outstanding + $amount,
      ]);
      $customerLedger->transaction()->associate($customerTransaction);
      $customerLedger->save();

      $customerCoin->outstanding += $amount;
      $customerCoin->all_time_reward += $amount;
      $customerCoin->save();
    });
  }
}
