# CoreApp kullanım kılavuzu

Bu kılavuz 3 Ekim 2026 tarihinde projede bulunan backend davranışlarını açıklar. CoreApp, birden fazla mobil uygulamanın kullanıcı, üyelik, cihaz ve mağaza aboneliklerini yöneten Laravel backend’idir. ShiftCal, ilk uygulamaya özel modüldür.

## 1. Projede neler var?

| Bileşen | İşlev |
| --- | --- |
| Laravel 13 | Sürümlenmiş JSON API ve iş kuralları |
| Sanctum | Uygulama ve cihaz kapsamlı Bearer token |
| Filament 5 ve Shield | Yönetim paneli, rol ve izinler |
| `apps` | Uygulama kataloğu; API adresindeki slug buradan gelir |
| `users` | Ortak e-posta/şifre hesabı |
| `app_users` | Kullanıcının her uygulamadaki üyeliği ve aktiflik durumu |
| `devices` | Kullanıcı + uygulama + kurulum kimliği bazında cihazlar |
| `store_apps` | iOS bundle ID / Android package name ve Apple uygulama ID |
| `subscription_plans` | Uygulamaya ait aylık/yıllık plan, katalog fiyatı ve özellik anahtarları |
| `store_products` | Planın mağaza ürününe, Android base plan’a ve ortama eşleştirilmesi |
| `purchases` | Doğrulanmış abonelik, durum ve bitiş tarihi |
| `store_notifications` | Mağaza bildirimlerinin kalıcı kaydı ve işleme durumu |
| ShiftCal modülü | Etkinlikler, vardiya şablonları ve ücret ayarları |

Kullanıcı hesabı uygulamalar arasında ortaktır. Bir uygulamada kayıtlı kullanıcı diğer uygulamada aynı e-posta ve şifreyle giriş yapabilir; o uygulamanın üyeliği giriş sırasında oluşturulur. Ücretli haklar ise kullanıcı + uygulama bazında hesaplanır.

## 2. Yerel kurulum

Komutları `CoreApp` dizininde çalıştırın. Proje geliştirme ortamı PHP 8.5 kullanır; PHP sürümü ve uzantıları için `composer check-platform-reqs` sonucunu esas alın. MySQL ve frontend derlemesi için Node.js/npm gerekir.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Yeni kurulumda `.env` içine veritabanı bilgilerinizi girin. Mevcut kurulumda `.env` dosyasını kopyalayarak üzerine yazmayın; mevcut `APP_KEY` değerini değiştirmeyin. Şifrelenmiş satın alma kanıtları bu anahtara bağlıdır.

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coreapp
DB_USERNAME=YOUR_DB_USER
DB_PASSWORD=YOUR_DB_PASSWORD
QUEUE_CONNECTION=database
CACHE_STORE=database
DB_QUEUE_RETRY_AFTER=180
```

Veritabanını oluşturduktan sonra:

```bash
php artisan migrate
php artisan db:seed --class=AppSeeder
php artisan db:seed --class=SubscriptionPlanSeeder
npm install
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

`AppSeeder`, `shiftcal` uygulamasını oluşturur. `SubscriptionPlanSeeder`, bu uygulama varsa `premium-yearly` planını, `premium` özellik anahtarını ve 14.99 USD katalog fiyatını oluşturur. Mağaza uygulamaları ve ürünleri ayrıca tanımlanmalıdır. Gerçek satış fiyatını mağaza yapılandırması belirler.

Genel `DatabaseSeeder` bir test kullanıcısı oluşturur; production başlangıcı için doğrudan `php artisan db:seed` kullanmayın.

## 3. Yönetim paneli

Panel adresi: `/manage`. Ana sayfa `/` adresindedir.

Yeni bir yönetici hesabı oluşturmak için:

```bash
php artisan make:filament-user
```

Oluşturulan kullanıcı ID’sini kullanarak yönetici rolü verin:

```bash
php artisan shield:super-admin --user=USER_ID --panel=manage --no-interaction
```

Mobil kayıt işlemi panel rolü vermez. Panel erişimi `super_admin` veya yapılandırılmış `panel_user` rolü gerektirir; kaynak işlemleri Shield izinlerine bağlıdır.

Panelde uygulamalar, uygulama üyelikleri, mağaza uygulamaları, abonelik planları, mağaza ürünleri ve Shield rol yönetimi bulunur. Uygulama detayında üyelikler ve mağaza uygulamaları da yönetilebilir.

Yeni uygulama tanımlama sırası:

1. Uygulama oluşturun: benzersiz slug, ad ve aktiflik.
2. iOS/Android mağaza kaydı ekleyin: platform, identifier, gerekli Apple ID.
3. Uygulamaya aylık veya yıllık abonelik planı ekleyin; özellik anahtarlarını belirleyin.
4. Mağaza ürününü ilgili mağaza ve plana bağlayın; production/sandbox ortamını seçin.
5. Android için `base_plan_id` girin. iOS için bu alan boş tutulur.
6. Mobil uygulamayı kendi slug’ının API adresine bağlayın.

Genel üyelik ve abonelik API’leri yeni uygulamalarda kullanılabilir. ShiftCal etkinlik/şablon/ücret API’leri yalnızca `shiftcal` slug’ına açıktır; başka uygulamaların iş alanları için ayrı modüller gerekir.

## 4. API kuralları

Yerel örnek taban adres:

```text
http://127.0.0.1:8000/api/v1/apps/shiftcal
```

Production için aynı yol, backend’in yayımlandığı domain üzerinden kullanılır. `sryya.dev` hedef domain’dir; bu belge deployment yapıldığını ifade etmez.

Tüm isteklerde:

```http
Accept: application/json
Content-Type: application/json
```

Korumalı isteklerde ayrıca:

```http
Authorization: Bearer YOUR_TOKEN
```

Tek kayıt yanıtları `data` içinde gelir. Sayfalı listeler `data`, `links` ve `meta` içerir. Silme ve çıkış başarılı olduğunda `204` döner. API yollarındaki `{app}` uygulamanın slug’ıdır; sayısal ID değildir.

## 5. E-posta ve şifreyle kayıt / giriş

### Kayıt

`POST /auth/register`

```json
{
  "name": "Örnek Kullanıcı",
  "email": "user@example.com",
  "password": "example-password",
  "password_confirmation": "example-password",
  "device_id": "11111111-1111-4111-8111-111111111111",
  "platform": "ios",
  "device_name": "iPhone"
}
```

Başarılı kayıt `201` döner. E-posta kırpılır ve küçük harfe çevrilir. Şifre en az 8 karakter, en fazla 72 karakter ve UTF-8 kodlamasında en fazla 72 bayt olabilir. Şifre hashlenerek saklanır; yanıta eklenmez. E-posta sistem genelinde benzersizdir.

### Giriş

`POST /auth/login`

```json
{
  "email": "user@example.com",
  "password": "example-password",
  "device_id": "11111111-1111-4111-8111-111111111111",
  "platform": "ios",
  "device_name": "iPhone"
}
```

Başarılı giriş `200` döner. Her iki işlemde yanıtın `data` alanında `token`, `token_type`, `expires_at`, `device`, `user` ve `app` bulunur. `data.token` değerini sonraki isteklerde Bearer olarak gönderin.

`device_id` mobil kurulum için üretilmiş, tekrar girişlerde korunan bir UUID olmalıdır. Her girişte yeni UUID üretmeyin. `platform` yalnızca `ios` veya `android` olabilir. Aynı kullanıcı/uygulama/cihazdan tekrar giriş eski cihaz tokenlarını iptal eder; diğer cihazlar korunur. Token 30 gün geçerlidir. Refresh-token endpoint’i bulunmaz; süresi dolunca yeniden giriş gerekir.

### Hesabı kontrol etme ve çıkış

| Metot | Yol | Sonuç |
| --- | --- | --- |
| GET | `/meta` | Kimlik doğrulamasız, aktif uygulamanın açık bilgileri |
| GET | `/auth/me` | Kullanıcı, uygulama ve üyelik bilgileri |
| POST | `/auth/logout` | Mevcut cihazın tokenlarını iptal eder |
| GET | `/devices` | Kullanıcının bu uygulamadaki cihazları |
| GET | `/devices/{device}` | Sayısal cihaz ID’siyle detay |
| DELETE | `/devices/{device}` | İlgili cihazın erişimini iptal eder |

Kullanıcı yalnızca kendi cihazlarını ve kayıtlarını yönetebilir. Başka uygulama tokenı veya genel wildcard token, mobil API’ye erişim sağlamaz. Pasif üyelik giriş ve korumalı isteklerde `403` alır; pasif veya bulunmayan uygulama `404` döner.

Kayıt/giriş için IP başına dakikada 20, uygulama + e-posta + IP başına dakikada 5 istek sınırı vardır. Genel korumalı mobil API sınırı uygulama + kullanıcı başına dakikada 120 istektir.

## 6. ShiftCal API’leri

Bu bölümdeki yollar yukarıdaki taban adrese eklenir ve Bearer token gerektirir.

### Etkinlikler

| Metot | Yol |
| --- | --- |
| GET / POST | `/shiftcal/events` |
| GET / PUT / PATCH / DELETE | `/shiftcal/events/{event}` |

`event` UUID’dir. Oluşturma örneği:

```json
{
  "type": "work",
  "starts_at": "2026-10-03T08:00:00+03:00",
  "ends_at": "2026-10-03T17:00:00+03:00",
  "timezone": "Europe/Istanbul",
  "color": "#2E9B62",
  "note": "Ofis mesaisi"
}
```

Türler: `work` (mesai), `freeTime` (boş zaman), `sleep` (uyku), `duty` (nöbet), `exercise` (spor), `other` (diğer).

Tarihler saniye ve saat dilimi offset’i veya `Z` içeren ISO 8601 olmalıdır. Bitiş başlangıçtan sonra olmalıdır; geceye taşan etkinlikte bitiş tarihini ertesi güne yazın. Veritabanında UTC tutulur, yanıt UTC offset’iyle gelir; `timezone` ayrıca korunur. Yanıtta gerçek geçen süreyi gösteren `duration_minutes` bulunur.

Liste filtreleri: `from`, `to`, `per_page`, `page`. `from` ve `to` birlikte kullanılmalıdır. Aralıkla kesişen etkinlikler döner; varsayılan sayfa boyutu 30, üst sınır 100’dür. Offset’teki `+` karakterini URL’de `%2B` olarak kodlayın veya HTTP istemcisinin query parametre desteğini kullanın.

`PATCH` yalnızca değişen alanları alabilir. `note` isteğe bağlıdır, `null` ile temizlenebilir ve en fazla 5000 karakterdir. Renk `#RRGGBB` biçimindedir.

### Vardiya şablonları

| Metot | Yol |
| --- | --- |
| GET / POST | `/shiftcal/shift-templates` |
| GET / PUT / PATCH / DELETE | `/shiftcal/shift-templates/{shift_template}` |

Şablon ID’si UUID’dir. Gece vardiyası örneği:

```json
{
  "name": "Gece nöbeti",
  "type": "duty",
  "start_time": "22:00",
  "end_time": "08:00",
  "end_day_offset": 1,
  "color": "#FF6A1A",
  "note": null
}
```

Saatler `HH:mm` biçimindedir. `end_day_offset` aynı gün için `0`, ertesi gün için `1` olur. Süre 0’dan büyük ve en fazla 24 saattir. Liste `page` ve `per_page` alır; varsayılan 30, üst sınır 100’dür. Şablon oluşturmak otomatik etkinlik oluşturmaz.

### Ücret ayarları

`GET /shiftcal/wage-settings` mevcut ayarı döndürür. Kayıt yoksa veritabanına yazmadan varsayılan değerler döner: saatlik ücret `0`, para birimi `TRY`, fazla mesai çarpanı `1.5`, haftalık hedef `2400` dakika.

`PUT /shiftcal/wage-settings` tüm alanlarla oluşturur veya günceller:

```json
{
  "hourly_rate": "150.00",
  "overtime_multiplier": "1.50",
  "currency": "TRY",
  "weekly_target_minutes": 2400
}
```

Ücret negatif olamaz; ücret ve çarpan en fazla iki ondalık basamak alır. Çarpan en az `1`, para birimi üç büyük harf, haftalık hedef `0–10080` dakikadır. Her kullanıcı/uygulama için tek ayar kaydı tutulur. API ayarı saklar; kazanç raporu endpoint’i henüz yoktur.

### UUID, saat dilimi ve gece yarısı kuralları

- Etkinlik, şablon ve kaydedilmiş ücret ayarının UUID’sini backend üretir. Oluşturma isteğinde `id` göndermeyin. Bozuk etkinlik/şablon UUID’si detay yollarında `404` döner; sahiplik ayrıca kontrol edilir. Mobil `device_id` istemcinin ürettiği kalıcı kurulum UUID’sidir; cihaz yönetimindeki `{device}` sayısal backend ID’sidir.
- `starts_at`, `ends_at`, `from` ve `to` açık offset veya `Z` içeren ISO 8601 ister. Yalnızca gün veya offset’siz saat `422` döner. Geçersiz takvim günü, `24:00:00` ve `+14:00` sınırını aşan offset reddedilir.
- Offset kesin anı belirler; IANA `timezone` gösterim bölgesini korur. UTC göndermek geçerlidir; offset’in bölgenin yerel offset’iyle aynı olması zorunlu değildir. Mobil istemci yerel saat seçimini bölgenin o tarihteki kurallarıyla kesin zamana çevirmelidir.
- Gece yarısı bitişi ertesi gün `00:00:00` olarak gönderilir. `22:00–08:00` etkinliğinde bitiş tarihi ertesi gündür; backend kendiliğinden tarih artırmaz. Etkinliklerde şablonların 24 saat sınırı uygulanmaz.
- Gün sorgusu `[from, to)` aralığıdır: başlangıç dahil, bitiş hariç. `starts_at < to` ve `ends_at > from` uygulanır. Gece yarısında biten kayıt ertesi gün görünmez. Gün aşan kayıt kesiştiği iki günün listesinde görünebilir; `duration_minutes` tam etkinliğin süresidir, güne düşen parçanın değil.
- DST geçişinde gün aralığını sabit 24 saat ekleyerek kurmayın. Bölgedeki yerel günün başlangıcı ve ertesi takvim gününün başlangıcını ayrı hesaplayıp offset’leriyle gönderin; gün 23 veya 25 saat olabilir. Tekrarlanan saatlerde offset hangi anın seçildiğini belirler. Süre gerçek zaman farkıdır.
- Şablonlar tarih ve bölge taşımaz. `end_day_offset=0` aynı gün, `1` ertesi gün demektir. Eşit saatler `0` ile geçersiz, `1` ile 24 saatlik şablondur. Şablondan etkinlik üretirken tarih ve bölge seçilmelidir; DST sırasında gerçek süre duvar saati süresinden farklı olabilir.

### Hesaba bağlı bulut yedeği

Mobil Ayarlar ekranındaki **Şimdi yedekle** ve **Buluttan geri yükle** işlemleri şu endpoint’i kullanır:

```text
GET /api/v1/apps/shiftcal/shiftcal/cloud-backup
POST /api/v1/apps/shiftcal/shiftcal/cloud-backup
```

Gerçek kullanıcı hesabı ve Bearer token gerekir. Demo giriş buluta erişmez. Mobil uygulama varsayılan olarak gerçek giriş kullanır; backend kök adresini derleme sırasında tanımlayın:

```bash
flutter run --dart-define=API_BASE_URL=https://YOUR_BACKEND_DOMAIN --dart-define=ENABLE_DEMO_AUTH=false
```

Bu komutu mobil `shiftcal` dizininde çalıştırın. API adresine `/api/v1` eklemeyin; istemci endpoint yollarını ekler. Production sunucusuna yeni `shiftcal_cloud_backups` migration’ı uygulanmalıdır.

`GET`, `data` içinde `exists`, `revision`, `saved_at`, `schema_version` ve `entries` döndürür. Yedek yoksa `exists=false`, `revision=0` gelir. `POST` için önce mevcut revision okunur; ardından `schema_version=1`, `revision` ve SQLite’tan alınan `entries` gönderilir. Her kayıtta UUID `id`, `YYYY-MM-DD` biçiminde `date_key`, `type`, dakika cinsinden `start`/`end`, ARGB tamsayı `color`, `note`, `timezone`, `starts_at` ve `ends_at` bulunur. Eski kayıtların zaman dilimi ve UTC tarih alanları boş olabilir; dolu tarihler ISO 8601 kurallarına uymalıdır.

Yedek en fazla 10.000 kayıt ve 2 MB istek içeriği olabilir. Kayıt UUID’leri aynı yedek içinde benzersiz olmalıdır. Hesap/uygulama sahipliği token’dan belirlenir. Yanlış revision `409` döner ve eski yedeği değiştirmez. Yedekler `APP_KEY` ile şifrelenmiş olarak saklanır; anahtar korunmalıdır.

Yedekleme hesabın en son bulut kopyasını değiştirir; sürüm arşivi veya otomatik canlı senkronizasyon değildir. Geri yükleme SQLite’a tek transaction içinde eksik UUID’leri ekler, aynı UUID’ye sahip yerel kayıtları korur. Tekrar geri yükleme kopya oluşturmaz; UUID’si farklı fakat içeriği aynı kayıtlar ayrı kayıt kabul edilir. Etkinliklerin saat dilimleri değişmez.

SQLite sürüm 2 hesaplara göre ayrılmıştır. Hesapsız eski yerel kayıtlar ilk gerçek giriş hesabına bir kez bağlanır. Sonraki hesaplar birbirinin kayıtlarını görmez. Bu özellik etkinlik yedeğini kapsar; ücret ayarları, vardiya şablonları ve cihaz tercihleri bu mobil yedeğe dahil değildir. Yedek, etkinlik CRUD API’sine kayıt üretmez; ayrı bir geri yükleme kopyasıdır.

## 7. Mağaza abonelikleri

Mobil abonelik satın alımları Apple In-App Purchase / Google Play Billing üzerinden yapılır. Backend mağazaya sorarak doğrular; istemcinin gönderdiği fiyat, aktiflik veya bitiş tarihine güvenmez.

### Satın alma akışı

1. Token ile `GET /billing/context` çağırın.
2. Dönen `data.app_account_token` değerini Apple satın alımında `appAccountToken` olarak kullanın.
3. Android’de `data.obfuscated_account_id` değerini satın alma akışındaki obfuscated account ID olarak kullanın.
4. Mağaza satın alımından sonra platformun işlem kanıtını `/purchases/verify` adresine gönderin.
5. `/entitlements` çağırarak uygulamanın ücretli haklarını güncelleyin.

Apple doğrulama isteği:

```json
{
  "platform": "ios",
  "transaction_id": "123456789012345",
  "environment": "production"
}
```

Android doğrulama isteği:

```json
{
  "platform": "android",
  "purchase_token": "STORE_PURCHASE_TOKEN",
  "environment": "production"
}
```

| Metot | Yol | İşlev |
| --- | --- | --- |
| GET | `/billing/context` | Satın alma hesabı bağlama kimlikleri |
| POST | `/purchases/verify` | Mağazadan doğrulama ve kaydetme |
| POST | `/purchases/restore` | Aynı doğrulama akışıyla geri yükleme |
| GET | `/purchases` | Kullanıcının bu uygulamadaki satın alımları; sayfa boyutu 20 |
| GET | `/entitlements` | `has_paid_access` ve bitiş tarihleriyle özellik anahtarları |

Geri yükleme için mobil tarafta mağazanın sahip olunan satın alımlarını sorgulayın; her ilgili işlem kanıtını `/purchases/restore` adresine gönderin. Backend’in restore endpoint’i mağazadaki tüm kullanıcı satın alımlarını kendiliğinden listelemez.

Haklar production ortamındaki, süresi dolmamış `active`, `grace` veya `canceled` aboneliklerden hesaplanır. Otomatik yenilemenin iptali mevcut ücretli dönemi hemen bitirmez. `expired` veya `revoked` durumları hak sağlamaz. Sandbox satın alımları production haklarına dahil edilmez.

ShiftCal API’lerinde şu anda premium zorunluluğu yoktur. Yeni ücretli endpoint’lerde `App\Http\FeatureAccess::allows()` ile ilgili özelliği ayrıca kontrol etmek gerekir. İstemcinin gösterdiği premium durumu tek başına backend yetkilendirmesi değildir.

### Ortam ve erişim ayarları

`.env` değişkenleri:

| Değişken | Kullanım |
| --- | --- |
| `BILLING_ALLOW_SANDBOX` | Sandbox doğrulamasına izin; varsayılan `false` |
| `APPLE_IAP_ISSUER_ID` | Apple API issuer ID |
| `APPLE_IAP_KEY_ID` | Apple API key ID |
| `APPLE_IAP_PRIVATE_KEY_PATH` | Sunucudaki okunabilir özel anahtar dosyası |
| `APPLE_IAP_ROOT_CA_PATHS` | Bildirim sertifika doğrulaması için virgülle ayrılmış PEM root CA dosya yolları |
| `GOOGLE_PLAY_SERVICE_ACCOUNT_PATH` | Google servis hesabı JSON dosya yolu |
| `GOOGLE_PLAY_PUSH_AUDIENCE` | Google push JWT için beklenen audience |
| `GOOGLE_PLAY_PUSH_EMAIL` | Push kimlik doğrulamasında beklenen servis hesabı e-postası |
| `GOOGLE_PLAY_PUSH_SUBSCRIPTION` | Beklenen Pub/Sub subscription |

Dosyaları public web dizini dışında tutun. Google servis hesabına ilgili uygulamada mağaza API erişimi tanımlanmalıdır. `config/billing.php` içindeki `apps` alanında uygulama bazlı `allow_sandbox` ayarı global değeri geçersiz kılabilir; örneğin `shiftcal` için `['allow_sandbox' => true]`.

Sandbox ve production için mağaza ürün eşleşmelerini ayrı tanımlayın. İsteklerde ortamı açıkça belirtmek önerilir; Apple için belirtilmezse production kullanılır, Google’ın gerçek ortamı mağaza yanıtından kontrol edilir.

### Mağaza bildirimleri

Mağaza konsollarında aşağıdaki production HTTPS endpoint’lerini backend domain’inizle yapılandırın:

```text
POST /api/v1/store-notifications/shiftcal/ios
POST /api/v1/store-notifications/shiftcal/android
```

Bunlar mobil Bearer token kullanmaz; Apple imzalı bildirimleri ve Google kimlik doğrulamalı push istekleri doğrulanır. Bildirim kalıcı kaydedilir ve kuyruğa aktarılır. Aynı mağaza/event ID bildiriminin tekrarları tek kayıtta tutulur. İşleyici güncel abonelik durumunu mağazadan tekrar sorgular. Henüz satın alımla eşleşmeyen bildirimler beklemeye alınarak tekrar denenir.

## 8. Kuyruk ve zamanlayıcı

Abonelik bildirimlerinin işlenmesi için worker gerekir:

```bash
php artisan queue:work --queue=billing,default --timeout=60
```

Yerel geliştirmede ayrı terminalde:

```bash
php artisan schedule:work
```

Production’da worker’ı süreç yöneticisiyle çalıştırın ve aşağıdaki cron satırında yolu gerçek sunucu yolu ile değiştirin:

```cron
* * * * * cd /path/to/CoreApp && php artisan schedule:run >> /dev/null 2>&1
```

Zamanlanmış işler:

| Komut | Sıklık | İşlev |
| --- | --- | --- |
| `billing:retry-notifications` | 10 dakikada bir | Bekleyen/başarısız bildirimleri tekrar kuyruğa gönderir |
| `billing:check-subscriptions` | 5 dakikada bir | Kontrol zamanı gelen production aboneliklerini kuyruğa gönderir |

İşler sınırlı sayıda yeniden deneme ve artan bekleme süreleri kullanır. Mağaza erişim hatası mevcut hakları hemen silmez; tekrar kontrol planlanır. Abonelik bitiş tarihi yine hak hesaplamasında geçerlidir.

İşletim komutları:

```bash
php artisan schedule:list
php artisan queue:failed
php artisan queue:retry JOB_ID
php artisan queue:restart
```

Deploy sonrasında uzun yaşayan worker’ların yeni kodu alması için `queue:restart` çalıştırın. Production’da `APP_DEBUG=false`, doğru `APP_URL`, HTTPS ve yazılabilir `storage` / `bootstrap/cache` dizinleri gerekir. Web sunucusunun document root’u `public` dizini olmalıdır. Yapılandırma değişikliklerinden sonra `php artisan config:cache` çalıştırın.

## 9. Hata yanıtları

| Kod | Anlam |
| --- | --- |
| 401 | Eksik, iptal edilmiş veya süresi dolmuş token |
| 403 | Üyelik/uygulama/token kapsamı veya satın alma sahipliği nedeniyle erişim reddi |
| 404 | Uygulama, mağaza ürün eşleşmesi veya kullanıcıya ait kayıt bulunamadı |
| 409 | Satın alma başka kullanıcı hesabına bağlı |
| 422 | Alan doğrulaması, yanlış e-posta/şifre veya mağaza kanıtı eşleşmesi hatası |
| 429 | İstek sınırı aşıldı |
| 502 / 503 | Mağaza yanıtı, erişim bilgisi veya geçici mağaza erişimi sorunu |

Örnek yanlış giriş yanıtı:

```json
{
  "message": "E-posta veya şifre hatalı.",
  "errors": {
    "email": ["E-posta veya şifre hatalı."]
  }
}
```

Formlarda `errors` içindeki alan mesajlarını gösterin. Sahiplik sunucuda token’dan belirlenir; `user_id`, `app_id`, rol veya izin alanlarını mobil payload’a eklemeyin.

## 10. Test ve mevcut sınırlar

```bash
php artisan test --compact
php artisan test --compact tests/Feature/EmailPasswordAuthTest.php tests/Feature/MobileApiTest.php
php artisan route:list --path=api --except-vendor
```

Kılavuz hazırlanırken son tam test sonucu 106 test ve 562 assertion başarılıdır. Testler backend davranışlarını doğrular; gerçek Apple/Google credentials ile canlı mağaza testi ve production deployment doğrulaması ayrıca yapılmalıdır.

Henüz tamamlanmamış alanlar:

- Etkinlik CRUD API’siyle otomatik senkronizasyon ve çakışma çözümü. Mobil gerçek kayıt/giriş ve manuel bulut yedeği artık bağlıdır.
- Şifre sıfırlama, e-posta doğrulama ve refresh-token API’leri.
- Mobil satın alma arayüzü ve mağaza konsollarındaki ürün/bildirim yapılandırmaları.
- ShiftCal kazanç raporları, offline senkronizasyon ve çakışma çözümü.

Kaynaklar: API yolları `routes/api.php`, doğrulamalar `app/Http/Requests/Api`, yanıtlar `app/Http/Resources/Api`, mağaza ayarları `config/billing.php`, zamanlanmış işler `routes/console.php` içinde bulunur.
