<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;


class AiConversation extends Model
{

    protected $fillable = [
        'user_id',
        'title',
        'model',
        'total_prompt_tokens',
        'total_completion_tokens',
    ];

    protected $casts = [
        'total_prompt_tokens'     => 'integer',
        'total_completion_tokens' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }

    public function totalTokens(): int
    {
        return $this->total_prompt_tokens + $this->total_completion_tokens;
    }

    /** Estimated cost in USD based on Groq gpt-oss-20b pricing */
    public function estimatedCostUsd(): float
    {
        // $0.000000075 per token (input+output same price for gpt-oss-20b)
        return $this->totalTokens() * 0.000000075;
    }

    /** Cost in PHP (1 USD ≈ 56 PHP) */
    public function estimatedCostPhp(): float
    {
        return $this->estimatedCostUsd() * 56;
    }
}
