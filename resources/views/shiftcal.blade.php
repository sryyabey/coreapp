@php
    $tr = $language === 'tr';
    $description = $tr ? 'Vardiyalarını planla, çalışma saatlerini takip et ve birlikte vakit ayır. ShiftCal ile iş ve özel hayatın tek takvimde.' : 'Plan your shifts, track working hours and make time together. Bring work and life into one calendar with ShiftCal.';
    $features = $tr ? [
        ['01', 'Vardiyalarına düzen getir.', 'Çalışma saatlerini ve etkinliklerini takvimine ekle. Tekrarlayan düzenler için şablonlardan yararlan.'],
        ['02', 'Gününü bir bakışta gör.', 'Günlük programın ve zaman çizelgen ile çalışma, kişisel etkinlik ve boş zamanlarını takip et.'],
        ['03', 'Birlikte zaman ayır.', 'Partnerinle bağlantı kur, ortak boş zamanları keşfet ve birlikte plan yap.'],
        ['04', 'Çalışma saatlerini takip et.', 'Saatlik ücretini ayarla ve çalışma kayıtların üzerinden hesaplanan kazanç tahminini gör.'],
        ['05', 'Hatırlatmalarla haberdar ol.', 'Bildirim tercihlerini kendine göre düzenle, planlarını takip etmeyi kolaylaştır.'],
        ['06', 'Takvimini kendine göre düzenle.', 'Etkinlik renklerini, görünümü ve uygulama dilini seç. Türkçe veya İngilizce kullan.'],
    ] : [
        ['01', 'Bring order to your shifts.', 'Add working hours and activities to your calendar. Use templates for recurring schedules.'],
        ['02', 'See your day at a glance.', 'Follow work, personal activities and free time through your daily schedule and timeline.'],
        ['03', 'Make time together.', 'Connect with your partner, discover shared free time and make plans together.'],
        ['04', 'Keep track of working hours.', 'Set your hourly rate and see estimated earnings calculated from your work entries.'],
        ['05', 'Stay in the loop.', 'Adjust notification preferences to make keeping up with your plans easier.'],
        ['06', 'Make your calendar yours.', 'Choose activity colors, appearance and app language. Available in Turkish and English.'],
    ];
@endphp
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tr ? 'ShiftCal — Vardiya takvimin, hayatına uyum sağlar.' : 'ShiftCal — A shift calendar that fits your life.' }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="theme-color" content="#0f6b4f">
    <link rel="icon" href="{{ asset('images/shiftcal-logo-ying.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/shiftcal-logo-ying.png') }}">
    <link rel="canonical" href="{{ route('shiftcal', $tr ? [] : ['lang' => 'en']) }}">
    @foreach(['tr', 'en'] as $locale)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ route('shiftcal', $locale === 'tr' ? [] : ['lang' => $locale]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('shiftcal') }}">
    <meta property="og:title" content="ShiftCal">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('shiftcal', $tr ? [] : ['lang' => 'en']) }}">
    <meta property="og:image" content="{{ asset('images/shiftcal-logo-ying.png') }}">
    <style>
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:#f6f3ea;color:#193b30;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.6}a{color:inherit;text-underline-offset:5px}a:focus-visible{outline:3px solid #df9220;outline-offset:6px}.wrap{max-width:1160px;margin:auto;padding:0 28px}header{padding:24px 0}nav,.brand,.links,.actions{display:flex;align-items:center;gap:24px}nav{justify-content:space-between}.brand{gap:12px;font-weight:800;font-size:23px;text-decoration:none}.brand img{border-radius:14px}.links{font-size:14px}.links [aria-current]{font-weight:800}.hero{display:grid;grid-template-columns:1.15fr 1fr;align-items:center;gap:72px;padding:72px 0 88px}.eyebrow{font-size:12px;font-weight:750;text-transform:uppercase;letter-spacing:.16em;color:#0f6b4f}h1{font-size:clamp(42px,5.8vw,72px);line-height:1.07;letter-spacing:-.055em;margin:22px 0}h1 em{font-style:normal;color:#0f6b4f}.intro{font-size:19px;color:#617166;max-width:540px}.actions{flex-wrap:wrap;margin-top:30px;gap:16px}.button{display:inline-block;padding:14px 22px;border-radius:14px;background:#0f6b4f;color:white;font-weight:700;text-decoration:none}.availability{font-size:13px;color:#617166;margin-top:20px}.preview{background:#fffdf8;border:1px solid #d9e1d4;border-radius:32px;padding:28px;box-shadow:0 24px 70px #193b3012;transform:rotate(2deg)}.preview-head{display:flex;justify-content:space-between;align-items:center;gap:12px}.preview-head strong{font-size:23px}.dot{width:10px;height:10px;background:#a8c686;border-radius:50%;display:inline-block}.clock{width:200px;height:200px;margin:30px auto;border-radius:50%;background:conic-gradient(from 0deg,#a8c686 0deg 75deg,#0f6b4f 75deg 195deg,#f6a623 195deg 235deg,#e9edde 235deg 360deg);display:grid;place-items:center}.clock-inner{width:155px;height:155px;background:#fffdf8;border-radius:50%;display:flex;flex-direction:column;justify-content:center;align-items:center}.clock-inner strong{font-size:32px;letter-spacing:-.05em}.clock-inner span{font-size:12px;color:#617166}.entry{display:flex;justify-content:space-between;align-items:center;border-radius:14px;padding:13px 16px;margin-top:10px;background:#eef3e7;gap:12px}.entry.personal{background:#fff0d6}.entry small{color:#617166}.example{font-size:12px;text-align:center;color:#617166;margin:18px 0 0}.section{padding:72px 0;border-top:1px solid #d9e1d4}h2{font-size:clamp(30px,4vw,44px);line-height:1.15;letter-spacing:-.035em;margin:14px 0 30px}.features{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.feature{padding:26px;background:#fffdf8;border:1px solid #e0e4d8;border-radius:20px}.number{font-size:12px;font-weight:700;color:#0f6b4f}.feature h3{font-size:21px;line-height:1.3;margin:22px 0 12px}.feature p{font-size:15px;color:#617166;margin:0}.banner{background:#163d32;color:#fffdf8;border-radius:28px;padding:42px;display:flex;align-items:center;justify-content:space-between;gap:32px;margin-bottom:64px}.banner h2{font-size:32px;margin:0 0 14px}.banner p{color:#d1dfd6;margin:0;max-width:600px}.banner .button{background:#d9e9c9;color:#163d32;white-space:nowrap}footer{border-top:1px solid #d9e1d4;padding:24px 0;font-size:13px;color:#617166}.footer-inner{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap}.skip{position:absolute;top:-100px;background:#fff;padding:12px}.skip:focus{top:10px}@media(max-width:850px){.hero{gap:36px;padding:48px 0 64px}.features{grid-template-columns:repeat(2,1fr)}.links{gap:14px}.preview{padding:22px}.banner{padding:30px}}@media(max-width:620px){.wrap{padding:0 20px}.hero{grid-template-columns:1fr}.preview{transform:none;max-width:420px;width:100%;margin:auto}.features{grid-template-columns:1fr}.links .features-link{display:none}.brand{font-size:20px}.brand img{width:38px;height:38px}.links{gap:12px}.banner{flex-direction:column;align-items:flex-start}.section{padding:48px 0}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
    </style>
</head>
<body>
<a class="skip" href="#main">{{ $tr ? 'İçeriğe geç' : 'Skip to content' }}</a>
<header class="wrap"><nav aria-label="{{ $tr ? 'Ana menü' : 'Main navigation' }}">
    <a class="brand" href="{{ route('shiftcal', $tr ? [] : ['lang' => 'en']) }}"><img src="{{ asset('images/shiftcal-logo-ying.png') }}" alt="" width="44" height="44">ShiftCal</a>
    <div class="links"><a class="features-link" href="#features">{{ $tr ? 'Özellikler' : 'Features' }}</a><a href="{{ route('shiftcal.support', $tr ? [] : ['lang' => 'en']) }}">{{ $tr ? 'Destek' : 'Support' }}</a><a href="{{ route('shiftcal') }}" lang="tr" aria-label="Türkçe" @if($tr) aria-current="page" @endif>TR</a><a href="{{ route('shiftcal', ['lang' => 'en']) }}" lang="en" aria-label="English" @if(!$tr) aria-current="page" @endif>EN</a></div>
</nav></header>
<main id="main" class="wrap">
    <section class="hero">
        <div><span class="eyebrow">{{ $tr ? 'İşin, zamanın, hayatın.' : 'Your work. Your time. Your life.' }}</span>
            <h1>{{ $tr ? 'Vardiyaların değişir.' : 'Your shifts change.' }}<br><em>{{ $tr ? 'Hayatın akmaya devam eder.' : 'Life keeps moving.' }}</em></h1>
            <p class="intro">{{ $description }}</p>
            <div class="actions"><a class="button" href="#features">{{ $tr ? 'ShiftCal’i keşfet' : 'Explore ShiftCal' }} <span aria-hidden="true">↓</span></a><a href="{{ route('shiftcal.support', $tr ? [] : ['lang' => 'en']) }}">{{ $tr ? 'Bize ulaş' : 'Get in touch' }}</a></div>
            <p class="availability">{{ $tr ? 'iOS ve Android için tasarlandı · Türkçe ve İngilizce' : 'Designed for iOS and Android · Turkish and English' }}</p>
        </div>
        <div class="preview" role="img" aria-label="{{ $tr ? 'Örnek gün planı: 09.00–17.00 vardiya, 18.00–19.00 birlikte zaman.' : 'Example daily plan: work from 09:00 to 17:00, time together from 18:00 to 19:00.' }}">
            <div class="preview-head"><strong>{{ $tr ? 'Günün bir bakışta.' : 'Your day at a glance.' }}</strong><span class="dot" aria-hidden="true"></span></div>
            <div class="clock" aria-hidden="true"><div class="clock-inner"><strong>24{{ $tr ? ' saat' : ' hrs' }}</strong><span>{{ $tr ? 'Sana ait bir gün' : 'A day that’s yours' }}</span></div></div>
            <div class="entry"><div><strong>{{ $tr ? 'Vardiya' : 'Work shift' }}</strong><br><small>{{ $tr ? 'Çalışma zamanı' : 'Working hours' }}</small></div><span>09:00–17:00</span></div>
            <div class="entry personal"><div><strong>{{ $tr ? 'Birlikte zaman' : 'Time together' }}</strong><br><small>{{ $tr ? 'Günün güzel molası' : 'A welcome break' }}</small></div><span>18:00–19:00</span></div>
            <p class="example">{{ $tr ? 'Örnek plan gösterimi' : 'Illustrative schedule' }}</p>
        </div>
    </section>
    <section id="features" class="section"><span class="eyebrow">{{ $tr ? 'Daha düzenli bir gün' : 'A more organized day' }}</span><h2>{{ $tr ? 'İş ve özel hayat, aynı takvimde.' : 'Work and life, in one calendar.' }}</h2>
        <div class="features">@foreach($features as [$number, $heading, $text])<article class="feature"><span class="number">{{ $number }}</span><h3>{{ $heading }}</h3><p>{{ $text }}</p></article>@endforeach</div>
    </section>
    <section class="banner"><div><h2>{{ $tr ? 'Bir sorunuz mu var?' : 'Have a question?' }}</h2><p>{{ $tr ? 'ShiftCal hakkında bilgi almak veya uygulamayla ilgili yardım istemek için destek ekibimize ulaşın.' : 'Contact our support team to learn more about ShiftCal or get help with the app.' }}</p></div><a class="button" href="{{ route('shiftcal.support', $tr ? [] : ['lang' => 'en']) }}">{{ $tr ? 'Destek merkezi' : 'Support center' }} <span aria-hidden="true">↗</span></a></section>
</main>
<footer><div class="wrap footer-inner"><span>© {{ date('Y') }} ShiftCal · <a href="{{ route('home') }}">SryyaLabs</a></span><a href="{{ route('shiftcal.support', $tr ? [] : ['lang' => 'en']) }}">{{ $tr ? 'Yardım ve iletişim' : 'Help & contact' }}</a></div></footer>
</body>
</html>
