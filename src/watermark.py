import json
import wave
from datetime import datetime

# =========================================================================
# SABİTLER VE TANIMLAR (Sihirli Kelime / İmzalar)
# =========================================================================

# Dosyanın mühürlü olup olmadığını anlamak için kullanılan 8 byte'lık imza (Sihirli Kelime)
MAGIC = b"ACJSON1\0"

# Gömülen JSON verisinin boyutunu (kaç karakter olduğunu) saklamak için ayrılan byte genişliği (4 byte)
LENGTH_SIZE = 4


# =========================================================================
# HATA SINIFLARI
# =========================================================================

# Ses dosyası boyutu gizli veriyi gömmek için yetersiz olduğunda tetiklenen hata
class WatermarkCapacityError(ValueError):
    pass

# Ses dosyasının mühürü okunurken hata oluştuğunda tetiklenen hata
class WatermarkReadError(ValueError):
    pass


# =========================================================================
# YARDIMCI FONKSİYONLAR (Veri Hazırlama & Bit-Byte Dönüşümleri)
# =========================================================================

def build_watermark_payload(
    seri_no,
    eser_adi,
    sanatci,
    telif_sahibi,
    lisanslayan,
    orijinal_dosya,
    dosya_hash,
    medya_turu="audio",
    ek_bilgiler=None,
):
    """
    [LİSANSLAMADA KULLANILIR]
    Kullanıcının formdan girdiği verileri standartlaştırılmış bir telif kartı (JSON) şemasına döker.
    """
    payload = {
        "schema": "AudioCryptWatermark",  # Dosya formatını doğrulama işareti
        "version": 1,                     # Versiyon numarası
        "medya_turu": medya_turu,         # Türü (Ses)
        "seri_no": seri_no,               # Benzersiz watermark seri numarası
        "eser_adi": eser_adi,             # Eserin adı
        "sanatci": sanatci,               # Sanatçının adı
        "telif_sahibi": telif_sahibi,     # Eserin yasal sahibi (Müşteri)
        "lisanslayan": lisanslayan,       # İşlemi yapan personel
        "orijinal_dosya": orijinal_dosya, # Temiz dosyanın orijinal adı
        "dosya_hash": dosya_hash,         # Orijinal sesin parmak izi (SHA-256)
        "olusturma_tarihi": datetime.now().strftime("%d.%m.%Y %H:%M"), # İşlem tarihi
    }

    # Eğer ekstra başka bir bilgi varsa telif kartına ekler
    if ek_bilgiler:
        payload.update(ek_bilgiler)

    return payload


def payload_to_json(payload):
    """
    [ARAYÜZDE GÖSTERİMDE KULLANILIR]
    Telif kartı verisini (Python sözlüğünü) ekranda güzel görünecek şekilde formatlı JSON metnine çevirir.
    """
    return json.dumps(payload, ensure_ascii=False, indent=2)


def _bytes_to_bits(data):
    """
    [GÖMMEDE KULLANILIR]
    Metin/Byte verilerini sese gömebilmek için 0 ve 1'lerden oluşan bit dizisine çevirir.
    (Her byte 8 bit'e ayrıştırılır. Örn: 'A' -> 65 -> 01000001)
    """
    for byte in data:
        for shift in range(7, -1, -1):
            yield (byte >> shift) & 1


def _bits_to_bytes(bits):
    """
    [OKUMADA KULLANILIR]
    Sesin en önemsiz bitlerinden okunan 0 ve 1'leri birleştirerek tekrar anlamlı byte/metin verisine dönüştürür.
    (Her 8 bit birleşerek 1 byte oluşturur)
    """
    values = []
    for index in range(0, len(bits), 8):
        byte_bits = bits[index:index + 8]
        if len(byte_bits) < 8:
            break

        value = 0
        for bit in byte_bits:
            value = (value << 1) | bit
        values.append(value)
    return bytes(values)


# =========================================================================
# ANA İŞLEM MOTORLARI (Gömme ve Okuma)
# =========================================================================

def embed_json_watermark(input_wav, output_wav, payload):
    """
    ### 1. ADIM: DİJİTAL MÜHÜRÜ SESİN İÇİNE GÖMME SÜRECİ (Lisanslama Paneli) ###
    LSB (Least Significant Bit) Steganografisi kullanarak telif kartını WAV dosyasına gömer.
    """
    # 1. Telif kartı (sözlük) sıkıştırılmış JSON metnine dönüştürülüp byte dizisi haline getirilir
    payload_bytes = json.dumps(
        payload,
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    ).encode("utf-8")

    # 2. Zarf (Envelope) oluşturulur: [ MAGIC İMZASI ] + [ VERİ BOYUTU (4 Byte) ] + [ ASIL JSON VERİSİ ]
    envelope = MAGIC + len(payload_bytes).to_bytes(LENGTH_SIZE, "big") + payload_bytes
    
    # 3. Zarfın tamamı 0 ve 1'lerden oluşan bit listesine çevrilir
    envelope_bits = list(_bytes_to_bits(envelope))

    # 4. Orijinal ses dosyası (.wav) açılır ve tüm ses dalgaları (byte dizisi) okunur
    with wave.open(input_wav, "rb") as source:
        params = source.getparams() # Kanallar, örnekleme hızı vb. parametreler alınır
        frames = bytearray(source.readframes(source.getnframes()))

    # 5. Kapasite Kontrolü: Ses dosyası çok kısaysa hata fırlatılır
    if len(envelope_bits) > len(frames):
        max_payload_bytes = max((len(frames) // 8) - len(MAGIC) - LENGTH_SIZE, 0)
        raise WatermarkCapacityError(
            f"Ses dosyası bu JSON mühür için küçük. Maksimum yaklaşık {max_payload_bytes} byte veri sığar."
        )

    # 6. LSB ALGORİTMASI: Ses dalgasının her byte'ının son biti sıfırlanır (& 0xFE) ve kendi bitimiz eklenir (| bit)
    for index, bit in enumerate(envelope_bits):
        frames[index] = (frames[index] & 0xFE) | bit

    # 7. Mühürlenmiş yeni ses verisi diske kaydedilir
    with wave.open(output_wav, "wb") as target:
        target.setparams(params)
        target.writeframes(frames)

    # İşlem istatistikleri arayüz günlüğüne yazılmak üzere döndürülür
    return {
        "payload_bytes": len(payload_bytes),
        "used_audio_bytes": len(envelope_bits),
        "capacity_audio_bytes": len(frames),
    }


def extract_json_watermark(audio_wav):
    """
    ### 2. ADIM: DİJİTAL MÜHÜRÜ SESTEN ÇÖZÜP ÇIKARMA SÜRECİ (Scanner / Tarayıcı) ###
    Mühürlü ses dosyasının en önemsiz son bitlerini toplayarak gizli JSON tescil kartını okur.
    """
    # 1. Ses dosyası açılır ve ham ses byte dizisi okunur
    with wave.open(audio_wav, "rb") as source:
        frames = bytearray(source.readframes(source.getnframes()))

    # 2. Başlık (İmza + Boyut Bilgisi) okumak için gereken bit sayısı hesaplanır
    header_bit_count = (len(MAGIC) + LENGTH_SIZE) * 8
    if len(frames) < header_bit_count:
        raise WatermarkReadError("Ses dosyası mühür başlığı için çok küçük.")

    # 3. Sesin ilk byte'larının son bitleri taranarak başlık byte dizisine çevrilir
    header_bits = [frames[index] & 1 for index in range(header_bit_count)]
    header = _bits_to_bytes(header_bits)

    # 4. İMZA KONTROLÜ: Başlık "ACJSON1\0" ile başlamıyorsa bu dosya mühürsüzdür
    if not header.startswith(MAGIC):
        return None

    # 5. Başlıktaki boyut bilgisi (length) okunur ve okunacak asıl telif verisinin bit sınırları hesaplanır
    payload_size = int.from_bytes(header[len(MAGIC):len(MAGIC) + LENGTH_SIZE], "big")
    payload_bit_count = payload_size * 8
    payload_start = header_bit_count
    payload_end = payload_start + payload_bit_count

    # 6. Eksik/Hatalı dosya boyut kontrolü
    if payload_size <= 0 or payload_end > len(frames):
        raise WatermarkReadError("Mühür uzunluğu geçersiz veya ses dosyası eksik.")

    # 7. Belirlenen sınırlardaki bitler toplanarak asıl telif kartı byte dizisine dönüştürülür
    payload_bits = [frames[index] & 1 for index in range(payload_start, payload_end)]
    payload_bytes = _bits_to_bytes(payload_bits)

    # 8. Byte dizisi UTF-8 olarak deşifre edilir ve JSON formatına (Python sözlüğüne) dönüştürülür
    try:
        payload = json.loads(payload_bytes.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as exc:
        raise WatermarkReadError(f"JSON mühür okunamadı: {exc}") from exc

    # 9. Şema doğrulanır
    if payload.get("schema") != "AudioCryptWatermark":
        raise WatermarkReadError("Bulunan JSON AudioCrypt mühürü değil.")

    return payload
