<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * 生成 llms.txt + llms-full.txt 到 storage/app/public/seo/
 *
 * 输出文件由 Nginx 的 alias 直接对外暴露：
 *   https://geo.rezzy.cn/llms.txt        ← 概览（站点元信息 + 栏目目录 + 文章列表）
 *   https://geo.rezzy.cn/llms-full.txt   ← 全文（每篇已发布文章的完整 Markdown）
 *
 * 设计依据：
 *   - llmstxt.org 规范（Markdown 结构、根域名暴露）
 *   - Mintlify 实测：ChatGPT 主要抓 llms-full.txt（周访问 79 次）
 *   - Tw93《AI 搜索 GEO 实战》：三层结构 概览/全文/独立项目页
 *   - 瑞思 v3 §9.2：可引用事实库结构（核心句 + 详细 + 证据）
 */
class GenerateLlmsTxtCommand extends Command
{
    protected $signature = 'geoflow:generate-llms
        {--dry-run : 只打印将要写入的内容大小，不实际写盘}';

    protected $description = '生成 llms.txt 与 llms-full.txt 到 storage/app/public/seo/，供 AI 抓取';

    private string $outputDir;
    private array $siteMeta;

    public function handle(): int
    {
        $this->outputDir = storage_path('app/public/seo');
        $this->siteMeta = $this->loadSiteMeta();

        if (! is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        try {
            $categories = $this->loadCategoriesWithArticles();

            $llmsTxt = $this->buildLlmsTxt($categories);
            $llmsFullTxt = $this->buildLlmsFullTxt($categories);

            $this->info(sprintf(
                'Generated: llms.txt = %s | llms-full.txt = %s | categories = %d | articles = %d',
                $this->humanBytes(strlen($llmsTxt)),
                $this->humanBytes(strlen($llmsFullTxt)),
                $categories->count(),
                $categories->sum(fn ($c) => $c->publishedArticles->count())
            ));

            if ($this->option('dry-run')) {
                $this->warn('--dry-run 模式：未写入磁盘');
                return self::SUCCESS;
            }

            $this->atomicWrite($this->outputDir . '/llms.txt', $llmsTxt);
            $this->atomicWrite($this->outputDir . '/llms-full.txt', $llmsFullTxt);

            $this->info('✓ 写入完成 → ' . $this->outputDir);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('生成失败: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    private function loadSiteMeta(): array
    {
        return [
            'name' => env('SITE_NAME', '瑞思产后康复中心'),
            'description' => env('SITE_DESCRIPTION', '贵阳本地医学产后康复机构，提供盆底修复、腹直肌分离、孕期按摩、母乳指导等专业服务。'),
            'url' => rtrim(env('APP_URL', 'https://geo.rezzy.cn'), '/'),
            'main_url' => env('SITE_MAIN_URL', 'https://rezzy.cn'),
            'phone' => env('SITE_PHONE', '189 8552 3034'),
            'wechat' => env('SITE_WECHAT', '189 8552 3034'),
            'address' => env('SITE_ADDRESS', '贵州省贵阳市'),
            'business_hours' => env('SITE_BUSINESS_HOURS', '请来电预约'),
            'icp' => env('SITE_ICP', ''),
            'disclaimer' => '本站为健康科普信息发布平台，所有内容不替代医生诊断与治疗建议。如出现发热、明显疼痛、异常出血、疑似感染等情况，请优先就医。',
        ];
    }

    private function loadCategoriesWithArticles()
    {
        return Category::query()
            ->with(['articles' => function ($q) {
                $q->published()
                    ->orderByDesc('published_at')
                    ->orderByDesc('id');
            }])
            ->whereHas('articles', fn ($q) => $q->published())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($category) {
                $category->publishedArticles = $category->articles;
                return $category;
            });
    }

    private function buildLlmsTxt($categories): string
    {
        $m = $this->siteMeta;
        $lines = [];

        $lines[] = '# ' . $m['name'];
        $lines[] = '';
        $lines[] = '> ' . $m['description'];
        $lines[] = '';

        $lines[] = '## 联系方式';
        $lines[] = '';
        $lines[] = '- 地址：' . $m['address'];
        $lines[] = '- 电话/微信：' . $m['phone'];
        $lines[] = '- 主站：' . $m['main_url'];
        $lines[] = '- 知识库：' . $m['url'];
        if (! empty($m['business_hours'])) {
            $lines[] = '- 营业时间：' . $m['business_hours'];
        }
        $lines[] = '';

        $lines[] = '## 知识资源';
        $lines[] = '';
        foreach ($categories as $cat) {
            $catUrl = $m['url'] . '/category/' . $cat->slug;
            $desc = $cat->description ?: '';
            $lines[] = sprintf('- [%s](%s)%s', $cat->name, $catUrl, $desc ? ': ' . $desc : '');
        }
        $lines[] = '';

        $lines[] = '## 文章索引';
        $lines[] = '';
        foreach ($categories as $cat) {
            $lines[] = '### ' . $cat->name;
            $lines[] = '';
            foreach ($cat->publishedArticles as $article) {
                $url = $m['url'] . '/article/' . $article->slug;
                $excerpt = $this->cleanExcerpt($article->excerpt, 100);
                $lines[] = sprintf('- [%s](%s)%s', $this->cleanInline($article->title), $url, $excerpt ? ' — ' . $excerpt : '');
            }
            $lines[] = '';
        }

        $lines[] = '## 完整版（推荐 AI 读取）';
        $lines[] = '';
        $lines[] = '- [llms-full.txt](' . $m['url'] . '/llms-full.txt) — 所有已发布文章的 Markdown 全文，便于 LLM 一次性嵌入或检索。';
        $lines[] = '';

        $lines[] = '## 免责声明';
        $lines[] = '';
        $lines[] = $m['disclaimer'];
        $lines[] = '';

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '<!-- generated_at: ' . Carbon::now()->toIso8601String() . ' -->';
        $lines[] = '<!-- generator: geoflow:generate-llms -->';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function buildLlmsFullTxt($categories): string
    {
        $m = $this->siteMeta;
        $lines = [];

        $lines[] = '# ' . $m['name'] . ' · 完整知识库';
        $lines[] = '';
        $lines[] = '> ' . $m['description'];
        $lines[] = '';
        $lines[] = '联系：' . $m['address'] . ' · 电话/微信 ' . $m['phone'] . ' · 主站 ' . $m['main_url'];
        $lines[] = '';
        $lines[] = '本文件包含 ' . $categories->sum(fn ($c) => $c->publishedArticles->count()) . ' 篇已发布文章的完整 Markdown 全文，供 AI 助手嵌入或检索。';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        foreach ($categories as $cat) {
            $lines[] = '## ' . $cat->name;
            $lines[] = '';
            if (! empty($cat->description)) {
                $lines[] = '> ' . $cat->description;
                $lines[] = '';
            }

            foreach ($cat->publishedArticles as $article) {
                $url = $m['url'] . '/article/' . $article->slug;

                $lines[] = '### ' . $this->cleanInline($article->title);
                $lines[] = '';

                $meta = [];
                $meta[] = '来源：' . $url;
                if ($article->published_at) {
                    $meta[] = '发布：' . $article->published_at->format('Y-m-d');
                }
                if (! empty($article->meta_description)) {
                    $meta[] = '摘要：' . $this->cleanInline($article->meta_description);
                }
                if (! empty($article->keywords)) {
                    $meta[] = '关键词：' . $this->cleanInline($article->keywords);
                }
                $lines[] = '_' . implode(' · ', $meta) . '_';
                $lines[] = '';

                $body = $this->normalizeBody($article->content ?? '');
                if ($body !== '') {
                    $lines[] = $body;
                    $lines[] = '';
                }

                $lines[] = '---';
                $lines[] = '';
            }
        }

        $lines[] = '## 免责声明';
        $lines[] = '';
        $lines[] = $m['disclaimer'];
        $lines[] = '';

        $lines[] = '<!-- generated_at: ' . Carbon::now()->toIso8601String() . ' -->';
        $lines[] = '<!-- generator: geoflow:generate-llms -->';

        return implode("\n", $lines);
    }

    private function cleanInline(?string $s): string
    {
        if ($s === null) return '';
        $s = preg_replace('/[\r\n]+/', ' ', $s);
        $s = preg_replace('/^#+\s*/m', '', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return $s;
    }

    private function cleanExcerpt(?string $s, int $max): string
    {
        $s = $this->cleanInline($s);
        if ($s === '') return '';
        return Str::limit($s, $max, '…');
    }

    private function normalizeBody(string $content): string
    {
        $content = trim($content);
        if ($content === '') return '';

        if (preg_match('/<(p|div|h[1-6]|ul|ol|li|br|strong|em)[\s>]/i', $content)) {
            $content = $this->htmlToMarkdownLite($content);
        }

        $content = preg_replace('/^#{1,3}\s/m', '#### ', $content);
        $content = preg_replace('/^---+\s*$/m', '', $content);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        return trim($content);
    }

    private function htmlToMarkdownLite(string $html): string
    {
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $html);
        $html = preg_replace('/<\/?(p|div)[^>]*>/i', "\n\n", $html);

        for ($i = 1; $i <= 6; $i++) {
            $hash = str_repeat('#', $i);
            $html = preg_replace('/<h' . $i . '[^>]*>(.*?)<\/h' . $i . '>/is', "\n\n{$hash} $1\n\n", $html);
        }

        $html = preg_replace('/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $html);
        $html = preg_replace('/<\/?(ul|ol)[^>]*>/i', "\n", $html);

        $html = preg_replace('/<(strong|b)[^>]*>(.*?)<\/\1>/is', '**$2**', $html);
        $html = preg_replace('/<(em|i)[^>]*>(.*?)<\/\1>/is', '*$2*', $html);

        $html = preg_replace('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', '[$2]($1)', $html);
        $html = preg_replace('/<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>/i', '![$2]($1)', $html);
        $html = preg_replace('/<img[^>]*src="([^"]*)"[^>]*>/i', '![]($1)', $html);

        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = preg_replace("/[ \t]+/", ' ', $html);
        $html = preg_replace("/\n[ \t]+/", "\n", $html);
        $html = preg_replace("/\n{3,}/", "\n\n", $html);

        return trim($html);
    }

    private function atomicWrite(string $path, string $content): void
    {
        $tmp = $path . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new \RuntimeException("写入临时文件失败: {$tmp}");
        }
        chmod($tmp, 0644);
        if (! rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("rename 失败: {$tmp} -> {$path}");
        }
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . 'B';
        if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . 'KB';
        return number_format($bytes / 1024 / 1024, 2) . 'MB';
    }
}
