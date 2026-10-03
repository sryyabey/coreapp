# CoreApp — Ortak mobil uygulama backend'i TODO

İnceleme: 3 Ekim 2026
Hedef: ShiftCal ve yaklaşık 10 bağımsız mobil uygulama için ortak Laravel/Filament altyapısı.
ClaryFlow bu çalışmanın kapsamı dışındadır; veri veya kod taşıma planı yoktur.

## Mevcut durum

- composer.json: Laravel ^13.17, Filament ^5.0, PHP ^8.3, PHPUnit ^12.5.12; Sanctum ve rol/yetki paketi yok.
- PHP 8.5.10 ve Composer 2.10.3 yerelde mevcut.
- Filament /manage paneli ve giriş sayfası kurulmuş; uygulama kaynakları henüz yok.
- User yalnızca standart name/email/password alanlarını ve temel trait'leri taşıyor; mobil token ve açık panel erişim politikası yok.
- routes/api.php yok; bootstrap/app.php API route dosyasını kaydetmiyor. API istekleri için JSON hata yanıtı tanımı mevcut.
- Veritabanı migration'ları users/cache/jobs başlangıç tablolarıyla sınırlı.
- Store doğrulaması, webhook, destek, yedekleme/sync modülü bulunmuyor.
- Testler yalnızca başlangıç ExampleTest örnekleri. Bu incelemede testler çalıştırılmadı.
- Kullanıcının mevcut .gitignore, composer.json, bootstrap/providers.php ve Filament provider değişiklikleri korunmalı.
- İnceleme .env içeriğini, gerçek DB kayıtlarını ve mağaza hesaplarını kapsamaz.

## Mimari kararlar

Tek Laravel projesi, modüler kod, başlangıçta tek sunucu veritabanı; her mobil uygulamada yerel depolama. Ortak çekirdek uygulama iş kurallarını bilmemeli. ShiftCal'a özel tablolar/servisler ayrı modülde olmalı. Mikroservis veya genel amaçlı JSON kayıt sistemi ilk sürümde gerekli değil.

- [ ] Merkezi hesabın uygulamalar arasında ortak mı yoksa uygulamaya özel mi olacağını karara bağlamak. Önerilen başlangıç: ortak kimlik + ayrı app_users üyeliği; otomatik sosyal hesap birleştirme yok.
- [ ] Bağımsız abonelik haklarını benimsemek; tek uygulamanın satın alması başka uygulamayı açmamalı. Ortak paket daha sonra ayrı ürün kararı olabilir.
- [ ] ShiftCal ücretsiz/ücretli özellik sınırını ve deneme politikasını belirlemek. Yıllık 14,99 USD ABD fiyatı hedef; ekrandaki gerçek fiyat mağazadan alınmalı.
- [ ] İlk bulut özelliğinin yedekleme mi çok cihaz sync mi olacağını seçmek. Önerilen ilk kapsam: yedekleme; sync ayrıca tasarlanır.
- [ ] Doğrudan Apple/Google doğrulama mı RevenueCat mi kararını uygulamadan önce vermek. CoreApp'te ikisi de henüz yok.

## P0 — Temel kurulum ve uygulama kataloğu

- [ ] AGENTS.md gereği uygulama kodunu değiştirmeden önce Laravel Boost dev kurulumunu tamamlamak; üretilen talimatları yeniden okumak. Bu TODO yalnızca planlama belgesidir, kurulum yapılmadı.
- [ ] Mevcut testleri çalıştırmak; CI'da test, Pint ve migration doğrulaması oluşturmak.
- [ ] Sanctum mobil API kurulumunu yapmak; routes/api.php ve bootstrap API route kaydını eklemek.
- [ ] apps tablosu/modeli: UUID/ID, unique slug, ad, aktiflik, destek ve uygulama bağlantıları.
- [ ] store_apps tablosu/modeli: app_id, platform, bundle/package ID, App Store uygulama ID, ortam ve güvenli credential referansları. Uygulama tanımlayıcısı ile gizli anahtarları ayırmak.
- [ ] app_users tablosu/modeli: app_id, user_id, durum, last_seen_at, uygulamaya özel tercihler; birleşik unique ve FK'lar.
- [ ] devices/oturum kaydı: app_id, user_id, cihaz kimliği, platform, son kullanım; token ilişkisinin tek kaynağını tanımlamak.
- [ ] ResolveApp middleware: bilinmeyen/pasif uygulama için kapalı davranış. app_id veya gömülü app anahtarı tek başına kimlik doğrulama değildir.
- [ ] Uygulama kapsamlı API tasarlamak: /api/v1/apps/{app}/auth, /meta, /subscription, /backups ve modül uçları.
- [ ] Aynı uygulama bağlamını token, üyelik, plan, dosya ve kayıt sorgularında doğrulamak. Mobil istemciye başka uygulamanın kaydını seçtirmemek.

## P0 — Mobil kimlik ve yönetici erişimi

- [ ] Register/login/me/logout; profil, şifre sıfırlama ve e-posta doğrulama akışlarını oluşturmak.
- [ ] Tokenları uygulama/cihazla kapsamlamak; süre, iptal ve güvenli yenileme politikasını tanımlamak. Genel kapsamlı token varsayılanı kullanmamak.
- [ ] Giriş ve kritik API işlemlerine kullanıcı+uygulama/IP temelli rate limit; tüm API hata yanıtları için ortak sözleşme eklemek.
- [ ] FilamentUser/canAccessPanel ve açık yönetici erişim kuralı eklemek. Mobil müşteri hesabını panel yöneticisi saymamak.
- [ ] Yönetici rol/yetki modelini seçmek; Filament 5 ile uyumunu doğrulamak. Basit admin kuralıyla başlamak mümkün, Shield zorunlu değil.
- [ ] Yönetici oluşturma komutu/seed'i hazırlamak; başlangıç Test User kaydını production yönetici kurulumundan ayırmak.
- [ ] Yönetici işlemlerine audit kaydı; yönetici oturumları için ikinci faktör seçeneğini değerlendirmek.

## P1 — Apple/Google abonelikleri

- [ ] subscription_plans: app_id, slug, period, katalog fiyatı/para birimi, aktiflik ve özellik hakları; (app_id, slug) unique.
- [ ] store_products: plan_id, store_app_id, product_id, Android base_plan_id ve ortam eşlemesi. Her app kendi mağaza ürünlerine sahip olmalı.
- [ ] purchases ve entitlements ayrımı: mağaza satın alma geçmişi ile kullanıcının güncel özellik hakkını ayrı tutmak; platform/ortam/sahiplik ve zaman alanları.
- [ ] Satın alma sync/restore API'si; receipt/token/JWS'yi sunucuda doğrulayıp ilgili app ve product ile eşleştirmek.
- [ ] Satın alma sahipliğini mağaza hesap eşleştirmesiyle doğrulamak; aynı satın almanın başka kullanıcıda yeniden kullanılmasını engellemek.
- [ ] Apple ve Google bildirimleri: mağazaya özgü doğrulama, kalıcı olay kaydı, idempotent işlem ve uygulama/ortam ayrımı.
- [ ] Kuyruk, retry, başarısız olay yönetimi, gecikmiş/sırasız bildirimler ve satın almadan önce gelen bildirimler için uzlaştırma.
- [ ] Zamanlanmış durum kontrolü ve son doğrulama tarihi; aktif, canceled-but-paid, expired, grace, pending, refunded/revoked durumları.
- [ ] FeatureAccessService(app, user, feature): uygulama bazında erişim ve offline kullanım politikasını oluşturmak. Yalnızca telefondaki premium boolean'a dayanılmamalı.
- [ ] Mağaza ürünü satıştan kaldırıldığında mevcut ücretli hakların istemeden kapanmasını önlemek.
- [ ] Satın alma, yenileme, iptal, iade, restore ve sandbox/production ayrımı testlerini kurmak; gerçek cihaz sandbox kontrolünü ayrıca yapmak.

## P1 — Kullanıcı yaşam döngüsü ve destek

- [ ] Apple/Google sosyal girişi gerekiyorsa audience/client yapılandırmasını uygulama/platform kapsamlı kurmak. Sosyal kimlikleri provider + identity scope + subject olarak modellemek.
- [ ] Misafir kullanım ve yerel veriyi hesaba bağlama; ortak demo kullanıcısını gerçek kullanıcı kimliği olarak kullanmamak.
- [ ] Uygulama üyeliğini/verilerini silme ile tüm CoreApp hesabını silmeyi ayırmak; etkilenecek uygulamaları kullanıcıya göstermek.
- [ ] Hesap silmede yerel/bulut veri, dosya ve kimlik iptali kapsamı; mağaza aboneliğinin ayrıca yönetildiğini açıklayan akış.
- [ ] app_id/user_id kapsamlı destek talepleri ve mesajlar; uygulama bazlı FAQ, destek, koşul ve gizlilik bağlantıları.
- [ ] Kullanıcı verisi dışa aktarma ve yedek saklama/silme politikası.

## P1 — ShiftCal modülü

- [ ] App kaydı ve ShiftCal store konfigürasyonu; gerçek mağaza kimlikleriyle yıllık ürün eşlemesi.
- [ ] ShiftCal namespace/modülü; etkinlik, tür, vardiya şablonu ve ücret ayarı modelleri. CoreApp çekirdeğine bu iş kurallarını eklememek.
- [ ] Etkinlik CRUD API'si: UUID, kullanıcı sahipliği, yerel tarih/saat ve IANA saat dilimi, başlangıç/bitiş, tür, renk, not, sürüm.
- [ ] Gece yarısı, saat dilimi/yaz saati, tekrar ve çakışma davranışını tanımlamak. Süreyi yalnızca iki saat metnini çıkartarak hesaplamamak.
- [ ] Flutter SharedPreferences etkinlik kayıtlarını SQLite'a taşımak; demo kayıtlarının gerçek veriye otomatik yüklenmesini önlemek.
- [ ] İlk yedekleme sürümü: şema sürümü, app/user sahipliği, kota, bütünlük, private storage, geri yükleme ve hesap değişimi.
- [ ] Çok cihaz sync seçilirse ayrıca UUID, outbox, cursor, optimistic concurrency, tombstone ve çakışma politikası kurmak.
- [ ] Kadran, etkinlik kartları, çalışma raporu ve kazancı gerçek kayıtlarla birleştirmek.

## P2 — Filament yönetim paneli

- [ ] Apps, StoreApps, AppUsers, SubscriptionPlans, StoreProducts, Entitlements, PurchaseEvents ve destek kaynakları.
- [ ] Uygulama seçici/filtre ve modül navigasyonu; seçili uygulama sorgulara ve işlemlere yansımalı, yalnızca menü görünümü değişmemeli.
- [ ] Uygulama kapsamlı yönetici izinleri: global admin ve sadece belirli uygulamayı yöneten personel.
- [ ] Uygulama bazında kullanıcı, aktif abonelik, yedekleme ve hata göstergeleri. Brüt fiyatı net mağaza geliri olarak sunmamak.
- [ ] Kontrollü erişim verme, olay yeniden işleme, cihaz oturumu iptali; tüm işlemler audit kaydıyla.
- [ ] ShiftCal etkinlik/şablon yönetimi; kullanıcı verilerine yönetici erişiminin kapsamını sınırlamak.

## P2 — İşletim, dosya ve testler

- [ ] Production DB motorunu seçmek; SQLite testleri yanında bu motorda migration, FK/unique ve eşzamanlılık doğrulaması.
- [ ] Yedekleri private diskte/S3'te saklamak; kısa süreli yetkili indirme, app/user yolu, boyut kotası, şifreleme ve saklama süresi.
- [ ] Database queue başlangıç için yeterli olabilir; worker ve scheduler işletimini kurmak. Redis/Horizon'u ihtiyaç oluşursa eklemek.
- [ ] app_id/request_id ile log/izleme, secret/token redaksiyonu; başarısız webhook, kuyruk ve yedekleme alarmları.
- [ ] DB/dosya yedeği ve geri yükleme tatbikatı; deploy, migration ve rollback prosedürü.
- [ ] Kullanıcılar ve uygulamalar arası kayıt/token/premium/dosya/destek izolasyon testleri.
- [ ] OpenAPI ve projeye özel README; ortak Flutter API/auth/billing bağlantıları için tekrar kullanılabilir paket.
- [ ] Yeni app ekleme şablonu: katalog, store kimliği, ürün, modül, panel kaynağı, test ve mobil config.

## Teslim sırası

1. apps/app_users + API + mobil auth + yönetici erişimi + temel Filament kaynakları.
2. Abonelik doğrulama ve hak modeli + izolasyon testleri.
3. ShiftCal CRUD + SQLite + hesapla yedekleme.
4. Destek, işletim ve gerçek mağaza sandbox doğrulaması.
5. İkinci bağımsız uygulamayla ortak çekirdeğin yeniden kullanılabildiğini sınamak.

İlk teslim ölçütleri: ShiftCal uygulaması katalogdan yönetilebilmeli; kullanıcıya app kapsamlı token verilmeli; bir app tokenı farklı app API'sine erişememeli; normal mobil kullanıcı /manage paneline girememeli; tüm yeni davranışlar test edilmelidir. İlk sürümde ClaryFlow taşıma, mikroservis, ortak uygulama abonelik paketi ve genel amaçlı şemasız kayıt motoru kapsam dışıdır.
