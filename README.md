# 🔊 AudioCrypt: Dijital Ses Kaşeleme ve YZ Koruma Sistemi

AudioCrypt, gizli metin mesajlarını `.wav` formatındaki ses dosyalarına insan kulağıyla fark edilemeyecek şekilde gömmek (steganografi) ve bu verileri kriptografik yöntemlerle korumak amacıyla geliştirilmiş bir **Dijital Hak Yönetimi (DRM)** aracıdır.

## 🌟 Proje Vizyonu: Yapay Zekaya Karşı Telif Koruması
Günümüzde yapay zeka modelleri, sanatçıların eserlerini izinsiz tarayarak eğitilmektedir. Bu projenin temel amacı:
* **Şifreli Dijital Kaşeleme:** Ses dosyasına sanatçı ve telif bilgilerini "görünmez mühür" olarak eklemek.
* **YZ Ayırt Etme:** Ses dosyasına gömülen özel parmak izleri sayesinde, gelecekte yapay zeka tarafından üretilen müzikler ile insan üretimi orijinal eserlerin ayırt edilmesini sağlamak.
* **Eğitim Engelleme:** Botların ve tarayıcıların, sese gömülü kaşeyi okuyarak "Bu eser teliflidir, eğitim setine dahil edilemez" uyarısını alabileceği bir altyapı oluşturmak.

## 🚀 Teknik Özellikler
* **LSB (Least Significant Bit) Algoritması:** Ses verisinin en önemsiz bitlerini kullanarak yüksek kaliteli veri gizleme.
* **Şifreli Güvenlik Katmanı:** Mesajlar sese gömülmeden önce kullanıcı tarafından belirlenen bir parola ile şifrelenir.
* **Dinamik Kapasite Analizi:** Ses dosyasının uzunluğuna göre ne kadar veri saklayabileceğini önceden hesaplar.
* **Modern GUI:** Python `CustomTkinter` kütüphanesi ile geliştirilmiş, kullanıcı dostu karanlık mod arayüzü.

## 🛠️ Kullanılan Teknolojiler
* **Python 3.14+**
* **NumPy:** Hızlı sayısal veri ve ses matrisi işleme.
* **CustomTkinter:** Modern masaüstü arayüz bileşenleri.
* **Wave:** Standart ses dosyası formatı işleme.

## 📂 Proje Yapısı
```text
AudioCrypt_Project/
├── src/                # Ana kaynak kodları (main.py, vb.)
├── src_denem/          # Geliştirme aşamasındaki test kodları
├── assets/             # Örnek ses dosyaları ve görseller
├── docs/               # Proje raporu ve akademik dökümanlar
└── README.md           # Proje tanıtımı ve kullanım kılavuzu