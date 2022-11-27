<?php

namespace App\Models;

use App\Enums\InstagramStoryStatusEnum;
use App\Enums\StoryApprovalStatusEnum;
use App\Services\CryptoService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoryToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'instagram_id',
        'expires_at',
        'purchase_id',
    ];

    protected $hidden = [
        'purchase_id',
    ];

    protected $casts = [
        'instagram_id' => 'integer',
    ];

    protected $appends = [
        'merchant',
        'issued_at',
        'redeemed_at',
        'expired_at',
        'submitted_at',
        'finished_at',
        'current_status',
        'approved_at',
        'rejected_at',
        'rejected_reason',
        'last_status_update_at',
        'status_history',
    ];

    protected function expiredAt(): Attribute
    {
        return new Attribute(
            function () {
                if ($this->expires_at->isPast() && !$this->submittedAt) {
                    return $this->expires_at;
                }
            }
        );
    }

    protected function code(): Attribute
    {
        return new Attribute(
            function ($value) {
                $token = CryptoService::decrypt($value);
                if (mb_check_encoding($token, 'UTF-8')) {
                    return $token;
                }
                return 'expired';
            },
            fn ($value) => CryptoService::encrypt($value)
        );
    }

    protected function merchant(): Attribute
    {
        return new Attribute(
            fn () => $this->purchase->merchant,
        );
    }

    protected function issuedAt(): Attribute
    {
        return new Attribute(
            fn () => $this->created_at,
        );
    }

    protected function redeemedAt(): Attribute
    {
        return new Attribute(
            function () {
                $story = $this->story;
                if ($story) {
                    return $story->created_at;
                }
            },
        );
    }

    protected function submittedAt(): Attribute
    {
        return new Attribute(
            fn () => $this->story ? $this->story->submitted_at : null,
        );
    }

    protected function finishedAt(): Attribute
    {
        return new Attribute(
            function () {
                $finalStatuses = ['approved', 'rejected', 'expired'];
                if (in_array($this->currentStatus, $finalStatuses)) {
                    return $this->lastStatusUpdateAt;
                }
            },
        );
    }

    protected function currentStatus(): Attribute
    {
        return new Attribute(
            function () {
                $statusesTimestamp = [
                    'issued' => $this->created_at,
                    'redeemed' => $this->redeemedAt,
                    'submitted' => $this->submittedAt,
                    'approved' => $this->approvedAt,
                    'rejected' => $this->rejectedAt,
                    'expired' => $this->expiredAt,
                ];
                $last = max($statusesTimestamp);
                $status = array_search($last, $statusesTimestamp);
                if ($status === 'issued' && $this->issuedAt == $this->redeemedAt) {
                    $status = 'redeemed';
                }

                return $status;
            }
        );
    }

    protected function approvedAt(): Attribute
    {
        return new Attribute(
            function () {
                $story = $this->story;
                if ($story && $story->approval_status === StoryApprovalStatusEnum::APPROVED && $story->instagram_story_status === InstagramStoryStatusEnum::VALIDATED) {
                    return max($this->story->assessed_at, $this->story->inspected_at);
                }
            },
        );
    }

    protected function rejectedAt(): Attribute
    {
        return new Attribute(
            function () {
                $story = $this->story;
                if ($story) {
                    if ($story->approval_status === StoryApprovalStatusEnum::REJECTED || $story->instagram_story_status === InstagramStoryStatusEnum::DELETED) {
                        $assessedAt = $story->assessed_at;
                        $inspectedAt = $story->inspected_at;
                        if ($assessedAt && $inspectedAt) {
                            return min($assessedAt, $inspectedAt);
                        }
                        return $assessedAt ?? $inspectedAt;
                    }
                }
            },
        );
    }

    protected function rejectedReason(): Attribute
    {
        return new Attribute(
            function () {
                if ($this->currentStatus === 'rejected') {
                    return $this->story->note;
                }
            },
        );
    }

    protected function lastStatusUpdateAt(): Attribute
    {
        return new Attribute(
            function () {
                $status = $this->currentStatus;
                return $this->{$status . 'At'};
            },
        );
    }

    protected function statusHistory(): Attribute
    {
        return new Attribute(
            function () {
                $history = [];
                $statusesTimestamp = [
                    // 'issued' => $this->created_at,
                    'redeemed' => $this->redeemedAt,
                    'submitted' => $this->submittedAt,
                    'approved' => $this->approvedAt,
                    'rejected' => $this->rejectedAt,
                    'expired' => $this->expiredAt,
                ];
                foreach ($statusesTimestamp as $status => $timestamp) {
                    if ($timestamp) {
                        $history[] = [
                            'status' => $status,
                            'timestamp' => $timestamp,
                        ];
                    }
                }
                return $history;
            },
        );
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'token_transactions');
    }

    public function story()
    {
        return $this->hasOne(CustomerStory::class);
    }

    public function cashback()
    {
        return $this->hasOne(TokenCashback::class, 'token_id');
    }
}
