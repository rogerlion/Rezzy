<?php

namespace App\Console\Commands;

use App\Models\AiMonitoringPlan;
use App\Models\Title;
use App\Models\TitleLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 把 AI 监控发现的"内容缺口"plan 自动转成 title 库里的待写标题。
 *
 * 闭环 v3 §17.2 的关键一环：
 *   监控（每天 9:00） → 创建 plan → **本命令把 plan 转 title** → task 消费 title 写文章
 *   → 文章发布 → SEO 推送 → 下次监控验证
 *
 * 用法：
 *   php artisan geoflow:plans-to-titles                # 默认转最多 10 条 todo plan
 *   php artisan geoflow:plans-to-titles --limit=20     # 转更多
 *   php artisan geoflow:plans-to-titles --library=1    # 指定 title library（默认 1 = 瑞思首批）
 *   php artisan geoflow:plans-to-titles --dry-run      # 只打印将要做什么
 *
 * 调度（routes/console.php）：每天 09:30（错峰监控 09:00）
 */
final class PlansToTitlesCommand extends Command
{
    protected $signature = 'geoflow:plans-to-titles
        {--limit=10 : 单次最多转换的 plan 数量}
        {--library=1 : 目标 title 库 ID}
        {--dry-run : 只打印将要做的事}';

    protected $description = '将 AI 监控发现的内容缺口 plan 转化为 title 库待写标题（闭环 v3 §17.2）';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $libraryId = (int) $this->option('library');
        $dryRun = (bool) $this->option('dry-run');

        $library = TitleLibrary::find($libraryId);
        if (! $library) {
            $this->error("Title library #{$libraryId} 不存在，请用 --library= 指定有效 ID");
            return self::FAILURE;
        }

        $this->info("Target library: #{$library->id} {$library->name}");

        // 优先级：尚未关联 article 且 status=todo 的 plan，越新越优先
        $plans = AiMonitoringPlan::query()
            ->where('status', 'todo')
            ->whereNull('article_id')
            ->orderByDesc('priority')   // priority 字段：high > normal > low
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        if ($plans->isEmpty()) {
            $this->info('没有待转换的 plan，退出');
            return self::SUCCESS;
        }

        $this->info(sprintf('将转换 %d 条 plan 到 title 库：', $plans->count()));

        $now = Carbon::now();
        $converted = 0;

        foreach ($plans as $plan) {
            // 标题：用 plan.title（已经是建议性的标题）
            $title = trim((string) $plan->title);
            if ($title === '') {
                $this->warn("  [skip] plan #{$plan->id} 没有 title，跳过");
                continue;
            }

            // keyword 字段塞 plan 标记，便于将来回链
            $keyword = sprintf('AI-MON-PLAN-%d', $plan->id);
            // target_keyword 优先（如果监控时填了），否则空
            if (! empty($plan->target_keyword)) {
                $keyword = (string) $plan->target_keyword . ' [' . $keyword . ']';
            }

            $this->line(sprintf('  [%d/%d] plan #%d → "%s" (keyword=%s)',
                ++$converted, $plans->count(), $plan->id, mb_substr($title, 0, 50), mb_substr($keyword, 0, 80)
            ));

            if ($dryRun) {
                continue;
            }

            // 检查 title 是否已存在（防重复）
            $exists = Title::where('library_id', $library->id)
                ->where('title', $title)
                ->exists();

            if ($exists) {
                $this->warn("    [exists] 标题已在库里，跳过");
                $plan->update(['status' => 'in_progress']);
                continue;
            }

            // 插入 title
            Title::create([
                'library_id' => $library->id,
                'title' => $title,
                'keyword' => $keyword,
                'is_ai_generated' => false, // 来自监控发现，不算 AI 生成
                'used_count' => 0,
                'usage_count' => 0,
                'created_at' => $now,
            ]);

            // plan 状态推进
            $plan->update(['status' => 'in_progress']);
        }

        // 更新 title library 计数（保持一致性）
        if (! $dryRun) {
            $library->title_count = Title::where('library_id', $library->id)->count();
            $library->save();
        }

        $this->info(sprintf('完成：转换 %d 条 plan 为 title。Library 当前总标题：%d',
            $converted, $library->fresh()->title_count
        ));

        if ($dryRun) {
            $this->warn('--dry-run 模式，未实际写入');
        }

        return self::SUCCESS;
    }
}
