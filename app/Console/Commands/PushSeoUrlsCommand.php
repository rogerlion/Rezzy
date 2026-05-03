<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Category;
use App\Services\Seo\SearchEnginePushService;
use Illuminate\Console\Command;

/**
 * 手动 / 计划任务调用：批量推送 URL 给搜索引擎。
 *
 * 用法：
 *   php artisan geoflow:push-seo-urls                    # 推送所有"实体页 + 已发布文章 + 分类"
 *   php artisan geoflow:push-seo-urls --new              # 仅推送过去 24 小时内新发布或更新的文章
 *   php artisan geoflow:push-seo-urls --url=https://...  # 推送单个 URL（可重复）
 *   php artisan geoflow:push-seo-urls --pages-only       # 仅 5 大实体页 + 首页
 */
final class PushSeoUrlsCommand extends Command
{
    protected $signature = 'geoflow:push-seo-urls
        {--url=* : 显式 URL（可多次提供）}
        {--new : 仅过去 24 小时内新发布或更新的文章}
        {--pages-only : 仅推 5 大实体页 + 首页}
        {--dry-run : 只打印将要推送的 URL，不实际调用 API}';

    protected $description = '批量推送 URL 给百度搜索资源平台 + IndexNow（Bing/Yandex）';

    /** 5 大实体页 slug 列表（与 PageController 白名单一致） */
    private const ENTITY_PAGES = ['about', 'services', 'team', 'contact', 'faq'];

    public function handle(SearchEnginePushService $svc): int
    {
        $urls = $this->collectUrls();
        $urls = array_values(array_unique(array_filter($urls)));

        $this->info(sprintf('Collected %d URLs', count($urls)));
        foreach ($urls as $u) {
            $this->line('  ' . $u);
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run mode, skipping push');
            return self::SUCCESS;
        }

        if (count($urls) === 0) {
            $this->warn('No URLs to push, exit');
            return self::SUCCESS;
        }

        $result = $svc->pushAll($urls);

        $this->info('--- Baidu ---');
        $this->line(json_encode($result['baidu'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->info('--- IndexNow ---');
        $this->line(json_encode($result['indexnow'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return ($result['baidu']['ok'] ?? false) || ($result['indexnow']['ok'] ?? false)
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function collectUrls(): array
    {
        $explicit = (array) $this->option('url');
        if (! empty($explicit)) {
            return $explicit;
        }

        $base = rtrim((string) config('app.url', 'https://geo.rezzy.cn'), '/');
        $urls = [$base . '/'];

        // 5 大实体页
        foreach (self::ENTITY_PAGES as $slug) {
            $urls[] = $base . '/' . $slug;
        }

        if ($this->option('pages-only')) {
            return $urls;
        }

        // 文章
        $articleQuery = Article::query()->published();
        if ($this->option('new')) {
            $articleQuery->where(function ($q): void {
                $q->where('updated_at', '>=', now()->subDay())
                  ->orWhere('published_at', '>=', now()->subDay());
            });
        }

        $articleQuery->select(['slug'])->orderByDesc('id')->chunk(500, function ($articles) use (&$urls, $base): void {
            foreach ($articles as $a) {
                $urls[] = $base . '/article/' . $a->slug;
            }
        });

        // 分类
        Category::query()->whereHas('articles', fn ($q) => $q->published())
            ->orderBy('sort_order')
            ->select(['slug'])
            ->chunk(100, function ($categories) use (&$urls, $base): void {
                foreach ($categories as $c) {
                    $urls[] = $base . '/category/' . $c->slug;
                }
            });

        return $urls;
    }
}
