<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $copy['meta']['title'] }}</title>
    <meta name="description" content="{{ $copy['meta']['description'] }}">
    <meta name="theme-color" content="#07111f">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
    <link rel="canonical" href="{{ route('home', $language === 'tr' ? [] : ['lang' => $language]) }}">
    @foreach (['tr', 'en', 'ru'] as $locale)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ route('home', $locale === 'tr' ? [] : ['lang' => $locale]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('home') }}">
    <meta property="og:title" content="{{ $copy['meta']['title'] }}">
    <meta property="og:description" content="{{ $copy['meta']['description'] }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('home', $language === 'tr' ? [] : ['lang' => $language]) }}">
    <meta property="og:image" content="{{ asset('og.png') }}">
    <meta property="og:site_name" content="sryya.dev">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/studio.css', 'resources/js/studio.js'])
</head>
<body>
<a class="skip-link" href="#top">{{ ['tr' => 'İçeriğe geç', 'en' => 'Skip to content', 'ru' => 'Перейти к содержанию'][$language] }}</a>
<header class="site-header" data-header>
    <div class="container nav-wrap">
        <a class="brand" href="#top" aria-label="{{ $copy['nav']['homeLabel'] }}"><img src="{{ asset('sryyadev-logo.png') }}" alt="sryya.dev" width="900" height="190"></a>
        <button class="menu-button" type="button" aria-label="{{ $copy['nav']['menuLabel'] }}" aria-expanded="false" aria-controls="main-nav" data-menu-button><span></span><span></span></button>
        <nav id="main-nav" class="main-nav" aria-label="{{ $copy['nav']['navLabel'] }}" data-menu>
            @foreach (['services', 'approach', 'projects', 'about'] as $section)
                <a href="#{{ $section }}">{{ $copy['nav'][$section] }}</a>
            @endforeach
            <a href="https://sryya.dev/{{ $language === 'tr' ? '' : $language.'/' }}blog/">{{ $copy['nav']['blog'] }}</a>
            <div class="language-switch" aria-label="{{ $copy['nav']['languageLabel'] }}">
                @foreach (['tr', 'en', 'ru'] as $locale)
                    <a href="{{ route('home', $locale === 'tr' ? [] : ['lang' => $locale]) }}" class="{{ $language === $locale ? 'active' : '' }}" lang="{{ $locale }}" @if($language === $locale) aria-current="page" @endif>{{ strtoupper($locale) }}</a>
                @endforeach
            </div>
            <a class="nav-cta" href="#contact">{{ $copy['nav']['cta'] }}</a>
        </nav>
    </div>
</header>
<main id="top">
    <section class="hero">
        <div class="hero-grid" aria-hidden="true"></div>
        <div class="container hero-layout">
            <div class="hero-copy">
                <div class="eyebrow"><span></span>{{ $copy['hero']['eyebrow'] }}</div>
                <h1>{{ $copy['hero']['line1'] }}<br><em>{{ $copy['hero']['accent'] }}</em><br>{{ $copy['hero']['line3'] }}</h1>
                <p>{{ $copy['hero']['intro'] }}</p>
                <div class="hero-actions"><a class="button button-primary" href="#contact">{{ $copy['hero']['cta'] }}<span aria-hidden="true">↗</span></a><a class="text-link" href="#projects">{{ $copy['hero']['work'] }}<span aria-hidden="true">↓</span></a></div>
            </div>
            <div class="hero-visual" aria-label="{{ $copy['hero']['aria'] }}">
                <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
                <div class="visual-card card-main"><span class="card-kicker">sryya / studio</span><div class="signal"><i></i><i></i><i></i><i></i><i></i></div><strong>{{ $copy['hero']['from'] }}<br>{{ $copy['hero']['launch'] }}</strong><div class="card-footer"><span>01</span><span>{{ $copy['hero']['disciplines'] }}</span></div></div>
                <div class="visual-card card-small card-code"><span>{{ $copy['hero']['performance'] }}</span><strong>98</strong><small>/ 100</small></div>
                <div class="visual-card card-small card-status"><span class="status-dot"></span><strong>{{ $copy['hero']['live'] }}</strong><small>{{ $copy['hero']['seamless'] }}</small></div>
            </div>
        </div>
        <div class="container proof-row">@foreach ($copy['hero']['proof'] as $proof)<span>{{ $proof }}</span>@endforeach</div>
    </section>
    <section id="services" class="section services"><div class="container">
        <div class="section-heading split-heading"><div><span class="section-index">{{ $copy['services']['index'] }}</span><h2>{{ $copy['services']['title1'] }}<br>{{ $copy['services']['title2'] }}</h2></div><p>{{ $copy['services']['intro'] }}</p></div>
        <div class="service-grid">@foreach ($copy['services']['items'] as $service)<article class="service-card"><span>{{ $service['no'] }}</span><h3>{{ $service['title'] }}</h3><p>{{ $service['text'] }}</p><i aria-hidden="true">↗</i></article>@endforeach</div>
    </div></section>
    <section id="approach" class="section process"><div class="container process-layout">
        <div class="process-intro"><span class="section-index light">{{ $copy['approach']['index'] }}</span><h2>{{ $copy['approach']['title1'] }}<br>{{ $copy['approach']['title2'] }}</h2><p>{{ $copy['approach']['intro'] }}</p></div>
        <div class="steps">@foreach ($copy['approach']['steps'] as $step)<article class="step"><span>{{ $step['no'] }}</span><div><h3>{{ $step['title'] }}</h3><p>{{ $step['text'] }}</p></div></article>@endforeach</div>
    </div></section>
    <section id="projects" class="section projects"><div class="container">
        <div class="section-heading split-heading project-heading"><div><span class="section-index">{{ $copy['projects']['index'] }}</span><h2>{{ $copy['projects']['title'] }}</h2></div><p>{{ $copy['projects']['intro'] }}</p></div>
        <div class="project-carousel" data-project-carousel>
            <div class="project-list" role="region" aria-label="{{ $copy['projects']['carouselLabel'] }}" tabindex="0" data-project-track>
                @foreach ($copy['projects']['items'] as $project)
                    <article class="project-feature project-{{ $loop->iteration }}" data-project-slide>
                        <div class="project-copy"><div><span class="project-type">{{ $project['type'] }}</span><h3>{{ $project['name'] }}</h3><p>{{ $project['description'] }}</p></div><div class="project-card-actions"><a href="https://sryya.dev/{{ $language === 'tr' ? '' : $language.'/' }}projects/{{ $project['slug'] }}/">{{ $copy['projects']['visit'] }}<span aria-hidden="true">↗</span></a>@if (!$project['url'])<span class="project-note">{{ $project['note'] }}</span>@endif</div></div>
                        <div class="project-art" aria-label="{{ $project['name'] }}"><div class="project-logo-wrap"><img src="{{ asset(ltrim($project['logo'], '/')) }}" alt="{{ $project['name'] }} logo" width="700" height="700" loading="lazy" decoding="async"></div><small class="project-art-note">{{ $project['note'] }}</small><div class="project-lines" aria-hidden="true"><i></i><i></i><i></i></div></div>
                    </article>
                @endforeach
            </div>
            <div class="carousel-footer"><span class="carousel-count" aria-live="polite"><b data-project-current>01</b><i></i><span>{{ str_pad(count($copy['projects']['items']), 2, '0', STR_PAD_LEFT) }}</span></span><div class="carousel-actions"><button type="button" aria-label="{{ $copy['projects']['previous'] }}" data-project-prev disabled>←</button><button type="button" aria-label="{{ $copy['projects']['next'] }}" data-project-next>→</button></div></div>
        </div>
    </div></section>
    <section id="about" class="section about"><div class="container about-layout">
        <div class="about-statement"><span class="section-index">{{ $copy['about']['index'] }}</span><h2>{{ $copy['about']['title1'] }}<br>{{ $copy['about']['title2'] }}</h2></div><div class="about-copy"><p class="lead">{{ $copy['about']['lead'] }}</p><p>{{ $copy['about']['text'] }}</p><div class="metrics">@foreach ($copy['about']['metrics'] as [$value, $label])<div><strong>{{ $value }}</strong><span>{{ $label }}</span></div>@endforeach</div></div>
    </div></section>
    <section class="section testimonials" aria-labelledby="testimonials-title">
        <div class="container testimonial-heading"><div><span class="section-index light">{{ $copy['testimonials']['index'] }}</span><h2 id="testimonials-title">{{ $copy['testimonials']['title'] }}</h2></div><p>{{ $copy['testimonials']['intro'] }}</p></div>
        <div class="testimonial-track" role="region" aria-label="{{ $copy['testimonials']['label'] }}" tabindex="0"><div class="testimonial-spacer" aria-hidden="true"></div>@foreach ($copy['testimonials']['items'] as $testimonial)<article class="testimonial-card"><div><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span aria-hidden="true">✦</span></div><blockquote>“{{ $testimonial['quote'] }}”</blockquote><footer><strong>{{ $testimonial['role'] }}</strong><span>{{ $testimonial['sector'] }}</span></footer></article>@endforeach<div class="testimonial-spacer" aria-hidden="true"></div></div>
    </section>
    <section id="contact" class="contact"><div class="container contact-inner"><span class="section-index light">{{ $copy['contact']['index'] }}</span><h2>{{ $copy['contact']['title'] }}</h2><p>{{ $copy['contact']['text'] }}</p><a class="button button-light" href="mailto:info@sryya.dev?subject={{ rawurlencode($copy['contact']['subject']) }}">info@sryya.dev<span aria-hidden="true">↗</span></a></div></section>
</main>
<footer class="footer"><div class="container footer-grid">
    <div><a class="brand brand-light" href="#top"><img src="{{ asset('sryyadev-logo.png') }}" alt="sryya.dev" width="900" height="190"></a><p>{{ $copy['footer']['tagline'] }}</p></div>
    <div class="footer-links"><a href="#services">{{ $copy['footer']['services'] }}</a><a href="#projects">{{ $copy['footer']['projects'] }}</a><a href="#contact">{{ $copy['footer']['contact'] }}</a></div>
    <div class="footer-meta"><a href="mailto:info@sryya.dev">info@sryya.dev</a><span>© {{ now()->year }} sryya.dev</span></div>
</div></footer>
</body>
</html>
