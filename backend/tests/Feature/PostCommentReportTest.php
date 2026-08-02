<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostCommentReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostCommentReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $author;

    private User $reporter;

    private User $otherReporter;

    private PostComment $publishedComment;

    private PostComment $pendingPostComment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->author = User::factory()->create([
            'role' => 'user',
        ]);

        $this->reporter = User::factory()->create([
            'role' => 'user',
        ]);

        $this->otherReporter = User::factory()->create([
            'role' => 'user',
        ]);

        $category = Category::create([
            'name' => 'Test Kategori',
            'slug' => 'test-kategori',
            'description' => 'Test açıklama',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $this->admin->id,
        ]);

        $publishedPost = Post::create([
            'user_id' => $this->author->id,
            'category_id' => $category->id,
            'title' => 'Yayınlanmış Yazı',
            'slug' => 'yayinlanmis-yazi-' . Str::random(6),
            'content' => 'Yayınlanmış yazı içeriği',
            'status' => 'published',
        ]);

        $pendingPost = Post::create([
            'user_id' => $this->author->id,
            'category_id' => $category->id,
            'title' => 'Bekleyen Yazı',
            'slug' => 'bekleyen-yazi-' . Str::random(6),
            'content' => 'Bekleyen yazı içeriği',
            'status' => 'pending',
        ]);

        $this->publishedComment = PostComment::create([
            'post_id' => $publishedPost->id,
            'user_id' => $this->author->id,
            'content' => 'Şikâyet edilebilir yorum içeriği',
        ]);

        $this->pendingPostComment = PostComment::create([
            'post_id' => $pendingPost->id,
            'user_id' => $this->author->id,
            'content' => 'Bekleyen yazı yorumu',
        ]);
    }

    public function test_user_can_report_another_users_comment(): void
    {
        Sanctum::actingAs($this->reporter);

        $response = $this->postJson(
            "/api/comments/{$this->publishedComment->id}/report",
            [
                'reason' => 'spam',
                'description' => 'Bu yorum spam içeriyor.',
            ],
        );

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'Şikâyetiniz başarıyla alındı.',
                'report' => [
                    'post_comment_id' => $this->publishedComment->id,
                    'reason' => 'spam',
                    'status' => PostCommentReport::STATUS_PENDING,
                ],
            ]);

        $this->assertDatabaseHas('post_comment_reports', [
            'post_comment_id' => $this->publishedComment->id,
            'reported_by' => $this->reporter->id,
            'reason' => 'spam',
            'description' => 'Bu yorum spam içeriyor.',
            'status' => PostCommentReport::STATUS_PENDING,
            'comment_content_snapshot' => 'Şikâyet edilebilir yorum içeriği',
            'comment_author_id_snapshot' => $this->author->id,
            'post_id_snapshot' => $this->publishedComment->post_id,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'admin_note' => null,
        ]);
    }

    public function test_user_cannot_report_own_comment(): void
    {
        Sanctum::actingAs($this->author);

        $response = $this->postJson(
            "/api/comments/{$this->publishedComment->id}/report",
            [
                'reason' => 'spam',
            ],
        );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Kendi yorumunuzu şikâyet edemezsiniz.',
            ]);
    }

    public function test_admin_cannot_create_report(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson(
            "/api/comments/{$this->publishedComment->id}/report",
            [
                'reason' => 'spam',
            ],
        );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Bu işlem yalnızca kullanıcılar tarafından yapılabilir.',
            ]);
    }

    public function test_user_cannot_report_same_comment_twice(): void
    {
        Sanctum::actingAs($this->reporter);

        PostCommentReport::create([
            'post_comment_id' => $this->publishedComment->id,
            'reported_by' => $this->reporter->id,
            'reason' => 'spam',
            'status' => PostCommentReport::STATUS_PENDING,
            'comment_content_snapshot' => $this->publishedComment->content,
            'comment_author_id_snapshot' => $this->publishedComment->user_id,
            'post_id_snapshot' => $this->publishedComment->post_id,
        ]);

        $response = $this->postJson(
            "/api/comments/{$this->publishedComment->id}/report",
            [
                'reason' => 'harassment',
            ],
        );

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Bu yorumu zaten şikâyet ettiniz.',
            ]);
    }

    public function test_different_users_can_report_same_comment(): void
    {
        Sanctum::actingAs($this->reporter);

        $this->postJson(
            "/api/comments/{$this->publishedComment->id}/report",
            ['reason' => 'spam'],
        )->assertCreated();

        Sanctum::actingAs($this->otherReporter);

        $this->postJson(
            "/api/comments/{$this->publishedComment->id}/report",
            ['reason' => 'inappropriate'],
        )
            ->assertCreated()
            ->assertJsonPath('report.reason', 'inappropriate');

        $this->assertDatabaseCount('post_comment_reports', 2);
    }

    public function test_invalid_reason_is_rejected(): void
    {
        Sanctum::actingAs($this->reporter);

        $response = $this->postJson(
            "/api/comments/{$this->publishedComment->id}/report",
            [
                'reason' => 'invalid_reason',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_long_description_is_rejected(): void
    {
        Sanctum::actingAs($this->reporter);

        $response = $this->postJson(
            "/api/comments/{$this->publishedComment->id}/report",
            [
                'reason' => 'other',
                'description' => str_repeat('a', 501),
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    public function test_comment_on_unpublished_post_cannot_be_reported(): void
    {
        Sanctum::actingAs($this->reporter);

        $response = $this->postJson(
            "/api/comments/{$this->pendingPostComment->id}/report",
            [
                'reason' => 'spam',
            ],
        );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Yalnızca yayınlanmış yazılardaki yorumlar şikâyet edilebilir.',
            ]);
    }

    public function test_reporting_nonexistent_comment_returns_not_found(): void
    {
        Sanctum::actingAs($this->reporter);

        $response = $this->postJson('/api/comments/999999/report', [
            'reason' => 'spam',
        ]);

        $response->assertNotFound();
    }

    public function test_report_is_preserved_when_comment_is_deleted(): void
    {
        Sanctum::actingAs($this->reporter);

        $this->postJson(
            "/api/comments/{$this->publishedComment->id}/report",
            ['reason' => 'spam'],
        )->assertCreated();

        $report = PostCommentReport::query()->firstOrFail();
        $commentId = $this->publishedComment->id;
        $postId = $this->publishedComment->post_id;

        $this->publishedComment->delete();

        $report->refresh();

        $this->assertDatabaseHas('post_comment_reports', [
            'id' => $report->id,
            'post_comment_id' => null,
            'comment_content_snapshot' => 'Şikâyet edilebilir yorum içeriği',
            'comment_author_id_snapshot' => $this->author->id,
            'post_id_snapshot' => $postId,
        ]);

        $this->assertNull(PostComment::query()->find($commentId));
    }
}
