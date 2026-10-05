<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int $book_id
 * @property int $current_page
 * @property int $progress_percent
 * @property Carbon|null $completed_at
 * @property Carbon $last_read_at
 */
class ReadingProgress extends Model
{
    protected $table = 'reading_progress';

    /**
     * Composite primary key (user_id, book_id); writes go through upserts.
     */
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['user_id', 'book_id', 'current_page', 'progress_percent', 'completed_at', 'last_read_at'];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'last_read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
