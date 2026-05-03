<?php

namespace App\Jobs;

use App\Services\Seo\SearchEnginePushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 异步推送 URL 给搜索引擎（百度 + IndexNow）。
 *
 * 用法：
 *   PushSeoUrlsJob::dispatch(['https://geo.rezzy.cn/article/xxx']);
 *
 * - 不阻塞业务流（如文章发布）
 * - 失败重试 1 次，最终失败仅记日志
 * - 队列：默认 redis 的 geoflow / default queue
 */
final class PushSeoUrlsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 2;

    /** @var int 单 job 最多 5 分钟 */
    public $timeout = 300;

    /**
     * @param  list<string>  $urls
     */
    public function __construct(public readonly array $urls) {}

    public function handle(SearchEnginePushService $svc): void
    {
        if (count($this->urls) === 0) {
            return;
        }

        $result = $svc->pushAll($this->urls);

        Log::info('seo.push_job.complete', [
            'urls' => count($this->urls),
            'baidu_ok' => (bool) ($result['baidu']['ok'] ?? false),
            'baidu_remain' => $result['baidu']['remain'] ?? null,
            'indexnow_ok' => (bool) ($result['indexnow']['ok'] ?? false),
        ]);
    }
}
