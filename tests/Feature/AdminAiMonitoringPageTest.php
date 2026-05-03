<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiMonitoringCheck;
use App\Models\AiMonitoringPlan;
use App\Models\Author;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiMonitoringPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login_when_visiting_ai_monitoring_page(): void
    {
        $this->get(route('admin.ai-monitoring.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_admin_can_view_ai_monitoring_page(): void
    {
        $admin = Admin::query()->create([
            'username' => 'ai_monitoring_admin',
            'password' => 'secret-123',
            'email' => 'ai-monitoring@example.com',
            'display_name' => 'AI Monitoring Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.ai-monitoring.index'))
            ->assertOk()
            ->assertSee(__('admin.ai_monitoring.heading'))
            ->assertSee(__('admin.ai_monitoring.new_check'));
    }

    public function test_monitoring_check_creates_article_and_media_plans(): void
    {
        $admin = Admin::query()->create([
            'username' => 'ai_monitoring_writer',
            'password' => 'secret-123',
            'email' => 'ai-monitoring-writer@example.com',
            'display_name' => 'AI Monitoring Writer',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.ai-monitoring.checks.store'), [
                'platform' => 'ChatGPT',
                'query_text' => '贵阳产后修复机构怎么选？',
                'target_brand' => 'REZZY GEO',
                'brand_mentioned' => '1',
                'mention_rank' => '2',
                'visibility_score' => '78',
                'sentiment' => 'positive',
                'answer_summary' => '提到了瑞思，但缺少盆底修复案例。',
                'mention_context' => 'AI 建议优先比较 REZZY GEO 的服务案例。',
                'gap_keywords' => '盆底修复案例, 腹直肌分离',
                'article_plan_title' => '贵阳产后盆底修复机构选择指南',
                'article_plan' => '补一篇机构选择标准和案例型文章。',
                'media_plan_title' => '小红书盆底修复案例投放',
                'media_plan' => '投放案例笔记并测试关键词覆盖。',
                'media_channel' => '小红书',
            ])
            ->assertRedirect(route('admin.ai-monitoring.index'));

        $this->assertDatabaseHas('ai_monitoring_checks', [
            'platform' => 'ChatGPT',
            'target_brand' => 'REZZY GEO',
            'brand_mentioned' => true,
            'visibility_score' => 78,
            'mention_context' => 'AI 建议优先比较 REZZY GEO 的服务案例。',
            'status' => 'planned',
        ]);

        $this->assertDatabaseHas('ai_monitoring_plans', [
            'plan_type' => 'article',
            'title' => '贵阳产后盆底修复机构选择指南',
            'status' => 'todo',
        ]);

        $this->assertDatabaseHas('ai_monitoring_plans', [
            'plan_type' => 'media',
            'title' => '小红书盆底修复案例投放',
            'target_channel' => '小红书',
            'status' => 'todo',
        ]);

        $plan = AiMonitoringPlan::query()->where('plan_type', 'article')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.ai-monitoring.plans.status', ['planId' => (int) $plan->id]), [
                'status' => 'in_progress',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_monitoring_plans', [
            'id' => (int) $plan->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_empty_scores_remain_null_and_check_status_can_be_updated(): void
    {
        $admin = Admin::query()->create([
            'username' => 'ai_monitoring_reviewer',
            'password' => 'secret-123',
            'email' => 'ai-monitoring-reviewer@example.com',
            'display_name' => 'AI Monitoring Reviewer',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.ai-monitoring.checks.store'), [
                'platform' => 'Kimi',
                'query_text' => '贵阳 GEO 优化服务有哪些？',
                'target_brand' => 'REZZY GEO',
                'sentiment' => 'neutral',
            ])
            ->assertRedirect(route('admin.ai-monitoring.index'));

        $check = AiMonitoringCheck::query()->firstOrFail();

        $this->assertNull($check->mention_rank);
        $this->assertNull($check->visibility_score);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.ai-monitoring.checks.status', ['checkId' => (int) $check->id]), [
                'status' => 'reviewed',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_monitoring_checks', [
            'id' => (int) $check->id,
            'status' => 'reviewed',
        ]);
    }

    public function test_article_plan_can_create_linked_article_draft(): void
    {
        $admin = Admin::query()->create([
            'username' => 'ai_monitoring_editor',
            'password' => 'secret-123',
            'email' => 'ai-monitoring-editor@example.com',
            'display_name' => 'AI Monitoring Editor',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $category = Category::query()->create([
            'name' => 'GEO',
            'slug' => 'geo',
            'description' => '',
            'sort_order' => 0,
        ]);
        $author = Author::query()->create([
            'name' => 'REZZY Editor',
            'bio' => '',
        ]);
        $check = AiMonitoringCheck::query()->create([
            'platform' => 'ChatGPT',
            'query_text' => '贵阳 GEO 怎么提升 AI 推荐？',
            'target_brand' => 'REZZY GEO',
            'brand_mentioned' => false,
            'sentiment' => 'neutral',
            'answer_summary' => '未提到 REZZY GEO，推荐补充品牌解释型内容。',
            'mention_context' => '回答引用了其他 GEO 服务商。',
            'status' => 'planned',
            'created_by' => 'ai_monitoring_editor',
            'checked_at' => now(),
        ]);
        $plan = AiMonitoringPlan::query()->create([
            'check_id' => (int) $check->id,
            'plan_type' => 'article',
            'title' => 'AI 推荐里的 GEO 优化完整指南',
            'priority' => 'high',
            'status' => 'todo',
            'target_keyword' => 'GEO 优化',
            'action_plan' => '解释 GEO 优化的流程、内容结构和投放配合。',
            'created_by' => 'ai_monitoring_editor',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.ai-monitoring.plans.article', ['planId' => (int) $plan->id]), [
                'category_id' => (int) $category->id,
                'author_id' => (int) $author->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'title' => 'AI 推荐里的 GEO 优化完整指南',
            'category_id' => (int) $category->id,
            'author_id' => (int) $author->id,
            'original_keyword' => 'GEO 优化',
            'status' => 'draft',
            'review_status' => 'pending',
        ]);

        $linkedPlan = $plan->fresh();

        $this->assertNotNull($linkedPlan?->article_id);
        $this->assertSame('in_progress', $linkedPlan?->status);
    }
}
