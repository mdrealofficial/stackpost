<?php

namespace Modules\AppAiPublishing\Models;

use Illuminate\Database\Eloquent\Model;

class AiPublishingOccurrence extends Model
{
    protected $guarded = [];

    protected $attributes = ['status' => 'pending', 'credits_spent' => 0, 'credits_reserved' => 0];

    public function creditsSince(int $timestamp): int
    {
        return collect((array) data_get($this->payload, 'credit_charges', []))->filter(fn ($charge) => (int) $charge['at'] >= $timestamp)->sum('amount');
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'slot_at' => 'integer', 'credits_spent' => 'integer', 'credits_reserved' => 'integer'];
    }
}
