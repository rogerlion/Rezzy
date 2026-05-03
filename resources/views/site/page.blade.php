@extends('site.layout')

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

        // 面包屑（每个实体页都有一条：首页 > 当前页）
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
    <article class="site-entity-page" style="max-width: 960px; margin: 2rem auto; padding: 0 1rem;">
        <nav aria-label="Breadcrumb" style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">
            <a href="{{ route('site.home') }}">{{ __('front.nav.home') }}</a>
            <span> / </span>
            <span>{{ $pageHeading }}</span>
        </nav>

        <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 1.5rem;">{{ $pageHeading }}</h1>

        <div class="site-entity-body">
            {!! $bodyHtml !!}
        </div>
    </article>
@endsection
