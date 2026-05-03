<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 主动把 URL 推送给搜索引擎的服务。
 *
 * 当前支持：
 * - 百度搜索资源平台（普通收录 API）：http://data.zz.baidu.com/urls
 * - IndexNow 协议（Bing / Yandex / Naver）：https://api.indexnow.org/indexnow
 *
 * 设计：
 * - 同步阻塞调用应在 PushSeoUrlsJob 这种异步队列里发起，不要直接在请求生命周期里使用
 * - 失败不抛异常，仅记日志，避免阻断业务发布动作
 * - 单次最多 2000 URL（IndexNow 限制 10000，百度普通收录 2000 起步）
 */
final class SearchEnginePushService
{
    /**
     * 推送到所有已配置的搜索引擎。
     *
     * @param  list<string>  $urls
     * @return array{baidu: array, indexnow: array}
     */
    public function pushAll(array $urls): array
    {
        $urls = array_values(array_unique(array_filter($urls)));

        return [
            'baidu' => $this->pushBaidu($urls),
            'indexnow' => $this->pushIndexNow($urls),
        ];
    }

    /**
     * 百度普通收录 API。
     *
     * 配置：
     * - SEO_BAIDU_PUSH_SITE  必填，例：https://geo.rezzy.cn
     * - SEO_BAIDU_PUSH_TOKEN 必填，从百度搜索资源平台「普通收录 → API 提交」拿
     *
     * 响应示例：{"remain":4999, "success":1}
     *
     * @param  list<string>  $urls
     * @return array{ok: bool, remain?: int, success?: int, error?: string, skipped?: bool}
     */
    public function pushBaidu(array $urls): array
    {
        $site = (string) config('seo.push.baidu.site');
        $token = (string) config('seo.push.baidu.token');

        if ($site === '' || $token === '' || count($urls) === 0) {
            return ['ok' => false, 'skipped' => true, 'error' => 'baidu_push_not_configured_or_empty'];
        }

        $endpoint = 'http://data.zz.baidu.com/urls';

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'text/plain'])
                ->withBody(implode("\n", $urls), 'text/plain')
                ->post($endpoint . '?' . http_build_query(['site' => $site, 'token' => $token]));

            $payload = $response->json() ?: [];

            $result = [
                'ok' => $response->successful() && isset($payload['success']),
                'remain' => $payload['remain'] ?? null,
                'success' => $payload['success'] ?? null,
                'error' => $payload['error'] ?? null,
                'message' => $payload['message'] ?? null,
                'http_status' => $response->status(),
            ];

            Log::info('seo.push.baidu', ['urls' => count($urls), 'result' => $result]);

            return $result;
        } catch (Throwable $e) {
            Log::warning('seo.push.baidu.exception', ['error' => $e->getMessage(), 'urls' => count($urls)]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * IndexNow 协议（Bing / Yandex / Naver / 其他实现）。
     *
     * 配置：
     * - SEO_INDEXNOW_KEY  必填，32 字符 hex，需在 https://geo.rezzy.cn/{key}.txt 暴露同名内容
     * - SEO_INDEXNOW_HOST 必填，例：geo.rezzy.cn
     *
     * @param  list<string>  $urls
     * @return array{ok: bool, http_status?: int, error?: string, skipped?: bool}
     */
    public function pushIndexNow(array $urls): array
    {
        $key = (string) config('seo.push.indexnow.key');
        $host = (string) config('seo.push.indexnow.host');

        if ($key === '' || $host === '' || count($urls) === 0) {
            return ['ok' => false, 'skipped' => true, 'error' => 'indexnow_not_configured_or_empty'];
        }

        $payload = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => sprintf('https://%s/%s.txt', $host, $key),
            'urlList' => array_values($urls),
        ];

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->asJson()
                ->post('https://api.indexnow.org/indexnow', $payload);

            $result = [
                'ok' => $response->successful(),
                'http_status' => $response->status(),
                'body' => substr((string) $response->body(), 0, 500),
            ];

            Log::info('seo.push.indexnow', ['urls' => count($urls), 'result' => $result]);

            return $result;
        } catch (Throwable $e) {
            Log::warning('seo.push.indexnow.exception', ['error' => $e->getMessage(), 'urls' => count($urls)]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
