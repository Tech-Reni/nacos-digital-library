<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * An anonymous ballot entry. It deliberately has no voter reference and no
 * timestamps (see the election migration for why).
 *
 * @property string $id
 * @property int $election_id
 * @property int $position_id
 * @property int $candidate_id
 */
class ElectionVote extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['election_id', 'position_id', 'candidate_id'];

    protected static function booted(): void
    {
        // Random UUIDv4, not Laravel's time-ordered UUIDs, so key order says
        // nothing about when a vote was cast.
        static::creating(function (self $vote): void {
            $vote->id = (string) Str::uuid();
        });
    }
}
