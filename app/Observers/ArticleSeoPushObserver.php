<?php

namespace App\Observers;

use App\Jobs\PushSeoUrlsJob;
use App\Models\Article;

/**
 * 文章状态转 'published' 时，自动派发 SEO 推送任务（百度 + IndexNow）。
 *
 * 触发条件：
 * - 创建文章直接 status='published'
 * - 编辑文章把 status 从其它值改为 'published'
 * - 编辑已发布文章正文（content/title 重大修改）
 *
 * 实现：
 * - 不阻塞业务流，dispatch 到 redis 队列
 * - 队列 worker（geoflow:worker）已在生产长驻
 */
final class ArticleSeoPushObserver
{
    public function saved(Article $article): void
    {
        $isNowPublished = $article->status === 'published';
        if (! $isNowPublished) {
            return;
        }

        $wasPublished = ($article->getOriginal('status') === 'published');
        $statusJustChanged = $article->wasChanged('status');
        $contentJustChanged = $article->wasChanged(['title', 'content', 'slug']);

        $shouldPush = (! $wasPublished && $statusJustChanged) || $contentJustChanged;
        if (! $shouldPush) {
            return;
        }

        $base = rtrim((string) config('app.url', 'https://geo.rezzy.cn'), '/');
        $urls = [
            $base . '/article/' . $article->slug,
        ];

        // 文章页变化也间接影响首页和栏目页（文章列表更新）
        $urls[] = $base . '/';
        if ($article->category) {
            $urls[] = $base . '/category/' . $article->category->slug;
        }

        PushSeoUrlsJob::dispatch($urls);
    }
}
