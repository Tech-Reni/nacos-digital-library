<?php

namespace App\Models;

use App\Enums\BookStatus;
use App\Enums\Level;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $title
 * @property string $author
 * @property string|null $description
 * @property int $department_id
 * @property Level $level
 * @property int|null $uploader_id
 * @property BookStatus $status
 * @property string|null $cover_path
 * @property int|null $page_count
 * @property int $views_count
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 */
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'title',
        'author',
        'description',
        'department_id',
        'level',
        'cover_path',
        'page_count',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'level' => Level::class,
            'status' => BookStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * The ULID lives in public_id; the integer id stays internal.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * URLs use the non-guessable public id instead of the sequential id.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', BookStatus::Approved);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * @return HasMany<BookFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(BookFile::class);
    }

    /**
     * @return HasOne<BookFile, $this>
     */
    public function currentFile(): HasOne
    {
        return $this->hasOne(BookFile::class)->where('is_current', true);
    }

    /**
     * @return HasMany<BookReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(BookReview::class);
    }
}
