<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Site\SeoPageReader;
use App\Support\Site\SiteSettingsBag;
use App\Support\Site\SiteThemeViewResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 5 大实体页（about / services / team / contact / faq）。
 *
 * 数据流：路由 → show($slug) → SeoPageReader::load($slug)（读 storage 里的 .md）
 *   → SiteThemeViewResolver::first('page') → 渲染 Blade（注入 schema.org JSON-LD）。
 *
 * URL 示例：/about /services /team /contact /faq
 *
 * 内容编辑：直接改 storage/app/public/seo/pages/{slug}.md（bind-mount，60s 缓存到期生效）。
 */
class PageController extends Controller
{
    /**
     * 实体页 slug 白名单（防止任意路径访问）。
     *
     * @var array<int, string>
     */
    private const ALLOWED_SLUGS = ['about', 'services', 'team', 'contact', 'faq'];

    public function show(Request $request, string $slug): View
    {
        if (! in_array($slug, self::ALLOWED_SLUGS, true)) {
            throw new NotFoundHttpException();
        }

        $page = SeoPageReader::load($slug);
        if ($page === null) {
            // .md 文件未部署或解析失败 → 404
            throw new NotFoundHttpException();
        }

        $meta = $page['meta'];
        $settings = SiteSettingsBag::all();

        $siteName = (string) ($settings['site_name'] ?? config('geoflow.site_name', config('app.name')));
        $siteKeywords = (string) ($settings['site_keywords'] ?? config('geoflow.site_keywords', ''));

        $pageTitle = (string) ($meta['title'] ?? $siteName);
        $pageDescription = (string) ($meta['description'] ?? ($settings['site_description'] ?? ''));

        // 拼接 SEO 标题模板，与 ArticleController 一致
        $titleTemplate = (string) ($settings['seo_title_template'] ?? '{title} - {site_name}');
        $renderedTitle = strtr($titleTemplate, [
            '{title}' => $pageTitle,
            '{site_name}' => $siteName,
        ]);

        $canonicalUrl = url('/' . $slug);

        return SiteThemeViewResolver::first('page', [
            'siteName' => $siteName,
            'slug' => $slug,
            'meta' => $meta,
            'bodyHtml' => $page['body_html'],
            'pageTitle' => $renderedTitle,
            'pageDescription' => $pageDescription,
            'canonicalUrl' => $canonicalUrl,
            'siteKeywords' => $siteKeywords,
            'pageHeading' => $pageTitle,
        ]);
    }
}
