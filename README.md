# User Management System

Laravel REST API ve Vue SPA ile geliştirilmiş full-stack bir uygulamadır. Kullanıcı ve profil yönetiminin yanında blog içeriği, kategori, etkileşim, yorum moderasyonu ve istatistik ekranlarını kapsar.

Kimlik doğrulama Laravel Sanctum Bearer token ile yapılır. Yazılar taslak, bekleyen, yayında ve reddedildi durumlarından geçer. Kullanıcılar görüntülenme, beğeni, yorum ve yanıt bırakır. Yöneticiler yazıları onaylar, kategorileri yönetir ve yorum şikayetlerini karara bağlar.

## Öne çıkan özellikler

### Kimlik doğrulama ve profil

- Kayıt, giriş ve çıkış
- Profil bilgisi, şifre değiştirme ve profil fotoğrafı
- Rol ayrımı: `user` ve `admin`

### Blog ve içerik yönetimi

- Yazı oluşturma, düzenleme, silme ve listeleme
- Taslak, bekleyen, yayında ve reddedildi durumları
- Yönetici onayı ve reddi
- Kategori yönetimi, aktif/pasif durum, soft delete ve geri yükleme
- İsteğe bağlı öne çıkan görsel

### Etkileşim

- Görüntülenme kaydı
- Yazı beğenisi
- Yorum, yanıt ve yorum beğenisi
- Yorum şikayeti

### Moderasyon ve admin

- Bekleyen yazılar
- Yorum şikayetlerini inceleme: yorumu bırakma veya kaldırma
- Kullanıcı istatistikleri ve admin istatistikleri

### Güvenlik

- Sanctum Bearer token
- Beni hatırla: normal kullanıcıda 7 gün, işaretliyse 30 gün; admin oturumu en fazla 24 saat
- Çıkışta yalnız mevcut token silinir
- Şifre değişince mevcut oturum kalır, diğer oturumlar kapanır
- Forgot Password ve Reset Password
- Reset bağlantısı 15 dakika geçerlidir; başarılı sıfırlamada tüm Sanctum tokenları silinir
- Parola en az 8 karakterdir
- Login, register, forgot password, reset password ve genel API için rate limit

Parola güç göstergesi yalnızca bilgilendirme amaçlıdır. Gönderimi engellemez.

## Teknolojiler

### Backend

- PHP ^8.3
- Laravel ^13.8
- Laravel Sanctum ^4
- PHPUnit ^12.5
- MySQL 8

### Frontend

- Vue ^3.5
- Vue Router ^5.1
- Pinia ^3
- Axios ^1.18
- Vite ^8

### Geliştirme araçları

- Docker ve Docker Compose
- Mailpit
- Git ve GitHub
- Postman
- DBeaver

Postman ve DBeaver proje bağımlılığı değildir. API ve veritabanını elle denemek için kullanılır.

## Mimari

```text
user-management-system
├── backend
├── frontend
└── docker-compose.yml
```

Backend Laravel REST API, frontend Vue tek sayfa uygulamasıdır. İstekler `Authorization: Bearer <token>` başlığı ile gider. MySQL ve Mailpit Docker Compose ile ayağa kalkar. Laravel ve Vite bilgisayarda, konteyner dışında çalışır.

## Kurulum

### 1. Projeyi alın

```bash
git clone https://github.com/idalkapan/user-management-system.git
cd user-management-system
```

### 2. Docker servislerini başlatın

```bash
docker compose up -d
```

Bu komut MySQL ve Mailpit konteynerlerini başlatır. MySQL ana makinede `3309` portundan yayınlanır. Veritabanı adı, kullanıcı ve parola `docker-compose.yml` içindeki `MYSQL_DATABASE`, `MYSQL_USER` ve `MYSQL_PASSWORD` alanlarındadır. Bu değerleri README'ye kopyalamayın.

Mailpit geliştirme sırasında şifre sıfırlama e-postasını yakalar. Web arayüzü: [http://127.0.0.1:8025](http://127.0.0.1:8025)

### 3. Backend

```bash
cd backend
composer install
cp .env.example .env
```

`.env.example` varsayılan olarak SQLite kullanır. Bu proje MySQL ile çalışacak şekilde kurulmalıdır. `.env` içinde veritabanını şöyle ayarlayın:

- `DB_CONNECTION=mysql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3309`
- `DB_DATABASE`, `DB_USERNAME` ve `DB_PASSWORD` değerlerini `docker-compose.yml` dosyasındaki MySQL ayarlarından alın

`.env.example` yerel Mailpit ve frontend adresi için hazır gelir. Laravel konteyner dışında çalıştığı için SMTP adresi `127.0.0.1` ve port `1025` olmalıdır. `FRONTEND_URL` geliştirmede `http://localhost:5173` olarak kalabilir. Bu adres şifre sıfırlama bağlantısının Vue ekranına gitmesi için kullanılır.

```bash
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

İlk admin kullanıcısı gerekiyorsa:

```bash
php artisan db:seed
```


API adresi: `http://localhost:8000/api`

### 4. Frontend

Yeni bir terminalde:

```bash
cd frontend
npm install
npm run dev
```

Uygulama: `http://localhost:5173`

Frontend'in ayrı bir `.env` dosyası yoktur. API adresi `frontend/src/services/api.js` içinde `http://localhost:8000/api` olarak tanımlıdır.

## Kimlik doğrulama ve güvenlik

Kayıt olan kullanıcının rolü `user` olur. Admin alanları `admin` rolü ve `admin` middleware ister.

Token süresi role ve Beni hatırla seçimine göre belirlenir:

| Kullanıcı | Süre |
| --- | --- |
| Normal kullanıcı | 7 gün |
| Beni hatırla işaretli kullanıcı | 30 gün |
| Admin | En fazla 24 saat |

Çıkış yalnız o isteğin tokenını siler. Şifre değiştirmede açık olan oturum korunur, aynı kullanıcının diğer tokenları silinir. Forgot Password oturumsuz bir kurtarma akışıdır. Geçerli formattaki her e-posta adresine aynı genel cevap döner. Reset bağlantısı 15 dakika yaşar. Sıfırlama başarılı olursa kullanıcının tüm Sanctum tokenları silinir. Yeni token üretilmez. Kullanıcı yeni parolasıyla tekrar giriş yapar.

Parola kuralı en az 8 karakterdir. Büyük harf, rakam veya sembol zorunlu değildir.

Rate limit dakikada şu şekildedir:

| İstek | Limit |
| --- | --- |
| Genel API | 60, kullanıcı veya IP |
| Login | 5, e-posta ve IP |
| Register | 5, IP |
| Forgot Password | 5, IP |
| Reset Password | 5, IP |

## Blog ve içerik

Yazar yazıyı taslak veya incelemeye gönderilmiş olarak kaydeder. Yönetici bekleyen yazıyı onaylar ya da reddeder. Durumlar: `draft`, `pending`, `published`, `rejected`.

Kategoriler yöneticidedir. Aktif veya pasif yapılabilir, soft delete ile kaldırılabilir ve geri yüklenebilir. Kullanıcı yazı formunda aktif kategorileri görür.

Görüntülenme yalnız yayındaki yazıda kaydedilir. Yazar kendi yazısı için sayılmaz. Aynı kullanıcı ve yazı için iki dakika içindeki tekrar işlenmez.

Yazı ve yorum ayrı ayrı beğenilebilir. Yorumlara yanıt yazılır. Yorum sahibi kendi yorumunu güncelleyebilir veya silebilir.

Şikayet nedenleri spam, taciz, nefret söylemi, uygunsuz içerik, yanıltıcı bilgi ve diğerdir. Yönetici şikayeti inceler, yorumu bırakır veya kaldırır.

Kullanıcı kendi yayınlanmış yazılarının, yönetici ise sistemin 7 veya 30 günlük istatistiklerini görür.

Blog endpointleri de Sanctum ister. Herkese açık bir blog API'si yoktur.

## API endpointleri

Tüm yollar `/api` önekini kullanır. Kaynak `backend/routes/api.php` dosyasıdır.

### Public Authentication

Bu dört endpoint `auth:sanctum` arkasında değildir.

| Method | Endpoint | Açıklama |
| --- | --- | --- |
| POST | `/api/register` | Kayıt |
| POST | `/api/login` | Giriş |
| POST | `/api/forgot-password` | Şifre sıfırlama bağlantısı iste |
| POST | `/api/reset-password` | Yeni parolayı kaydet |

### Authenticated User

| Method | Endpoint | Açıklama |
| --- | --- | --- |
| GET | `/api/user` | Giriş yapan kullanıcı |
| POST | `/api/logout` | Çıkış |
| GET | `/api/profile` | Profil |
| PUT | `/api/profile` | Profil güncelle |
| PUT | `/api/change-password` | Şifre değiştir |
| POST | `/api/profile/photo` | Profil fotoğrafı yükle |

### Blog ve etkileşim

| Method | Endpoint | Açıklama |
| --- | --- | --- |
| GET | `/api/categories` | Aktif kategoriler |
| GET | `/api/posts` | Yazı listesi |
| POST | `/api/posts` | Yazı oluştur |
| GET | `/api/posts/{id}` | Yazı detayı |
| PUT | `/api/posts/{id}` | Yazı güncelle |
| DELETE | `/api/posts/{id}` | Yazı sil |
| GET | `/api/my-posts` | Kendi yazılarım |
| GET | `/api/my-statistics` | Kendi istatistiklerim |
| POST | `/api/posts/{post}/views` | Görüntülenme kaydet |
| POST | `/api/posts/{post}/like` | Yazıyı beğen |
| DELETE | `/api/posts/{post}/like` | Yazı beğenisini kaldır |
| GET | `/api/posts/{post}/comments` | Yorumlar |
| POST | `/api/posts/{post}/comments` | Yorum yaz |
| GET | `/api/comments/{comment}/replies` | Yanıtlar |
| POST | `/api/comments/{comment}/replies` | Yanıt yaz |
| PUT | `/api/comments/{comment}` | Yorumu güncelle |
| DELETE | `/api/comments/{comment}` | Yorumu sil |
| POST | `/api/comments/{comment}/like` | Yorumu beğen |
| DELETE | `/api/comments/{comment}/like` | Yorum beğenisini kaldır |
| POST | `/api/comments/{comment}/report` | Yorumu şikayet et |

### Admin

`auth:sanctum` ve `admin` middleware gerekir.

| Method | Endpoint | Açıklama |
| --- | --- | --- |
| GET | `/api/admin/test` | Admin erişim kontrolü |
| GET | `/api/admin/dashboard` | Yönetici özeti |
| GET | `/api/admin/statistics` | Sistem istatistikleri |
| GET | `/api/admin/posts/pending` | Bekleyen yazılar |
| PATCH | `/api/admin/posts/{id}/approve` | Yazıyı onayla |
| PATCH | `/api/admin/posts/{id}/reject` | Yazıyı reddet |
| GET | `/api/admin/categories` | Kategoriler |
| POST | `/api/admin/categories` | Kategori oluştur |
| PUT | `/api/admin/categories/{category}` | Kategori güncelle |
| PATCH | `/api/admin/categories/{category}/status` | Kategoriyi aktif veya pasif yap |
| GET | `/api/admin/categories/{category}/posts` | Kategorideki yazılar |
| POST | `/api/admin/categories/{category}/restore` | Silinen kategoriyi geri yükle |
| DELETE | `/api/admin/categories/{category}` | Kategoriyi sil |
| GET | `/api/admin/comment-reports` | Yorum şikayetleri |
| GET | `/api/admin/comment-reports/{report}` | Şikayet detayı |
| PATCH | `/api/admin/comment-reports/{report}/resolve` | Şikayeti sonuçlandır |

## Testler

Feature testleri `backend/tests/Feature` altındadır. Kapsanan başlıklar arasında token süresi, parola kuralları, rate limit, şifre sıfırlama ve yorum şikayeti vardır.

PHPUnit ortamı SQLite bellek veritabanı kullanır. Yazı durumu migration'ındaki MySQL `ENUM` ifadesi bu ortamda çalışmadığı için test paketinin tamamı şu anda kurulumu geçemeyebilir. Bu nedenle burada testlerin geçtiği söylenmez.

## Proje yapısı

```text
backend/app
backend/routes
backend/database/migrations
backend/tests
frontend/src/components
frontend/src/views
frontend/src/services
frontend/src/stores
frontend/src/router
docker-compose.yml
```

## Geliştirici

**İdal Kapan**

Bilgisayar Mühendisliği Öğrencisi
