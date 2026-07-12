# Kullanıcı Yönetim Sistemi

## Proje Hakkında

Bu proje, Laravel ve Vue.js kullanılarak geliştirilen basit bir **Kullanıcı Yönetim Sistemi** uygulamasıdır.

Projenin amacı; Laravel ile REST API geliştirme, Vue.js ile API kullanımı, Laravel Sanctum ile token tabanlı kimlik doğrulama, Form Request kullanımı ve frontend-backend entegrasyonu konularında uygulamalı deneyim kazanmaktır.

---

# Kullanılan Teknolojiler

## Backend

- Laravel
- PHP
- MySQL
- Laravel Sanctum
- Laravel Form Request
- Laravel API Resource

## Frontend

- Vue.js
- Vue Router
- Axios
- Vite

## Kullanılan Araçlar

- Docker
- Git
- GitHub
-Postman
-DBeaver

---

# Proje Özellikleri

## Kimlik Doğrulama

- Kullanıcı kayıt olma
- Kullanıcı giriş yapma
- Kullanıcı çıkış yapma
- Laravel Sanctum ile Token Authentication

## Kullanıcı İşlemleri

- Giriş yapan kullanıcının bilgilerini görüntüleme
- Profil bilgilerini güncelleme
- Şifre değiştirme
- Profil fotoğrafı yükleme

## Doğrulama

- Form Request kullanımı
- Türkçe doğrulama mesajları
- Hatalı isteklerde uygun HTTP durum kodları
- Standart JSON Response yapısı

---

# Proje Yapısı

```text
user-management-system
│
├── backend
│
├── frontend
│
└── README.md
```

---

# Kurulum

## 1. Projeyi Klonlayın

```bash
git clone <repo_adresi>
```

```bash
cd user-management-system
```

---

# Backend Kurulumu

```bash
cd backend
```

Bağımlılıkları yükleyin.

```bash
composer install
```

Ortam dosyasını oluşturun.

```bash
cp .env.example .env
```

Laravel uygulama anahtarını oluşturun.

```bash
php artisan key:generate
```

Migration dosyalarını çalıştırın.

```bash
php artisan migrate
```

Storage bağlantısını oluşturun.

```bash
php artisan storage:link
```

Backend'i çalıştırın.

```bash
php artisan serve
```

---

# Frontend Kurulumu

Yeni bir terminal açın.

```bash
cd frontend
```

Bağımlılıkları yükleyin.

```bash
npm install
```

Frontend'i çalıştırın.

```bash
npm run dev
```

---

# API Endpointleri

| Method | Endpoint | Açıklama |
|---------|----------|----------|
| POST | /api/register | Kullanıcı kayıt |
| POST | /api/login | Kullanıcı giriş |
| POST | /api/logout | Kullanıcı çıkış |
| GET | /api/user | Giriş yapan kullanıcı bilgileri |
| PUT/PATCH | /api/profile | Profil bilgilerini güncelle |
| PUT | /api/change-password | Şifre değiştir |
| POST | /api/profile/photo | Profil fotoğrafı yükle |

> Endpoint adresleri projedeki `routes/api.php` dosyasında tanımlanmıştır.

---

# Doğrulama

Projede doğrulama işlemleri Form Request sınıfları kullanılarak gerçekleştirilmiştir.

Kullanılan Request sınıfları:

- RegisterRequest
- LoginRequest
- UpdateProfileRequest
- ChangePasswordRequest
- UploadProfilePhotoRequest

Tüm doğrulama mesajları Türkçedir.

---

# Veritabanı

Veritabanı yapısı Laravel Migration dosyaları ile oluşturulmaktadır.

Migration dosyaları:

```text
backend/database/migrations
```

klasörü içerisinde bulunmaktadır.

---

# Bonus Özellikler

Bu proje kapsamında aşağıdaki bonus özellikler de geliştirilmiştir.

- Şifre değiştirme
- Profil fotoğrafı yükleme
- Docker ile veritabanı kurulumu

---

# Öğrenilen Konular

Bu proje geliştirilirken aşağıdaki konularda uygulamalı deneyim kazanılmıştır.

- Laravel proje yapısı
- REST API geliştirme
- Controller kullanımı
- Model kullanımı
- Migration kullanımı
- Form Request kullanımı
- API Resource kullanımı
- Laravel Sanctum
- Token Authentication
- Vue.js ile API kullanımı
- Axios
- Authorization Header yönetimi
- JSON Response yapısı
- Git ile sürüm kontrolü
- Docker kullanımı

---

# Geliştirici

**İdal Kapan**

Bilgisayar Mühendisliği Öğrencisi