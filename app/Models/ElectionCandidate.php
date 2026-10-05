<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $position_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $matric_number
 * @property string|null $manifesto
 * @property string|null $photo_path
 * @property int $sort_order
 * @property int $votes_count
 */
class ElectionCandidate extends Model
{
    /**
     * votes_count is only ever changed by the ballot transaction.
     *
     * @var list<string>
     */
    protected $fillable = ['user_id', 'name', 'matric_number', 'manifesto', 'photo_path', 'sort_order'];

    /**
     * @return BelongsTo<ElectionPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(ElectionPosition::class, 'position_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
