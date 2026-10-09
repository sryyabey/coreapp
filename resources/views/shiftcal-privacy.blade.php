@php
    $tr = $language === 'tr';
    $title = $tr ? 'ShiftCal Gizlilik Politikası' : 'ShiftCal Privacy Policy';
@endphp
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · SryyaLabs</title>
    <meta name="description" content="{{ $tr ? 'ShiftCal kişisel veriler, kullanım amaçları, paylaşım, saklama ve hesap silme talepleri hakkında gizlilik politikası.' : 'ShiftCal privacy policy covering personal data, use, sharing, retention and account deletion requests.' }}">
    <meta name="theme-color" content="#0f6b4f">
    <link rel="icon" href="{{ asset('images/shiftcal-logo-ying.png') }}">
    <link rel="canonical" href="{{ route('shiftcal.privacy', $tr ? [] : ['lang' => 'en']) }}">
    @foreach (['tr', 'en'] as $locale)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ route('shiftcal.privacy', $locale === 'tr' ? [] : ['lang' => $locale]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('shiftcal.privacy') }}">
    <style>
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:#f6f3ea;color:#20352c;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.75}a{color:#0f6b4f;text-underline-offset:4px}a:focus-visible{outline:3px solid #b47b22;outline-offset:5px}header,main,footer{max-width:980px;margin:auto;padding:24px}header{display:flex;align-items:center;justify-content:space-between;gap:20px}.brand{display:flex;align-items:center;gap:12px;font-size:22px;font-weight:750;text-decoration:none}.brand img{border-radius:12px}.langs{display:flex;gap:16px}.langs [aria-current]{font-weight:750}main{padding-top:36px;padding-bottom:64px}.label{font-size:12px;letter-spacing:.15em;font-weight:750;text-transform:uppercase;color:#507461}h1{font-size:clamp(32px,6vw,54px);line-height:1.15;letter-spacing:-.04em;margin:16px 0 20px}h2{font-size:23px;line-height:1.4;margin:0 0 16px}.date{color:#607168;font-size:14px}.card{background:#fffdf8;border:1px solid #dbe4dc;border-radius:20px;padding:28px;margin-top:24px}.contents ul{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;padding-left:20px;margin-bottom:0}section{scroll-margin-top:24px}p{overflow-wrap:anywhere}.contact{background:#163d32;color:#fff}.contact a{color:#e6f2df}.button{display:inline-block;background:#e6f2df;color:#163d32!important;padding:12px 20px;border-radius:12px;font-weight:700;text-decoration:none}footer{border-top:1px solid #dbe4dc;font-size:14px;color:#607168;display:flex;justify-content:space-between;gap:16px}.skip{position:absolute;top:-100px;background:white;padding:12px}.skip:focus{top:10px}@media(max-width:640px){header,main,footer{padding-left:20px;padding-right:20px}.card{padding:22px}.contents ul{grid-template-columns:1fr}.langs{gap:12px;font-size:14px}footer{flex-direction:column}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
    </style>
</head>
<body>
<a class="skip" href="#main">{{ $tr ? 'İçeriğe geç' : 'Skip to content' }}</a>
<header>
    <a class="brand" href="{{ route('shiftcal', $tr ? [] : ['lang' => 'en']) }}"><img src="{{ asset('images/shiftcal-logo-ying.png') }}" alt="" width="40" height="40">ShiftCal</a>
    <nav class="langs" aria-label="{{ $tr ? 'Dil seçimi' : 'Language' }}"><a href="{{ route('shiftcal.privacy') }}" lang="tr" @if($tr) aria-current="page" @endif>Türkçe</a><a href="{{ route('shiftcal.privacy', ['lang' => 'en']) }}" lang="en" @if(!$tr) aria-current="page" @endif>English</a></nav>
</header>
<main id="main">
    <span class="label">ShiftCal / {{ $tr ? 'Gizlilik' : 'Privacy' }}</span>
    <h1>{{ $tr ? 'Gizlilik Politikası' : 'Privacy Policy' }}</h1>
    <p class="date">{{ $tr ? 'Son güncelleme: 9 Ekim 2026' : 'Last updated: 9 October 2026' }}</p>
    <nav class="card contents" aria-label="{{ $tr ? 'İçindekiler' : 'Contents' }}"><h2>{{ $tr ? 'İçindekiler' : 'Contents' }}</h2><ul>@foreach ($sections as $section)<li><a href="#{{ $section['id'] }}">{{ $section['title'] }}</a></li>@endforeach</ul></nav>
    @foreach ($sections as $section)
        <section class="card" id="{{ $section['id'] }}">
            <h2>{{ $section['title'] }}</h2>
            @foreach ($section['paragraphs'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
            @if ($section['id'] === 'sharing')
                <p><a href="https://firebase.google.com/support/privacy">{{ $tr ? 'Firebase gizlilik ve güvenlik bilgileri' : 'Firebase privacy and security information' }}</a></p>
            @endif
            @if ($section['id'] === 'delete-account')
                <a class="button" href="mailto:{{ $email }}?subject=ShiftCal%20account%20deletion">{{ $tr ? 'Hesap silme talebi gönder' : 'Request account deletion' }}</a>
            @endif
        </section>
    @endforeach
    <section class="card contact" id="contact"><h2>{{ $tr ? 'Gizlilik için bize ulaşın' : 'Contact us about privacy' }}</h2><p>Süreyya Karabay · SryyaLabs · ShiftCal</p><p><a href="mailto:{{ $email }}">{{ $email }}</a></p><a href="{{ route('shiftcal.support', $tr ? [] : ['lang' => 'en']) }}">{{ $tr ? 'Destek merkezi' : 'Support centre' }}</a></section>
</main>
<footer><span>© {{ date('Y') }} SryyaLabs · ShiftCal</span><a href="{{ route('shiftcal', $tr ? [] : ['lang' => 'en']) }}">{{ $tr ? 'ShiftCal’i keşfet' : 'Explore ShiftCal' }}</a><a href="{{ route('shiftcal.terms', $tr ? [] : ['lang' => 'en']) }}">{{ $tr ? 'Kullanım koşulları' : 'Terms of service' }}</a></footer>
</body>
</html>
