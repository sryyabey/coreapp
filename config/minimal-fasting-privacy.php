<?php

return [
    'tr' => [
        'title' => 'Gizlilik Politikası',
        'description' => 'Minimal Fasting verilerinin cihazda saklanması, isteğe bağlı yedekleme, bildirimler ve verilerin silinmesi hakkında gizlilik politikası.',
        'updated' => 'Son güncelleme: 14 Ekim 2026',
        'intro' => 'Minimal Fasting, hesap açmadan oruç ve yeme sürelerini takip etmenizi sağlar. Bu politika mevcut uygulama sürümünü ve bu politika sayfasını kapsar.',
        'badges' => [
            'Hesap gerektirmez',
            'Cihazda kayıt',
            'İsteğe bağlı yedek',
        ],
        'sections' => [
            [
                'id' => 'developer',
                'title' => '1. Geliştirici ve kapsam',
                'paragraphs' => [
                    'Minimal Fasting, Süreyya Karabay tarafından SryyaLabs adı altında geliştirilmektedir. Mağaza uygulama kimliği dev.sryya.mfasting’dir. Gizlilik soruları için aşağıdaki iletişim adresini kullanabilirsiniz.',
                ],
            ],
            [
                'id' => 'device-data',
                'title' => '2. Telefonda işlenen veriler',
                'paragraphs' => [
                    'Seçtiğiniz oruç planı, oruç başlangıç ve bitiş saatleri, hedef süreler, mevcut yeme penceresi ve kaydedilmiş yeme bitişleri cihazınızda tutulur. Geçmiş ve istatistikler bu kayıtlardan cihazda hesaplanır. Dil, görünüm, tanıtım ekranlarını tamamlama durumu ve son yedekleme zamanı gibi ayarlar da yerel olarak saklanır.',
                    'Bu veriler süreleri takip etmek, geçmişinizi göstermek, ayarlarınızı korumak ve izin verdiğiniz bildirimleri hazırlamak için kullanılır. Oruç kayıtları sağlık alışkanlıklarınız hakkında bilgi verebilir; bu nedenle cihazınızı ve dışa aktardığınız dosyaları korumanız önemlidir.',
                ],
            ],
            [
                'id' => 'collection',
                'title' => '3. Sunucuya gönderim ve takip',
                'paragraphs' => [
                    'Mevcut uygulama hesap, giriş veya geliştirici sunucusuna otomatik senkronizasyon kullanmaz. Oruç geçmişiniz geliştirici sunucularına gönderilmez. Uygulamada reklam, reklam kimliğiyle takip, üçüncü taraf analitik veya geliştiriciye otomatik hata raporu gönderimi bulunmaz. Verilerinizi satmayız.',
                    'Verilerinizi dosya olarak dışa aktarmanız, başka bir uygulamayla paylaşmanız veya bize e-posta göndermeniz durumunda, seçtiğiniz içerik ilgili alıcıya aktarılabilir. İşletim sisteminin ve uygulama mağazasının kendi yedekleme ve tanılama hizmetleri, cihaz ve hesap ayarlarınıza göre ayrıca veri işleyebilir.',
                ],
            ],
            [
                'id' => 'backups',
                'title' => '4. İsteğe bağlı yedekleme ve paylaşım',
                'paragraphs' => [
                    'Uygulama, kayıtlarınızı ve bazı ayarlarınızı JSON dosyasına dışa aktarmanıza veya böyle bir dosyadan geri yüklemenize izin verir. Dosya seçici ve paylaşım menüsü üzerinden telefonunuzdaki bir konumu, Google Drive, iCloud Drive veya desteklenen başka bir sağlayıcıyı siz seçersiniz. Uygulama kendi başına bir Drive/iCloud hesabına bağlanmaz ve sürekli bulut senkronizasyonu yapmaz.',
                    'Yedek dosyaları uygulama tarafından ayrıca şifrelenmez. Dosyayı alan kişiler içeriğini okuyabilir. Bulutta saklanan veya başka bir uygulamayla paylaşılan kopyalar ilgili sağlayıcının gizlilik, saklama ve güvenlik kurallarına tabidir; veriler başka ülkelerde işlenebilir. Bu kopyalara erişim ve silme işlemlerini ilgili sağlayıcıdan yönetmelisiniz.',
                ],
            ],
            [
                'id' => 'permissions',
                'title' => '5. Bildirimler, widget ve izinler',
                'paragraphs' => [
                    'Oruç ve yeme hedefi bildirimleri telefonunuzda yerel olarak planlanır. Bildirim iznini cihaz ayarlarından kapatabilirsiniz. Bildirimler için geliştiriciye bir bildirim belirteci gönderilmez.',
                    'Widget için mevcut aşama, plan süresi, başlangıç/hedef bitiş saatleri, dil metinleri ve özellik erişim durumu cihazdaki uygulama ile widget arasında paylaşılır. Widget ana ekranınızda görünür olabilir. Dosya seçimi yalnızca seçtiğiniz dosya veya konumu kullanır; uygulama kişi listenizi, konumunuzu, kameranızı veya mikrofonunuzu istemez.',
                ],
            ],
            [
                'id' => 'payments',
                'title' => '6. Mağazalar ve ödemeler',
                'paragraphs' => [
                    'Mevcut uygulama sürümünde uygulama içi satın alma entegrasyonu bulunmaz; ödeme kartı bilgilerinizi toplamayız. Uygulama mağazasının indirme, hesap veya işlem kayıtları ilgili mağaza tarafından kendi kurallarına göre işlenir. Uygulamanın veri işleme davranışı değişirse bu politika da güncellenir.',
                ],
            ],
            [
                'id' => 'retention',
                'title' => '7. Saklama ve silme',
                'paragraphs' => [
                    'Yerel kayıtlar siz silene, uygulama verilerini temizleyene veya uygulamayı tamamen kaldırana kadar cihazda kalır. Geçmiş ekranından tek tek kayıtları silebilirsiniz. Android’de uygulamanın depolama/veri temizleme seçeneklerini; iPhone’da uygulamayı silme seçeneğini kullanabilirsiniz. iPhone’da uygulamayı boşaltma işlemi verileri saklayabilir.',
                    'Uygulamadan bir kaydı silmek, daha önce dışa aktardığınız yedekleri, bulut dosyalarını veya alıcılara gönderdiğiniz kopyaları silmez. Bunları ve işletim sistemi yedeklerini ayrıca yönetmelisiniz. Hesap oluşturulmadığı ve yerel kayıtlar bize aktarılmadığı için bu kayıtları uzaktan silemeyiz; silme konusunda e-posta ile yardımcı olabiliriz.',
                ],
            ],
            [
                'id' => 'support',
                'title' => '8. Bize ulaşmanız ve haklarınız',
                'paragraphs' => [
                    'E-posta ile iletişime geçtiğinizde e-posta adresiniz, mesajınız ve gönderdiğiniz ekler talebinizi yanıtlamak için işlenir. Oruç geçmişinizi veya yedek dosyanızı destek almak için göndermek zorunda değilsiniz. Destek yazışmaları talebi ele almak ve geçerli yasal yükümlülükleri yerine getirmek için gerektiği ölçüde saklanır.',
                    'Bize ilettiğiniz kişisel veriler hakkında bilgi, düzeltme veya silme talebinde bulunabilirsiniz. Talebinize uygulanabilir veri koruma kuralları çerçevesinde yanıt veririz. Yalnız cihazınızdaki verilere erişimimiz olmadığından bunları yönetmeniz için size yönlendirme sunarız.',
                ],
            ],
            [
                'id' => 'website',
                'title' => '9. Bu web sayfası',
                'paragraphs' => [
                    'Bu politika sayfasına eriştiğinizde barındırma altyapısı güvenlik ve hizmet sunumu için IP adresi, istek zamanı ve tarayıcı bilgisi gibi teknik erişim kayıtlarını işleyebilir. Gerekli oturum ve güvenlik çerezleri kullanılabilir. Bu sayfada reklam veya üçüncü taraf analitik betiği bulunmaz. Web erişim kayıtları uygulamadaki oruç geçmişinizi içermez.',
                ],
            ],
            [
                'id' => 'security',
                'title' => '10. Güvenlik ve çocuklar',
                'paragraphs' => [
                    'Uygulama, yerel veriler için telefonunuzun uygulama alanını ve işletim sistemi korumalarını kullanır. Ayrıca şifrelenmiş veritabanı veya yedek garantisi vermeyiz. Cihaz kilidi ve yedek dosyalarının erişim ayarlarını kullanmanız önerilir.',
                    'Minimal Fasting çocuklara yönelik bir hizmet olarak tasarlanmamıştır. Bir çocuğun bize kişisel bilgi gönderdiğini düşünüyorsanız incelememiz ve uygun şekilde silmemiz için iletişime geçebilirsiniz.',
                ],
            ],
            [
                'id' => 'changes',
                'title' => '11. Değişiklikler',
                'paragraphs' => [
                    'Veri işleme davranışı değiştiğinde bu sayfayı günceller ve son güncelleme tarihini değiştiririz. Yeni veri toplama veya paylaşım özellikleri kullanıma sunulursa gerektiğinde uygulama içinde bilgi ve izin sağlanır.',
                ],
            ],
        ],
    ],
    'en' => [
        'title' => 'Privacy Policy',
        'description' => 'Minimal Fasting privacy policy about on-device records, optional backups, notifications and deleting your data.',
        'updated' => 'Last updated: 14 October 2026',
        'intro' => 'Minimal Fasting lets you track fasting and eating windows without creating an account. This policy covers the current app version and this policy page.',
        'badges' => [
            'No account required',
            'On-device records',
            'Optional backups',
        ],
        'sections' => [
            [
                'id' => 'developer',
                'title' => '1. Developer and scope',
                'paragraphs' => [
                    'Minimal Fasting is developed by Süreyya Karabay under the SryyaLabs name. Its store application identifier is dev.sryya.mfasting. Use the contact address below for privacy enquiries.',
                ],
            ],
            [
                'id' => 'device-data',
                'title' => '2. Data processed on your device',
                'paragraphs' => [
                    'Your fasting plan, fasting start and end times, target durations, current eating window and recorded eating end times are stored on your device. History and statistics are calculated on your device from those records. Preferences such as language, appearance, onboarding completion and the last backup time are also stored locally.',
                    'This data is used to track durations, display your history, retain preferences and prepare notifications you permit. Fasting records can reveal information about your health habits, so protect your device and exported files.',
                ],
            ],
            [
                'id' => 'collection',
                'title' => '3. Server transmission and tracking',
                'paragraphs' => [
                    'The current app does not use accounts, sign-in or automatic synchronisation with a developer server. Your fasting history is not sent to developer servers. The app contains no advertising, advertising identifier tracking, third-party analytics or automatic crash reporting to the developer. We do not sell your data.',
                    'If you export records, share them with another app or email us, the content you choose may be transferred to the recipient you select. Operating system and app store backup or diagnostic services may separately process data according to your device and account settings.',
                ],
            ],
            [
                'id' => 'backups',
                'title' => '4. Optional backups and sharing',
                'paragraphs' => [
                    'The app lets you export records and some preferences to a JSON file or restore from such a file. You choose a location on your phone, Google Drive, iCloud Drive or another supported provider through the system file picker or share sheet. The app does not independently connect to a Drive/iCloud account or perform continuous cloud synchronisation.',
                    'Backup files are not additionally encrypted by the app. People who receive a file can read its contents. Copies stored in the cloud or shared with another app are governed by the provider’s privacy, retention and security practices; data may be processed in other countries. Manage access and deletion of those copies with the relevant provider.',
                ],
            ],
            [
                'id' => 'permissions',
                'title' => '5. Notifications, widgets and permissions',
                'paragraphs' => [
                    'Fasting and eating target notifications are scheduled locally on your phone. You can disable notification permission in device settings. No notification token is sent to the developer.',
                    'For widgets, the current phase, plan duration, start/target end times, language labels and feature access status are shared between the app and widget on your device. A widget may be visible on your home screen. File selection uses only the file or location you choose; the app does not request your contacts, location, camera or microphone.',
                ],
            ],
            [
                'id' => 'payments',
                'title' => '6. Stores and payments',
                'paragraphs' => [
                    'The current app version has no in-app purchase integration; we do not collect payment card details. App store download, account or transaction records are handled by the store under its own rules. We update this policy if the app’s data handling changes.',
                ],
            ],
            [
                'id' => 'retention',
                'title' => '7. Retention and deletion',
                'paragraphs' => [
                    'Local records remain on your device until you delete them, clear app data or fully uninstall the app. You can delete individual records in History. On Android, use the app storage/data clearing options; on iPhone, use Delete App. Offloading an iPhone app may preserve its data.',
                    'Deleting a record in the app does not delete previously exported backups, cloud files or copies sent to recipients. Manage those copies and operating system backups separately. Because the app has no account and local records are not sent to us, we cannot delete those records remotely; you can email us for deletion guidance.',
                ],
            ],
            [
                'id' => 'support',
                'title' => '8. Contacting us and your rights',
                'paragraphs' => [
                    'If you email us, your email address, message and any attachments are processed to respond to your enquiry. You do not have to send fasting history or a backup file to request support. Support correspondence is kept only as needed to handle the enquiry and fulfil applicable legal obligations.',
                    'You can request information, correction or deletion of personal data you have sent us. We respond under applicable data protection rules. Since we cannot access data stored only on your device, we can provide guidance for managing it yourself.',
                ],
            ],
            [
                'id' => 'website',
                'title' => '9. This web page',
                'paragraphs' => [
                    'When you visit this policy page, hosting infrastructure may process technical access logs such as your IP address, request time and browser details for security and service delivery. Necessary session and security cookies may be used. This page contains no advertising or third-party analytics scripts. Web access logs do not contain your in-app fasting history.',
                ],
            ],
            [
                'id' => 'security',
                'title' => '10. Security and children',
                'paragraphs' => [
                    'The app uses your phone’s app storage area and operating system protections for local data. We do not promise a separately encrypted database or encrypted backups. Use a device lock and manage access to backup files.',
                    'Minimal Fasting is not designed as a service for children. If you believe a child has sent us personal information, contact us so we can review and delete it as appropriate.',
                ],
            ],
            [
                'id' => 'changes',
                'title' => '11. Changes',
                'paragraphs' => [
                    'We update this page and its last-updated date when data handling changes. If new collection or sharing features are introduced, we provide in-app information and permission where required.',
                ],
            ],
        ],
    ],
    'de' => [
        'title' => 'Datenschutzerklärung',
        'description' => 'Datenschutzerklärung von Minimal Fasting zu lokalen Daten, optionalen Sicherungen, Benachrichtigungen und der Datenlöschung.',
        'updated' => 'Zuletzt aktualisiert: 14. Oktober 2026',
        'intro' => 'Mit Minimal Fasting kannst du Fasten- und Essensfenster ohne Benutzerkonto erfassen. Diese Erklärung gilt für die aktuelle App-Version und diese Webseite.',
        'badges' => [
            'Kein Konto nötig',
            'Daten auf dem Gerät',
            'Optionale Sicherungen',
        ],
        'sections' => [
            [
                'id' => 'developer',
                'title' => '1. Entwickler und Geltungsbereich',
                'paragraphs' => [
                    'Minimal Fasting wird von Süreyya Karabay unter dem Namen SryyaLabs entwickelt. Die App-Kennung im Store lautet dev.sryya.mfasting. Für Datenschutzfragen kannst du die unten angegebene Kontaktadresse verwenden.',
                ],
            ],
            [
                'id' => 'device-data',
                'title' => '2. Daten auf deinem Gerät',
                'paragraphs' => [
                    'Dein Fastenplan, Start- und Endzeiten, Zieldauer, aktuelles Essensfenster und erfasste Essensendzeiten werden auf deinem Gerät gespeichert. Verlauf und Statistiken werden daraus lokal berechnet. Auch Sprache, Darstellung, abgeschlossene Einführung und Zeitpunkt der letzten Sicherung werden lokal gespeichert.',
                    'Diese Daten dienen der Zeiterfassung, dem Verlauf, deinen Einstellungen und den von dir erlaubten Benachrichtigungen. Fasteneinträge können Informationen über deine Gesundheitsgewohnheiten erkennen lassen. Schütze deshalb dein Gerät und exportierte Dateien.',
                ],
            ],
            [
                'id' => 'collection',
                'title' => '3. Serverübertragung und Tracking',
                'paragraphs' => [
                    'Die aktuelle App verwendet weder Benutzerkonten noch Anmeldung oder automatische Synchronisierung mit einem Entwicklerserver. Dein Fastenverlauf wird nicht an Entwicklerserver gesendet. Die App enthält keine Werbung, kein Tracking über Werbe-IDs, keine Drittanbieter-Analyse und keine automatischen Absturzberichte an den Entwickler. Wir verkaufen deine Daten nicht.',
                    'Wenn du Daten exportierst, mit einer anderen App teilst oder uns per E-Mail kontaktierst, können die von dir gewählten Inhalte an den ausgewählten Empfänger übermittelt werden. Sicherungs- und Diagnosedienste des Betriebssystems und App-Stores können entsprechend deinen Geräte- und Kontoeinstellungen zusätzlich Daten verarbeiten.',
                ],
            ],
            [
                'id' => 'backups',
                'title' => '4. Optionale Sicherungen und Teilen',
                'paragraphs' => [
                    'Du kannst Einträge und einige Einstellungen als JSON-Datei exportieren oder aus einer solchen Datei wiederherstellen. Über die Dateiauswahl oder das Teilen-Menü wählst du selbst einen Speicherort, Google Drive, iCloud Drive oder einen anderen unterstützten Anbieter. Die App verbindet sich nicht selbstständig mit einem Drive-/iCloud-Konto und führt keine laufende Cloud-Synchronisierung durch.',
                    'Sicherungsdateien werden von der App nicht zusätzlich verschlüsselt. Empfänger einer Datei können ihren Inhalt lesen. Für Cloud-Kopien und mit anderen Apps geteilte Daten gelten die Datenschutz-, Aufbewahrungs- und Sicherheitsregeln des jeweiligen Anbieters; Daten können in anderen Ländern verarbeitet werden. Zugriff und Löschung dieser Kopien verwaltest du beim jeweiligen Anbieter.',
                ],
            ],
            [
                'id' => 'permissions',
                'title' => '5. Benachrichtigungen, Widgets und Berechtigungen',
                'paragraphs' => [
                    'Benachrichtigungen zum Fasten- und Essensziel werden lokal auf deinem Telefon geplant. Die Berechtigung kannst du in den Geräteeinstellungen deaktivieren. Es wird kein Benachrichtigungs-Token an den Entwickler gesendet.',
                    'Für Widgets werden aktuelle Phase, Plandauer, Start-/Zielendzeiten, Sprachtexte und Funktionszugang zwischen App und Widget auf deinem Gerät ausgetauscht. Das Widget kann auf deinem Startbildschirm sichtbar sein. Die Dateiauswahl verwendet nur die ausgewählte Datei oder den Speicherort. Die App fordert keinen Zugriff auf Kontakte, Standort, Kamera oder Mikrofon an.',
                ],
            ],
            [
                'id' => 'payments',
                'title' => '6. Stores und Zahlungen',
                'paragraphs' => [
                    'Die aktuelle App-Version enthält keine In-App-Kauf-Integration; wir erfassen keine Zahlungskartendaten. Download-, Konto- und Transaktionsdaten des App-Stores verarbeitet der jeweilige Store nach seinen eigenen Regeln. Wenn sich die Datenverarbeitung der App ändert, aktualisieren wir diese Erklärung.',
                ],
            ],
            [
                'id' => 'retention',
                'title' => '7. Aufbewahrung und Löschung',
                'paragraphs' => [
                    'Lokale Einträge bleiben auf deinem Gerät, bis du sie löschst, die App-Daten zurücksetzt oder die App vollständig deinstallierst. Einzelne Einträge kannst du im Verlauf löschen. Auf Android kannst du die Optionen zum Löschen des App-Speichers bzw. der App-Daten nutzen; auf dem iPhone „App löschen“. Beim Auslagern einer iPhone-App können Daten erhalten bleiben.',
                    'Das Löschen eines Eintrags entfernt keine zuvor exportierten Sicherungen, Cloud-Dateien oder an Empfänger gesendeten Kopien. Diese Kopien und Betriebssystem-Sicherungen musst du separat verwalten. Da es kein Konto gibt und lokale Einträge nicht an uns gesendet werden, können wir sie nicht aus der Ferne löschen. Per E-Mail können wir dir dabei helfen.',
                ],
            ],
            [
                'id' => 'support',
                'title' => '8. Kontakt und deine Rechte',
                'paragraphs' => [
                    'Wenn du uns per E-Mail kontaktierst, verarbeiten wir deine E-Mail-Adresse, Nachricht und Anhänge zur Beantwortung deiner Anfrage. Für Unterstützung musst du weder deinen Fastenverlauf noch eine Sicherungsdatei senden. Support-Korrespondenz wird nur aufbewahrt, soweit es für die Anfrage und geltende rechtliche Pflichten erforderlich ist.',
                    'Du kannst Auskunft, Berichtigung oder Löschung der an uns übermittelten personenbezogenen Daten verlangen. Wir antworten nach den geltenden Datenschutzregeln. Auf ausschließlich lokal gespeicherte Daten haben wir keinen Zugriff; wir können dir Hinweise zur Verwaltung geben.',
                ],
            ],
            [
                'id' => 'website',
                'title' => '9. Diese Webseite',
                'paragraphs' => [
                    'Beim Besuch dieser Seite kann die Hosting-Infrastruktur technische Zugriffsprotokolle wie IP-Adresse, Anfragezeit und Browserinformationen für Sicherheit und Bereitstellung verarbeiten. Notwendige Sitzungs- und Sicherheitscookies können verwendet werden. Diese Seite enthält keine Werbung und keine Analyse-Skripte von Drittanbietern. Web-Zugriffsprotokolle enthalten keinen Fastenverlauf aus der App.',
                ],
            ],
            [
                'id' => 'security',
                'title' => '10. Sicherheit und Kinder',
                'paragraphs' => [
                    'Die App nutzt den App-Speicherbereich und die Schutzmechanismen deines Betriebssystems für lokale Daten. Eine separat verschlüsselte Datenbank oder verschlüsselte Sicherungen werden nicht zugesichert. Verwende eine Gerätesperre und verwalte den Zugriff auf Sicherungsdateien.',
                    'Minimal Fasting ist nicht als Dienst für Kinder konzipiert. Wenn du glaubst, dass ein Kind uns personenbezogene Daten gesendet hat, kontaktiere uns, damit wir dies prüfen und die Daten gegebenenfalls löschen können.',
                ],
            ],
            [
                'id' => 'changes',
                'title' => '11. Änderungen',
                'paragraphs' => [
                    'Bei Änderungen der Datenverarbeitung aktualisieren wir diese Seite und das Aktualisierungsdatum. Wenn neue Funktionen zur Datenerfassung oder Weitergabe hinzukommen, stellen wir bei Bedarf Informationen und Berechtigungen in der App bereit.',
                ],
            ],
        ],
    ],
];
