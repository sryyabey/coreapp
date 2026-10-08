# CoreApp

CoreApp, birden fazla mobil uygulamanın kullanıcılarını, uygulama üyeliklerini ve destek görüşmelerini tek yönetim panelinde toplar.

## Çoklu uygulama destek sistemi

Her destek talebi bir **uygulama + kullanıcı** çiftine bağlıdır. Aynı kullanıcı ShiftCal ve başka bir uygulamayı kullanıyorsa iki ayrı destek kutusu vardır. Panelden yanıt verirken hedef, açılan talebin uygulaması ve kullanıcısıdır; talebin sahibi veya uygulaması sonradan değiştirilemez.

Destek API’si tüm uygulamalar için ortaktır ve ShiftCal’e özel değildir. Yanıtlar ilgili uygulamanın **uygulama içi destek kutusunda** görünür. Destek yanıtları için telefon push bildirimi veya e-posta gönderimi mevcut değildir.

### Sunucu kurulumu

Komutları `CoreApp` dizininde çalıştır:

```sh
php artisan migrate --no-interaction
php artisan shield:generate --resource=SupportTicketResource --option=permissions --panel=manage --no-interaction
```

Migration, `support_tickets` ve `support_messages` tablolarını oluşturur. İkinci komut yalnızca destek kaynağının panel izinlerini üretir; mevcut özel policy dosyasını değiştirmez. Her yeni uygulama için tekrar tablo veya kaynak oluşturmak gerekmez.

### Yönetim panelinden kullanma

1. `/manage` adresinde yetkili panel hesabıyla giriş yap.
2. **Destek → Destek Talepleri** bölümünü aç (`/manage/support-tickets`).
3. Listede uygulama adı, kullanıcı adı/e-postası, konu, durum ve son hareket tarihini gör. Uygulama ve durum filtreleriyle listeyi daralt.
4. **Görüşmeyi aç** ile talebi aç. Uygulama/kullanıcı bilgilerini ve mesaj geçmişini kontrol et.
5. **Yanıt gönder** düğmesine bas. Pencerede hedef uygulama ve kullanıcı e-postası görünür. Yanıtı yazıp gönder; mesaj yalnızca bu talebin uygulama içi görüşmesine kaydedilir.
6. İşlem tamamlandığında **Talebi kapat** seçeneğini kullan. Kullanıcı aynı görüşmeye yeni mesaj gönderirse talep yeniden açılır.

Destek görevlisinin panel erişimi için yapılandırılmış panel rolü (varsayılan `panel_user`) ve şu izinler gerekir. **Yönetim → Roller ve İzinler** bölümünden ilgili role ata:

| İzin | Kullanım |
| --- | --- |
| `ViewAny:SupportTicket` | Destek listesini açma |
| `View:SupportTicket` | Görüşmeyi görüntüleme |
| `Update:SupportTicket` | Yanıt gönderme ve talebi kapatma |

Panelde talep oluşturma, silme veya uygulama/kullanıcı değiştirme işlemi yoktur. Talepler uygulama üzerinden oluşturulur. İzinli destek görevlileri merkezi panelde tüm uygulamaların taleplerini görebilir; uygulama filtresi bir görüntüleme filtresidir, görevli bazında erişim sınırı değildir.

### Talep durumları

| API değeri | Paneldeki karşılığı | Ne zaman oluşur? |
| --- | --- | --- |
| `open` | Yanıt bekliyor | Yeni talep veya kullanıcının yeni mesajı |
| `answered` | Yanıtlandı | Destek görevlisinin yanıtı |
| `closed` | Kapatıldı | Destek görevlisinin talebi kapatması |

### Yeni bir uygulamayı bağlama

1. **Uygulamalar** bölümünde uygulamayı oluştur ve aktif hale getir. Benzersiz `slug` belirle; örneğin `new-app`. API yollarındaki `{app}` bu slug’dır, sayısal uygulama kimliği değildir.
2. Mobil uygulamanın `API_BASE_URL` değerini CoreApp sunucusunun kök adresine ayarla (örneğin `https://api.example.com`, `/api/v1` eklemeden). Gerçek destek kullanımı için demo oturumunu kapat: `ENABLE_DEMO_AUTH=false`.
3. Kayıt ve giriş isteklerini bu uygulamanın yollarına yönlendir:
   - `POST /api/v1/apps/new-app/auth/register`
   - `POST /api/v1/apps/new-app/auth/login`
   - `GET /api/v1/apps/new-app/auth/me`
   - `POST /api/v1/apps/new-app/auth/logout`
4. Kullanıcının zaten başka bir uygulamada CoreApp hesabı varsa aynı hesapla **giriş yaptır**. Tekrar kayıt oluşturma. Giriş, bu uygulamaya ait üyeliği ve cihaz oturumunu oluşturur. Giriş/kayıt isteğinde mevcut kimlik doğrulama sözleşmesindeki `device_id`, `device_name` ve `platform` alanlarını da gönder. `device_id`, kurulum boyunca saklanan UUID’dir; `platform` değeri `android` veya `ios` olur.
5. Giriş yanıtındaki `data.token` değerini güvenli sakla ve destek isteklerinde `Authorization: Bearer <token>` başlığıyla gönder. Token yalnızca giriş yapılan uygulama için geçerlidir; ShiftCal token’ını yeni uygulamada kullanma.
6. Destek istemcisini aynı slug ile oluştur. Flutter için ShiftCal’deki yeniden kullanılabilir repository örneği:

   ```dart
   final supportRepository = SupportRepository(
     apiClient,
     appSlug: 'new-app',
   );
   ```

   Mevcut Flutter yapısını kullanıyorsan repository’yi `AppController` içindeki `supportRepository` parametresine ver ve Ayarlar’dan `SupportPage(controller: controller)` aç. Kaynaklar, ShiftCal projesindeki `lib/features/support/data/support_repository.dart` ve `lib/features/support/presentation/support_page.dart` dosyalarıdır. Yeni uygulamada ekranlardaki `ShiftCal` marka metinlerini de güncelle; yalnızca repository slug’ını değiştirmek giriş yollarını veya marka metinlerini değiştirmez.
7. Yeni uygulamada talep oluşturup panelde doğru uygulama adıyla göründüğünü kontrol et. Panelden yanıt ver ve yanıtın yalnızca o uygulamanın destek kutusunda göründüğünü doğrula. Aynı hesabın diğer uygulamadaki kutusuna da bak.

Sunucu uygulamayı URL ve aktif uygulama kaydından, kullanıcıyı Bearer token’dan belirler. Aktif uygulama üyeliği ve geçerli cihaz oturumu gerekir. İstek gövdesinde `app_id`, `user_id`, `sender_id`, `sender_type` veya `status` gönderme; talep ve kullanıcı mesajı oluşturma uçları bu alanları reddeder.

### Destek API sözleşmesi

Tüm yolların başı: `/api/v1/apps/{app}/support/tickets`. İsteklerde `Accept: application/json`, JSON gövdeli isteklerde ayrıca `Content-Type: application/json` kullan. Tüm uçlar Bearer token gerektirir.

| Metot | Yol | Kullanım |
| --- | --- | --- |
| `GET` | `/support/tickets?page=1` | Geçerli uygulama ve kullanıcının talepleri; sayfa başına 20 kayıt, en son hareket eden önce |
| `POST` | `/support/tickets` | Yeni talep ve ilk mesaj |
| `GET` | `/support/tickets/{ticket}` | Talep bilgileri ve mesaj geçmişi |
| `POST` | `/support/tickets/{ticket}/messages` | Kullanıcının yeni mesajı |
| `POST` | `/support/tickets/{ticket}/read` | Görüntülenen mesajları okundu olarak işaretleme |

Tablodaki yollar `/api/v1/apps/{app}` önekinin devamıdır. `{ticket}`, talebin UUID değeridir.

Yeni talep gövdesi:

```json
{
  "subject": "Takvim hakkında soru",
  "body": "Haftalık planımı nasıl oluşturabilirim?",
  "locale": "tr",
  "client_request_id": "a58e6a37-59d0-4819-8f7f-9d2d214249e4"
}
```

`subject` en fazla 160, `body` en fazla 5000 karakterdir; yalnızca boşluk içeren metin kabul edilmez. `locale`, `tr` veya `en` olmalıdır.

Görüşmeye yeni mesaj gövdesi:

```json
{
  "body": "Bir sorum daha var.",
  "client_request_id": "dc97dc4f-0bb8-4462-9e1e-6a506b86c6c7"
}
```

Her yeni gönderim için yeni `client_request_id` UUID’si üret. Ağ hatası veya kayıp yanıt sonrası **aynı metni tekrar gönderirken aynı UUID’yi kullan**; böylece çift talep/mesaj oluşmaz. Metin veya konu değişirse yeni UUID üret. Aynı UUID’yi farklı içerikle kullanmak `409` döndürür.

Yanıtlar `data` içinde gelir. Talepte `id`, `app_slug`, `app_name`, `subject`, `status`, `has_unread_reply`, `created_at` ve `updated_at` alanları bulunur. Talep ayrıntısı, oluşturma ve mesaj gönderme yanıtlarında ayrıca `messages` vardır; her mesaj `id`, `sender_type` (`user`/`staff`), `body` ve `created_at` taşır. Liste yanıtında sayfalama için `meta.current_page` ve `meta.last_page` değerlerini kullan.

Okundu isteğinde gerçekten görüntülediğin son mesajın sayısal `id` değerini gönder:

```json
{
  "last_message_id": 42
}
```

Talebi görüntülemek tek başına okundu işareti koymaz. Son görüntülenen mesajı bildirmen, bu sırada gelen daha yeni destek yanıtlarının yanlışlıkla okunmuş sayılmasını önler.

### Yenileme ve hata yönetimi

ShiftCal destek ekranları açıkken 30 saniyede bir yenilenir; uygulama ön plana geldiğinde ve kullanıcı yenile düğmesine bastığında da veri alınır. Bildirim için kuyruk veya push kurulumu gerekmez; bu akış uygulama içi mesajlaşmadır. Demo hesaplarında destek gönderimi kullanılamaz.

| HTTP durumu | Kontrol edilecek durum |
| --- | --- |
| `401` | Eksik veya süresi dolmuş oturum; tekrar giriş |
| `403` | Yanlış uygulamaya ait token, geçersiz cihaz oturumu veya pasif üyelik |
| `404` | Aktif uygulama bulunamaması, talebin başka uygulama/kullanıcıya ait olması veya kayıt bulunamaması |
| `409` | Aynı gönderim UUID’sinin farklı içerikle kullanılması |
| `422` | Geçersiz/eksik alan, uzunluk sınırı veya yasak sahiplik alanı |
| `429` | İstek sınırı; bir süre bekleyip aynı gönderim UUID’siyle tekrar deneme |

Yeni talep oluşturma dakikada 5, kullanıcı mesajı gönderme dakikada 10 istekle sınırlıdır; genel mobil API sınırı da uygulanır. Panelden yanıt gönderilebilmesi için hedef uygulama ve kullanıcının bu uygulamadaki üyeliği aktif olmalıdır.

### Doğrulama

CoreApp dizininde:

```sh
php artisan test --compact tests/Feature/SupportTicketTest.php tests/Feature/PanelAccessTest.php
```

ShiftCal dizininde:

```sh
flutter test test/support_test.dart
```

Testler aynı kullanıcının farklı uygulamalardaki kutularını, başka kullanıcıya erişimin engellenmesini, panel yanıtını, okundu durumunu, yeniden açılmayı, tekrar gönderimde kayıt çoğalmamasını ve mobil hesap değişiminde eski mesajların gizlenmesini kapsar.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
