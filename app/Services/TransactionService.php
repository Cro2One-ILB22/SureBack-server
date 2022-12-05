<?php

namespace App\Services;

use App\Enums\CoinTypeEnum;
use App\Enums\PaymentInstrumentEnum;
use App\Enums\TransactionCategoryEnum;
use App\Enums\TransactionStatusEnum;
use App\Enums\AccountingEntryEnum;
use App\Models\Cashback;
use App\Models\CoinExchange;
use App\Models\CustomerStory;
use App\Models\Transaction;
use App\Models\Ledger;
use App\Models\PaymentInstrument;
use App\Models\Purchase;
use App\Models\StoryToken;
use App\Models\TransactionCategory;
use App\Models\TransactionStatus;
use App\Models\User;
use App\Models\UserCoin;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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

            $customerTransaction = Transaction::create([
                'amount' => $paymentAmount,
                'accounting_entry' => AccountingEntryEnum::DEBIT,
                'transaction_category_id' => $transactionCategory->id,
                'transaction_status_id' => $transactionStatus->id,
                'payment_instrument_id' => $paymentInstrument->id,
            ]);

            $merchantTransaction = Transaction::create([
                'amount' => $purchaseAmount,
                'accounting_entry' => AccountingEntryEnum::CREDIT,
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

    function exchangeCoin(User $merchant, User $customer, int $coinsUsed, Purchase $purchase)
    {
        return DB::transaction(function () use ($merchant, $customer, $coinsUsed, $purchase) {
            $userCoin = UserCoin::where('customer_id', $customer->id)
                ->where('merchant_id', $merchant->id)
                ->where('coin_type', CoinTypeEnum::LOCAL)
                ->first();

            $merchantCoin = $merchant->coins()->where('coin_type', CoinTypeEnum::LOCAL)->first();
            $customerCoin = $customer->coins()->where('coin_type', CoinTypeEnum::LOCAL)->first();

            $transactionStatus = TransactionStatus::where('slug', TransactionStatusEnum::SUCCESS)->first();
            $transactionCategory = TransactionCategory::where('slug', TransactionCategoryEnum::COIN_EXCHANGE)->first();
            $paymentInstrument = PaymentInstrument::where('slug', PaymentInstrumentEnum::COINS)->first();

            // customer
            $customerCoinTransaction = new Transaction([
                'amount' => $coinsUsed,
                'accounting_entry' => AccountingEntryEnum::DEBIT,
            ]);
            $customerCoinTransaction->status()->associate($transactionStatus);
            $customerCoinTransaction->category()->associate($transactionCategory);
            $customerCoinTransaction->paymentInstrument()->associate($paymentInstrument);
            $customerCoinTransaction->user()->associate($customer);
            $customerCoinTransaction->save();
            $customerCoinLedger = new Ledger([
                'before' => $customerCoin->outstanding,
                'after' => $customerCoin->outstanding - $coinsUsed,
            ]);
            $customerCoinLedger->transaction()->associate($customerCoinTransaction);
            $customerCoinLedger->save();

            // merchant
            $merchantCoinTransaction = new Transaction([
                'amount' => $coinsUsed,
                'accounting_entry' => AccountingEntryEnum::CREDIT,
            ]);
            $merchantCoinTransaction->status()->associate($transactionStatus);
            $merchantCoinTransaction->category()->associate($transactionCategory);
            $merchantCoinTransaction->paymentInstrument()->associate($paymentInstrument);
            $merchantCoinTransaction->user()->associate($merchant);
            $merchantCoinTransaction->save();
            $merchantCoinLedger = new Ledger([
                'before' => $merchantCoin->outstanding,
                'after' => $merchantCoin->outstanding - $coinsUsed,
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

            $userCoin->exchanged += $coinsUsed;
            $userCoin->outstanding -= $coinsUsed;
            $userCoin->save();

            $merchantCoin->update([
                'exchanged' => $merchantCoin->exchanged + $coinsUsed,
                'outstanding' => $merchantCoin->outstanding - $coinsUsed,
            ]);
            $customerCoin->update([
                'exchanged' => $customerCoin->exchanged + $coinsUsed,
                'outstanding' => $customerCoin->outstanding - $coinsUsed,
            ]);
        });
    }

    function payStoryToken(StoryToken $token)
    {
        DB::transaction(function () use ($token) {
            $amount = $token->cashback->amount;

            $transactionStatus = TransactionStatus::where('slug', TransactionStatusEnum::SUCCESS)->first();
            $paymentInstrument = PaymentInstrument::where('slug', PaymentInstrumentEnum::COINS)->first();

            $merchant = $token->purchase->merchant;
            $merchantCoin = $merchant->coins()
                ->where('coin_type', CoinTypeEnum::LOCAL)
                ->first();

            $merchantTransaction = new Transaction([
                'amount' => $amount,
                'accounting_entry' => AccountingEntryEnum::DEBIT,
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
        $cashbackAmount = $story->token->cashback->amount;
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
            $customerCashbackTransaction = new Transaction([
                'amount' => $story->token->cashback->amount,
                'accounting_entry' => AccountingEntryEnum::CREDIT,
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

    function checkCoinsAvailability(User $user, $merchantId, $purchaseAmount, $coinsUsed)
    {
        $customerCoins = $user->customerCoins()->where('merchant_id', $merchantId)->where('coin_type', CoinTypeEnum::LOCAL)->first();

        if (!$customerCoins || $customerCoins->outstanding < $coinsUsed) {
            throw new BadRequestHttpException('Insufficient coins');
        }
        if ($coinsUsed > $purchaseAmount) {
            throw new BadRequestHttpException('Cannot use more coins than purchase amount');
        }
    }

    private function confirmCustomerCashback(CustomerStory $story)
    {
        DB::transaction(function () use ($story) {
            $customer = $story->customer;
            $amount = $story->token->cashback->amount;

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
