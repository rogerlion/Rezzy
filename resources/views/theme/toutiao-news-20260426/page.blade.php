@extends('theme.toutiao-news-20260426.layout')

@push('head')
    @php
        $atContext = chr(64).'context';
        $atType = chr(64).'type';
        $schemaType = (string) ($meta['schema_type'] ?? '');
        $schemaData = (array) ($meta['schema_data'] ?? []);

        $primarySchema = $schemaType !== '' ? array_merge(
            [$atContext => 'https://schema.org', $atType => $schemaType],
            $schemaData
        ) : null;

        $breadcrumbSchema = [
            $atContext => 'https://schema.org',
            $atType => 'BreadcrumbList',
            'itemListElement' => [
                [
                    $atType => 'ListItem',
                    'position' => 1,
                    'name' => $siteName,
                    'item' => route('site.home'),
                ],
                [
                    $atType => 'ListItem',
                    'position' => 2,
                    'name' => $pageHeading,
                    'item' => $canonicalUrl,
                ],
            ],
        ];
    @endphp

    <meta property="og:title" content="{{ $pageHeading }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonicalUrl }}">

    @if($primarySchema)
        <script type="application/ld+json">
            {!! json_encode($primarySchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
        </script>
    @endif

    <script type="application/ld+json">
        {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>
@endpush

@section('content')
    <div class="tt-shell tt-article-layout">
        <nav class="tt-breadcrumb tt-article-module" aria-label="Breadcrumb">
            <a href="{{ route('site.home') }}">{{ __('front.nav.home') }}</a>
            <span>/</span>
            <span>{{ $pageHeading }}</span>
        </nav>

        <article class="tt-article-main tt-article-module">
            <h1 class="tt-article-h1">{{ $pageHeading }}</h1>

            @if(!empty($pageDescription))
                <p class="mt-5 rounded-2xl bg-gray-50 p-5 text-lg leading-8 text-gray-600">{{ $pageDescription }}</p>
            @endif

            <div class="tt-prose">
                {!! $bodyHtml !!}
            </div>
        </article>
    </div>
@endsection
