@php
    $ui = [
        'tr' => ['skip' => 'İçeriğe geç', 'languages' => 'Dil seçimi', 'contents' => 'İçindekiler', 'contact' => 'Gizlilik için bize ulaşın', 'home' => 'SryyaLabs ana sayfa', 'mail' => 'Gizlilik hakkında e-posta gönder'],
        'en' => ['skip' => 'Skip to content', 'languages' => 'Language', 'contents' => 'Contents', 'contact' => 'Contact us about privacy', 'home' => 'SryyaLabs home', 'mail' => 'Email us about privacy'],
        'de' => ['skip' => 'Zum Inhalt', 'languages' => 'Sprache', 'contents' => 'Inhalt', 'contact' => 'Kontakt zum Datenschutz', 'home' => 'SryyaLabs Startseite', 'mail' => 'E-Mail zum Datenschutz senden'],
    ][$language];
    $languageNames = ['tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch'];
@endphp
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minimal Fasting {{ $copy['title'] }} · SryyaLabs</title>
    <meta name="description" content="{{ $copy['description'] }}">
    <meta name="theme-color" content="#faf7f2">
    <link rel="icon" href="{{ asset('images/minimal-fasting-logo.png') }}">
    <link rel="canonical" href="{{ route('minimal-fasting.privacy', $language === 'tr' ? [] : ['lang' => $language]) }}">
    @foreach ($languageNames as $locale => $name)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ route('minimal-fasting.privacy', $locale === 'tr' ? [] : ['lang' => $locale]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('minimal-fasting.privacy') }}">
    <style>
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:#faf7f2;color:#142830;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.75}a{color:#426d83;text-underline-offset:4px}a:focus-visible{outline:3px solid #aa7652;outline-offset:5px}header,main,footer{max-width:920px;margin:auto;padding:24px}header{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}.brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit}.brand img{border-radius:50%}.brand span{font-size:18px;font-weight:750;line-height:1.15}.brand strong{display:block;color:#628ca0}.langs{display:flex;flex-wrap:wrap;gap:16px;font-size:14px}.langs a{padding:6px 0}.langs [aria-current]{font-weight:750}main{padding-top:28px;padding-bottom:56px}h1{font-size:clamp(32px,6vw,52px);line-height:1.15;letter-spacing:-.035em;margin:12px 0 20px}h2{font-size:22px;line-height:1.4;margin:0 0 16px}.eyebrow{color:#628ca0;font-weight:700;font-size:13px;letter-spacing:.1em}.intro{max-width:690px;font-size:18px}.date{color:#546c78;font-size:14px}.badges{display:flex;flex-wrap:wrap;gap:8px;margin:22px 0}.badge{background:#e6edf0;color:#426275;padding:6px 12px;border-radius:24px;font-size:13px;font-weight:600}.card{background:#fffdfa;border:1px solid #e1e6e6;border-radius:22px;padding:28px;margin-top:20px}.contents ul{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;padding-left:20px;margin-bottom:0}section{scroll-margin-top:24px}p{overflow-wrap:anywhere}.contact{background:#1f3b49;color:#fff}.contact a{color:#eef6fa}.button{display:inline-block;background:#edf3f5;color:#244553!important;padding:12px 18px;border-radius:12px;font-weight:700;text-decoration:none}.contact p{margin:10px 0}.contact .button{margin-top:12px}footer{border-top:1px solid #e1e6e6;font-size:14px;color:#546c78;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}.skip{position:absolute;top:-100px;background:#fff;padding:12px}.skip:focus{top:10px}@media(max-width:640px){header,main,footer{padding-left:20px;padding-right:20px}.card{padding:22px}.contents ul{grid-template-columns:1fr}.intro{font-size:17px}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}@media(prefers-color-scheme:dark){body{background:#18272e;color:#f3f1ec}.card{background:#20343d;border-color:#38515b}a,.eyebrow{color:#a6c7d7}.date,footer{color:#b5c5ce}.badge{background:#2a4654;color:#dceaf1}.contact{background:#294b5b}footer{border-color:#38515b}.skip{background:#20343d}}
    </style>
</head>
<body>
<a class="skip" href="#main">{{ $ui['skip'] }}</a>
<header>
    <a class="brand" href="{{ route('minimal-fasting', $language === 'tr' ? [] : ['lang' => $language]) }}"><img src="{{ asset('images/minimal-fasting-logo.png') }}" alt="" width="48" height="48"><span>Minimal<strong>Fasting</strong></span></a>
    <nav class="langs" aria-label="{{ $ui['languages'] }}">
        @foreach ($languageNames as $locale => $name)
            <a href="{{ route('minimal-fasting.privacy', $locale === 'tr' ? [] : ['lang' => $locale]) }}" lang="{{ $locale }}" @if($language === $locale) aria-current="page" @endif>{{ $name }}</a>
        @endforeach
    </nav>
</header>
<main id="main">
    <span class="eyebrow">MINIMAL FASTING</span>
    <h1>{{ $copy['title'] }}</h1>
    <p class="intro">{{ $copy['intro'] }}</p>
    <p class="date">{{ $copy['updated'] }}</p>
    <div class="badges">@foreach ($copy['badges'] as $badge)<span class="badge">{{ $badge }}</span>@endforeach</div>
    <nav class="card contents" aria-label="{{ $ui['contents'] }}">
        <h2>{{ $ui['contents'] }}</h2>
        <ul>@foreach ($copy['sections'] as $section)<li><a href="#{{ $section['id'] }}">{{ $section['title'] }}</a></li>@endforeach<li><a href="#contact">{{ $ui['contact'] }}</a></li></ul>
    </nav>
    @foreach ($copy['sections'] as $section)
        <section class="card" id="{{ $section['id'] }}">
            <h2>{{ $section['title'] }}</h2>
            @foreach ($section['paragraphs'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
            @if ($section['id'] === 'backups')
                <p><a href="https://policies.google.com/privacy">Google Privacy</a> · <a href="https://www.apple.com/legal/privacy/">Apple Privacy</a></p>
            @endif
        </section>
    @endforeach
    <section class="card contact" id="contact">
        <h2>{{ $ui['contact'] }}</h2>
        <p>Süreyya Karabay · SryyaLabs · Minimal Fasting</p>
        <p><a href="mailto:{{ $email }}">{{ $email }}</a></p>
        <a class="button" href="mailto:{{ $email }}?subject=Minimal%20Fasting%20privacy">{{ $ui['mail'] }}</a>
    </section>
</main>
<footer><span>© {{ date('Y') }} SryyaLabs · Minimal Fasting</span><a href="{{ route('home', $language === 'tr' ? [] : ['lang' => $language]) }}">{{ $ui['home'] }}</a></footer>
</body>
</html>
