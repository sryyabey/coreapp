<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#126c56">
    <title>ClaryFlow’u İndir · App Store ve Google Play</title>
    <meta name="description" content="ClaryFlow’u iPhone veya Android cihazına indir. Farkındalık ve dengeli rutinlere ilk adımını at.">
    <link rel="canonical" href="{{ route('claryflow') }}">
    <link rel="icon" href="{{ asset('images/projects/claryflow-logo.png') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100svh; display: grid; place-items: center; padding: 28px 20px; background: radial-gradient(ellipse at top, #dceee1, #f6f8f3 65%); color: #183e33; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 540px; text-align: center; padding: 48px 32px; border: 1px solid #dde6dc; border-radius: 32px; background: #ffffffec; box-shadow: 0 24px 80px #1846330d; }
        .logo { width: 96px; height: 96px; object-fit: contain; border-radius: 24px; }
        .brand { margin: 20px 0 12px; font-size: 14px; font-weight: 700; letter-spacing: .12em; }
        h1 { margin: 0; font-size: clamp(34px, 8vw, 48px); line-height: 1.12; letter-spacing: -.045em; }
        .intro { margin: 22px auto 28px; color: #61756c; font-size: 17px; line-height: 1.7; }
        .stores { display: grid; gap: 12px; }
        .store { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 72px; padding: 16px 22px; border-radius: 16px; background: #126c56; color: white; text-align: left; text-decoration: none; }
        .store.secondary { background: #edf3ee; color: #183e33; }
        .store small { display: block; margin-bottom: 3px; font-size: 12px; opacity: .8; }
        .store strong { font-size: 20px; }
        .store:hover { filter: brightness(.95); }
        a:focus-visible { outline: 3px solid #c58a39; outline-offset: 5px; }
        .status { margin: 24px 0 0; color: #61756c; font-size: 13px; line-height: 1.6; }
        footer { margin-top: 32px; color: #61756c; font-size: 12px; }
        @media (max-width: 400px) { main { padding: 36px 22px; } }
    </style>
</head>
<body>
<main>
    <img class="logo" src="{{ asset('images/projects/claryflow-logo.png') }}" alt="ClaryFlow" width="96" height="96">
    <p class="brand">CLARYFLOW</p>
    <h1>Kendine bir<br>an ayır.</h1>
    <p class="intro">Farkındalığa ve dengeli rutinlere ilk adımını at. ClaryFlow’u indir, kendine zaman aç.</p>
    <div class="stores" aria-label="Uygulamayı indir">
        <a class="store" id="ios-store" href="https://apps.apple.com/tr/app/claryflow/id6786839637"><span><small>iPhone ve iPad için</small><strong>App Store’dan indir</strong></span><span aria-hidden="true">↗</span></a>
        <a class="store secondary" id="android-store" href="https://play.google.com/store/apps/details?id=com.claryflow.app"><span><small>Android için</small><strong>Google Play’den indir</strong></span><span aria-hidden="true">↗</span></a>
    </div>
    <p class="status" id="store-status" role="status">Cihazına uygun mağazayı seçerek ClaryFlow’u indirebilirsin.</p>
    <noscript><p class="status">İndirmek için yukarıdaki mağaza butonlarından birine dokun.</p></noscript>
    <footer>ClaryFlow · Kendin için küçük bir adım.</footer>
</main>
<script>
    (() => {
        const userAgent = navigator.userAgent || '';
        const isAndroid = /Android/i.test(userAgent);
        const isIOS = /iPhone|iPad|iPod/i.test(userAgent)
            || (/Macintosh/i.test(userAgent) && navigator.maxTouchPoints > 1);
        const store = isAndroid ? document.getElementById('android-store')
            : isIOS ? document.getElementById('ios-store') : null;

        if (!store) {
            return;
        }

        document.getElementById('store-status').textContent =
            `${isAndroid ? 'Google Play’e' : 'App Store’a'} yönlendiriliyorsun. Mağaza açılmazsa yukarıdaki butona dokun.`;

        const redirectTimer = window.setTimeout(() => window.location.replace(store.href), 1200);
        document.querySelectorAll('.store').forEach((link) => {
            link.addEventListener('click', () => window.clearTimeout(redirectTimer));
        });
    })();
</script>
</body>
</html>
