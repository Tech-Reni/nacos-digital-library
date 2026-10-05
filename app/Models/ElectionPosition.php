<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $election_id
 * @property string $title
 * @property int $sort_order
 */
class ElectionPosition extends Model
{
    protected $fillable = ['title', 'sort_order'];

    /**
     * @return BelongsTo<Election, $this>
     */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /**
     * @return HasMany<ElectionCandidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(ElectionCandidate::class, 'position_id')->orderBy('sort_order');
    }
}
