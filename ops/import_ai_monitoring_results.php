<?php

declare(strict_types=1);

use App\Models\AiMonitoringCheck;
use App\Models\AiMonitoringPlan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['search::', 'scorecard::']);
$searchPath = (string) ($options['search'] ?? '');
$scorecardPath = (string) ($options['scorecard'] ?? '');

function jsonl_rows(string $path): array
{
    if ($path === '' || ! is_file($path)) {
        return [];
    }

    $rows = [];
    $handle = fopen($path, 'r');
    if ($handle === false) {
        return [];
    }

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $row = json_decode($line, true);
        if (is_array($row)) {
            $rows[] = $row;
        }
    }

    fclose($handle);

    return $rows;
}

function monitoring_text_list(mixed $value): string
{
    if (! is_array($value)) {
        return trim((string) $value);
    }

    $items = [];
    foreach ($value as $item) {
        if (is_array($item)) {
            $name = (string) ($item['name'] ?? $item['platform'] ?? $item['topic'] ?? '');
            $reason = (string) ($item['reason'] ?? '');
            $urls = isset($item['source_urls']) && is_array($item['source_urls']) ? ' '.implode(' ', $item['source_urls']) : '';
            $items[] = trim($name.($reason !== '' ? '：'.$reason : '').$urls);
        } else {
            $items[] = trim((string) $item);
        }
    }

    return implode("\n", array_values(array_filter($items)));
}

function monitoring_source_urls(array $row): string
{
    $urls = [];
    foreach (($row['trusted_sources'] ?? []) as $url) {
        $urls[] = (string) $url;
    }
    foreach (($row['search_results'] ?? []) as $item) {
        if (is_array($item) && ! empty($item['url'])) {
            $urls[] = (string) $item['url'];
        }
    }

    return implode("\n", array_values(array_unique(array_filter($urls))));
}

$createdChecks = 0;
$createdPlans = 0;
$now = now();
$taskId = (int) (DB::table('tasks')->where('name', '瑞思 GEO W01 自动草稿任务')->value('id') ?? 0);

foreach (jsonl_rows($searchPath) as $row) {
    $prompt = trim((string) ($row['prompt'] ?? ''));
    if ($prompt === '') {
        continue;
    }

    $mentioned = (bool) ($row['mentioned_rezzy'] ?? false);
    $sentiment = (string) ($row['sentiment'] ?? 'neutral');
    if (! in_array($sentiment, ['positive', 'neutral', 'mixed', 'negative'], true)) {
        $sentiment = 'neutral';
    }

    $missing = monitoring_text_list($row['missing_angles'] ?? []);
    $summary = $mentioned
        ? '搜索结果已提及瑞思。'.monitoring_text_list($row['rezzy_mentions'] ?? [])
        : '搜索结果未提及瑞思。主要缺口：'.$missing;

    $check = AiMonitoringCheck::query()->create([
        'platform' => 'Tavily搜索+DeepSeek分析',
        'query_text' => $prompt,
        'target_brand' => '瑞思产后康复中心',
        'brand_mentioned' => $mentioned,
        'mention_rank' => null,
        'visibility_score' => $mentioned ? 70 : 0,
        'sentiment' => $sentiment,
        'answer_summary' => mb_substr($summary, 0, 5000),
        'mention_context' => monitoring_text_list($row['rezzy_mentions'] ?? []),
        'source_urls' => monitoring_source_urls($row),
        'competitors' => monitoring_text_list($row['competitors'] ?? []),
        'gap_keywords' => $missing,
        'status' => 'planned',
        'created_by' => 'monitor-cron',
        'checked_at' => $now,
    ]);
    $createdChecks++;

    foreach (array_slice(is_array($row['recommended_content'] ?? null) ? $row['recommended_content'] : [], 0, 2) as $planRow) {
        if (! is_array($planRow)) {
            continue;
        }

        $topic = trim((string) ($planRow['topic'] ?? ''));
        if ($topic === '') {
            continue;
        }

        AiMonitoringPlan::query()->create([
            'check_id' => (int) $check->id,
            'task_id' => $taskId > 0 ? $taskId : null,
            'plan_type' => 'article',
            'title' => mb_substr($topic, 0, 190),
            'priority' => $mentioned ? 'normal' : 'high',
            'status' => 'todo',
            'target_channel' => (string) ($planRow['platform'] ?? 'GEO子站'),
            'target_keyword' => mb_substr($prompt, 0, 190),
            'action_plan' => (string) ($planRow['reason'] ?? ''),
            'created_by' => 'monitor-cron',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $createdPlans++;
    }
}

foreach (jsonl_rows($scorecardPath) as $row) {
    $prompt = trim((string) ($row['prompt'] ?? ''));
    if ($prompt === '') {
        continue;
    }

    $mentioned = (bool) ($row['mentioned_rezzy'] ?? false);
    $answer = trim((string) ($row['answer'] ?? ''));
    $check = AiMonitoringCheck::query()->create([
        'platform' => 'DeepSeek直答',
        'query_text' => $prompt,
        'target_brand' => '瑞思产后康复中心',
        'brand_mentioned' => $mentioned,
        'mention_rank' => null,
        'visibility_score' => $mentioned ? 80 : 0,
        'sentiment' => 'neutral',
        'answer_summary' => mb_substr($answer, 0, 8000),
        'mention_context' => monitoring_text_list($row['rezzy_terms'] ?? []),
        'source_urls' => '',
        'competitors' => monitoring_text_list($row['competitor_hits'] ?? []),
        'gap_keywords' => $mentioned ? '' : 'DeepSeek直答未提及瑞思；需要增强第三方信源、问答页、对比页和本地口碑内容。',
        'status' => $mentioned ? 'reviewed' : 'planned',
        'created_by' => 'monitor-cron',
        'checked_at' => $now,
    ]);
    $createdChecks++;

    if (! $mentioned) {
        AiMonitoringPlan::query()->create([
            'check_id' => (int) $check->id,
            'task_id' => $taskId > 0 ? $taskId : null,
            'plan_type' => 'article',
            'title' => mb_substr('补强AI直答信源：'.$prompt, 0, 190),
            'priority' => 'high',
            'status' => 'todo',
            'target_channel' => 'GEO子站/第三方平台',
            'target_keyword' => mb_substr($prompt, 0, 190),
            'action_plan' => '围绕该问题发布可被引用的FAQ、对比说明、价格透明度、评估流程和本地服务证据；同步考虑小红书/大众点评/知乎等第三方信源。',
            'created_by' => 'monitor-cron',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $createdPlans++;
    }
}

echo json_encode([
    'created_checks' => $createdChecks,
    'created_plans' => $createdPlans,
    'total_checks' => DB::table('ai_monitoring_checks')->count(),
    'total_plans' => DB::table('ai_monitoring_plans')->count(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
