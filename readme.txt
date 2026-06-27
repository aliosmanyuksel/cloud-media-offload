=== R2 Media Offload ===
Requires PHP: 7.4
License: GPLv2 or later

WordPress medyasını Cloudflare R2 veya S3-uyumlu depolamaya taşır ve sunar.
SDK yok; AWS SigV4 saf cURL ile imzalanır.

== Kullanım ==
1. R2 Offload > Kurulum Sihirbazı ile sağlayıcı ve kimlik bilgilerini girin.
2. Bağlantıyı test edin.
3. R2 Offload > Migrasyon ile mevcut medyayı taşıyın.
4. Migrasyon bittiğinde Ayarlar'dan "Tam sayfa URL yeniden yazma"yı açın.

wp-config.php sabitleri (DB'yi ezer):
  define('R2MO_ACCOUNT_ID', '...');
  define('R2MO_ACCESS_KEY', '...');
  define('R2MO_SECRET_KEY', '...');
  define('R2MO_BUCKET', '...');
  define('R2MO_ENDPOINT', '...');   // genel S3-uyumlu için
  define('R2MO_REGION', '...');
  define('R2MO_PUBLIC_BASE', 'https://cdn.example.com');

WP-CLI: wp r2mo migrate [--delete-local]
