<?php

namespace App\View\Composers;

use App\Models\Category;
use App\Support\Site\SiteSettingsBag;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * 为前台 Blade 布局注入站点名称、分类导航等公共变量。
 */
final class SiteLayoutComposer
{
    public function compose(View $view): void
    {
        $map = SiteSettingsBag::all();
        $siteName = (string) ($map['site_name'] ?? config('geoflow.site_name', config('app.name')));
        $siteLogo = (string) ($map['site_logo'] ?? '');
        $siteFavicon = (string) ($map['site_favicon'] ?? '');
        $copyright = (string) ($map['copyright_info'] ?? '');
        $analyticsCode = (string) ($map['analytics_code'] ?? '');

        $categories = collect();
        if (Schema::hasTable('categories')) {
            $categories = Category::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->withCount([
                    'articles as published_count' => function ($q): void {
                        $q->published();
                    },
                ])
                ->get();
        }

        $navPages = [
            ['slug' => 'about',    'label' => __('front.nav.about', [], 'zh_CN') !== 'front.nav.about' ? __('front.nav.about') : '机构介绍'],
            ['slug' => 'services', 'label' => __('front.nav.services', [], 'zh_CN') !== 'front.nav.services' ? __('front.nav.services') : '服务总览'],
            ['slug' => 'team',     'label' => __('front.nav.team', [], 'zh_CN') !== 'front.nav.team' ? __('front.nav.team') : '团队介绍'],
            ['slug' => 'faq',      'label' => __('front.nav.faq', [], 'zh_CN') !== 'front.nav.faq' ? __('front.nav.faq') : '常见问题'],
            ['slug' => 'contact',  'label' => __('front.nav.contact', [], 'zh_CN') !== 'front.nav.contact' ? __('front.nav.contact') : '联系我们'],
        ];

        $view->with([
            'siteName' => $siteName,
            'siteLogo' => $siteLogo,
            'siteFavicon' => $siteFavicon,
            'footerCopyright' => $copyright,
            'headAnalyticsCode' => $analyticsCode,
            'navCategories' => $categories,
            'navPages' => $navPages,
        ]);
    }
}
