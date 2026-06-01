import sqlite3
import hashlib
from datetime import datetime

DB_FILE = "audiocrypt_kurumsal.db"

def get_connection():
    """
    Veritabanı bağlantısı açar ve Yabancı Anahtar (Foreign Key) desteğini aktif eder.
    """
    baglanti = sqlite3.connect(DB_FILE)
    baglanti.execute("PRAGMA foreign_keys = 1")
    return baglanti

def hash_sifre(sifre):
    """
    Girilen şifreyi SHA-256 ile hashler.
    """
    return hashlib.sha256(sifre.encode("utf-8")).hexdigest()

def veritabani_kur():
    """
    Veritabanı tablolarını sıfırdan kurar ve varsayılan verileri yükler.
    """
    baglanti = get_connection()
    imlec = baglanti.cursor()

    # 1. YETKİLER TABLOSU
    imlec.execute('''
    CREATE TABLE IF NOT EXISTS yetkiler (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rol_adi TEXT UNIQUE,
        okuma_izni INTEGER DEFAULT 1,
        yazma_izni INTEGER DEFAULT 0,
        silme_izni INTEGER DEFAULT 0,
        admin_izni INTEGER DEFAULT 0
    )
    ''')

    # 2. PERSONELLER TABLOSU
    imlec.execute('''
    CREATE TABLE IF NOT EXISTS personeller (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kullanici_adi TEXT UNIQUE,
        sifre_hash TEXT,
        ad_soyad TEXT,
        rol_id INTEGER,
        FOREIGN KEY (rol_id) REFERENCES yetkiler(id)
    )
    ''')

    # 3. MÜŞTERİLER TABLOSU
    imlec.execute('''
    CREATE TABLE IF NOT EXISTS musteriler (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        unvan_ad TEXT,
        iletisim_eposta TEXT,
        telefon TEXT,
        kayit_tarihi TEXT
    )
    ''')

    # 4. SES KAYITLARI TABLOSU (Soft Delete durum sütunu ile)
    imlec.execute('''
    CREATE TABLE IF NOT EXISTS ses_kayitlari (
        seri_no TEXT PRIMARY KEY,
        orijinal_dosya TEXT,
        dosya_hash TEXT UNIQUE,
        islem_tarihi TEXT,
        durum TEXT DEFAULT 'Aktif',
        musteri_id INTEGER,
        personel_id INTEGER,
        FOREIGN KEY (musteri_id) REFERENCES musteriler(id),
        FOREIGN KEY (personel_id) REFERENCES personeller(id)
    )
    ''')

    # Varsayılan Rolleri Ekle
    imlec.executemany('''
        INSERT OR IGNORE INTO yetkiler (rol_adi, okuma_izni, yazma_izni, silme_izni, admin_izni)
        VALUES (?, ?, ?, ?, ?)
    ''', [
        ('Sistem Yöneticisi', 1, 1, 1, 1),
        ('Müzik Prodüktörü', 1, 1, 0, 0),
        ('Stajyer / Tarayıcı', 1, 0, 0, 0)
    ])

    baglanti.commit()

    # Varsayılan Personelleri Ekle (Admin, Prod, Staj)
    sifre_123456 = hash_sifre("123456")
    
    # Rol ID'lerini çek
    imlec.execute("SELECT id, rol_adi FROM yetkiler")
    roller = {rol_adi: id for id, rol_adi in imlec.fetchall()}

    imlec.executemany('''
        INSERT OR IGNORE INTO personeller (kullanici_adi, sifre_hash, ad_soyad, rol_id)
        VALUES (?, ?, ?, ?)
    ''', [
        ('admin', sifre_123456, 'Sistem Yöneticisi Emre', roller.get('Sistem Yöneticisi')),
        ('prod', sifre_123456, 'Prodüktör Ahmet', roller.get('Müzik Prodüktörü')),
        ('staj', sifre_123456, 'Stajyer Melis', roller.get('Stajyer / Tarayıcı'))
    ])

    # Varsayılan Müşterileri Ekle
    tarih_bugun = datetime.now().strftime("%d.%m.%Y")
    imlec.executemany('''
        INSERT OR IGNORE INTO musteriler (unvan_ad, iletisim_eposta, telefon, kayit_tarihi)
        VALUES (?, ?, ?, ?)
    ''', [
        ('Örnek Müzik A.Ş.', 'iletisim@ornekmuzik.com', '05551112233', tarih_bugun),
        ('Netd Müzik', 'info@netd.com', '05552223344', tarih_bugun),
        ('Bağımsız Sanatçı X', 'sanatcix@gmail.com', '05553334455', tarih_bugun)
    ])

    baglanti.commit()
    baglanti.close()
    print("[SUCCESS] Veritabani, yetkiler, personeller ve musteriler basariyla kuruldu!")

# =========================================================================
# VERİTABANI ERİŞİM APILERI (CRUD VE DOĞRULAMALAR)
# =========================================================================

def dogrula_kullanici(kullanici_adi, girilen_sifre):
    """
    Kullanıcı adı ve şifreyi kontrol eder. Eşleşirse kullanıcı bilgilerini ve rolünü döner.
    """
    baglanti = get_connection()
    imlec = baglanti.cursor()
    
    sifre_hash = hash_sifre(girilen_sifre)
    
    imlec.execute('''
        SELECT p.id, p.ad_soyad, y.rol_adi, y.okuma_izni, y.yazma_izni, y.silme_izni, y.admin_izni
        FROM personeller p
        JOIN yetkiler y ON p.rol_id = y.id
        WHERE p.kullanici_adi = ? AND p.sifre_hash = ?
    ''', (kullanici_adi, sifre_hash))
    
    sonuc = imlec.fetchone()
    baglanti.close()
    
    if sonuc:
        return {
            "id": sonuc[0],
            "ad_soyad": sonuc[1],
            "rol_adi": sonuc[2],
            "okuma_izni": sonuc[3],
            "yazma_izni": sonuc[4],
            "silme_izni": sonuc[5],
            "admin_izni": sonuc[6]
        }
    return None

def musterileri_getir():
    """
    Tüm müşterileri (Telif Sahiplerini) ID ve Ünvan olarak liste halinde getirir.
    """
    baglanti = get_connection()
    imlec = baglanti.cursor()
    imlec.execute("SELECT id, unvan_ad FROM musteriler")
    sonuc = imlec.fetchall()
    baglanti.close()
    return sonuc

def hash_var_mi(dosya_hash):
    """
    Bir dosyanın hash bilgisinin veritabanında olup olmadığını kontrol eder.
    """
    baglanti = get_connection()
    imlec = baglanti.cursor()
    imlec.execute("SELECT durum FROM ses_kayitlari WHERE dosya_hash = ?", (dosya_hash,))
    sonuc = imlec.fetchone()
    baglanti.close()
    return sonuc is not None

def ses_kaydi_ekle(seri_no, orijinal_dosya, dosya_hash, musteri_id, personel_id):
    """
    Yeni şifrelenen ses kaydı kaydını veritabanına ekler.
    """
    baglanti = get_connection()
    imlec = baglanti.cursor()
    tarih_bugun = datetime.now().strftime("%d.%m.%Y %H:%M")
    
    try:
        imlec.execute('''
            INSERT INTO ses_kayitlari (seri_no, orijinal_dosya, dosya_hash, islem_tarihi, durum, musteri_id, personel_id)
            VALUES (?, ?, ?, ?, 'Aktif', ?, ?)
        ''', (seri_no, orijinal_dosya, dosya_hash, tarih_bugun, musteri_id, personel_id))
        baglanti.commit()
        return True
    except sqlite3.IntegrityError as e:
        print(f"Hata: {e}")
        return False
    finally:
        baglanti.close()

def ses_kaydi_bul_seri(seri_no):
    """
    Verilen seri numarasına göre ses kaydı bilgilerini ve müşteri/personel detayını getirir.
    """
    baglanti = get_connection()
    imlec = baglanti.cursor()
    imlec.execute('''
        SELECT s.seri_no, s.orijinal_dosya, s.islem_tarihi, s.durum, m.unvan_ad, p.ad_soyad
        FROM ses_kayitlari s
        JOIN musteriler m ON s.musteri_id = m.id
        JOIN personeller p ON s.personel_id = p.id
        WHERE s.seri_no = ?
    ''', (seri_no,))
    sonuc = imlec.fetchone()
    baglanti.close()
    
    if sonuc:
        return {
            "seri_no": sonuc[0],
            "orijinal_dosya": sonuc[1],
            "islem_tarihi": sonuc[2],
            "durum": sonuc[3],
            "musteri_adi": sonuc[4],
            "personel_adi": sonuc[5]
        }
    return None

def gecmis_listesi_getir(arama_sorgusu=None):
    """
    Geçmiş listesini filtreleyerek veya tümüyle getirir.
    """
    baglanti = get_connection()
    imlec = baglanti.cursor()
    
    sorgu = '''
        SELECT s.seri_no, s.orijinal_dosya, m.unvan_ad, p.ad_soyad, s.islem_tarihi, s.durum
        FROM ses_kayitlari s
        JOIN musteriler m ON s.musteri_id = m.id
        JOIN personeller p ON s.personel_id = p.id
    '''
    
    parametreler = []
    if arama_sorgusu:
        sorgu += ''' WHERE s.seri_no LIKE ? OR s.orijinal_dosya LIKE ? OR m.unvan_ad LIKE ?'''
        like_par = f"%{arama_sorgusu}%"
        parametreler = [like_par, like_par, like_par]
        
    imlec.execute(sorgu, parametreler)
    sonuc = imlec.fetchall()
    baglanti.close()
    return sonuc

def ses_kaydi_soft_delete(seri_no):
    """
    Kaydın durumunu 'Silindi' olarak günceller (Soft Delete).
    """
    baglanti = get_connection()
    imlec = baglanti.cursor()
    imlec.execute("UPDATE ses_kayitlari SET durum = 'Silindi' WHERE seri_no = ?", (seri_no,))
    baglanti.commit()
    baglanti.close()
    return True

if __name__ == "__main__":
    # Test ve ilk kurulum için doğrudan çalıştır
    veritabani_kur()
