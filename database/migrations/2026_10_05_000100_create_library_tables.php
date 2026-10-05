<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title');
            $table->string('author', 150);
            $table->text('description')->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('level', 8);
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('cover_path')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Serves the catalog: approved books for a department, filtered by level, newest first.
            $table->index(['status', 'department_id', 'level', 'created_at']);
            $table->index(['uploader_id', 'status']);

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['title', 'author', 'description']);
            }
        });

        Schema::create('book_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('private');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('source', 8);
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['book_id', 'is_current']);
        });

        Schema::create('book_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'book_id']);
            $table->index('book_id');
        });

        Schema::create('reading_progress', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_page')->default(1);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_read_at')->useCurrent();

            $table->primary(['user_id', 'book_id']);
            $table->index(['user_id', 'last_read_at']);
            $table->index('book_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_progress');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('book_reviews');
        Schema::dropIfExists('book_files');
        Schema::dropIfExists('books');
    }
};
