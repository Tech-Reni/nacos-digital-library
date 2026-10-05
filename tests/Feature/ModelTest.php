<?php

namespace Tests\Feature;

use App\Enums\BookStatus;
use App\Enums\ElectionStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Election;
use App\Models\ElectionVote;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function matric_numbers_are_stored_uppercase(): void
    {
        $user = User::factory()->create(['matric_number' => '  f/nd/24/1234567 ']);

        $this->assertSame('F/ND/24/1234567', $user->fresh()->matric_number);
    }

    #[Test]
    public function role_and_status_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->make();

        try {
            $user->fill(['role' => 'admin', 'status' => 'suspended']);
            $this->fail('Mass assigning role/status should be rejected.');
        } catch (MassAssignmentException) {
            // Expected: strict mode rejects it outright instead of ignoring it.
        }

        $this->assertSame(Role::Student, $user->role);
        $this->assertFalse($user->isSuspended());
    }

    #[Test]
    public function two_factor_secrets_are_encrypted_at_rest_and_hidden(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        $raw = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        $this->assertNotSame('JBSWY3DPEHPK3PXP', $raw);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->two_factor_secret);
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
    }

    #[Test]
    public function books_use_an_unguessable_public_id_in_urls(): void
    {
        $book = Book::factory()->create();

        $this->assertSame(26, strlen($book->public_id));
        $this->assertSame($book->public_id, $book->getRouteKey());
        $this->assertSame(BookStatus::Pending, $book->status);
    }

    #[Test]
    public function approved_scope_only_returns_approved_books(): void
    {
        Book::factory()->approved()->count(2)->create();
        Book::factory()->status(BookStatus::Rejected)->create();
        Book::factory()->create();

        $this->assertSame(2, Book::approved()->count());
    }

    #[Test]
    public function live_announcements_respect_their_window(): void
    {
        Announcement::factory()->create(['title' => 'now']);
        Announcement::factory()->create(['title' => 'future', 'starts_at' => now()->addDay()]);
        Announcement::factory()->create(['title' => 'expired', 'ends_at' => now()->subDay()]);
        Announcement::factory()->create(['title' => 'draft', 'is_published' => false]);

        $this->assertSame(['now'], Announcement::live()->pluck('title')->all());
    }

    #[Test]
    public function elections_only_accept_votes_while_open_and_in_window(): void
    {
        $election = new Election(['title' => 'Test']);
        $this->assertFalse($election->isAcceptingVotes());

        $election->status = ElectionStatus::Open;
        $this->assertTrue($election->isAcceptingVotes());

        $election->ends_at = now()->subMinute();
        $this->assertFalse($election->isAcceptingVotes());
    }

    #[Test]
    public function a_voter_can_only_be_recorded_once_per_election(): void
    {
        $election = $this->electionWithCandidate();
        $user = User::factory()->create();

        $election->voters()->attach($user->id);

        $this->expectException(QueryException::class);
        $election->voters()->attach($user->id);
    }

    #[Test]
    public function vote_ids_are_random_and_carry_no_voter_or_time(): void
    {
        $election = $this->electionWithCandidate();
        $position = $election->positions()->firstOrFail();
        $candidate = $position->candidates()->firstOrFail();

        $vote = ElectionVote::create([
            'election_id' => $election->id,
            'position_id' => $position->id,
            'candidate_id' => $candidate->id,
        ]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $vote->id);
        $this->assertSame(['id', 'election_id', 'position_id', 'candidate_id'], array_keys((array) DB::table('election_votes')->first()));
    }

    #[Test]
    public function audit_entries_record_actor_subject_and_request(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $this->actingAs($user);

        Audit::record('book.viewed', $book, ['page' => 3]);

        $log = AuditLog::firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Book', $log->subject_type);
        $this->assertSame($book->id, $log->subject_id);
        $this->assertSame(['page' => 3], $log->meta);
        $this->assertNotNull($log->ip_address);
    }

    private function electionWithCandidate(): Election
    {
        $election = Election::create(['title' => 'Test']);
        $position = $election->positions()->create(['title' => 'President']);
        $position->candidates()->create(['name' => 'Candidate']);

        return $election;
    }
}
