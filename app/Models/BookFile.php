<?php

namespace App\Models;

use App\Enums\BookSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $book_id
 * @property string $disk
 * @property string $path
 * @property string|null $original_name
 * @property string $mime
 * @property int $size_bytes
 * @property string $sha256
 * @property int|null $page_count
 * @property BookSource $source
 * @property bool $is_current
 */
class BookFile extends Model
{
    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
        'sha256',
        'page_count',
        'source',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'source' => BookSource::class,
            'is_current' => 'boolean',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
