<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- 搜索引擎站点验证（按 config/seo.php 自动渲染） --}}
    @php($siteVerifications = config('"'"'seo.verifications'"'"', []))
    @if(!empty($siteVerifications['"'"'bing'"'"']))
        <meta name="msvalidate.01" content="{{ $siteVerifications['"'"'bing'"'"'] }}">
    @endif
    @if(!empty($siteVerifications['"'"'baidu'"'"']))
        <meta name="baidu-site-verification" content="{{ $siteVerifications['"'"'baidu'"'"'] }}">
    @endif
    @if(!empty($siteVerifications['"'"'google'"'"']))
        <meta name="google-site-verification" content="{{ $siteVerifications['"'"'google'"'"'] }}">
    @endif
    @if(!empty($siteVerifications['"'"'360'"'"']))
        <meta name="360-site-verification" content="{{ $siteVerifications['"'"'360'"'"'] }}">
    @endif
    @if(!empty($siteVerifications['"'"'sogou'"'"']))
        <meta name="sogou_site_verification" content="{{ $siteVerifications['"'"'sogou'"'"'] }}">
    @endif
    @if(!empty($siteVerifications['"'"'shenma'"'"']))
        <meta name="shenma-site-verification" content="{{ $siteVerifications['"'"'shenma'"'"'] }}">
    @endif
    <title>{{ $pageTitle ?? $siteName }}</title>
    <meta name="description" content="{{ $pageDescription ?? '' }}">
    @isset($siteKeywords)
        @if($siteKeywords !== '')
            <meta name="keywords" content="{{ $siteKeywords }}">
        @endif
    @endisset
    @if(!empty($siteFavicon))
        <link rel="icon" href="{{ $siteFavicon }}">
    @endif
    <link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">
    @stack('head')
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/toutiao-news-20260426/theme.css') }}">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    @if(!empty($headAnalyticsCode))
        {!! $headAnalyticsCode !!}
    @endif
    @php
        $schemaAtContext = chr(64).'context';
        $schemaAtType = chr(64).'type';
        $websiteSchema = [
            $schemaAtContext => 'https://schema.org',
            $schemaAtType => 'WebSite',
            'name' => $siteName,
            'url' => route('site.home'),
            'potentialAction' => [
                $schemaAtType => 'SearchAction',
                'target' => route('site.home').'?search={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    @endphp
    <script type="application/ld+json">
        {!! json_encode($websiteSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>
</head>
<body class="tt-body">
    @include('theme.toutiao-news-20260426.partials.header')
    <main class="tt-main">
        @yield('content')
    </main>
    @include('theme.toutiao-news-20260426.partials.footer')
    @stack('scripts')
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script src="{{ asset('themes/toutiao-news-20260426/theme.js') }}" defer></script>
</body>
</html>
