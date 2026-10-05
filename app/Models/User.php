<?php

namespace App\Models;

use App\Enums\Level;
use App\Enums\Programme;
use App\Enums\Role;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $fullname
 * @property string $matric_number
 * @property string|null $email
 * @property int $department_id
 * @property Level $level
 * @property Programme $programme
 * @property Role $role
 * @property UserStatus $status
 * @property bool $must_change_password
 * @property Carbon|null $last_login_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Role and status are deliberately not mass assignable: they may only be
     * changed through explicit admin actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fullname',
        'matric_number',
        'email',
        'department_id',
        'level',
        'programme',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'student',
        'status' => 'active',
        'must_change_password' => false,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'level' => Level::class,
            'programme' => Programme::class,
            'role' => Role::class,
            'status' => UserStatus::class,
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Normalise matric numbers so lookups are case-insensitive.
     *
     * @return Attribute<string, string>
     */
    protected function matricNumber(): Attribute
    {
        return Attribute::make(set: fn (string $value) => strtoupper(trim($value)));
    }

    public function hasRole(Role $role): bool
    {
        return $this->role->atLeast($role);
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', UserStatus::Active);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Book, $this>
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(Book::class, 'uploader_id');
    }

    /**
     * @return BelongsToMany<Book, $this>
     */
    public function bookmarks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'bookmarks')->withPivot('created_at');
    }

    /**
     * @return HasMany<ReadingProgress, $this>
     */
    public function readingProgress(): HasMany
    {
        return $this->hasMany(ReadingProgress::class);
    }
}
