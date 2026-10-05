<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ballot secrecy is enforced by the schema itself:
 *
 * - election_voters records WHO voted (one row per voter per election).
 * - election_votes records WHAT was voted, with no voter reference, no
 *   timestamps and a random (UUIDv4) key, so rows cannot be linked back to a
 *   voter by id order or by time.
 *
 * Both are written in the same transaction, so a ballot is either fully
 * counted or not counted at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('election_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['election_id', 'sort_order']);
        });

        Schema::create('election_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('election_positions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('matric_number', 32)->nullable();
            $table->text('manifesto')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();

            $table->index(['position_id', 'sort_order']);
        });

        Schema::create('election_voters', function (Blueprint $table) {
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('voted_at')->useCurrent();

            $table->primary(['election_id', 'user_id']);
        });

        Schema::create('election_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('election_positions')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('election_candidates')->cascadeOnDelete();

            $table->index(['position_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_votes');
        Schema::dropIfExists('election_voters');
        Schema::dropIfExists('election_candidates');
        Schema::dropIfExists('election_positions');
        Schema::dropIfExists('elections');
    }
};
