<?php

namespace App\Models;

use App\Enums\ReviewAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry in a book's moderation history.
 *
 * @property int $id
 * @property int $book_id
 * @property int|null $reviewer_id
 * @property ReviewAction $action
 * @property string|null $comment
 */
class BookReview extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['reviewer_id', 'action', 'comment'];

    protected function casts(): array
    {
        return ['action' => ReviewAction::class];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
