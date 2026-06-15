# 🔊 AudioCrypt: Dijital Ses Kaşeleme ve Telif Koruma Sistemi

AudioCrypt, ses dosyalarının (`.wav`) içerisine LSB (Least Significant Bit) steganografi yöntemi ile **JSON tabanlı dijital mühürler (telif kartları)** gömmek ve bu bilgileri bir veritabanı ile entegre çalışarak yönetmek amacıyla geliştirilmiş kapsamlı bir **Dijital Hak Yönetimi (DRM)** aracıdır.

## 🌟 Proje Vizyonu
Bu projenin temel amacı; sanatçıların eserlerini, günümüzde yetkisiz olarak kullanılan yapay zeka eğitim modellerinden ve dijital korsanlardan korumaktır.
* **Dijital Mühürleme:** Orijinal ses dosyalarının içine, insan kulağının algılayamayacağı şekilde sanatçı ve müşteri bilgilerini gizler.
* **Veritabanı Arşivi:** Şifrelenen tüm eserlerin kim tarafından ve ne zaman lisanslandığı SQL tabanlı sistemde tutulur.
* **Güvenilir Doğrulama:** Ses dosyasının formatı değiştirilerek mühür bozulsa dahi, dosyanın eşsiz parmak izi (Hash değeri) ile eser veritabanından tekrar bulunabilir.

## 🚀 Öne Çıkan Teknik Özellikler
* **LSB Steganografi Algoritması:** Ses verisinin en önemsiz bitlerini değiştirerek veriyi gizler. Ses kalitesinde duyulabilir hiçbir bozulma yaşanmaz.
* **Çoklu Dosya (Albüm) Lisanslama:** Klasör halindeki bir albümün içindeki tüm müzik dosyalarını aynı anda tarar ve otomatik isimlendirerek döngüsel (batch) olarak lisanslar.
* **JSON Payload Şifreleme:** Gömülecek telif kartı JSON formatında yapılandırılır ve "MAGIC" anahtar kelimeleriyle koruma altına alınır.
* **İki Aşamalı Doğrulama Sistemi:** Mührün okunması (1. Aşama) ve Hash Doğrulaması (2. Aşama) ile çifte güvenlik sağlar.
* **Rol Bazlı Kullanıcı Yönetimi (RBAC):** Admin (Yönetici) ve Personel girişleri ayrılarak yetkisiz kişilerin şifreleme veya veri silme işlemi yapması engellenir.
* **Modern GUI:** `CustomTkinter` ile tasarlanmış karanlık mod uyumlu (dark mode), modern ve şık bir masaüstü arayüzü sunar.

## 🛠️ Kullanılan Teknolojiler
* **Programlama Dili:** Python 3.10+
* **Kullanıcı Arayüzü (GUI):** CustomTkinter, Tkinter
* **Veritabanı:** SQLite3
* **Ses İşleme:** Python `wave` modülü (Standart Kütüphane)
* **Kriptografi & Doğrulama:** Hashlib (SHA-256), UUID

## 📂 Proje Yapısı
```text
AudioCrypt_Project/
├── src/                
│   ├── app.py          # Ana masaüstü arayüzü (Uygulamayı buradan başlatın)
│   ├── watermark.py    # LSB Steganografi ve şifreleme/okuma algoritmaları
│   └── database.py     # SQLite veritabanı tabloları ve sorgu fonksiyonları
├── audiocrypt_kurumsal.db # (Çalıştırınca otomatik oluşur) Kurumsal veri deposu
├── input_audio/        # Temiz ve orijinal ses dosyalarının kopyalandığı yer
├── output_audio/       # İçine mühür gömülmüş (şifreli) dosyaların çıktısı
└── README.md           # Proje tanıtımı ve kullanım kılavuzu
```

## 💻 Nasıl Çalıştırılır?
1. Proje ana dizinindeyken terminalinizi açın.
2. Uygulamayı başlatmak için şu komutu girin:
```bash
python src/app.py
```
3. Varsayılan Yönetici (Admin) Girişi:
   * **Kullanıcı Adı:** `admin`
   * **Şifre:** `admin`

## 🎓 Bitirme Projesi Kapsamı
Bu proje, yazılım mühendisliği "Dijital Hak Yönetimi" (DRM) ve "Veri Gizleme" (Steganografi) konularını modern bir masaüstü yazılımında birleştirerek gerçek dünyada telif ajansları ve kayıt stüdyoları tarafından kullanılabilecek uçtan uca bir ürün (End-to-End Product) olarak tasarlanmıştır.