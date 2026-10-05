<?php

namespace App\Models;

use App\Enums\ElectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property ElectionStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class Election extends Model
{
    protected $fillable = ['title', 'description', 'starts_at', 'ends_at'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => ElectionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Whether ballots are accepted right now (open and inside its window).
     */
    public function isAcceptingVotes(): bool
    {
        if ($this->status !== ElectionStatus::Open) {
            return false;
        }

        $now = now();

        return ($this->starts_at === null || $this->starts_at->lte($now))
            && ($this->ends_at === null || $this->ends_at->gt($now));
    }

    /**
     * @return HasMany<ElectionPosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(ElectionPosition::class)->orderBy('sort_order');
    }

    /**
     * Users who have cast a ballot (never what they voted for).
     *
     * @return BelongsToMany<User, $this>
     */
    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'election_voters')->withPivot('voted_at');
    }
}
