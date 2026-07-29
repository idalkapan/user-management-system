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
        Schema::table('post_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('post_comments')
                ->cascadeOnDelete();

            $table->foreignId('replied_to_comment_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('post_comments')
                ->nullOnDelete();

            $table->foreignId('replied_to_user_id')
                ->nullable()
                ->after('replied_to_comment_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['parent_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'created_at']);
            $table->dropConstrainedForeignId('replied_to_user_id');
            $table->dropConstrainedForeignId('replied_to_comment_id');
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
