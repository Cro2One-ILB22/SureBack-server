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
        'expired_at',
        'submitted_at',
        'finished_at',
        'final_status',
        'approved_at',
        'rejected_at',
        'rejected_reason',
        'last_status_update_at',
        'status_history',
    ];

    protected function expiredAt(): Attribute
    {
        return new Attribute(
            fn () => $this->finalStatus === 'expired' ? $this->expires_at : null,
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
                if (!$this->story) {
                    return null;
                }
                $assessedAt = $this->story->assessed_at;
                $inspectedAt = $this->story->inspected_at;
                if ($assessedAt && $inspectedAt) {
                    return max($assessedAt, $inspectedAt);
                }
                if ($assessedAt) {
                    return $assessedAt;
                }
                if ($inspectedAt) {
                    return $inspectedAt;
                }
                if ($this->approvedAt) {
                    return $this->approvedAt;
                }
                if ($this->rejectedAt) {
                    return $this->rejectedAt;
                }
                if ($this->expires_at->isPast()) {
                    return $this->expires_at;
                }
            },
        );
    }

    protected function finalStatus(): Attribute
    {
        return new Attribute(
            function () {
                if (!$this->story) {
                    return 'issued';
                }
                $approvalStatus = $this->story->approval_status;
                $instagramStoryStatus = $this->story->instagram_story_status;
                if ($approvalStatus === null && $instagramStoryStatus === null) {
                    if ($this->expires_at->isPast()) {
                        return 'expired';
                    }
                    if ($this->created_at) {
                        return 'redeemed';
                    }
                }
                if ($approvalStatus === StoryApprovalStatusEnum::APPROVED && $instagramStoryStatus === InstagramStoryStatusEnum::VALIDATED) {
                    return 'approved';
                }
                if ($approvalStatus === StoryApprovalStatusEnum::REJECTED || $instagramStoryStatus === InstagramStoryStatusEnum::DELETED) {
                    return 'rejected';
                }
                return 'submitted';
            }
        );
    }

    protected function approvedAt(): Attribute
    {
        return new Attribute(
            function () {
                if ($this->finalStatus === 'approved') {
                    return $this->finishedAt;
                }
            },
        );
    }

    protected function rejectedAt(): Attribute
    {
        return new Attribute(
            function () {
                if ($this->finalStatus === 'rejected') {
                    return $this->finishedAt;
                }
            },
        );
    }

    protected function rejectedReason(): Attribute
    {
        return new Attribute(
            function () {
                if ($this->finalStatus === 'rejected') {
                    return $this->story->note;
                }
            },
        );
    }

    protected function lastStatusUpdateAt(): Attribute
    {
        return new Attribute(
            function () {
                $status = $this->finalStatus;
                return $this->{$status . 'At'};
            },
        );
    }

    protected function statusHistory(): Attribute
    {
        return new Attribute(
            function () {
                $history = [];
                if ($this->issuedAt) {
                    $history[] = [
                        'status' => 'issued',
                        'at' => $this->issuedAt,
                    ];
                }
                if ($this->submittedAt) {
                    $history[] = [
                        'status' => 'submitted',
                        'at' => $this->submittedAt,
                    ];
                }
                if ($this->approvedAt) {
                    $history[] = [
                        'status' => 'approved',
                        'at' => $this->approvedAt,
                    ];
                }
                if ($this->rejectedAt) {
                    $history[] = [
                        'status' => 'rejected',
                        'at' => $this->rejectedAt,
                    ];
                }
                if ($this->expiredAt) {
                    $history[] = [
                        'status' => 'expired',
                        'at' => $this->expiredAt,
                    ];
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
