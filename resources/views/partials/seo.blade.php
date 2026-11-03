{{-- Metadata SEO & kartu berbagi. Diisi di server (App\Support\Seo) supaya crawler dan WhatsApp/Facebook membacanya
     dari HTML awal; mereka tidak menjalankan React. Semua nilai di-escape oleh {{ }}. --}}
@php($seo = app(\App\Support\Seo::class))
<title inertia>{{ $seo->title }}</title>
<meta name="description" content="{{ $seo->description }}" />
<meta name="robots" content="{{ $seo->robots() }}" />
@if ($seo->canonical)
    <link rel="canonical" href="{{ $seo->canonical }}" />

    <meta property="og:type" content="{{ $seo->type }}" />
    <meta property="og:site_name" content="{{ config('school.name') }}" />
    <meta property="og:locale" content="id_ID" />
    <meta property="og:url" content="{{ $seo->canonical }}" />
    <meta property="og:title" content="{{ $seo->ogTitle ?? $seo->title }}" />
    <meta property="og:description" content="{{ $seo->description }}" />
    <meta name="twitter:card" content="{{ $seo->image ? 'summary_large_image' : 'summary' }}" />
    <meta name="twitter:title" content="{{ $seo->ogTitle ?? $seo->title }}" />
    <meta name="twitter:description" content="{{ $seo->description }}" />
    @if ($seo->image)
        <meta property="og:image" content="{{ $seo->image }}" />
        @if ($seo->imageWidth && $seo->imageHeight)
            <meta property="og:image:width" content="{{ $seo->imageWidth }}" />
            <meta property="og:image:height" content="{{ $seo->imageHeight }}" />
        @endif
        @if ($seo->imageAlt)
            <meta property="og:image:alt" content="{{ $seo->imageAlt }}" />
            <meta name="twitter:image:alt" content="{{ $seo->imageAlt }}" />
        @endif
        <meta name="twitter:image" content="{{ $seo->image }}" />
    @endif
    @if ($seo->type === 'article')
        @if ($seo->publishedTime)
            <meta property="article:published_time" content="{{ $seo->publishedTime }}" />
        @endif
        @if ($seo->section)
            <meta property="article:section" content="{{ $seo->section }}" />
        @endif
    @endif
@endif
@if ($seo->preloadImage)
    <link rel="preload" as="image" href="{{ $seo->preloadImage }}" fetchpriority="high" />
@endif
@foreach ($seo->jsonLd as $node)
    <script type="application/ld+json">{!! $seo->jsonLdString($node) !!}</script>
@endforeach
