<?php

namespace App\Http\Controllers;

use App\Enums\AccountingEntryEnum;
use App\Enums\PaymentInstrumentEnum;
use App\Enums\RoleEnum;
use App\Enums\TransactionCategoryEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Transaction;
use Illuminate\Validation\Rules\Enum;

class TransactionController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $userRoles = $user->roles->pluck('slug');
        $isMerchant = $userRoles->contains(RoleEnum::MERCHANT);
        $isCustomer = $userRoles->contains(RoleEnum::CUSTOMER);

        $request = request()->validate([
            'merchant_id' => 'integer',
            'accounting_entry' => [new Enum(AccountingEntryEnum::class)],
            'status' => [new Enum(TransactionStatusEnum::class)],
            'category' => [new Enum(TransactionCategoryEnum::class)],
        ]);
        $user = auth()->user();

        if ($isMerchant && array_key_exists('merchant_id', $request)) {
            return response()->json([
                'message' => 'You are not allowed to view other merchants\' transactions',
            ], 403);
        }

        $transactions = Transaction::where('user_id', $user->id);
        if ($isCustomer && array_key_exists('merchant_id', $request)) {
            $merchantId = $request['merchant_id'];
            $transactions = $transactions
                ->where(
                    function ($query) use ($merchantId) {
                        $query
                            ->where(function ($query) use ($merchantId) {
                                $query->whereHas('category', function ($query) {
                                    $query->where('slug', 'coin_exchange');
                                })
                                    ->whereHas('customerCoinExchange', function ($query) use ($merchantId) {
                                        $query->whereHas('purchase', function ($query) use ($merchantId) {
                                            $query->whereHas('merchant', function ($query) use ($merchantId) {
                                                $query->where('id', $merchantId);
                                            });
                                        });
                                    });
                            })
                            ->orWhere(function ($query) use ($merchantId) {
                                $query->whereHas('category', function ($query) {
                                    $query->where('slug', 'cashback');
                                })
                                    ->whereHas('cashback', function ($query) use ($merchantId) {
                                        $query->whereHas('story', function ($query) use ($merchantId) {
                                            $query->whereHas('token', function ($query) use ($merchantId) {
                                                $query->whereHas('purchase', function ($query) use ($merchantId) {
                                                    $query->whereHas('merchant', function ($query) use ($merchantId) {
                                                        $query->where('id', $merchantId);
                                                    });
                                                });
                                            });
                                        });
                                    });
                            });
                    }
                );
        }

        if (array_key_exists('accounting_entry', $request)) {
            $entryType = AccountingEntryEnum::from($request['accounting_entry']);
            $transactions = $transactions->where('accounting_entry', $entryType);
        }

        if (array_key_exists('status', $request)) {
            $status = TransactionStatusEnum::from($request['status']);
            $transactions = $transactions->whereHas('status', function ($query) use ($status) {
                $query->where('slug', $status);
            });
        }

        if (array_key_exists('category', $request)) {
            $category = TransactionCategoryEnum::from($request['category']);
            $transactions = $transactions->whereHas('category', function ($query) use ($category) {
                $query->where('slug', $category);
            });
        }

        $transactions = $transactions->with('status', 'category', 'paymentInstrument')
            ->with('user', 'tokens', 'customerCoinExchange', 'merchantCoinExchange', 'cashback')
            ->whereHas('paymentInstrument', function ($query) {
                $query->where('slug', '!=', PaymentInstrumentEnum::OTHER);
            })
            ->orderBy('id', 'desc')
            ->paginate()
            ->through(function ($transaction) {
                $transaction->makeHidden('user', 'tokens', 'customerCoinExchange', 'merchantCoinExchange', 'cashback');
                return [
                    ...$transaction->toArray(),
                    'status' => $transaction->status->slug,
                    'category' => $transaction->category->slug,
                    'payment_instrument' => $transaction->paymentInstrument->slug,
                ];
            });

        return response()->json($transactions);
    }
}
