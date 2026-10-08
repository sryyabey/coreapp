@php
    $tr = $language === 'tr';
    $title = $tr ? 'ShiftCal Destek' : 'ShiftCal Support';
@endphp
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · SryyaLabs</title>
    <meta name="description" content="{{ $tr ? 'ShiftCal için yardım, destek ve iletişim. Vardiya takviminiz, hesabınız ve uygulamayla ilgili bize ulaşın.' : 'Help, support and contact for ShiftCal. Get assistance with your shift calendar, account and app.' }}">
    <meta name="theme-color" content="#163d32">
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link rel="canonical" href="{{ route('shiftcal.support', $tr ? [] : ['lang' => 'en']) }}">
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f6f8f5;color:#20352c;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.65}a{color:#21644c;text-underline-offset:4px}a:focus-visible,summary:focus-visible{outline:3px solid #b47b22;outline-offset:5px}header,main,footer{max-width:980px;margin:auto;padding:24px}header{display:flex;align-items:center;justify-content:space-between;gap:20px}.brand{font-size:20px;font-weight:750;text-decoration:none}.langs{display:flex;gap:16px}.langs [aria-current]{font-weight:750}main{padding-top:40px;padding-bottom:64px}.label{font-size:12px;letter-spacing:.16em;font-weight:750;text-transform:uppercase;color:#507461}h1{font-size:clamp(36px,7vw,64px);line-height:1.1;letter-spacing:-.045em;margin:16px 0 24px}h2{font-size:24px;line-height:1.3;margin:0 0 16px}.intro{max-width:650px;font-size:19px;color:#5a6c62}.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:36px 0}.card{background:#fff;border:1px solid #dbe4dc;border-radius:22px;padding:28px}.contact{background:#163d32;color:#fff}.contact p{color:#d0e0d6}.contact a{color:#fff}.button{display:inline-block;background:#e6f2df;color:#163d32!important;padding:12px 20px;border-radius:12px;font-weight:700;text-decoration:none;overflow-wrap:anywhere}.steps{padding-left:22px}.steps li{padding:4px 0}details{border-top:1px solid #dbe4dc;padding:18px 0}summary{cursor:pointer;font-weight:650}details p{color:#5a6c62;margin-bottom:0}.note{font-size:14px;color:#607168}footer{border-top:1px solid #dbe4dc;font-size:14px;color:#607168;display:flex;justify-content:space-between;gap:16px}@media(max-width:640px){.grid{grid-template-columns:1fr}header,main,footer{padding-left:20px;padding-right:20px}.card{padding:24px}footer{flex-direction:column}}
    </style>
</head>
<body>
<header>
    <a class="brand" href="{{ route('home') }}">SryyaLabs <span aria-hidden="true">↗</span></a>
    <nav class="langs" aria-label="{{ $tr ? 'Dil seçimi' : 'Language' }}">
        <a href="{{ route('shiftcal.support') }}" lang="tr" @if($tr) aria-current="page" @endif>Türkçe</a>
        <a href="{{ route('shiftcal.support', ['lang' => 'en']) }}" lang="en" @if(!$tr) aria-current="page" @endif>English</a>
    </nav>
</header>
<main>
    <div class="label">ShiftCal / {{ $tr ? 'Yardım merkezi' : 'Help center' }}</div>
    <h1>{{ $tr ? 'Size yardımcı olmak için buradayız.' : 'We’re here to help.' }}</h1>
    <p class="intro">{{ $tr ? 'Vardiya takviminiz, hesabınız veya ShiftCal kullanımıyla ilgili bir sorunuz mu var? Destek ekibimize uygulamadan veya e-posta ile ulaşabilirsiniz.' : 'Have a question about your shift calendar, account or using ShiftCal? Contact our support team in the app or by email.' }}</p>
    <div class="grid">
        <section class="card contact">
            <h2>{{ $tr ? 'E-posta ile iletişime geçin' : 'Contact us by email' }}</h2>
            <p>{{ $tr ? 'Uygulamaya giriş yapamıyorsanız veya doğrudan bize yazmak istiyorsanız e-posta gönderebilirsiniz.' : 'If you cannot sign in to the app or prefer to contact us directly, send us an email.' }}</p>
            <a class="button" href="mailto:{{ $email }}?subject=ShiftCal%20Support">{{ $email }}</a>
        </section>
        <section class="card">
            <h2>{{ $tr ? 'Uygulama içinden destek' : 'Support in the app' }}</h2>
            <ol class="steps">
                <li>{{ $tr ? 'ShiftCal hesabınıza giriş yapın.' : 'Sign in to your ShiftCal account.' }}</li>
                <li>{{ $tr ? 'Ayarlar → Destek ile iletişime geç bölümünü açın.' : 'Open Settings → Contact support.' }}</li>
                <li>{{ $tr ? 'Konuyu ve yaşadığınız sorunu yazıp gönderin. Yanıtları aynı bölümden takip edebilirsiniz.' : 'Enter a subject, describe your issue and send your request. Follow replies in the same section.' }}</li>
            </ol>
        </section>
    </div>
    <section class="card">
        <h2>{{ $tr ? 'Sık sorulan sorular' : 'Frequently asked questions' }}</h2>
        <details><summary>{{ $tr ? 'Destek talebime hangi bilgileri eklemeliyim?' : 'What should I include in my request?' }}</summary><p>{{ $tr ? 'Cihaz modelinizi, iOS veya Android sürümünüzü, ShiftCal sürümünü ve sorunun oluştuğu adımları paylaşın. Ekran görüntüsü ekliyorsanız kişisel bilgilerinizi gizleyin. Şifrenizi veya ödeme bilgilerinizi göndermeyin.' : 'Include your device model, iOS or Android version, ShiftCal version and the steps that led to the issue. Hide personal information in screenshots. Do not send passwords or payment details.' }}</p></details>
        <details><summary>{{ $tr ? 'Hesabıma giriş yapamıyorum. Ne yapmalıyım?' : 'I cannot sign in. What should I do?' }}</summary><p>{{ $tr ? 'İnternet bağlantınızı kontrol edin ve hesabı oluştururken kullandığınız giriş yöntemiyle tekrar deneyin. Sorun devam ederse yukarıdaki e-posta adresinden bize ulaşın ve gördüğünüz hata mesajını paylaşın.' : 'Check your internet connection and try the sign-in method you used to create your account. If the issue continues, email us using the address above and include the error message you see.' }}</p></details>
        <details><summary>{{ $tr ? 'Hesabımı nasıl silebilirim?' : 'How can I delete my account?' }}</summary><p>{{ $tr ? 'Uygulamada Ayarlar > Hesabımı sil bölümünü kullanabilirsiniz. Hesabınıza erişemiyorsanız kayıtlı e-posta adresinizden “ShiftCal hesap silme” konulu bir mesaj gönderin veya uygulama içindeki Destek bölümünden talep oluşturun. Şifrenizi paylaşmayın.' : 'Use Settings > Delete my account in the app. If you cannot access your account, send a message with the subject “ShiftCal account deletion” from your registered email address, or submit a request through in-app Support. Do not share your password.' }} <a href="{{ route('shiftcal.privacy', $tr ? [] : ['lang' => 'en']) }}#delete-account">{{ $tr ? 'Silme talebi ve ayrıntılar' : 'Deletion request and details' }}</a></p></details>
        <details><summary>{{ $tr ? 'Bir hata veya özellik önerisi nasıl paylaşılır?' : 'How can I report a bug or suggest a feature?' }}</summary><p>{{ $tr ? 'Uygulama içindeki destek bölümünden talep oluşturabilir veya bize e-posta gönderebilirsiniz. Hata bildirirken beklediğiniz sonucu ve gerçekte ne olduğunu açıklayın.' : 'Create a request through in-app support or email us. For bug reports, explain what you expected to happen and what actually happened.' }}</p></details>
    </section>
    <p class="note">{{ $tr ? 'Bu sayfa ShiftCal uygulamasının resmi destek sayfasıdır.' : 'This is the official support page for the ShiftCal app.' }}</p>
</main>
<footer><span>© {{ date('Y') }} SryyaLabs · ShiftCal</span><a href="{{ route('shiftcal.privacy', $tr ? [] : ['lang' => 'en']) }}">{{ $tr ? 'Gizlilik politikası' : 'Privacy policy' }}</a><a href="{{ route('shiftcal', $tr ? [] : ['lang' => 'en']) }}">{{ $tr ? 'ShiftCal’i keşfet' : 'Explore ShiftCal' }}</a><a href="{{ route('shiftcal.terms', $tr ? [] : ['lang' => 'en']) }}">{{ $tr ? 'Kullanım koşulları' : 'Terms of service' }}</a></footer>
</body>
</html>
