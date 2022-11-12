<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

class TransactionController extends Controller
{
    public function index()
    {
        $merchantId = request()->merchant;
        $user = auth()->user();
        $transactions = Transaction::where('user_id', $user->id);

        if ($merchantId && $user->isCustomer()) {
            $transactions = $transactions
                ->where(
                    function ($query) use ($merchantId) {
                        $query
                            ->where(function ($query) use ($merchantId) {
                                $query->whereHas('category', function ($query) {
                                    $query->where('slug', 'cashback');
                                })->whereHas('cashback', function ($query) use ($merchantId) {
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
                            })
                            ->orWhere(function ($query) use ($merchantId) {
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
                            });
                    }
                );
        }

        $transactions = $transactions->with('status', 'category', 'paymentInstrument')
            ->paginate()
            ->through(function ($transaction) {
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
