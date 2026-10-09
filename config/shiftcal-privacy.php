<?php

return [
    'tr' => [
        ['id' => 'about', 'title' => '1. Kapsam ve iletişim', 'paragraphs' => [
            'Bu politika, SryyaLabs markası altında sunulan ShiftCal mobil uygulaması ve uygulamayla bağlantılı hesap, takvim, ortak plan, yedekleme ve destek hizmetlerinde kişisel verilerin nasıl işlendiğini açıklar. Veri sorumlusu geliştirici Süreyya Karabay’dır. Gizlilikle ilgili sorularınız ve talepleriniz için aşağıdaki iletişim adresini kullanabilirsiniz.',
        ]],
        ['id' => 'data', 'title' => '2. İşlenen veriler', 'paragraphs' => [
            'Hesap bilgileri: kayıt sırasında verdiğiniz ad, e-posta adresi ve şifre; hesap ve üyelik kimlikleri. Şifreler sunucuda hash olarak saklanır. Giriş oturumları için erişim tokenları kullanılır.',
            'Cihaz ve bildirim bilgileri: uygulamanın oluşturduğu kurulum kimliği, cihaz adı, iOS veya Android platform bilgisi, son bağlantı zamanı, dil ve bildirim tercihleri ile Firebase Cloud Messaging bildirim tokenı.',
            'Planlama verileri: eklediğiniz vardiyalar, etkinlik türleri, başlangıç ve bitiş saatleri, saat dilimi, renkler, notlar, şablonlar ve ortak planlar. Saatlik ücret tercihiniz cihazda tutulur; kazanç tahminleri çalışma kayıtlarınızdan hesaplanır. Bulut yedekleme kullandığınızda yedeklenen takvim kayıtları sunucuya gönderilir.',
            'Abonelik bilgileri: mağaza, ürün, işlem veya satın alma tokenı, hesaba özel mağaza eşleştirme kimliği, doğrulanmış abonelik durumu, deneme, yenileme ve erişim bitişi. Doğrulama kanıtları sunucuda şifreli saklanır. Ödeme kartı bilgilerinizi almayız; ödemeyi Apple App Store veya Google Play işler. Eşiniz abonelik kaynağı, deneme durumu ve erişim bitişini görebilir; işlem tokenınızı göremez.',
            'Partner bağlantıları: davet ve bağlantı kimlikleri, bağlantı kurduğunuz hesap, ortak plan önerileri ve bu önerilere verdiğiniz yanıtlar.',
            'Destek bilgileri: gönderdiğiniz talep konusu, mesajlar, yanıtlar, dil, tarih ve okunma bilgileri. E-posta ile iletişim kurduğunuzda gönderici adresiniz ve mesaj içeriğiniz de destek talebinizin işlenmesi için kullanılır. Mesajlarda şifre veya ödeme kartı bilgisi paylaşmayın.',
            'Teknik bilgiler: hizmete yapılan bağlantılar sırasında sunucu altyapısı IP adresi ve istek zamanları gibi bağlantı ve hata bilgilerini işleyebilir. Uygulama, dil ve görünüm tercihleri ile takvim kayıtlarının bir kopyasını cihazınızda saklar.',
        ]],
        ['id' => 'purposes', 'title' => '3. Verileri neden kullanırız?', 'paragraphs' => [
            'Verileri hesabınızı oluşturmak ve oturumunuzu yönetmek, takvim kayıtlarını saklamak ve eşitlemek, seçtiğiniz partnerle ortak planları yürütmek, talep ettiğiniz yedekleme ve geri yüklemeyi sağlamak, mağaza aboneliğini doğrulayıp Duo erişimini hesap ve mevcut eşe uygulamak, tercihlerinize göre bildirim göndermek ve destek taleplerinizi yanıtlamak için kullanırız.',
            'Hesap ve hizmet güvenliğini sağlamak, hataları gidermek, kötüye kullanımı önlemek ve uygulanabilir yasal yükümlülükleri yerine getirmek için gerekli verileri de işleriz. Veriler hizmetin sunulması, meşru güvenlik ihtiyaçları ve uygulanabilir yasal yükümlülükler kapsamında; izin gerektiren özellikler ise verdiğiniz izin kapsamında işlenir.',
        ]],
        ['id' => 'sharing', 'title' => '4. Partnerle ve hizmet sağlayıcılarla paylaşım', 'paragraphs' => [
            'Partner bağlantısı kurduğunuzda bağlı hesabın adı, gün içindeki çalışma, uyku, boş veya meşgul zaman aralıkları ve birlikte oluşturduğunuz plan bilgileri bağlantı kurduğunuz kişi tarafından görülebilir. Partnerin günlük uygunluk görünümü kişisel etkinlik notlarını veya saatlik ücretinizi içermez. Ortak planlara yazdığınız bilgiler planın diğer katılımcısına gösterilebilir.',
            'Sunucu ve barındırma altyapısı, destek e-posta hizmeti ve Google Firebase Cloud Messaging gibi hizmet sağlayıcılar, ilgili hizmeti sağlamak için gerekli verileri işleyebilir. Firebase bildirimlerin cihazınıza ulaştırılmasında kullanılır. Bu sağlayıcıların altyapıları nedeniyle veriler bulunduğunuz ülke dışında da işlenebilir.',
            'Kişisel verilerinizi satmayız. Mevcut uygulama reklam SDK’sı, Firebase Analytics veya Crashlytics içermemektedir. Yetkili makamların hukuka uygun talepleri veya hakların korunması için gereken durumlarda gerekli bilgiler paylaşılabilir.',
        ]],
        ['id' => 'permissions', 'title' => '5. İzinler ve cihazdaki veriler', 'paragraphs' => [
            'Bildirim ve hatırlatma özellikleri için işletim sisteminin bildirim izni ve gerektiğinde alarm ayarları kullanılır. Bu tercihleri uygulama ayarlarından veya cihaz ayarlarından değiştirebilirsiniz. Uygulama saat diliminizi zamanların doğru gösterilmesi için kullanır; GPS konumu, kişiler, mikrofon veya fotoğraf kitaplığı erişimi istemez.',
            'Oturum bilgileri cihazın güvenli saklama mekanizmasıyla, takvim ve tercihler ise uygulamanın yerel depolamasında tutulur. Bu sayfa üçüncü taraf reklam veya analitik izleme kodu içermez; web altyapısı gerekli oturum ve güvenlik çerezlerini kullanabilir.',
        ]],
        ['id' => 'retention', 'title' => '6. Saklama ve güvenlik', 'paragraphs' => [
            'Hesap ve hizmet verileri hesabınızın ve ilgili özelliklerin sunulması için gerektiği sürece saklanır. Destek yazışmaları taleplerin çözülmesi ve takibi için kullanılır. Silme talebi geldiğinde ilgili aktif veriler silinir; yasal yükümlülük, güvenlik veya uyuşmazlık nedeniyle saklanması gereken sınırlı kayıtlar yalnızca bu amaç için gerekli süreyle tutulur. Talebiniz sırasında varsa bu istisnalar hakkında bilgi veririz.',
            'Tekil etkinliği silmek, daha önce oluşturulmuş bulut yedeğini veya kendiniz dışa aktardığınız kopyaları otomatik olarak silmez. Bulut yedeğinizin de silinmesini talebinizde belirtin. Kendi cihazınızdaki ve dışa aktardığınız dosyalardaki kopyaları ayrıca kaldırmanız gerekir.',
            'Uygulama ve sunucu arasındaki bağlantılarda HTTPS, hesaplarda hashlenmiş şifreler, yetkilendirilmiş erişim ve kullanıcıya göre ayrılmış kayıtlar kullanılır. Hiçbir dijital saklama veya aktarım yönteminin mutlak güvenliği garanti edilemez.',
        ]],
        ['id' => 'delete-account', 'title' => '7. Hesap ve veri silme talebi', 'paragraphs' => [
            'Uygulama içinde Ayarlar > Hesabımı sil bölümünden ShiftCal hesabınızı ve verilerinizi kalıcı olarak silebilirsiniz. Diğer uygulamalardaki üyelikleriniz korunur. Apple ile açılmış hesaplarda Apple hesabınızı yeniden doğrulamanız istenir. Varsa mağaza aboneliğinizi App Store veya Google Play üzerinden ayrıca iptal etmelisiniz. Uygulamayı yüklemeden de hesap ve verilerinizin silinmesini isteyebilirsiniz. Aşağıdaki e-posta bağlantısını kullanarak hesabınızda kayıtlı e-posta adresinden “ShiftCal hesap silme” konulu bir mesaj gönderin. Hesabınıza erişiyorsanız uygulama içindeki Destek bölümünden de talep oluşturabilirsiniz. Şifrenizi göndermeyin; hesabın size ait olduğunu doğrulamak için ek bilgi isteyebiliriz.',
            'Talebiniz ShiftCal hesabınıza bağlı takvim kayıtları, bulut yedeği, cihaz ve bildirim kayıtları, partner bağlantıları, ortak planlar ve destek verilerinin silinmesini kapsar. Ortak planların silinmesi partnerinizin görünümünü de etkileyebilir. Saklanması gereken sınırlı kayıtlar varsa size açıklanır.',
        ]],
        ['id' => 'rights', 'title' => '8. Haklarınız ve tercihleriniz', 'paragraphs' => [
            'Uygulanabilir mevzuata göre verilerinizin işlenip işlenmediğini öğrenme, bilgi ve erişim isteme, yanlış bilgilerin düzeltilmesini veya verilerinizin silinmesini talep etme, işlemeye itiraz etme ve uygun durumlarda taşınabilir kopya isteme haklarınız olabilir. İzne dayalı özellikler için izninizi geri çekebilirsiniz. Taleplerinizi aşağıdaki adrese iletin; haklarınızı kullanmanız için kimliğinizi doğrulamamız gerekebilir. İlgili veri koruma makamına başvurma hakkınız saklıdır.',
        ]],
        ['id' => 'children', 'title' => '9. Çocukların gizliliği ve politika değişiklikleri', 'paragraphs' => [
            'ShiftCal çocuklara yönelik tasarlanmış bir hizmet değildir. Bir çocuğun gerekli veli izni olmadan kişisel veri sağladığını düşünüyorsanız bize ulaşın; ilgili verilerin kaldırılması için talebinizi inceleriz.',
            'Özellikler veya veri işleme uygulamaları değiştiğinde bu politika güncellenir ve son güncelleme tarihi değiştirilir. Önemli değişiklikler gerektiğinde uygulama üzerinden veya iletişim kanallarıyla duyurulur.',
        ]],
    ],
    'en' => [
        ['id' => 'about', 'title' => '1. Scope and contact', 'paragraphs' => [
            'This policy explains how personal data is handled by the ShiftCal mobile app and its related account, calendar, shared planning, backup and support services, offered under the SryyaLabs brand. The developer and data controller is Süreyya Karabay. Use the contact address below for privacy questions and requests.',
        ]],
        ['id' => 'data', 'title' => '2. Data we process', 'paragraphs' => [
            'Account information: the name, email address and password you provide during registration, plus account and membership identifiers. Passwords are stored on the server as hashes. Access tokens are used to manage signed-in sessions.',
            'Device and notification information: an app-generated installation identifier, device name, iOS or Android platform, last connection time, language and notification preferences, and a Firebase Cloud Messaging notification token.',
            'Planning data: shifts, activity types, start and end times, time zone, colours, notes, templates and shared plans. Your hourly rate preference is stored on your device; estimated earnings are calculated from work entries. When you use cloud backup, the backed-up calendar entries are sent to the server.',
            'Subscription information: store, product, transaction or purchase token, account-specific store association identifier, verified subscription status, trial, renewal and access expiry. Verification proofs are encrypted on the server. We do not receive payment card details; Apple App Store or Google Play processes payment. Your partner can see the subscription source, trial status and access expiry, but cannot see your transaction token.',
            'Partner connections: invitation and connection identifiers, the connected account, shared plan proposals and your responses to them.',
            'Support information: request subjects, messages, replies, language, timestamps and read status. If you contact us by email, your sender address and message content are used to handle your request. Do not include passwords or payment card details in messages.',
            'Technical information: server infrastructure may process connection and error information, such as IP addresses and request times, when you connect to the service. The app stores language and appearance preferences and a copy of calendar entries on your device.',
        ]],
        ['id' => 'purposes', 'title' => '3. Why we use your data', 'paragraphs' => [
            'We use data to create your account and manage sessions, store and synchronise calendar entries, enable shared planning with your chosen partner, provide requested backup and restore features, send notifications according to your preferences and respond to support requests.',
            'We also process necessary data to secure accounts and services, resolve errors, prevent abuse and meet applicable legal obligations. Processing is based on providing the service, legitimate security needs and applicable legal obligations; features requiring permission operate with your permission.',
        ]],
        ['id' => 'sharing', 'title' => '4. Sharing with partners and service providers', 'paragraphs' => [
            'When you establish a partner connection, the connected account name, time intervals marked as working, sleeping, free or busy, and shared plan information can be visible to your connected partner. The daily availability view does not include personal activity notes or your hourly rate. Information you enter in shared plans may be shown to the other participant.',
            'Service providers, including server and hosting infrastructure, support email services and Google Firebase Cloud Messaging, may process the data needed to provide their services. Firebase delivers notifications to your device. These providers may process data outside your country because of the locations of their infrastructure.',
            'We do not sell your personal data. The current app does not include advertising SDKs, Firebase Analytics or Crashlytics. Necessary information may be disclosed in response to lawful requests from authorities or where required to protect rights.',
        ]],
        ['id' => 'permissions', 'title' => '5. Permissions and on-device storage', 'paragraphs' => [
            'Notifications and reminders use operating system notification permission and, where needed, alarm settings. You can change these preferences in the app or device settings. The app uses your time zone to display times correctly; it does not request access to GPS location, contacts, microphone or your photo library.',
            'Session information is kept using secure device storage, while calendars and preferences are kept in local app storage. This page does not contain third-party advertising or analytics tracking code; the web infrastructure may use necessary session and security cookies.',
        ]],
        ['id' => 'retention', 'title' => '6. Retention and security', 'paragraphs' => [
            'Account and service data is kept for as long as needed to provide your account and related features. Support correspondence is used to resolve and follow up requests. When deletion is requested, the relevant active data is deleted; limited records that must be retained for legal obligations, security or disputes are kept only for the period necessary for those purposes. We explain any such exceptions when handling your request.',
            'Deleting an individual activity does not automatically remove an earlier cloud backup or copies you exported yourself. Please specify if you also want your cloud backup deleted. You need to remove copies on your own devices and in exported files separately.',
            'We use HTTPS for app-to-server connections, hashed account passwords, authorised access and records scoped to each user. No method of digital storage or transmission can guarantee absolute security.',
        ]],
        ['id' => 'delete-account', 'title' => '7. Request account and data deletion', 'paragraphs' => [
            'You can permanently delete your ShiftCal account and data in Settings > Delete my account. Memberships in other apps are preserved. Accounts created with Apple require Apple reauthentication. Any store subscription must be canceled separately in the App Store or Google Play. You can request account and data deletion without installing the app. Use the email link below to send a message with the subject “ShiftCal account deletion” from your registered email address. If you can access your account, you can also submit a request through the in-app Support section. Do not send your password; we may request additional information to verify account ownership.',
            'Your request covers deletion of calendar entries, cloud backup, device and notification records, partner connections, shared plans and support data associated with your ShiftCal account. Deleting shared plans may also affect your partner’s view. Any limited records that must be retained will be explained to you.',
        ]],
        ['id' => 'rights', 'title' => '8. Your rights and choices', 'paragraphs' => [
            'Depending on applicable law, you may have rights to learn whether your data is processed, request information and access, correct inaccurate information, request deletion, object to processing and request a portable copy where applicable. You can withdraw permissions for permission-based features. Contact us at the address below to exercise your rights; we may need to verify your identity. You may also contact the relevant data protection authority.',
        ]],
        ['id' => 'children', 'title' => '9. Children’s privacy and policy updates', 'paragraphs' => [
            'ShiftCal is not designed as a service for children. If you believe a child has provided personal data without any required parental permission, contact us so we can review removal of the relevant data.',
            'We update this policy and its last updated date when features or data practices change. Significant changes are communicated through the app or our contact channels where needed.',
        ]],
    ],
];
