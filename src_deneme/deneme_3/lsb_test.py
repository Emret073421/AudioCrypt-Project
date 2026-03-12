import wave

def mesaj_gizle(audio_yolu, mesaj, cikis_yolu):
    """
    Seçilen ses dosyasının içine gizli bir mesajı LSB yöntemiyle gömer.
    """
    try:
        # 1. Ses dosyasını oku
        with wave.open(audio_yolu, 'rb') as ses:
            parametreler = ses.getparams()
            # Ses verisini değiştirilebilir sayısal bir listeye (bytearray) çeviriyoruz
            frame_verisi = bytearray(ses.readframes(ses.getnframes()))

        # 2. Mesajı Binary'e (0 ve 1) çevir ve bitiş işareti (#####) ekle
        mesaj += "#####"
        binary_mesaj = ''.join(format(ord(i), '08b') for i in mesaj)
        
        # 3. Kapasite Kontrolü
        if len(binary_mesaj) > len(frame_verisi):
            print("Hata: Mesaj ses dosyası için çok büyük!")
            return

        # 4. LSB Gömme (Mühürleme)
        # Her bir ses örneğinin (frame) en önemsiz bitini (LSB) mesajın bitiyle değiştiriyoruz
        for i in range(len(binary_mesaj)):
            # Sayının son bitini 0 yapıp kendi bitimizi ekliyoruz
            frame_verisi[i] = (frame_verisi[i] & 254) | int(binary_mesaj[i])

        # 5. Yeni (kaşelenmiş) dosyayı kaydet
        with wave.open(cikis_yolu, 'wb') as yeni_ses:
            yeni_ses.setparams(parametreler)
            yeni_ses.writeframes(frame_verisi)
        print(f"\n[+] Başarılı: Mesaj '{cikis_yolu}' dosyasına mühürlendi.")

    except Exception as e:
        print(f"\n[-] Gizleme Hatası: {e}")

def mesaj_oku(audio_yolu):
    """
    Kaşelenmiş ses dosyasından gizli mesajı ayrıştırıp okur.
    """
    try:
        with wave.open(audio_yolu, 'rb') as ses:
            frame_verisi = bytearray(ses.readframes(ses.getnframes()))

        # Her frame'in sadece son bitini (0 veya 1) topluyoruz
        binary_data = ""
        for i in range(len(frame_verisi)):
            binary_data += str(frame_verisi[i] & 1)

        # 8'erli gruplar (byte) halinde harfe geri çeviriyoruz
        mesaj = ""
        for i in range(0, len(binary_data), 8):
            byte = binary_data[i:i+8]
            karakter = chr(int(byte, 2))
            mesaj += karakter
            
            # Eğer bitiş işaretini görürsek okumayı durdur
            if "#####" in mesaj:
                break
                
        return mesaj.replace("#####", "")

    except Exception as e:
        print(f"\n[-] Okuma Hatası: {e}")
        return None

# --- TEST ETME ---
# ÖNEMLİ: Ring01.wav dosyasının lsb_test.py ile aynı klasörde olduğundan emin ol!
input_file = "Ring01.wav" 
output_file = "gizli_ses.wav" 
gizli_not = "Emre Tuncer - AI Kase Testi 2026"

# 1. Adım: Gizleme İşlemi
mesaj_gizle(input_file, gizli_not, output_file)

# 2. Adım: Okuma İşlemi (Doğrulama)
print("[*] Gizli mesaj okunuyor...")
cozulen_mesaj = mesaj_oku(output_file)

if cozulen_mesaj:
    print(f"\n>>> Sesten Okunan Gizli Kaşe: {cozulen_mesaj}\n")