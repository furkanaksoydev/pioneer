# Pioneer yorum sistemi: yerel kurulum ve yayın politikası

## Mevcut çalışma biçimi

- `comments-api.php`, ilk istekte `data/comments.sqlite` SQLite veritabanını hazırlar ve dört onaylı örnek yorum ekler.
- Her yeni yorum `pending` statüsünde kaydedilir. Ana sayfa ve `yorumlar.html` yalnızca `approved` yorumları gösterir.
- `comments-admin.php`, parola korumalı manuel inceleme ekranıdır. Onay veya ret işlemi; inceleme notu ve zaman bilgisiyle veritabanına yazılır.
- E-posta adresi yalnızca yönetici ekranında görünür; herkese açık API yanıtına hiç dahil edilmez.
- `data/.htaccess`, Apache üzerinde veritabanı dosyalarının doğrudan istenmesini engeller. `data/*.sqlite*` Git tarafından izlenmez.

## Yayına almadan önce zorunlu ayarlar

Sunucu ortamında aşağıdaki iki değişkeni güçlü, rastgele değerlerle tanımlayın ve Apache/PHP-FPM sürecini yeniden başlatın:

```text
PIONEER_COMMENTS_ADMIN_PASSWORD=uzun-ve-tekil-bir-yonetici-parolasi
PIONEER_COMMENT_IP_SALT=uzun-ve-rastgele-bir-sunucu-sirri
```

İlk değişken olmadan yönetici ekranı kapalı kalır. İkinci değişken, ham IP tutmadan saatlik gönderim sınırı için kullanılan tek yönlü HMAC özetini üretir. Bu değerleri kaynak koduna, Git deposuna veya istemci tarafına yazmayın.

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
