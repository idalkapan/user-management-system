<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_comment_id')
                ->nullable()
                ->constrained('post_comments')
                ->nullOnDelete();
            $table->foreignId('reported_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('reason', 50);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('comment_content_snapshot')->nullable();
            $table->unsignedBigInteger('comment_author_id_snapshot')->nullable();
            $table->unsignedBigInteger('post_id_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['post_comment_id', 'reported_by']);
            $table->index('status');
            $table->index('reason');
            $table->index('created_at');
            $table->index(['post_comment_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_comment_reports');
    }
};
