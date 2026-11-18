@php
    $publicPage = in_array(request()->route()?->getName(), ['home', 'hotel.detail', 'deals', 'blog', 'blog.detail', 'experiences.index', 'experiences.show', 'guides.index', 'guides.show'], true);
    $configuredBase = rtrim(config('app.url', ''), '/');
    $validBase = filter_var($configuredBase, FILTER_VALIDATE_URL) && in_array(parse_url($configuredBase, PHP_URL_SCHEME), ['http', 'https'], true)
        && !parse_url($configuredBase, PHP_URL_USER) && !parse_url($configuredBase, PHP_URL_QUERY) && !parse_url($configuredBase, PHP_URL_FRAGMENT);
    $metadataTitle = html_entity_decode(strip_tags($__env->yieldContent('title', 'Angel')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $metadataDescription = request()->routeIs('experiences.*', 'guides.*')
        ? html_entity_decode(strip_tags($__env->yieldContent('description', 'Khám phá địa điểm tham quan, ẩm thực và hoạt động địa phương cùng Angel.')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        : 'Xem thông tin khách sạn, loại phòng và lựa chọn lưu trú trên Angel.';
@endphp
@if($publicPage)
    <meta name="description" content="{{ $metadataDescription }}">
    <meta property="og:title" content="{{ str_ends_with($metadataTitle, 'Angel') ? $metadataTitle : $metadataTitle . ' — Angel' }}">
    <meta property="og:description" content="{{ $metadataDescription }}">
    <meta property="og:type" content="{{ request()->routeIs('guides.show') ? 'article' : 'website' }}">
    @if($validBase)
        <link rel="canonical" href="{{ $configuredBase . '/' . ltrim(request()->path() === '/' ? '' : request()->path(), '/') }}">
        <meta property="og:url" content="{{ $configuredBase . '/' . ltrim(request()->path() === '/' ? '' : request()->path(), '/') }}">
        <meta property="og:image" content="{{ $configuredBase . '/' . config('brand.logo_url') }}">
    @endif
@else
    <meta name="robots" content="noindex,noarchive">
@endif
