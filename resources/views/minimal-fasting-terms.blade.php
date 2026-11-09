<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minimal Fasting Terms of Service · SryyaLabs</title>
    <meta name="description" content="Terms for using Minimal Fasting, including fasting tracking, local records, backups, and the optional iOS Premium purchase.">
    <meta name="theme-color" content="#faf7f2">
    <link rel="icon" href="{{ asset('images/minimal-fasting-logo.png') }}">
    <link rel="canonical" href="{{ route('minimal-fasting.terms') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #faf7f2; color: #142830; font: 1rem/1.7 system-ui, sans-serif; }
        header, main, footer { max-width: 820px; margin: auto; padding: 24px; }
        header, footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        header a { display: flex; align-items: center; gap: 12px; font-weight: 700; text-decoration: none; }
        header img { border-radius: 50%; }
        a { color: #365f75; text-underline-offset: 4px; }
        a:focus-visible { outline: 2px solid currentColor; outline-offset: 4px; }
        h1 { margin: 0 0 12px; font-size: clamp(2rem, 6vw, 3.25rem); line-height: 1.15; }
        h2 { margin: 0; font-size: 1.35rem; line-height: 1.4; }
        section { padding: 24px; margin-top: 18px; border: 1px solid #dce3e5; border-radius: 20px; background: #fffdfa; overflow-wrap: anywhere; }
        section p:last-child { margin-bottom: 0; }
        footer { border-top: 1px solid #dce3e5; }
        @media (prefers-color-scheme: dark) {
            body { background: #18272e; color: #f3f1ec; }
            section { background: #20343d; }
            section, footer { border-color: #38515b; }
            a { color: #b3d4e4; }
        }
    </style>
</head>
<body>
<header>
    <a href="{{ route('minimal-fasting', ['lang' => 'en']) }}">
        <img src="{{ asset('images/minimal-fasting-logo.png') }}" alt="" width="48" height="48">
        Minimal Fasting
    </a>
</header>
<main>
    <h1>Terms of Service</h1>
    <p>Last updated 9 November 2026</p>
    <p>These terms apply to the Minimal Fasting mobile app for Android and iOS, provided by Süreyya Karabay under the SryyaLabs name.</p>

    <section>
        <h2>Using Minimal Fasting</h2>
        <p>Minimal Fasting lets you time fasting and eating windows, review your history and statistics, and optionally back up your records. You can use the core tracking features without creating a Minimal Fasting account. You may use the app for personal, lawful purposes.</p>
    </section>

    <section>
        <h2>Fasting and health</h2>
        <p>Minimal Fasting is a personal tracking tool. It is not a medical device and does not diagnose, treat, cure, or prevent any medical condition. Its timers, plans, and statistics do not provide medical advice or determine whether fasting is suitable for you. If you have questions about fasting and your health, speak with a qualified healthcare professional.</p>
    </section>

    <section>
        <h2>Records, backups, and reminders</h2>
        <p>Your fasting history is stored on your device. Clearing app data or uninstalling the app can remove records. You can export a backup in Settings and choose where to store it. The app does not continuously sync your history between devices.</p>
        <p>Reminders and widgets depend on device settings and operating system behavior. Use them as conveniences for your routine.</p>
        <p>The <a href="{{ route('minimal-fasting.privacy', ['lang' => 'en']) }}">Privacy Policy</a> explains how the app handles local records, backups, and information you share through support.</p>
    </section>

    <section>
        <h2>Premium on iOS</h2>
        <p>Where available, Premium is an optional one-time App Store purchase with no recurring charge. It unlocks additional fasting plans, custom durations, and home screen widgets. Apple shows the applicable price and processes the payment.</p>
        <p>To recover access on iOS, use Restore Purchases with the Apple Account used for the original purchase. This restores Premium access. To recover fasting records, use a backup. You can <a href="https://support.apple.com/en-us/118223">request an App Store refund through Apple</a>. Android currently has no in-app purchase option.</p>
    </section>

    <section>
        <h2>Updates and consumer rights</h2>
        <p>We may update the app and these terms as features or requirements change. The date above identifies the current version of these terms. Your rights under applicable consumer protection laws remain in effect.</p>
    </section>

    <section>
        <h2>Contact</h2>
        <p>For questions about these terms, use in-app support or email <a href="mailto:{{ config('support.minimal_fasting_email') }}">{{ config('support.minimal_fasting_email') }}</a>.</p>
    </section>
</main>
<footer>
    <span>© {{ date('Y') }} SryyaLabs · Minimal Fasting</span>
    <a href="{{ route('minimal-fasting.privacy', ['lang' => 'en']) }}">Privacy Policy</a>
</footer>
</body>
</html>
