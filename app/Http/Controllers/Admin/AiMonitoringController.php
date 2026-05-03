<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiMonitoringCheck;
use App\Models\AiMonitoringPlan;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Task;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ArticleWorkflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AiMonitoringController extends Controller
{
    /**
     * AI 监控工作台：记录平台结果，并把发现转成文章方向与媒体投放计划。
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('admin.ai-monitoring.index', [
            'pageTitle' => __('admin.ai_monitoring.page_title'),
            'activeMenu' => 'ai_monitoring',
            'adminSiteName' => AdminWeb::siteName(),
            'filters' => $filters,
            'platforms' => $this->platforms(),
            'checks' => $this->checks($filters),
            'plans' => $this->plans(),
            'stats' => $this->stats(),
            'articleCategories' => $this->articleCategories(),
            'articleAuthors' => $this->articleAuthors(),
            'contentTasks' => $this->contentTasks(),
        ]);
    }

    public function storeCheck(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'platform' => ['required', 'string', 'max:80'],
            'query_text' => ['required', 'string', 'max:5000'],
            'target_brand' => ['nullable', 'string', 'max:120'],
            'brand_mentioned' => ['nullable', 'boolean'],
            'mention_rank' => ['nullable', 'integer', 'min:0', 'max:999'],
            'visibility_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sentiment' => ['nullable', 'string', 'in:positive,neutral,mixed,negative'],
            'answer_summary' => ['nullable', 'string', 'max:20000'],
            'mention_context' => ['nullable', 'string', 'max:20000'],
            'source_urls' => ['nullable', 'string', 'max:10000'],
            'competitors' => ['nullable', 'string', 'max:10000'],
            'gap_keywords' => ['nullable', 'string', 'max:10000'],
            'status' => ['nullable', 'string', 'in:new,reviewed,planned,done'],
            'checked_at' => ['nullable', 'date'],
            'article_plan_title' => ['nullable', 'string', 'max:200'],
            'article_plan' => ['nullable', 'string', 'max:20000'],
            'article_target_keyword' => ['nullable', 'string', 'max:200'],
            'article_priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'article_due_date' => ['nullable', 'date'],
            'media_plan_title' => ['nullable', 'string', 'max:200'],
            'media_plan' => ['nullable', 'string', 'max:20000'],
            'media_channel' => ['nullable', 'string', 'max:120'],
            'media_budget_range' => ['nullable', 'string', 'max:100'],
            'media_priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'media_due_date' => ['nullable', 'date'],
        ]);

        $admin = $request->user('admin');
        $createdBy = (string) ($admin?->username ?? '');

        DB::transaction(function () use ($payload, $createdBy): void {
            $check = AiMonitoringCheck::query()->create([
                'platform' => trim((string) $payload['platform']),
                'query_text' => trim((string) $payload['query_text']),
                'target_brand' => trim((string) ($payload['target_brand'] ?? '')) ?: AdminWeb::siteName(),
                'brand_mentioned' => (bool) ($payload['brand_mentioned'] ?? false),
                'mention_rank' => $this->nullableInt($payload['mention_rank'] ?? null),
                'visibility_score' => $this->nullableInt($payload['visibility_score'] ?? null),
                'sentiment' => (string) ($payload['sentiment'] ?? 'neutral'),
                'answer_summary' => $this->nullableText($payload['answer_summary'] ?? null),
                'mention_context' => $this->nullableText($payload['mention_context'] ?? null),
                'source_urls' => $this->nullableText($payload['source_urls'] ?? null),
                'competitors' => $this->nullableText($payload['competitors'] ?? null),
                'gap_keywords' => $this->nullableText($payload['gap_keywords'] ?? null),
                'status' => (string) ($payload['status'] ?? 'new'),
                'created_by' => $createdBy,
                'checked_at' => ! empty($payload['checked_at']) ? Carbon::parse((string) $payload['checked_at']) : now(),
            ]);

            $plans = array_filter([
                $this->createPlanIfPresent($check, 'article', [
                    'title' => $payload['article_plan_title'] ?? '',
                    'action_plan' => $payload['article_plan'] ?? '',
                    'target_keyword' => $payload['article_target_keyword'] ?? '',
                    'priority' => $payload['article_priority'] ?? 'normal',
                    'due_date' => $payload['article_due_date'] ?? null,
                    'created_by' => $createdBy,
                ]),
                $this->createPlanIfPresent($check, 'media', [
                    'title' => $payload['media_plan_title'] ?? '',
                    'action_plan' => $payload['media_plan'] ?? '',
                    'target_channel' => $payload['media_channel'] ?? '',
                    'budget_range' => $payload['media_budget_range'] ?? '',
                    'priority' => $payload['media_priority'] ?? 'normal',
                    'due_date' => $payload['media_due_date'] ?? null,
                    'created_by' => $createdBy,
                ]),
            ]);

            if ($plans !== [] && $check->status === 'new') {
                $check->update(['status' => 'planned']);
            }
        });

        return redirect()
            ->route('admin.ai-monitoring.index')
            ->with('message', __('admin.ai_monitoring.message.created'));
    }

    public function updateCheckStatus(Request $request, int $checkId): RedirectResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string', 'in:new,reviewed,planned,done'],
        ]);

        AiMonitoringCheck::query()->whereKey($checkId)->update([
            'status' => (string) $payload['status'],
            'updated_at' => now(),
        ]);

        return back()->with('message', __('admin.ai_monitoring.message.check_updated'));
    }

    public function updatePlanStatus(Request $request, int $planId): RedirectResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string', 'in:todo,in_progress,done,archived'],
        ]);

        $plan = AiMonitoringPlan::query()->with('check')->whereKey($planId)->firstOrFail();
        $plan->update([
            'status' => (string) $payload['status'],
            'updated_at' => now(),
        ]);

        $this->syncCheckStatusFromPlans($plan);

        return back()->with('message', __('admin.ai_monitoring.message.plan_updated'));
    }

    public function createArticleFromPlan(Request $request, int $planId): RedirectResponse
    {
        $payload = $request->validate([
            'category_id' => ['required', 'integer', 'min:1', 'exists:categories,id'],
            'author_id' => ['required', 'integer', 'min:1', 'exists:authors,id'],
        ]);

        $plan = AiMonitoringPlan::query()
            ->with('check')
            ->whereKey($planId)
            ->where('plan_type', 'article')
            ->firstOrFail();

        if ($plan->article_id !== null) {
            return back()->with('message', __('admin.ai_monitoring.message.article_already_created'));
        }

        DB::transaction(function () use ($plan, $payload): void {
            $content = $this->buildArticleDraftContent($plan);
            $excerptSource = $plan->action_plan ?: ($plan->check?->answer_summary ?? '');
            $excerpt = Str::limit(trim(strip_tags($excerptSource)), 200, '');

            $article = Article::query()->create([
                'title' => (string) $plan->title,
                'slug' => ArticleWorkflow::generateUniqueSlug((string) $plan->title),
                'excerpt' => $excerpt !== '' ? $excerpt : (string) $plan->title,
                'content' => $content,
                'category_id' => (int) $payload['category_id'],
                'author_id' => (int) $payload['author_id'],
                'task_id' => $plan->task_id,
                'original_keyword' => (string) ($plan->target_keyword ?? ''),
                'keywords' => (string) ($plan->target_keyword ?? ''),
                'meta_description' => Str::limit($excerpt !== '' ? $excerpt : (string) $plan->title, 120, ''),
                'status' => 'draft',
                'review_status' => 'pending',
                'is_ai_generated' => 0,
                'view_count' => 0,
            ]);

            $plan->update([
                'article_id' => (int) $article->id,
                'status' => 'in_progress',
            ]);

            $plan->check?->update(['status' => 'planned']);
        });

        return back()->with('message', __('admin.ai_monitoring.message.article_created'));
    }

    public function attachTaskToPlan(Request $request, int $planId): RedirectResponse
    {
        $payload = $request->validate([
            'task_id' => ['required', 'integer', 'min:1', 'exists:tasks,id'],
        ]);

        $plan = AiMonitoringPlan::query()->with('check')->whereKey($planId)->firstOrFail();
        $plan->update([
            'task_id' => (int) $payload['task_id'],
            'status' => $plan->status === 'todo' ? 'in_progress' : $plan->status,
        ]);

        $plan->check?->update(['status' => 'planned']);

        return back()->with('message', __('admin.ai_monitoring.message.task_attached'));
    }

    /**
     * @return array{search:string,platform:string,status:string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'platform' => trim((string) $request->query('platform', '')),
            'status' => trim((string) $request->query('status', '')),
        ];
    }

    /**
     * @param  array{search:string,platform:string,status:string}  $filters
     */
    private function checks(array $filters): LengthAwarePaginator
    {
        $query = AiMonitoringCheck::query()
            ->with(['plans:id,check_id,article_id,task_id,plan_type,title,status,priority'])
            ->orderByDesc('checked_at')
            ->orderByDesc('id');

        if ($filters['platform'] !== '') {
            $query->where('platform', $filters['platform']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['search'] !== '') {
            $keyword = '%'.$filters['search'].'%';
            $query->where(static function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('query_text', 'like', $keyword)
                    ->orWhere('answer_summary', 'like', $keyword)
                    ->orWhere('competitors', 'like', $keyword)
                    ->orWhere('gap_keywords', 'like', $keyword);
            });
        }

        return $query->paginate(15)->withQueryString();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AiMonitoringPlan>
     */
    private function plans()
    {
        return AiMonitoringPlan::query()
            ->with([
                'check:id,platform,query_text,checked_at',
                'article:id,title,status,review_status',
                'task:id,name,status',
            ])
            ->orderByRaw("CASE status WHEN 'todo' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'done' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * @return array{total_checks:int,mention_rate:float,avg_visibility:float,open_plans:int}
     */
    private function stats(): array
    {
        $total = AiMonitoringCheck::query()->count();
        $mentioned = AiMonitoringCheck::query()->where('brand_mentioned', true)->count();
        $avgVisibility = (float) (AiMonitoringCheck::query()->whereNotNull('visibility_score')->avg('visibility_score') ?? 0);
        $openPlans = AiMonitoringPlan::query()->whereIn('status', ['todo', 'in_progress'])->count();

        return [
            'total_checks' => $total,
            'mention_rate' => $total > 0 ? round(($mentioned / $total) * 100, 1) : 0.0,
            'avg_visibility' => round($avgVisibility, 1),
            'open_plans' => $openPlans,
        ];
    }

    /**
     * @return list<string>
     */
    private function platforms(): array
    {
        return [
            'ChatGPT',
            'DeepSeek',
            '豆包',
            'Kimi',
            '通义千问',
            '文心一言',
            '腾讯元宝',
            'Perplexity',
            'Google AI Overviews',
            '其他',
        ];
    }

    /**
     * @param  array{title:mixed,action_plan:mixed,target_keyword?:mixed,target_channel?:mixed,budget_range?:mixed,priority:mixed,due_date:mixed,created_by:string}  $payload
     */
    private function createPlanIfPresent(AiMonitoringCheck $check, string $type, array $payload): ?AiMonitoringPlan
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $actionPlan = trim((string) ($payload['action_plan'] ?? ''));
        if ($title === '' && $actionPlan === '') {
            return null;
        }

        return AiMonitoringPlan::query()->create([
            'check_id' => (int) $check->id,
            'plan_type' => $type,
            'title' => $title !== '' ? $title : Str::limit($actionPlan, 80, ''),
            'priority' => (string) ($payload['priority'] ?? 'normal'),
            'status' => 'todo',
            'target_channel' => trim((string) ($payload['target_channel'] ?? '')),
            'target_keyword' => trim((string) ($payload['target_keyword'] ?? '')),
            'action_plan' => $actionPlan !== '' ? $actionPlan : null,
            'budget_range' => trim((string) ($payload['budget_range'] ?? '')),
            'due_date' => ! empty($payload['due_date']) ? Carbon::parse((string) $payload['due_date'])->toDateString() : null,
            'created_by' => (string) ($payload['created_by'] ?? ''),
        ]);
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (int) $value;
    }

    private function syncCheckStatusFromPlans(AiMonitoringPlan $plan): void
    {
        $check = $plan->check;
        if (! $check) {
            return;
        }

        $statuses = $check->plans()->pluck('status')->all();
        if ($statuses === []) {
            $check->update(['status' => 'reviewed']);

            return;
        }

        $allClosed = collect($statuses)->every(static fn (string $status): bool => in_array($status, ['done', 'archived'], true));
        $check->update(['status' => $allClosed ? 'done' : 'planned']);
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function articleCategories(): array
    {
        return Category::query()
            ->select(['id', 'name'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(static fn (Category $category): array => ['id' => (int) $category->id, 'name' => (string) $category->name])
            ->all();
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function articleAuthors(): array
    {
        return Author::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(static fn (Author $author): array => ['id' => (int) $author->id, 'name' => (string) $author->name])
            ->all();
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function contentTasks(): array
    {
        return Task::query()
            ->select(['id', 'name'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(static fn (Task $task): array => ['id' => (int) $task->id, 'name' => (string) $task->name])
            ->all();
    }

    private function buildArticleDraftContent(AiMonitoringPlan $plan): string
    {
        $check = $plan->check;
        $sections = [
            '<h2>AI监控文章方向</h2>',
            '<p>'.$this->htmlText($plan->action_plan ?: $plan->title).'</p>',
        ];

        if ((string) $plan->target_keyword !== '') {
            $sections[] = '<h2>目标关键词</h2><p>'.$this->htmlText($plan->target_keyword).'</p>';
        }

        if ($check) {
            $sections[] = '<h2>监控问题</h2><p>'.$this->htmlText($check->query_text).'</p>';

            if ($check->answer_summary) {
                $sections[] = '<h2>AI回答摘要</h2><p>'.$this->htmlText($check->answer_summary).'</p>';
            }

            if ($check->mention_context) {
                $sections[] = '<h2>品牌提及上下文</h2><p>'.$this->htmlText($check->mention_context).'</p>';
            }

            if ($check->gap_keywords) {
                $sections[] = '<h2>内容缺口</h2><p>'.$this->htmlText($check->gap_keywords).'</p>';
            }
        }

        return implode("\n", $sections);
    }

    private function htmlText(mixed $value): string
    {
        return nl2br(e(trim((string) $value)), false);
    }
}
