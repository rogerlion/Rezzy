<?php

namespace App\Support\Site;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Yaml\Yaml;

/**
 * 读取 storage/app/public/seo/pages/{slug}.md 实体页内容（YAML frontmatter + Markdown body）。
 *
 * 设计：
 * - 文件 bind-mount 自宿主机；磁盘上修改 → 60s 缓存到期后立即生效，无需 rebuild 镜像。
 * - frontmatter 用 Symfony YAML 解析（支持嵌套对象、数组），便于配置 schema.org JSON-LD 数据。
 * - body 使用既有 {@see ArticleHtmlPresenter::markdownToHtml()} 渲染，含 XSS 过滤。
 */
final class SeoPageReader
{
    private const CACHE_PREFIX = 'geoflow.seo_page.';
    private const CACHE_TTL_SECONDS = 60;

    /**
     * 加载某个实体页 .md 文件。文件不存在或解析失败 → 返回 null（让 controller 决定 404）。
     *
     * @return array{
     *   meta: array<string, mixed>,
     *   body_md: string,
     *   body_html: string,
     * }|null
     */
    public static function load(string $slug): ?array
    {
        if (! self::isValidSlug($slug)) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . $slug;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, static function () use ($slug): ?array {
            $path = storage_path('app/public/seo/pages/' . $slug . '.md');
            if (! is_file($path)) {
                return null;
            }

            $raw = (string) file_get_contents($path);
            if ($raw === '') {
                return null;
            }

            [$meta, $body] = self::splitFrontmatter($raw);
            if ($meta === null) {
                return null;
            }

            return [
                'meta' => $meta,
                'body_md' => $body,
                'body_html' => ArticleHtmlPresenter::markdownToHtml($body),
            ];
        });
    }

    /**
     * 站点设置变更后由后台调用（保留接口，目前 cron 60s TTL 已够）。
     */
    public static function forget(string $slug): void
    {
        Cache::forget(self::CACHE_PREFIX . $slug);
    }

    /**
     * 仅允许 [a-z0-9-] 的 slug，防路径穿越。
     */
    private static function isValidSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9-]{1,32}$/', $slug) === 1;
    }

    /**
     * 拆分 YAML frontmatter + body。
     * frontmatter 必须以 --- 开头与结尾。如果缺失 frontmatter，返回 [null, ''] 让调用方判 404。
     *
     * @return array{0: array<string, mixed>|null, 1: string}
     */
    private static function splitFrontmatter(string $raw): array
    {
        // 容忍 BOM 和 CRLF
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $raw = str_replace("\r\n", "\n", $raw);

        if (! str_starts_with($raw, "---\n")) {
            return [null, $raw];
        }

        $rest = substr($raw, 4);
        $endPos = strpos($rest, "\n---\n");
        if ($endPos === false) {
            // 还允许 frontmatter 最后一行是 --- 后无 \n（文件末尾）
            $endPos = strpos($rest, "\n---");
            if ($endPos === false) {
                return [null, $raw];
            }
            $yamlStr = substr($rest, 0, $endPos);
            $body = '';
        } else {
            $yamlStr = substr($rest, 0, $endPos);
            $body = ltrim(substr($rest, $endPos + 5), "\n");
        }

        try {
            $meta = Yaml::parse($yamlStr) ?? [];
            if (! is_array($meta)) {
                return [null, $raw];
            }
        } catch (\Throwable) {
            return [null, $raw];
        }

        return [$meta, $body];
    }
}
