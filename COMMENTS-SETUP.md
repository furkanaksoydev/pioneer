# Pioneer yorum sistemi: yerel kurulum ve yayın politikası

## Mevcut çalışma biçimi

- `comments-api.php`, ilk istekte MySQL/phpMyAdmin üzerindeki `laviraco_pioneer` veritabanında yorum tablosunu hazırlar ve dört onaylı örnek yorum ekler.
- Her yeni yorum `pending` statüsünde kaydedilir. Ana sayfa ve `yorumlar.html` yalnızca `approved` yorumları gösterir.
- `admin.php`, oturum korumalı yorum ve finans yönetim ekranıdır. Yorumlar burada yanıtlanır, onaylanır, reddedilir veya silinir. Yorum yanıtı, yorum onaylandığında herkese açık akışta görünür.
- Aynı MySQL veritabanı; gelir/gider kayıtlarını, parça parça tahsilat ve ödemeleri ve denetim kaydını da saklar. Para tutarları yuvarlama hatası oluşturmaması için kuruş cinsinden tutulur.
- E-posta adresi yalnızca yönetici ekranında görünür; herkese açık API yanıtına hiç dahil edilmez.
- Veritabanı giriş bilgileri yalnızca Git tarafından izlenmeyen `config.local.php` dosyasında tutulur. Paylaşılan `database-config.php` dosyası, canlı sunucuda aynı bilgileri ortam değişkenlerinden de okuyabilir.

## Yayına almadan önce zorunlu ayarlar

Sunucu ortamında aşağıdaki ayarları tanımlayın ve Apache/PHP-FPM sürecini yeniden başlatın:

```text
PIONEER_COMMENT_IP_SALT=uzun-ve-rastgele-bir-sunucu-sirri
PIONEER_DB_HOST=localhost
PIONEER_DB_NAME=laviraco_pioneer
PIONEER_DB_USER=laviraco_pioneer
PIONEER_DB_PASSWORD=guclu-ve-gizli-parola
```

IP salt değeri, ham IP tutmadan saatlik gönderim sınırı için kullanılan tek yönlü HMAC özetini üretir. Veritabanı parolası Git’e ya da istemci tarafına yazılmaz; yerelde `config.local.php`, canlıda ise ortam değişkenleri kullanılmalıdır. Yönetici parolası yalnızca `admin.php` içinde bcrypt özeti olarak tutulur.

## Moderasyon kararı

İlk canlı sürümde **manuel onay zorunlu** olmalıdır. Otomatik yayımlama; küfür, yönlendirme bağlantıları, taklit yorumlar ve anlamsız metin riskini gereksiz biçimde artırır. Mevcut sistemde şu katmanlar zaten bulunur:

1. Ad, doğrulanabilir e-posta, 1–5 puan, en az 20 karakter ve açık rıza zorunluluğu.
2. Gizli honeypot alanı, anlamsız tekrar-karakter kontrolü, en az üç anlamlı kelime kontrolü ve IP özeti başına saatte en fazla üç kayıt.
3. İnceleme kuyruğu: yönetici onayı olmadan hiçbir kayıt genel akışa çıkmaz.

Canlıya geçmeden önce bu katmanlara **Cloudflare Turnstile** eklenmelidir. Turnstile jetonu hem tarayıcıda alınmalı hem de `comments-api.php` tarafından sunucu tarafında doğrulanmalıdır. İhtiyaç büyürse, e-posta doğrulama bağlantısı ve yasaklı kelime/link ön filtresi eklenebilir; fakat bunlar manuel kontrolün yerine geçmemelidir.

## R2 görsel yükleme: şu anda bilinçli olarak kapalı

Form, JPG/PNG/WEBP seçimini ve 10 MB üst sınırını arayüzde doğrular; seçilen dosya **tarayıcıdan gönderilmez**. Yalnızca dosya adı `awaiting-r2` durumu ile yorum kaydına yazılır. Böylece R2 anahtarı gelmeden yanlışlıkla yükleme yapılmaz.

R2 erişimi hazır olduğunda doğru akış şudur:

1. Turnstile doğrulamasından sonra sunucuda veya Cloudflare Worker'da kısa ömürlü, tek kullanımlık yükleme URL'si üretin.
2. Tarayıcı dosyayı yalnızca bu imzalı URL'ye doğrudan R2'ye yüklesin; R2 gizli anahtarları hiçbir zaman JavaScript'e yazılmasın.
3. Worker/PHP tarafında dosya uzantısı yerine gerçek MIME imzasını, boyutu ve görsel çözünürlüğünü doğrulayın; adı rastgele UUID yapın.
4. Yönetici görseli de inceleyip yorumla birlikte onayladıktan sonra yalnızca güvenli CDN URL'sini `image_url` alanına yazsın.

Bu plan uygulamaya hazırdır; gerçek dosya aktarımı R2 API bilgileri gelene kadar kasıtlı olarak devre dışıdır.
